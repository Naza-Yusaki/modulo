<?php
// conexion.php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'bodega_local';

// Crear conexión
$conn = new mysqli($host, $user, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset
$conn->set_charset("utf8mb4");
