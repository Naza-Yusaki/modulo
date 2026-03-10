<?php
// CRUD/crud_ventas.php
require_once __DIR__ . '/../conexion.php';

// Iniciar sesión
session_start();

// Determinar la acción
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'registrar_venta':
        registrarVenta($conn);
        break;

    case 'devolucion':
        procesarDevolucion($conn);
        break;

    case 'cambio':
        procesarCambio($conn);
        break;

    case 'eliminar':
        eliminarVenta($conn);
        break;

    default:
        header('Location: ../index.php?tab=ventas');
        break;
}

function registrarVenta($conn)
{
    // Recibir y validar datos
    $items_raw = $_POST['items'] ?? '[]';
    $items = json_decode($items_raw, true);

    if (!is_array($items)) {
        $_SESSION['error'] = "Error en los datos del carrito";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $monto_efectivo = floatval($_POST['monto_efectivo'] ?? 0);
    $monto_nequi = floatval($_POST['monto_nequi'] ?? 0);
    $total_venta = floatval($_POST['total_venta'] ?? 0);
    $observaciones = htmlspecialchars($_POST['observaciones'] ?? '');

    // Validaciones básicas
    if (empty($items)) {
        $_SESSION['error'] = "No hay productos en la venta";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    if (empty($metodo_pago)) {
        $_SESSION['error'] = "Debe seleccionar un método de pago";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    // Validar montos de pago
    if ($metodo_pago == 'efectivo' && $monto_efectivo < $total_venta) {
        $_SESSION['error'] = "El monto en efectivo es insuficiente. Faltan $" . number_format($total_venta - $monto_efectivo, 0);
        header('Location: ../index.php?tab=ventas');
        return;
    }

    if ($metodo_pago == 'nequi' && $monto_nequi < $total_venta) {
        $_SESSION['error'] = "El monto en Nequi es insuficiente. Faltan $" . number_format($total_venta - $monto_nequi, 0);
        header('Location: ../index.php?tab=ventas');
        return;
    }

    if ($metodo_pago == 'mixto' && ($monto_efectivo + $monto_nequi) < $total_venta) {
        $_SESSION['error'] = "El monto total es insuficiente. Faltan $" . number_format($total_venta - ($monto_efectivo + $monto_nequi), 0);
        header('Location: ../index.php?tab=ventas');
        return;
    }

    $conn->begin_transaction();

    try {
        $ventas_registradas = [];

        foreach ($items as $item) {
            $id_producto = intval($item['id']);
            $cantidad_grande = intval($item['cantidad_grande']);
            $cantidad_unidad = intval($item['cantidad_unidad']);
            $precio_unitario = floatval($item['precio_unitario']);
            $precio_grande = floatval($item['precio_grande']);
            $subtotal = floatval($item['subtotal']);
            $tipo = $item['tipo'];

            // Obtener info producto
            $sql_producto = "SELECT * FROM productos WHERE id_producto = ?";
            $stmt_producto = $conn->prepare($sql_producto);
            $stmt_producto->bind_param("i", $id_producto);
            $stmt_producto->execute();
            $result_producto = $stmt_producto->get_result();

            if ($result_producto->num_rows == 0) {
                throw new Exception("Producto no encontrado: ID $id_producto");
            }

            $producto = $result_producto->fetch_assoc();
            $stmt_producto->close();

            // Calcular unidades totales disponibles
            $unidades_disponibles = ($producto['cantidad_grande'] * $producto['cantidad_unidad']) + $producto['unidades_sueltas'];
            $unidades_a_descontar = ($cantidad_grande * $producto['cantidad_unidad']) + $cantidad_unidad;

            if ($unidades_a_descontar > $unidades_disponibles) {
                throw new Exception("Stock insuficiente para {$producto['nombre_producto']}. Disponible: $unidades_disponibles");
            }

            // Validar stock específico según el tipo de venta
            if ($tipo == 'grande' && $cantidad_grande > $producto['cantidad_grande']) {
                throw new Exception("No hay suficientes {$producto['modulo']}s de {$producto['nombre_producto']}. Disponible: {$producto['cantidad_grande']}");
            }

            // Actualizar inventario
            $nuevas_grandes = $producto['cantidad_grande'] - $cantidad_grande;
            $nuevas_sueltas = $producto['unidades_sueltas'] - $cantidad_unidad;

            // Si las sueltas quedan negativas, convertir grandes a sueltas
            if ($nuevas_sueltas < 0) {
                $grandes_necesarios = ceil(abs($nuevas_sueltas) / $producto['cantidad_unidad']);

                if ($nuevas_grandes < $grandes_necesarios) {
                    throw new Exception("Stock insuficiente para {$producto['nombre_producto']}. No hay suficientes paquetes grandes para descomponer.");
                }

                $nuevas_grandes -= $grandes_necesarios;
                $nuevas_sueltas += $grandes_necesarios * $producto['cantidad_unidad'];
            }

            $sql_update = "UPDATE productos SET cantidad_grande = ?, unidades_sueltas = ? WHERE id_producto = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("iii", $nuevas_grandes, $nuevas_sueltas, $id_producto);
            $stmt_update->execute();
            $stmt_update->close();

            // Registrar en historial_venta (SIN CAMPOS DE DESGLOSE)
            $sql_insert = "INSERT INTO historial_venta 
                          (id_producto, nombre_producto, modulo, cantidad_grande, cantidad_unidad, precio, metodo_pago) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param(
                "issiids",
                $id_producto,
                $producto['nombre_producto'],
                $producto['modulo'],
                $cantidad_grande,
                $cantidad_unidad,
                $subtotal,
                $metodo_pago
            );
            $stmt_insert->execute();
            $insert_id = $stmt_insert->insert_id;
            $stmt_insert->close();

            $ventas_registradas[] = [
                'id' => $insert_id,
                'producto' => $producto['nombre_producto'],
                'cantidad' => $tipo == 'grande' ? "$cantidad_grande {$producto['modulo']}s" : "$cantidad_unidad und",
                'subtotal' => $subtotal
            ];
        }

        $conn->commit();

        $cambio = ($monto_efectivo + $monto_nequi) - $total_venta;
        $mensaje = "✅ Venta registrada correctamente\n";
        $mensaje .= "Total: $" . number_format($total_venta, 0) . "\n";
        $mensaje .= "Método de pago: " . ucfirst($metodo_pago) . "\n";

        if ($cambio > 0) {
            $mensaje .= "Cambio: $" . number_format($cambio, 0);
        }

        $_SESSION['success'] = $mensaje;
        $_SESSION['ultima_venta'] = [
            'items' => $ventas_registradas,
            'total' => $total_venta,
            'metodo_pago' => $metodo_pago,
            'cambio' => $cambio,
            'fecha' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al registrar venta: " . $e->getMessage();
    }

    header('Location: ../index.php?tab=ventas');
}

function procesarDevolucion($conn)
{
    $id_venta = intval($_POST['id_venta'] ?? 0);
    $motivo = htmlspecialchars($_POST['motivo_devolucion'] ?? '');

    if ($id_venta <= 0) {
        $_SESSION['error'] = "Venta no válida";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    $sql_venta = "SELECT * FROM historial_venta WHERE id_historial_venta = ?";
    $stmt_venta = $conn->prepare($sql_venta);
    $stmt_venta->bind_param("i", $id_venta);
    $stmt_venta->execute();
    $result_venta = $stmt_venta->get_result();

    if ($result_venta->num_rows == 0) {
        $_SESSION['error'] = "Venta no encontrada";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    $venta = $result_venta->fetch_assoc();
    $stmt_venta->close();

    if (stripos($venta['metodo_pago'], 'DEVUELTA') !== false || stripos($venta['metodo_pago'], 'DEVOLUCION') !== false) {
        $_SESSION['error'] = "No se puede devolver una venta que ya fue devuelta";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    $conn->begin_transaction();

    try {
        // Reintegrar productos al inventario
        $sql_update = "UPDATE productos 
                      SET cantidad_grande = cantidad_grande + ?,
                          unidades_sueltas = unidades_sueltas + ?
                      WHERE id_producto = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iii", $venta['cantidad_grande'], $venta['cantidad_unidad'], $venta['id_producto']);
        $stmt_update->execute();
        $stmt_update->close();

        // Actualizar la venta original
        $metodo_devuelto = "DEVUELTA - " . substr($motivo, 0, 30);

        $sql_update_venta = "UPDATE historial_venta 
                            SET metodo_pago = ?, 
                                precio = 0 
                            WHERE id_historial_venta = ?";
        $stmt_update_venta = $conn->prepare($sql_update_venta);
        $stmt_update_venta->bind_param("si", $metodo_devuelto, $id_venta);
        $stmt_update_venta->execute();
        $stmt_update_venta->close();

        // Registrar la devolución
        $sql_insert = "INSERT INTO historial_venta 
                      (id_producto, nombre_producto, modulo, cantidad_grande, cantidad_unidad, precio, metodo_pago) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $precio_negativo = -$venta['precio'];
        $metodo_registro = "DEVOLUCION: " . substr($motivo, 0, 30);

        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param(
            "issiids",
            $venta['id_producto'],
            $venta['nombre_producto'],
            $venta['modulo'],
            $venta['cantidad_grande'],
            $venta['cantidad_unidad'],
            $precio_negativo,
            $metodo_registro
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        $conn->commit();

        $_SESSION['success'] = "✅ Devolución procesada correctamente.\n" .
            "Producto: {$venta['nombre_producto']}\n" .
            "Motivo: $motivo";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al procesar devolución: " . $e->getMessage();
    }

    header('Location: ../index.php?tab=ventas');
}

function procesarCambio($conn)
{
    $_SESSION['info'] = "Función de cambio en desarrollo. Use devolución y nueva venta.";
    header('Location: ../index.php?tab=ventas');
}

function eliminarVenta($conn)
{
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['error'] = "ID de venta inválido";
        header('Location: ../index.php?tab=ventas');
        return;
    }

    // Solo permitir eliminar si es un registro de devolución
    $sql_check = "SELECT metodo_pago FROM historial_venta WHERE id_historial_venta = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        if (stripos($row['metodo_pago'], 'DEVUELTA') === false && stripos($row['metodo_pago'], 'DEVOLUCION') === false) {
            $_SESSION['error'] = "No se puede eliminar una venta real. Use devolución en su lugar.";
            $stmt_check->close();
            header('Location: ../index.php?tab=ventas');
            return;
        }
    }
    $stmt_check->close();

    $sql = "DELETE FROM historial_venta WHERE id_historial_venta = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registro eliminado correctamente";
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php?tab=ventas');
}
