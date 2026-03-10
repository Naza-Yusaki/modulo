<?php
// CRUD/crud_productos.php
require_once __DIR__ . '/../conexion.php';

// Iniciar sesión para mensajes
session_start();

// Determinar la acción a realizar
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'crear':
    case 'editar':
        guardarProducto($conn);
        break;

    case 'eliminar':
        eliminarProducto($conn);
        break;

    default:
        // Si no hay acción específica, redirigir al index
        header('Location: ../index.php');
        break;
}

function guardarProducto($conn)
{
    $id = isset($_POST['id_producto']) ? $_POST['id_producto'] : '';

    // Recoger todos los datos del formulario
    $id_proveedor = $_POST['id_proveedor'] ?? '';
    $nombre_producto = $_POST['nombre_producto'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $marca = $_POST['marca'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $presentacion = $_POST['presentacion'] ?? '';
    $modulo = $_POST['modulo'] ?? '';
    $cantidad_grande = intval($_POST['cantidad_grande'] ?? 0);
    $cantidad_unidad = intval($_POST['cantidad_unidad'] ?? 0);
    $unidades_sueltas = intval($_POST['unidades_sueltas'] ?? 0);
    $precio_compra = floatval($_POST['precio_compra'] ?? 0);
    $precio_venta_unidad = floatval($_POST['precio_venta_unidad'] ?? 0);
    $precio_venta_cantidad_grande = floatval($_POST['precio_venta_cantidad_grande'] ?? 0);
    $observaciones = $_POST['observaciones'] ?? '';

    // Validaciones básicas
    if (empty($nombre_producto)) {
        $_SESSION['error'] = "El nombre del producto es requerido";
        header('Location: ../index.php?tab=productos');
        return;
    }

    if (empty($id_proveedor)) {
        $_SESSION['error'] = "Debe seleccionar un proveedor";
        header('Location: ../index.php?tab=productos');
        return;
    }

    // CORRECCIÓN: Asegurar que cantidad_unidad no sea cero si hay cantidad_grande
    if ($cantidad_grande > 0 && $cantidad_unidad == 0) {
        $_SESSION['error'] = "Si hay cantidad grande, debe especificar cuántas unidades tiene cada una";
        header('Location: ../index.php?tab=productos');
        return;
    }

    // CORRECCIÓN: Normalizar el inventario
    if ($cantidad_unidad > 0) {
        $total_unidades = ($cantidad_grande * $cantidad_unidad) + $unidades_sueltas;
        $cantidad_grande = floor($total_unidades / $cantidad_unidad);
        $unidades_sueltas = $total_unidades % $cantidad_unidad;
    }

    if (empty($id)) {
        // Crear nuevo producto
        $sql = "INSERT INTO productos (
                    id_proveedor, nombre_producto, descripcion, marca, categoria, 
                    presentacion, modulo, cantidad_grande, cantidad_unidad, 
                    unidades_sueltas, precio_compra, precio_venta_unidad, 
                    precio_venta_cantidad_grande, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssiiiddds",
            $id_proveedor,
            $nombre_producto,
            $descripcion,
            $marca,
            $categoria,
            $presentacion,
            $modulo,
            $cantidad_grande,
            $cantidad_unidad,
            $unidades_sueltas,
            $precio_compra,
            $precio_venta_unidad,
            $precio_venta_cantidad_grande,
            $observaciones
        );
        $mensaje = "Producto creado correctamente";
    } else {
        // Actualizar producto existente
        $sql = "UPDATE productos SET 
                    id_proveedor = ?, nombre_producto = ?, descripcion = ?, 
                    marca = ?, categoria = ?, presentacion = ?, modulo = ?,
                    cantidad_grande = ?, cantidad_unidad = ?, unidades_sueltas = ?,
                    precio_compra = ?, precio_venta_unidad = ?, 
                    precio_venta_cantidad_grande = ?, observaciones = ?
                WHERE id_producto = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssiiidddsi",
            $id_proveedor,
            $nombre_producto,
            $descripcion,
            $marca,
            $categoria,
            $presentacion,
            $modulo,
            $cantidad_grande,
            $cantidad_unidad,
            $unidades_sueltas,
            $precio_compra,
            $precio_venta_unidad,
            $precio_venta_cantidad_grande,
            $observaciones,
            $id
        );
        $mensaje = "Producto actualizado correctamente";
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = $mensaje;
    } else {
        $_SESSION['error'] = "Error al guardar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php?tab=productos');
}

function eliminarProducto($conn)
{
    $id = isset($_GET['id']) ? $_GET['id'] : 0;

    if ($id <= 0) {
        $_SESSION['error'] = "ID de producto inválido";
        header('Location: ../index.php?tab=productos');
        return;
    }

    // Verificar si tiene ventas o salidas asociadas
    $check_ventas = "SELECT COUNT(*) as total FROM historial_venta WHERE id_producto = ?";
    $check_stmt = $conn->prepare($check_ventas);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result_ventas = $check_stmt->get_result();
    $row_ventas = $result_ventas->fetch_assoc();

    if ($row_ventas['total'] > 0) {
        $_SESSION['error'] = "No se puede eliminar porque tiene $row_ventas[total] venta(s) asociada(s)";
        header('Location: ../index.php?tab=productos');
        return;
    }

    $check_salidas = "SELECT COUNT(*) as total FROM historial_salida WHERE id_producto = ?";
    $check_stmt = $conn->prepare($check_salidas);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result_salidas = $check_stmt->get_result();
    $row_salidas = $result_salidas->fetch_assoc();

    if ($row_salidas['total'] > 0) {
        $_SESSION['error'] = "No se puede eliminar porque tiene $row_salidas[total] salida(s) asociada(s)";
        header('Location: ../index.php?tab=productos');
        return;
    }

    $sql = "DELETE FROM productos WHERE id_producto = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Producto eliminado correctamente";
        } else {
            $_SESSION['error'] = "Producto no encontrado";
        }
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php?tab=productos');
}

$conn->close();
