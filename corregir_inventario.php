<?php
// corregir_inventario.php
require_once 'conexion.php';

echo "<h2>Corrigiendo inventario...</h2>";

$sql = "SELECT * FROM productos";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($producto = $result->fetch_assoc()) {
        $id = $producto['id_producto'];
        $nombre = $producto['nombre_producto'];
        $cantidad_grande = $producto['cantidad_grande'];
        $cantidad_unidad = $producto['cantidad_unidad'];
        $unidades_sueltas = $producto['unidades_sueltas'];

        // Calcular total de unidades
        $total_unidades = ($cantidad_grande * $cantidad_unidad) + $unidades_sueltas;

        // Calcular la distribución correcta
        $nuevas_grandes = floor($total_unidades / $cantidad_unidad);
        $nuevas_sueltas = $total_unidades % $cantidad_unidad;

        echo "Producto: $nombre<br>";
        echo "Antes: {$cantidad_grande} grandes, {$cantidad_unidad} und/grande, {$unidades_sueltas} sueltas = {$total_unidades} und<br>";
        echo "Después: {$nuevas_grandes} grandes, {$cantidad_unidad} und/grande, {$nuevas_sueltas} sueltas<br><br>";

        // Actualizar el producto
        $sql_update = "UPDATE productos SET cantidad_grande = ?, unidades_sueltas = ? WHERE id_producto = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("iii", $nuevas_grandes, $nuevas_sueltas, $id);
        $stmt->execute();
        $stmt->close();
    }
    echo "<h3 style='color: green;'>✅ Inventario corregido correctamente</h3>";
} else {
    echo "<h3>No hay productos para corregir</h3>";
}

$conn->close();
echo "<br><a href='index.php'>Volver al inicio</a>";
