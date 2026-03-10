<?php
// CRUD/crud_proveedores.php
require_once __DIR__ . '/../conexion.php';

// Iniciar sesión para mensajes
session_start();

// Determinar la acción a realizar
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'crear':
    case 'editar':
        guardarProveedor($conn);
        break;

    case 'eliminar':
        eliminarProveedor($conn);
        break;

    default:
        // Si no hay acción específica, redirigir al index
        header('Location: ../index.php');
        break;
}

function guardarProveedor($conn)
{
    $id = isset($_POST['id_proveedor']) ? $_POST['id_proveedor'] : '';
    $nombre = $_POST['nombre_proveedor'];
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $contacto2 = $_POST['contacto2'] ?? '';
    $direccion = $_POST['direccion'] ?? '';

    if (empty($nombre)) {
        $_SESSION['error'] = "El nombre del proveedor es requerido";
        header('Location: ../index.php');
        return;
    }

    if (empty($id)) {
        // Crear nuevo proveedor
        $sql = "INSERT INTO proveedores (nombre_proveedor, telefono, direccion, email, contacto2) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $nombre, $telefono, $direccion, $email, $contacto2);
        $mensaje = "Proveedor creado correctamente";
    } else {
        // Actualizar proveedor existente
        $sql = "UPDATE proveedores 
                SET nombre_proveedor = ?, telefono = ?, direccion = ?, email = ?, contacto2 = ? 
                WHERE id_proveedor = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $nombre, $telefono, $direccion, $email, $contacto2, $id);
        $mensaje = "Proveedor actualizado correctamente";
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = $mensaje;
    } else {
        $_SESSION['error'] = "Error al guardar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php');
}

function eliminarProveedor($conn)
{
    $id = isset($_GET['id']) ? $_GET['id'] : 0;

    if ($id <= 0) {
        $_SESSION['error'] = "ID de proveedor inválido";
        header('Location: ../index.php');
        return;
    }

    // Verificar si tiene productos asociados
    $check_sql = "SELECT COUNT(*) as total FROM productos WHERE id_proveedor = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['total'] > 0) {
        $_SESSION['error'] = "No se puede eliminar porque tiene $row[total] producto(s) asociado(s)";
        header('Location: ../index.php');
        return;
    }

    $sql = "DELETE FROM proveedores WHERE id_proveedor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Proveedor eliminado correctamente";
        } else {
            $_SESSION['error'] = "Proveedor no encontrado";
        }
    } else {
        $_SESSION['error'] = "Error al eliminar: " . $stmt->error;
    }

    $stmt->close();
    header('Location: ../index.php');
}

$conn->close();
