<?php
// CRUD/crud_historial_salida.php
require_once __DIR__ . '/../conexion.php';

// Iniciar sesión para mensajes
session_start();

// Determinar la acción a realizar
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'salida':
        registrarSalida($conn);
        break;

    case 'devolucion':
        registrarDevolucion($conn);
        break;

    case 'eliminar':
        eliminarRegistro($conn);
        break;

    default:
        // Si no hay acción específica, redirigir al index
        header('Location: ../index.php?tab=salidas');
        break;
}

function registrarSalida($conn)
{
    $id_producto = $_POST['id_producto'] ?? 0;
    $cantidad_grande = intval($_POST['cantidad_grande'] ?? 0);
    $cantidad_unidad = intval($_POST['cantidad_unidad'] ?? 0);
    $documento_ref = $_POST['documento_ref'] ?? '';
    $destino = $_POST['destino'] ?? '';

    // Validaciones básicas
    if ($id_producto <= 0) {
        $_SESSION['error'] = "Debe seleccionar un producto";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    if ($cantidad_grande <= 0 && $cantidad_unidad <= 0) {
        $_SESSION['error'] = "Debe ingresar al menos una cantidad";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    if (empty($destino)) {
        $_SESSION['error'] = "El destino es requerido";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    // Obtener información del producto
    $sql_producto = "SELECT * FROM productos WHERE id_producto = ?";
    $stmt_producto = $conn->prepare($sql_producto);
    $stmt_producto->bind_param("i", $id_producto);
    $stmt_producto->execute();
    $result_producto = $stmt_producto->get_result();

    if ($result_producto->num_rows == 0) {
        $_SESSION['error'] = "Producto no encontrado";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    $producto = $result_producto->fetch_assoc();
    $stmt_producto->close();

    // Calcular unidades totales disponibles
    $unidades_disponibles = ($producto['cantidad_grande'] * $producto['cantidad_unidad']) + $producto['unidades_sueltas'];
    $unidades_salida = ($cantidad_grande * $producto['cantidad_unidad']) + $cantidad_unidad;

    if ($unidades_salida > $unidades_disponibles) {
        $_SESSION['error'] = "No hay suficiente inventario. Disponible: $unidades_disponibles unidades";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Actualizar inventario en tabla productos
        $nuevas_grandes = $producto['cantidad_grande'] - $cantidad_grande;
        $nuevas_sueltas = $producto['unidades_sueltas'] - $cantidad_unidad;

        // Si las sueltas quedan negativas, convertir una grande en sueltas
        if ($nuevas_sueltas < 0) {
            $grandes_a_convertir = ceil(abs($nuevas_sueltas) / $producto['cantidad_unidad']);
            $nuevas_grandes -= $grandes_a_convertir;
            $nuevas_sueltas += $grandes_a_convertir * $producto['cantidad_unidad'];
        }

        $sql_update = "UPDATE productos SET cantidad_grande = ?, unidades_sueltas = ? WHERE id_producto = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iii", $nuevas_grandes, $nuevas_sueltas, $id_producto);
        $stmt_update->execute();
        $stmt_update->close();

        // Registrar en historial_salida
        $sql_insert = "INSERT INTO historial_salida 
                      (id_producto, nombre_producto, modulo, cantidad_grande, cantidad_unidad, documento_ref, destino) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param(
            "issiiss",
            $id_producto,
            $producto['nombre_producto'],
            $producto['modulo'],
            $cantidad_grande,
            $cantidad_unidad,
            $documento_ref,
            $destino
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        $conn->commit();
        $_SESSION['success'] = "Salida registrada correctamente. Se dieron de baja $unidades_salida unidades.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al registrar la salida: " . $e->getMessage();
    }

    header('Location: ../index.php?tab=salidas');
}

function registrarDevolucion($conn)
{
    $id_historial = $_POST['id_historial'] ?? 0;
    $cantidad_grande_devuelta = intval($_POST['cantidad_grande_devuelta'] ?? 0);
    $cantidad_unidad_devuelta = intval($_POST['cantidad_unidad_devuelta'] ?? 0);
    $motivo_devolucion = $_POST['motivo_devolucion'] ?? '';

    if ($id_historial <= 0) {
        $_SESSION['error'] = "Registro de salida no válido";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    if ($cantidad_grande_devuelta <= 0 && $cantidad_unidad_devuelta <= 0) {
        $_SESSION['error'] = "Debe ingresar al menos una cantidad a devolver";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    // Obtener información del historial
    $sql_historial = "SELECT hs.*, p.cantidad_unidad as unidades_por_modulo 
                     FROM historial_salida hs
                     JOIN productos p ON hs.id_producto = p.id_producto
                     WHERE hs.id_historial_salida = ?";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("i", $id_historial);
    $stmt_historial->execute();
    $result_historial = $stmt_historial->get_result();

    if ($result_historial->num_rows == 0) {
        $_SESSION['error'] = "Registro de salida no encontrado";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    $historial = $result_historial->fetch_assoc();
    $stmt_historial->close();

    // Validar que no se devuelva más de lo que se sacó
    if (
        $cantidad_grande_devuelta > $historial['cantidad_grande'] ||
        ($cantidad_grande_devuelta == $historial['cantidad_grande'] && $cantidad_unidad_devuelta > $historial['cantidad_unidad'])
    ) {
        $_SESSION['error'] = "No puede devolver más de lo que se sacó originalmente";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    $conn->begin_transaction();

    try {
        // Actualizar inventario en productos
        $sql_update = "UPDATE productos 
                      SET cantidad_grande = cantidad_grande + ?,
                          unidades_sueltas = unidades_sueltas + ?
                      WHERE id_producto = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iii", $cantidad_grande_devuelta, $cantidad_unidad_devuelta, $historial['id_producto']);
        $stmt_update->execute();
        $stmt_update->close();

        // Registrar la devolución (como una nueva entrada en historial pero con nota)
        $nota = "DEVOLUCIÓN - $motivo_devolucion";
        $sql_devolucion = "INSERT INTO historial_salida 
                          (id_producto, nombre_producto, modulo, cantidad_grande, cantidad_unidad, documento_ref, destino) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt_devolucion = $conn->prepare($sql_devolucion);
        $cero = 0;
        $stmt_devolucion->bind_param(
            "issiiss",
            $historial['id_producto'],
            $historial['nombre_producto'],
            $historial['modulo'],
            $cero,
            $cero,
            $nota,
            $motivo_devolucion
        );
        $stmt_devolucion->execute();
        $stmt_devolucion->close();

        $conn->commit();
        $_SESSION['success'] = "Devolución registrada correctamente. Se reingresaron " .
            ($cantidad_grande_devuelta * $historial['unidades_por_modulo'] + $cantidad_unidad_devuelta) . " unidades.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al registrar la devolución: " . $e->getMessage();
    }

    header('Location: ../index.php?tab=salidas');
}

function eliminarRegistro($conn)
{
    $id = isset($_GET['id']) ? $_GET['id'] : 0;

    if ($id <= 0) {
        $_SESSION['error'] = "ID de registro inválido";
        header('Location: ../index.php?tab=salidas');
        return;
    }

    // Solo permitir eliminar si es un registro de devolución o si realmente es necesario
    $sql = "DELETE FROM historial_salida WHERE id_historial_salida = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Registro eliminado correctamente";
        } else {
            $_SESSION['error'] = "Registro no encontrado";
        }
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php?tab=salidas');
}

function buscarProductos($conn, $termino)
{
    $termino = "%$termino%";
    $sql = "SELECT p.*, pr.nombre_proveedor,
            (p.cantidad_grande * p.cantidad_unidad + p.unidades_sueltas) as stock_total
            FROM productos p
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
            WHERE p.nombre_producto LIKE ? 
               OR p.marca LIKE ? 
               OR pr.nombre_proveedor LIKE ?
            ORDER BY p.nombre_producto ASC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $termino, $termino, $termino);
    $stmt->execute();
    $result = $stmt->get_result();

    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }

    $stmt->close();
    return $productos;
}

$conn->close();
