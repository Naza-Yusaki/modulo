<?php
// lista_precios.php
session_start();
require_once 'conexion.php';

// Obtener todos los productos con precios
$sql = "SELECT 
            p.id_producto,
            p.nombre_producto,
            p.marca,
            p.modulo,
            p.cantidad_unidad,
            p.precio_venta_unidad,
            p.precio_venta_cantidad_grande,
            pr.nombre_proveedor,
            (p.cantidad_grande * p.cantidad_unidad + p.unidades_sueltas) as stock_total
        FROM productos p
        LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
        WHERE p.precio_venta_unidad > 0 OR p.precio_venta_cantidad_grande > 0
        ORDER BY p.nombre_producto ASC";

$result = $conn->query($sql);
$productos = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Precios</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .buscador-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .buscador-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .buscador-input {
            width: 100%;
            padding: 18px 25px;
            font-size: 1.2em;
            border: 3px solid #e0e0e0;
            border-radius: 50px;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .buscador-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .stats {
            text-align: center;
            margin-top: 15px;
            color: #666;
        }

        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 30px;
            max-height: 600px;
            overflow-y: auto;
        }

        .producto-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
            cursor: pointer;
        }

        .producto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .producto-nombre {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .producto-marca {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.95em;
        }

        .producto-detalle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #e0e0e0;
        }

        .precio-unidad {
            text-align: center;
            flex: 1;
        }

        .precio-unidad .label {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .precio-unidad .value {
            font-size: 1.6em;
            font-weight: bold;
            color: #28a745;
        }

        .precio-mayor {
            text-align: center;
            flex: 1;
            border-left: 1px solid #e0e0e0;
        }

        .precio-mayor .label {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .precio-mayor .value {
            font-size: 1.4em;
            font-weight: bold;
            color: #17a2b8;
        }

        .badge-modulo {
            display: inline-block;
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            color: #495057;
            margin-top: 10px;
        }

        .badge-stock {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-left: 5px;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            color: #666;
        }

        .btn-volver {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-volver:hover {
            background: #5a67d8;
        }

        .no-resultados {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2em;
        }

        .indice-rapido {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
            padding: 10px;
        }

        .letra-indice {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            color: #666;
            transition: all 0.2s;
        }

        .letra-indice:hover {
            background: #667eea;
            color: white;
        }

        /* Agrega esto al final de tus estilos */
        .floating-panel {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 20px;
            z-index: 1000;
            border: 3px solid #667eea;
        }

        .floating-panel.minimized {
            width: 60px;
            height: 60px;
            border-radius: 30px;
            overflow: hidden;
            cursor: pointer;
        }

        .floating-panel .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            cursor: pointer;
        }

        .floating-panel .toggle {
            font-size: 1.5em;
            background: none;
            border: none;
            cursor: pointer;
        }
    </style>

</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📋 LISTA DE PRECIOS</h1>
            <p>Consulta rápida de productos y precios</p>
        </div>

        <div class="buscador-section">
            <div class="buscador-container">
                <input type="text"
                    class="buscador-input"
                    id="buscador"
                    placeholder="🔍 Buscar producto, marca o proveedor..."
                    autocomplete="off"
                    onkeyup="filtrarProductos()">
            </div>
            <div class="stats" id="stats">
                Mostrando <span id="mostrando"><?php echo count($productos); ?></span> de <?php echo count($productos); ?> productos
            </div>

            <!-- Índice alfabético rápido -->
            <div class="indice-rapido" id="indice-rapido">
                <?php
                $letras = range('A', 'Z');
                foreach ($letras as $letra) {
                    echo "<div class='letra-indice' onclick='filtrarPorLetra(\"$letra\")'>$letra</div>";
                }
                echo "<div class='letra-indice' onclick='filtrarPorLetra(\"#\")'>#</div>";
                ?>
            </div>
        </div>

        <!-- Grid de productos -->
        <div class="productos-grid" id="productos-grid">
            <?php foreach ($productos as $p): ?>
                <div class="producto-card"
                    data-nombre="<?php echo strtolower($p['nombre_producto']); ?>"
                    data-marca="<?php echo strtolower($p['marca'] ?? ''); ?>"
                    data-proveedor="<?php echo strtolower($p['nombre_proveedor'] ?? ''); ?>"
                    onclick="resaltarProducto(this)">
                    <div class="producto-nombre"><?php echo htmlspecialchars($p['nombre_producto']); ?></div>
                    <?php if (!empty($p['marca'])): ?>
                        <div class="producto-marca"><?php echo htmlspecialchars($p['marca']); ?></div>
                    <?php endif; ?>

                    <div style="margin: 10px 0;">
                        <span class="badge-modulo">📦 <?php echo $p['modulo']; ?> (<?php echo $p['cantidad_unidad']; ?> und)</span>
                        <span class="badge-stock">📊 Stock: <?php echo $p['stock_total']; ?> und</span>
                    </div>

                    <div class="producto-detalle">
                        <div class="precio-unidad">
                            <div class="label">Unidad</div>
                            <div class="value">$<?php echo number_format($p['precio_venta_unidad'], 0); ?></div>
                        </div>
                        <div class="precio-mayor">
                            <div class="label"><?php echo $p['modulo']; ?></div>
                            <div class="value">$<?php echo number_format($p['precio_venta_cantidad_grande'], 0); ?></div>
                        </div>
                    </div>

                    <div style="font-size: 0.8em; color: #999; margin-top: 10px; text-align: right;">
                        Proveedor: <?php echo htmlspecialchars($p['nombre_proveedor'] ?? 'N/A'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <p>Sistema de Gestión de Bodega - Lista de Precios</p>
            <a href="index.php" class="btn-volver">← Volver al Sistema</a>
        </div>
    </div>

    <script>
        const productos = document.querySelectorAll('.producto-card');

        function filtrarProductos() {
            const termino = document.getElementById('buscador').value.toLowerCase();
            let contador = 0;

            productos.forEach(p => {
                const nombre = p.dataset.nombre;
                const marca = p.dataset.marca;
                const proveedor = p.dataset.proveedor;

                if (nombre.includes(termino) || marca.includes(termino) || proveedor.includes(termino)) {
                    p.style.display = 'block';
                    contador++;
                } else {
                    p.style.display = 'none';
                }
            });

            document.getElementById('mostrando').textContent = contador;

            if (contador === 0) {
                document.getElementById('productos-grid').innerHTML = '<div class="no-resultados">❌ No se encontraron productos que coincidan con la búsqueda</div>';
            }
        }

        function filtrarPorLetra(letra) {
            const termino = letra.toLowerCase();
            let contador = 0;

            productos.forEach(p => {
                const nombre = p.dataset.nombre;

                if (letra === '#') {
                    // Mostrar productos que empiezan con número
                    if (nombre.match(/^\d/)) {
                        p.style.display = 'block';
                        contador++;
                    } else {
                        p.style.display = 'none';
                    }
                } else {
                    if (nombre.startsWith(termino)) {
                        p.style.display = 'block';
                        contador++;
                    } else {
                        p.style.display = 'none';
                    }
                }
            });

            document.getElementById('buscador').value = '';
            document.getElementById('mostrando').textContent = contador;
        }

        function resaltarProducto(elemento) {
            // Remover resaltado de todos
            productos.forEach(p => {
                p.style.transform = 'scale(1)';
                p.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            });

            // Resaltar el seleccionado
            elemento.style.transform = 'scale(1.02)';
            elemento.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.4)';

            setTimeout(() => {
                elemento.style.transform = 'scale(1)';
                elemento.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            }, 500);
        }
    </script>
</body>

</html>