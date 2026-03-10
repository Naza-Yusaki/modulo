<?php
// index.php
session_start();
require_once 'conexion.php';

// Obtener la pestaña activa
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'proveedores';

// Obtener todos los proveedores para el select de productos
$sql_proveedores = "SELECT id_proveedor, nombre_proveedor FROM proveedores ORDER BY nombre_proveedor ASC";
$result_proveedores = $conn->query($sql_proveedores);
$proveedores_lista = [];
if ($result_proveedores && $result_proveedores->num_rows > 0) {
    while ($row = $result_proveedores->fetch_assoc()) {
        $proveedores_lista[] = $row;
    }
}
/// ========== VARIABLES GLOBALES ==========
$tab = $_GET['tab'] ?? 'inicio';
$hoy = date('Y-m-d');

// ========== DATOS PARA VENTAS (VERSIÓN ORIGINAL SIN DESGLOSE) ==========
$productos_venta = [];
$ventas_hoy = [];
$total_hoy = 0;
$historial_ventas = [];
$efectivo_hoy = 0;
$nequi_hoy = 0;
$mixto_hoy = 0;

// ========== CONSULTAS DE VENTAS ==========
if ($tab == 'ventas') {

    // 1. OBTENER PRODUCTOS DISPONIBLES PARA VENTA
    $sql_productos_venta = "SELECT 
                                p.id_producto,
                                p.nombre_producto,
                                p.marca,
                                p.modulo,
                                p.cantidad_grande,
                                p.unidades_sueltas,
                                p.cantidad_unidad,
                                p.precio_venta_unidad,
                                p.precio_venta_cantidad_grande,
                                pr.nombre_proveedor,
                                (p.cantidad_grande * p.cantidad_unidad + p.unidades_sueltas) as stock_total
                            FROM productos p
                            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
                            WHERE (p.cantidad_grande > 0 OR p.unidades_sueltas > 0)
                            ORDER BY p.nombre_producto ASC";

    $result_productos_venta = $conn->query($sql_productos_venta);

    if ($result_productos_venta && $result_productos_venta->num_rows > 0) {
        while ($prod = $result_productos_venta->fetch_assoc()) {
            $productos_venta[] = $prod;
        }
    }

    // 2. OBTENER VENTAS DEL DÍA (SIN CAMPOS DE DESGLOSE)
    $sql_ventas_hoy = "SELECT 
                          hv.id_historial_venta,
                          hv.id_producto,
                          hv.nombre_producto,
                          hv.modulo,
                          hv.cantidad_grande,
                          hv.cantidad_unidad,
                          hv.precio,
                          hv.metodo_pago,
                          hv.fecha_hora
                       FROM historial_venta hv
                       WHERE DATE(hv.fecha_hora) = ? 
                       AND hv.precio > 0 
                       AND hv.metodo_pago NOT LIKE '%DEVUELTA%'
                       AND hv.metodo_pago NOT LIKE '%DEVOLUCION%'
                       ORDER BY hv.fecha_hora DESC";

    $stmt_ventas_hoy = $conn->prepare($sql_ventas_hoy);
    $stmt_ventas_hoy->bind_param("s", $hoy);
    $stmt_ventas_hoy->execute();
    $result_ventas_hoy = $stmt_ventas_hoy->get_result();

    if ($result_ventas_hoy && $result_ventas_hoy->num_rows > 0) {
        while ($venta = $result_ventas_hoy->fetch_assoc()) {
            $ventas_hoy[] = $venta;
            $total_hoy += floatval($venta['precio']);

            // Calcular totales por método de pago
            if ($venta['metodo_pago'] == 'efectivo') {
                $efectivo_hoy += floatval($venta['precio']);
            } elseif ($venta['metodo_pago'] == 'nequi') {
                $nequi_hoy += floatval($venta['precio']);
            } elseif ($venta['metodo_pago'] == 'mixto') {
                $mixto_hoy += floatval($venta['precio']);
            }
        }
    }

    $stmt_ventas_hoy->close();

    // 3. OBTENER HISTORIAL COMPLETO DE VENTAS
    $sql_historial_ventas = "SELECT 
                              hv.id_historial_venta,
                              hv.id_producto,
                              hv.nombre_producto,
                              hv.modulo,
                              hv.cantidad_grande,
                              hv.cantidad_unidad,
                              hv.precio,
                              hv.metodo_pago,
                              hv.fecha_hora,
                              CASE 
                                 WHEN hv.metodo_pago LIKE '%DEVUELTA%' THEN 'Venta Anulada'
                                 WHEN hv.metodo_pago LIKE '%DEVOLUCION%' THEN 'Devolución'
                                 WHEN hv.precio < 0 THEN 'Ajuste'
                                 ELSE 'Venta'
                              END as tipo
                           FROM historial_venta hv
                           ORDER BY hv.fecha_hora DESC
                           LIMIT 100";

    $result_historial_ventas = $conn->query($sql_historial_ventas);

    if ($result_historial_ventas && $result_historial_ventas->num_rows > 0) {
        while ($reg = $result_historial_ventas->fetch_assoc()) {
            $historial_ventas[] = $reg;
        }
    }
}
// ===== DATOS PARA PROVEEDORES =====
if ($tab == 'proveedores') {
    // Obtener todos los proveedores
    $sql = "SELECT * FROM proveedores ORDER BY nombre_proveedor ASC";
    $result = $conn->query($sql);
    $proveedores = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $proveedores[] = $row;
        }
    }

    // Obtener datos para edición si se solicita
    $proveedor_editar = null;
    if (isset($_GET['editar']) && $_GET['editar'] > 0) {
        $id_editar = $_GET['editar'];
        $sql_editar = "SELECT * FROM proveedores WHERE id_proveedor = ?";
        $stmt = $conn->prepare($sql_editar);
        $stmt->bind_param("i", $id_editar);
        $stmt->execute();
        $result_editar = $stmt->get_result();
        if ($result_editar && $result_editar->num_rows > 0) {
            $proveedor_editar = $result_editar->fetch_assoc();
        }
        $stmt->close();
    }
}

// ===== DATOS PARA PRODUCTOS =====
if ($tab == 'productos') {
    // Obtener todos los productos
    $sql = "SELECT p.*, pr.nombre_proveedor 
            FROM productos p
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
            ORDER BY p.nombre_producto ASC";
    $result = $conn->query($sql);
    $productos = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    }

    // Obtener datos para edición si se solicita
    $producto_editar = null;
    if (isset($_GET['editar']) && $_GET['editar'] > 0) {
        $id_editar = $_GET['editar'];
        $sql_editar = "SELECT * FROM productos WHERE id_producto = ?";
        $stmt = $conn->prepare($sql_editar);
        $stmt->bind_param("i", $id_editar);
        $stmt->execute();
        $result_editar = $stmt->get_result();
        if ($result_editar && $result_editar->num_rows > 0) {
            $producto_editar = $result_editar->fetch_assoc();
        }
        $stmt->close();
    }
}

// ===== DATOS PARA PRODUCTOS =====
if ($tab == 'productos') {
    // Obtener todos los productos
    $sql = "SELECT p.*, pr.nombre_proveedor 
            FROM productos p
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
            ORDER BY p.nombre_producto ASC";
    $result = $conn->query($sql);
    $productos = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    }

    // Obtener datos para edición si se solicita
    $producto_editar = null;
    if (isset($_GET['editar']) && $_GET['editar'] > 0) {
        $id_editar = $_GET['editar'];
        $sql_editar = "SELECT * FROM productos WHERE id_producto = ?";
        $stmt = $conn->prepare($sql_editar);
        $stmt->bind_param("i", $id_editar);
        $stmt->execute();
        $result_editar = $stmt->get_result();
        if ($result_editar && $result_editar->num_rows > 0) {
            $producto_editar = $result_editar->fetch_assoc();
        }
        $stmt->close();
    }
}

// ===== DATOS PARA SALIDAS =====
// Si tienes código para salidas, va aquí
if ($tab == 'salidas') {
    // Tu código de salidas existente
}

// Cerrar conexión

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Bodega</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        /* Tabs de navegación */
        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab:hover {
            background: #e9ecef;
            color: #333;
        }

        .tab.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: white;
        }

        .tab a {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .main-content {
            padding: 30px;
        }

        /* Formularios */
        .form-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-container h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 600;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            color: white;
        }

        /* Tablas */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table-container h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .action-btn.edit {
            background-color: #ffc107;
            color: white;
        }

        .action-btn.delete {
            background-color: #dc3545;
            color: white;
        }

        .action-btn.view {
            background-color: #17a2b8;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Buscador */
        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Badges para categorías */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .badge-bebidas {
            background: #cce5ff;
            color: #004085;
        }

        .badge-alimentos {
            background: #d4edda;
            color: #155724;
        }

        .badge-limpieza {
            background: #fff3cd;
            color: #856404;
        }

        .badge-higiene {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-otros {
            background: #e2e3e5;
            color: #383d41;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📦 Sistema de Gestión de Bodega</h1>
            <p>Administración de Proveedores y Productos</p>
        </div>

        <!-- Tabs de navegación -->
        <!-- Tabs de navegación (agrega esta nueva pestaña) -->
        <div class="tabs">
            <div class="tab <?php echo $tab == 'proveedores' ? 'active' : ''; ?>">
                <a href="?tab=proveedores">👥 Proveedores</a>
            </div>
            <div class="tab <?php echo $tab == 'productos' ? 'active' : ''; ?>">
                <a href="?tab=productos">📦 Productos</a>
            </div>
            <div class="tab <?php echo $tab == 'salidas' ? 'active' : ''; ?>">
                <a href="?tab=salidas">🚚 Salidas/Devoluciones</a>
            </div>
            <div class="tab <?php echo $tab == 'ventas' ? 'active' : ''; ?>">
                <a href="?tab=ventas">💰 Ventas</a>
            </div>
        </div>

        <div class="main-content">
            <!-- Mostrar mensajes de sesión -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>


            <?php if ($tab == 'proveedores'): ?>
                <!-- ========== SECCIÓN DE PROVEEDORES ========== -->
                <!-- Formulario de Proveedores -->
                <div class="form-container">
                    <h2>✏️ <?php echo isset($proveedor_editar) ? 'Editar' : 'Nuevo'; ?> Proveedor</h2>
                    <form action="CRUD/crud_proveedores.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo isset($proveedor_editar) ? 'editar' : 'crear'; ?>">
                        <?php if (isset($proveedor_editar)): ?>
                            <input type="hidden" name="id_proveedor" value="<?php echo $proveedor_editar['id_proveedor']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre_proveedor">Nombre del Proveedor *</label>
                                <input type="text" id="nombre_proveedor" name="nombre_proveedor" required
                                    placeholder="Ingrese el nombre del proveedor"
                                    value="<?php echo isset($proveedor_editar) ? htmlspecialchars($proveedor_editar['nombre_proveedor']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono"
                                    placeholder="Ingrese el teléfono"
                                    value="<?php echo isset($proveedor_editar) ? htmlspecialchars($proveedor_editar['telefono'] ?? '') : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email"
                                    placeholder="correo@ejemplo.com"
                                    value="<?php echo isset($proveedor_editar) ? htmlspecialchars($proveedor_editar['email'] ?? '') : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="contacto2">Contacto Alternativo</label>
                                <input type="text" id="contacto2" name="contacto2"
                                    placeholder="Nombre del contacto alternativo"
                                    value="<?php echo isset($proveedor_editar) ? htmlspecialchars($proveedor_editar['contacto2'] ?? '') : ''; ?>">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="direccion">Dirección</label>
                                <input type="text" id="direccion" name="direccion"
                                    placeholder="Dirección completa"
                                    value="<?php echo isset($proveedor_editar) ? htmlspecialchars($proveedor_editar['direccion'] ?? '') : ''; ?>">
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-success">💾 Guardar Proveedor</button>
                            <?php if (isset($proveedor_editar)): ?>
                                <a href="?tab=proveedores" class="btn btn-secondary">❌ Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Lista de Proveedores -->
                <div class="table-container">
                    <h2>📋 Lista de Proveedores</h2>

                    <!-- Buscador -->
                    <div class="search-box">
                        <input type="text" id="buscadorProveedores" placeholder="Buscar proveedor..." onkeyup="filtrarTabla('tablaProveedores', this.value)">
                    </div>

                    <!-- Tabla de proveedores -->
                    <table id="tablaProveedores">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th>Contacto Alt.</th>
                                <th>Dirección</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($proveedores)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No hay proveedores registrados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($proveedores as $prov): ?>
                                    <tr>
                                        <td><?php echo $prov['id_proveedor']; ?></td>
                                        <td><?php echo htmlspecialchars($prov['nombre_proveedor']); ?></td>
                                        <td><?php echo htmlspecialchars($prov['telefono'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($prov['email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($prov['contacto2'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($prov['direccion'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($prov['fecha_registro'])); ?></td>
                                        <td class="action-buttons">
                                            <a href="?tab=proveedores&editar=<?php echo $prov['id_proveedor']; ?>" class="action-btn edit">✏️ Editar</a>
                                            <a href="CRUD/crud_proveedores.php?action=eliminar&id=<?php echo $prov['id_proveedor']; ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('¿Está seguro de eliminar este proveedor?')">🗑️ Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab == 'productos'): ?>
                <!-- ========== SECCIÓN DE PRODUCTOS ========== -->
                <!-- Formulario de Productos -->
                <div class="form-container">
                    <h2>✏️ <?php echo isset($producto_editar) ? 'Editar' : 'Nuevo'; ?> Producto</h2>
                    <form action="CRUD/crud_productos.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo isset($producto_editar) ? 'editar' : 'crear'; ?>">
                        <?php if (isset($producto_editar)): ?>
                            <input type="hidden" name="id_producto" value="<?php echo $producto_editar['id_producto']; ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="id_proveedor">Proveedor *</label>
                                <select id="id_proveedor" name="id_proveedor" required>
                                    <option value="">Seleccione un proveedor</option>
                                    <?php foreach ($proveedores_lista as $prov): ?>
                                        <option value="<?php echo $prov['id_proveedor']; ?>"
                                            <?php echo (isset($producto_editar) && $producto_editar['id_proveedor'] == $prov['id_proveedor']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nombre_producto">Nombre del Producto *</label>
                                <input type="text" id="nombre_producto" name="nombre_producto" required
                                    placeholder="Ingrese el nombre del producto"
                                    value="<?php echo isset($producto_editar) ? htmlspecialchars($producto_editar['nombre_producto']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="marca">Marca</label>
                                <input type="text" id="marca" name="marca"
                                    placeholder="Marca del producto"
                                    value="<?php echo isset($producto_editar) ? htmlspecialchars($producto_editar['marca'] ?? '') : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="categoria">Categoría *</label>
                                <select id="categoria" name="categoria" required>
                                    <option value="">Seleccione categoría</option>
                                    <option value="Bebidas" <?php echo (isset($producto_editar) && $producto_editar['categoria'] == 'Bebidas') ? 'selected' : ''; ?>>Bebidas</option>
                                    <option value="Alimentos" <?php echo (isset($producto_editar) && $producto_editar['categoria'] == 'Alimentos') ? 'selected' : ''; ?>>Alimentos</option>
                                    <option value="Limpieza" <?php echo (isset($producto_editar) && $producto_editar['categoria'] == 'Limpieza') ? 'selected' : ''; ?>>Limpieza</option>
                                    <option value="Higiene" <?php echo (isset($producto_editar) && $producto_editar['categoria'] == 'Higiene') ? 'selected' : ''; ?>>Higiene</option>
                                    <option value="Otros" <?php echo (isset($producto_editar) && $producto_editar['categoria'] == 'Otros') ? 'selected' : ''; ?>>Otros</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="presentacion">Presentación</label>
                                <input type="text" id="presentacion" name="presentacion"
                                    placeholder="Ej: Botella 1.5L, Bolsa 500g"
                                    value="<?php echo isset($producto_editar) ? htmlspecialchars($producto_editar['presentacion'] ?? '') : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="modulo">Módulo *</label>
                                <select id="modulo" name="modulo" required>
                                    <option value="">Seleccione módulo</option>
                                    <option value="Caja" <?php echo (isset($producto_editar) && $producto_editar['modulo'] == 'Caja') ? 'selected' : ''; ?>>Caja</option>
                                    <option value="Paca" <?php echo (isset($producto_editar) && $producto_editar['modulo'] == 'Paca') ? 'selected' : ''; ?>>Paca</option>
                                    <option value="Paquete" <?php echo (isset($producto_editar) && $producto_editar['modulo'] == 'Paquete') ? 'selected' : ''; ?>>Paquete</option>
                                    <option value="Pedazo" <?php echo (isset($producto_editar) && $producto_editar['modulo'] == 'Pedazo') ? 'selected' : ''; ?>>Pedazo</option>
                                    <option value="Unidad" <?php echo (isset($producto_editar) && $producto_editar['modulo'] == 'Unidad') ? 'selected' : ''; ?>>Unidad</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="cantidad_grande">Cantidad Grande (Paquetes)</label>
                                <input type="number" id="cantidad_grande" name="cantidad_grande" min="0"
                                    placeholder="0"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['cantidad_grande'] : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="cantidad_unidad">Unidades por Paquete</label>
                                <input type="number" id="cantidad_unidad" name="cantidad_unidad" min="0"
                                    placeholder="0"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['cantidad_unidad'] : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="unidades_sueltas">Unidades Sueltas</label>
                                <input type="number" id="unidades_sueltas" name="unidades_sueltas" min="0"
                                    placeholder="0"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['unidades_sueltas'] : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label for="precio_compra">Precio de Compra</label>
                                <input type="number" id="precio_compra" name="precio_compra" min="0" step="0.01"
                                    placeholder="0.00"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['precio_compra'] : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="precio_venta_unidad">Precio Venta por Unidad</label>
                                <input type="number" id="precio_venta_unidad" name="precio_venta_unidad" min="0" step="0.01"
                                    placeholder="0.00"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['precio_venta_unidad'] : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="precio_venta_cantidad_grande">Precio Venta por Paquete</label>
                                <input type="number" id="precio_venta_cantidad_grande" name="precio_venta_cantidad_grande" min="0" step="0.01"
                                    placeholder="0.00"
                                    value="<?php echo isset($producto_editar) ? $producto_editar['precio_venta_cantidad_grande'] : ''; ?>">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="descripcion">Descripción</label>
                                <textarea id="descripcion" name="descripcion" placeholder="Descripción del producto"><?php echo isset($producto_editar) ? htmlspecialchars($producto_editar['descripcion'] ?? '') : ''; ?></textarea>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="observaciones">Observaciones</label>
                                <textarea id="observaciones" name="observaciones" placeholder="Observaciones adicionales"><?php echo isset($producto_editar) ? htmlspecialchars($producto_editar['observaciones'] ?? '') : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-success">💾 Guardar Producto</button>
                            <?php if (isset($producto_editar)): ?>
                                <a href="?tab=productos" class="btn btn-secondary">❌ Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Lista de Productos -->
                <div class="table-container">
                    <h2>📋 Lista de Productos</h2>

                    <!-- Buscador -->
                    <div class="search-box">
                        <input type="text" id="buscadorProductos" placeholder="Buscar producto..." onkeyup="filtrarTabla('tablaProductos', this.value)">
                    </div>

                    <!-- Tabla de productos -->
                    <table id="tablaProductos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th>Marca</th>
                                <th>Categoría</th>
                                <th>Módulo</th>
                                <th>Stock</th>
                                <th>Precio Venta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center;">No hay productos registrados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $prod):
                                    $stock_total = ($prod['cantidad_grande'] * $prod['cantidad_unidad']) + $prod['unidades_sueltas'];
                                    $badge_class = 'badge-otros';
                                    switch ($prod['categoria']) {
                                        case 'Bebidas':
                                            $badge_class = 'badge-bebidas';
                                            break;
                                        case 'Alimentos':
                                            $badge_class = 'badge-alimentos';
                                            break;
                                        case 'Limpieza':
                                            $badge_class = 'badge-limpieza';
                                            break;
                                        case 'Higiene':
                                            $badge_class = 'badge-higiene';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $prod['id_producto']; ?></td>
                                        <td><?php echo htmlspecialchars($prod['nombre_producto']); ?></td>
                                        <td><?php echo htmlspecialchars($prod['nombre_proveedor'] ?? 'Sin proveedor'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['marca'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo $prod['categoria']; ?></span></td>
                                        <td><?php echo $prod['modulo']; ?></td>
                                        <td>
                                            <?php echo $stock_total; ?> unidades
                                            <br><small>(<?php echo $prod['cantidad_grande']; ?> <?php echo strtolower($prod['modulo']); ?>s + <?php echo $prod['unidades_sueltas']; ?> sueltas)</small>
                                        </td>
                                        <td>
                                            $<?php echo number_format($prod['precio_venta_unidad'], 0); ?> x unidad<br>
                                            <small>$<?php echo number_format($prod['precio_venta_cantidad_grande'], 0); ?> x <?php echo strtolower($prod['modulo']); ?></small>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="?tab=productos&editar=<?php echo $prod['id_producto']; ?>" class="action-btn edit">✏️ Editar</a>
                                            <a href="CRUD/crud_productos.php?action=eliminar&id=<?php echo $prod['id_producto']; ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('¿Está seguro de eliminar este producto?')">🗑️ Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab == 'salidas'): ?>
                <!-- ========== SECCIÓN DE SALIDAS Y DEVOLUCIONES ========== -->

                <?php
                // Obtener productos con stock para los selects (solo para el select inicial, pero lo usaremos menos)
                $sql_productos_con_stock = "SELECT p.*, pr.nombre_proveedor,
                                (p.cantidad_grande * p.cantidad_unidad + p.unidades_sueltas) as stock_total,
                                CONCAT(p.nombre_producto, ' - ', p.marca, ' (', pr.nombre_proveedor, ')') as nombre_completo
                                FROM productos p
                                LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
                                WHERE (p.cantidad_grande > 0 OR p.unidades_sueltas > 0)
                                ORDER BY p.nombre_producto ASC";
                $result_productos_con_stock = $conn->query($sql_productos_con_stock);
                $productos_con_stock = [];
                if ($result_productos_con_stock && $result_productos_con_stock->num_rows > 0) {
                    while ($prod = $result_productos_con_stock->fetch_assoc()) {
                        $productos_con_stock[] = $prod;
                    }
                }

                // Obtener historial de salidas para devoluciones
                $sql_historial_salidas = "SELECT hs.*, p.nombre_producto, p.marca, p.cantidad_unidad as unidades_por_modulo,
                              CONCAT(p.nombre_producto, ' - ', DATE_FORMAT(hs.fecha_salida, '%d/%m/%Y')) as descripcion
                              FROM historial_salida hs
                              JOIN productos p ON hs.id_producto = p.id_producto
                              WHERE hs.destino NOT LIKE '%DEVOLUCIÓN%'
                              ORDER BY hs.fecha_salida DESC
                              LIMIT 50";
                $result_historial_salidas = $conn->query($sql_historial_salidas);
                $historial_salidas = [];
                if ($result_historial_salidas && $result_historial_salidas->num_rows > 0) {
                    while ($reg = $result_historial_salidas->fetch_assoc()) {
                        $historial_salidas[] = $reg;
                    }
                }

                // Obtener historial completo
                $sql_historial_completo = "SELECT hs.*, 
                              CASE 
                                 WHEN hs.destino LIKE '%DEVOLUCIÓN%' THEN 'Devolución'
                                 ELSE 'Salida'
                              END as tipo
                              FROM historial_salida hs
                              ORDER BY hs.fecha_salida DESC
                              LIMIT 100";
                $result_historial_completo = $conn->query($sql_historial_completo);
                $historial_completo = [];
                if ($result_historial_completo && $result_historial_completo->num_rows > 0) {
                    while ($reg = $result_historial_completo->fetch_assoc()) {
                        $historial_completo[] = $reg;
                    }
                }
                ?>

                <!-- Formulario de Salida con Búsqueda Mejorada -->
                <div class="form-container">
                    <h2>🚚 Registrar Salida de Productos</h2>
                    <form action="CRUD/crud_historial_salida.php" method="POST" onsubmit="return validarSalida()">
                        <input type="hidden" name="action" value="salida">
                        <input type="hidden" name="id_producto" id="id_producto_seleccionado">

                        <div class="form-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="buscador_productos">Buscar Producto *</label>
                                <div style="position: relative;">
                                    <input type="text"
                                        id="buscador_productos"
                                        class="form-control"
                                        placeholder="Escriba para buscar productos (mínimo 2 caracteres)"
                                        autocomplete="off"
                                        onkeyup="buscarProductos(this.value)">
                                    <div id="resultados_busqueda" class="resultados-busqueda"></div>
                                </div>
                                <small id="producto_seleccionado_info" style="color: #28a745; font-weight: bold;"></small>
                            </div>

                            <div class="form-group">
                                <label for="info_stock">Stock Actual</label>
                                <input type="text" id="info_stock" readonly placeholder="Primero seleccione un producto" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="cantidad_grande_salida" id="label_grande">Cantidad (Paquetes)</label>
                                <input type="number" id="cantidad_grande_salida" name="cantidad_grande" min="0" value="0" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="cantidad_unidad_salida">Unidades Sueltas</label>
                                <input type="number" id="cantidad_unidad_salida" name="cantidad_unidad" min="0" value="0" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="documento_ref">Documento de Referencia</label>
                                <input type="text" id="documento_ref" name="documento_ref" placeholder="Factura, remisión, etc." class="form-control">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="destino">Destino *</label>
                                <input type="text" id="destino" name="destino" required placeholder="¿A dónde se envía?" class="form-control">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <div id="resumen_salida" class="alert" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-warning">🚚 Registrar Salida</button>
                            <button type="button" class="btn btn-secondary" onclick="limpiarSeleccionProducto()">🔄 Limpiar Búsqueda</button>
                        </div>
                    </form>
                </div>

                <!-- Formulario de Devolución con Búsqueda Mejorada -->
                <div class="form-container">
                    <h2>↩️ Registrar Devolución</h2>
                    <form action="CRUD/crud_historial_salida.php" method="POST" onsubmit="return validarDevolucion()">
                        <input type="hidden" name="action" value="devolucion">
                        <input type="hidden" name="id_historial" id="id_historial_seleccionado">

                        <div class="form-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="buscador_salidas">Buscar Salida *</label>
                                <div style="position: relative;">
                                    <input type="text"
                                        id="buscador_salidas"
                                        class="form-control"
                                        placeholder="Buscar por producto, destino o documento..."
                                        autocomplete="off"
                                        onkeyup="buscarSalidas(this.value)">
                                    <div id="resultados_salidas" class="resultados-busqueda"></div>
                                </div>
                                <small id="salida_seleccionada_info" style="color: #28a745; font-weight: bold;"></small>
                            </div>

                            <div class="form-group">
                                <label for="info_salida">Detalle de Salida</label>
                                <input type="text" id="info_salida" readonly placeholder="Seleccione un registro" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="cantidad_grande_devuelta" id="label_devuelta">Devolver (Paquetes)</label>
                                <input type="number" id="cantidad_grande_devuelta" name="cantidad_grande_devuelta" min="0" value="0" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="cantidad_unidad_devuelta">Unidades Sueltas a Devolver</label>
                                <input type="number" id="cantidad_unidad_devuelta" name="cantidad_unidad_devuelta" min="0" value="0" class="form-control">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label for="motivo_devolucion">Motivo de la Devolución *</label>
                                <select id="motivo_devolucion" name="motivo_devolucion" required class="form-control">
                                    <option value="">Seleccione un motivo</option>
                                    <option value="Producto dañado">Producto dañado</option>
                                    <option value="Error en envío">Error en envío</option>
                                    <option value="Cliente devolvió">Cliente devolvió</option>
                                    <option value="Producto equivocado">Producto equivocado</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <div id="resumen_devolucion" class="alert" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-info">↩️ Registrar Devolución</button>
                            <button type="button" class="btn btn-secondary" onclick="limpiarSeleccionSalida()">🔄 Limpiar Búsqueda</button>
                        </div>
                    </form>
                </div>

                <!-- Lista de Historial de Salidas -->
                <div class="table-container">
                    <h2>📋 Historial de Salidas y Devoluciones</h2>

                    <div class="search-box">
                        <input type="text" id="buscadorSalidas" placeholder="Buscar en historial..." onkeyup="filtrarTabla('tablaHistorial', this.value)">
                    </div>

                    <table id="tablaHistorial">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Destino/Motivo</th>
                                <th>Documento</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($historial_completo)): ?>
                                <?php foreach ($historial_completo as $reg):
                                    $total_unidades = ($reg['cantidad_grande'] * 1) + $reg['cantidad_unidad'];
                                    $tipo_class = ($reg['tipo'] == 'Devolución') ? 'badge' : 'badge';
                                    $tipo_style = ($reg['tipo'] == 'Devolución') ? 'background-color: #17a2b8; color: white;' : 'background-color: #ffc107; color: black;';
                                ?>
                                    <tr>
                                        <td><?php echo $reg['id_historial_salida']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($reg['fecha_salida'])); ?></td>
                                        <td><?php echo htmlspecialchars($reg['nombre_producto']); ?></td>
                                        <td>
                                            <?php echo $reg['cantidad_grande']; ?> <?php echo strtolower($reg['modulo']); ?>s
                                            + <?php echo $reg['cantidad_unidad']; ?> und
                                            <br><small>Total: <?php echo $total_unidades; ?> unidades</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($reg['destino']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['documento_ref'] ?? '-'); ?></td>
                                        <td><span style="<?php echo $tipo_style; ?> padding: 3px 8px; border-radius: 4px;"><?php echo $reg['tipo']; ?></span></td>
                                        <td class="action-buttons">
                                            <a href="CRUD/crud_historial_salida.php?action=eliminar&id=<?php echo $reg['id_historial_salida']; ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('¿Está seguro de eliminar este registro?')">🗑️ Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No hay registros en el historial</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <style>
                    .resultados-busqueda {
                        position: absolute;
                        background: white;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        max-height: 300px;
                        overflow-y: auto;
                        width: 100%;
                        z-index: 1000;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        display: none;
                    }

                    .resultado-item {
                        padding: 12px 15px;
                        cursor: pointer;
                        border-bottom: 1px solid #eee;
                        transition: background-color 0.2s;
                    }

                    .resultado-item:hover {
                        background-color: #f0f0f0;
                    }

                    .resultado-item .producto-nombre {
                        font-weight: bold;
                        color: #333;
                    }

                    .resultado-item .producto-detalle {
                        font-size: 0.85em;
                        color: #666;
                        margin-top: 3px;
                    }

                    .resultado-item .producto-stock {
                        font-size: 0.85em;
                        color: #28a745;
                        margin-top: 3px;
                    }

                    .resultado-item.seleccionado {
                        background-color: #e3f2fd;
                    }

                    .form-control {
                        width: 100%;
                        padding: 12px 15px;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 1em;
                        transition: border-color 0.3s;
                    }

                    .form-control:focus {
                        outline: none;
                        border-color: #667eea;
                    }
                </style>

                <script>
                    // Variable para almacenar todos los productos (cargados desde PHP)
                    const productosDisponibles = <?php echo json_encode($productos_con_stock); ?>;
                    const historialSalidas = <?php echo json_encode($historial_salidas); ?>;

                    // Función para buscar productos en tiempo real
                    function buscarProductos(termino) {
                        const resultadosDiv = document.getElementById('resultados_busqueda');
                        const productoSeleccionadoInfo = document.getElementById('producto_seleccionado_info');

                        if (termino.length < 2) {
                            resultadosDiv.style.display = 'none';
                            return;
                        }

                        termino = termino.toLowerCase();
                        const resultados = productosDisponibles.filter(producto => {
                            const nombreCompleto = (producto.nombre_producto + ' ' + (producto.marca || '') + ' ' + (producto.nombre_proveedor || '')).toLowerCase();
                            return nombreCompleto.includes(termino);
                        });

                        if (resultados.length > 0) {
                            let html = '';
                            resultados.slice(0, 10).forEach(producto => { // Mostrar solo primeros 10
                                const stockTotal = producto.stock_total;
                                const modulo = producto.modulo.toLowerCase();
                                html += `
                        <div class="resultado-item" onclick="seleccionarProducto(${producto.id_producto})">
                            <div class="producto-nombre">${producto.nombre_producto} ${producto.marca ? '- ' + producto.marca : ''}</div>
                            <div class="producto-detalle">
                                Proveedor: ${producto.nombre_proveedor || 'Sin proveedor'} | 
                                Módulo: ${producto.modulo} | 
                                Presentación: ${producto.presentacion || 'N/A'}
                            </div>
                            <div class="producto-stock">
                                Stock: ${producto.cantidad_grande} ${modulo}s + ${producto.unidades_sueltas} und = ${stockTotal} unidades
                            </div>
                        </div>
                    `;
                            });
                            resultadosDiv.innerHTML = html;
                            resultadosDiv.style.display = 'block';
                        } else {
                            resultadosDiv.innerHTML = '<div class="resultado-item">No se encontraron productos</div>';
                            resultadosDiv.style.display = 'block';
                        }
                    }

                    // Función para seleccionar un producto de la búsqueda
                    function seleccionarProducto(idProducto) {
                        const producto = productosDisponibles.find(p => p.id_producto == idProducto);
                        if (!producto) return;

                        // Guardar ID seleccionado
                        document.getElementById('id_producto_seleccionado').value = idProducto;

                        // Mostrar información del producto seleccionado
                        const infoDiv = document.getElementById('producto_seleccionado_info');
                        infoDiv.innerHTML = `✅ Producto seleccionado: ${producto.nombre_producto} ${producto.marca ? '- ' + producto.marca : ''}`;

                        // Actualizar campo de stock
                        const stockTotal = producto.stock_total;
                        const modulo = producto.modulo.toLowerCase();
                        document.getElementById('info_stock').value =
                            `Stock: ${producto.cantidad_grande} ${modulo}s + ${producto.unidades_sueltas} und = ${stockTotal} unidades`;

                        // Actualizar label de cantidad grande
                        document.getElementById('label_grande').textContent = `Cantidad (${modulo}s)`;

                        // Ocultar resultados
                        document.getElementById('resultados_busqueda').style.display = 'none';

                        // Limpiar campo de búsqueda
                        document.getElementById('buscador_productos').value = producto.nombre_producto + ' ' + (producto.marca || '');

                        // Guardar datos del producto para validaciones
                        document.getElementById('id_producto_seleccionado').dataset.modulo = producto.modulo;
                        document.getElementById('id_producto_seleccionado').dataset.unidad = producto.cantidad_unidad;
                        document.getElementById('id_producto_seleccionado').dataset.grandes = producto.cantidad_grande;
                        document.getElementById('id_producto_seleccionado').dataset.sueltas = producto.unidades_sueltas;
                        document.getElementById('id_producto_seleccionado').dataset.stock = stockTotal;

                        actualizarResumenSalida();
                    }

                    // Función para limpiar selección de producto
                    function limpiarSeleccionProducto() {
                        document.getElementById('id_producto_seleccionado').value = '';
                        document.getElementById('buscador_productos').value = '';
                        document.getElementById('producto_seleccionado_info').innerHTML = '';
                        document.getElementById('info_stock').value = '';
                        document.getElementById('cantidad_grande_salida').value = 0;
                        document.getElementById('cantidad_unidad_salida').value = 0;
                        document.getElementById('resumen_salida').style.display = 'none';
                    }

                    // Función para buscar salidas en tiempo real
                    function buscarSalidas(termino) {
                        const resultadosDiv = document.getElementById('resultados_salidas');

                        if (termino.length < 2) {
                            resultadosDiv.style.display = 'none';
                            return;
                        }

                        termino = termino.toLowerCase();
                        const resultados = historialSalidas.filter(salida => {
                            const textoBusqueda = (salida.nombre_producto + ' ' + salida.destino + ' ' + (salida.documento_ref || '')).toLowerCase();
                            return textoBusqueda.includes(termino);
                        });

                        if (resultados.length > 0) {
                            let html = '';
                            resultados.slice(0, 10).forEach(salida => {
                                const fecha = new Date(salida.fecha_salida).toLocaleDateString('es-CO');
                                html += `
                        <div class="resultado-item" onclick="seleccionarSalida(${salida.id_historial_salida})">
                            <div class="producto-nombre">${salida.nombre_producto}</div>
                            <div class="producto-detalle">
                                Fecha: ${fecha} | Destino: ${salida.destino} | Documento: ${salida.documento_ref || 'N/A'}
                            </div>
                            <div class="producto-stock">
                                Cantidad: ${salida.cantidad_grande} ${salida.modulo}s + ${salida.cantidad_unidad} und
                            </div>
                        </div>
                    `;
                            });
                            resultadosDiv.innerHTML = html;
                            resultadosDiv.style.display = 'block';
                        } else {
                            resultadosDiv.innerHTML = '<div class="resultado-item">No se encontraron salidas</div>';
                            resultadosDiv.style.display = 'block';
                        }
                    }

                    // Función para seleccionar una salida
                    function seleccionarSalida(idHistorial) {
                        const salida = historialSalidas.find(s => s.id_historial_salida == idHistorial);
                        if (!salida) return;

                        document.getElementById('id_historial_seleccionado').value = idHistorial;

                        const infoDiv = document.getElementById('salida_seleccionada_info');
                        infoDiv.innerHTML = `✅ Salida seleccionada: ${salida.nombre_producto} - ${salida.destino}`;

                        document.getElementById('info_salida').value =
                            `${salida.nombre_producto}: ${salida.cantidad_grande} ${salida.modulo}s + ${salida.cantidad_unidad} und - Destino: ${salida.destino}`;

                        document.getElementById('label_devuelta').textContent = `Devolver (${salida.modulo}s)`;

                        document.getElementById('resultados_salidas').style.display = 'none';
                        document.getElementById('buscador_salidas').value = salida.nombre_producto + ' - ' + salida.destino;

                        // Guardar datos para validaciones
                        document.getElementById('id_historial_seleccionado').dataset.modulo = salida.modulo;
                        document.getElementById('id_historial_seleccionado').dataset.unidadPorModulo = salida.unidades_por_modulo;

                        actualizarResumenDevolucion();
                    }

                    // Función para limpiar selección de salida
                    function limpiarSeleccionSalida() {
                        document.getElementById('id_historial_seleccionado').value = '';
                        document.getElementById('buscador_salidas').value = '';
                        document.getElementById('salida_seleccionada_info').innerHTML = '';
                        document.getElementById('info_salida').value = '';
                        document.getElementById('cantidad_grande_devuelta').value = 0;
                        document.getElementById('cantidad_unidad_devuelta').value = 0;
                        document.getElementById('resumen_devolucion').style.display = 'none';
                    }

                    // Actualizar resumen de salida
                    function actualizarResumenSalida() {
                        const idProducto = document.getElementById('id_producto_seleccionado').value;
                        if (!idProducto) return;

                        const producto = productosDisponibles.find(p => p.id_producto == idProducto);
                        if (!producto) return;

                        const grandes = parseInt(document.getElementById('cantidad_grande_salida').value) || 0;
                        const sueltas = parseInt(document.getElementById('cantidad_unidad_salida').value) || 0;
                        const modulo = producto.modulo.toLowerCase();
                        const stockTotal = producto.stock_total;
                        const unidadesPorModulo = producto.cantidad_unidad || 1;

                        const unidadesSalida = (grandes * unidadesPorModulo) + sueltas;

                        if (unidadesSalida > 0) {
                            const resumen = document.getElementById('resumen_salida');
                            resumen.style.display = 'block';

                            if (unidadesSalida > stockTotal) {
                                resumen.className = 'alert alert-error';
                                resumen.innerHTML = `⚠️ No hay suficiente stock. Disponible: ${stockTotal} unidades`;
                            } else {
                                resumen.className = 'alert alert-info';
                                resumen.innerHTML = `📊 Total a dar de baja: ${grandes} ${modulo}s + ${sueltas} und = ${unidadesSalida} unidades`;
                            }
                        } else {
                            document.getElementById('resumen_salida').style.display = 'none';
                        }
                    }

                    // Actualizar resumen de devolución
                    function actualizarResumenDevolucion() {
                        const idHistorial = document.getElementById('id_historial_seleccionado').value;
                        if (!idHistorial) return;

                        const salida = historialSalidas.find(s => s.id_historial_salida == idHistorial);
                        if (!salida) return;

                        const grandesDevueltas = parseInt(document.getElementById('cantidad_grande_devuelta').value) || 0;
                        const sueltasDevueltas = parseInt(document.getElementById('cantidad_unidad_devuelta').value) || 0;
                        const modulo = salida.modulo.toLowerCase();
                        const unidadesPorModulo = salida.unidades_por_modulo || 1;

                        const unidadesDevueltas = (grandesDevueltas * unidadesPorModulo) + sueltasDevueltas;

                        if (unidadesDevueltas > 0) {
                            const resumen = document.getElementById('resumen_devolucion');
                            resumen.style.display = 'block';
                            resumen.className = 'alert alert-info';
                            resumen.innerHTML = `📦 Se reingresarán: ${grandesDevueltas} ${modulo}s + ${sueltasDevueltas} und = ${unidadesDevueltas} unidades`;
                        } else {
                            document.getElementById('resumen_devolucion').style.display = 'none';
                        }
                    }

                    // Event listeners para actualizar resúmenes
                    document.getElementById('cantidad_grande_salida')?.addEventListener('input', actualizarResumenSalida);
                    document.getElementById('cantidad_unidad_salida')?.addEventListener('input', actualizarResumenSalida);
                    document.getElementById('cantidad_grande_devuelta')?.addEventListener('input', actualizarResumenDevolucion);
                    document.getElementById('cantidad_unidad_devuelta')?.addEventListener('input', actualizarResumenDevolucion);

                    // Cerrar resultados al hacer clic fuera
                    document.addEventListener('click', function(event) {
                        const resultadosBusqueda = document.getElementById('resultados_busqueda');
                        const buscador = document.getElementById('buscador_productos');
                        if (resultadosBusqueda && !buscador.contains(event.target) && !resultadosBusqueda.contains(event.target)) {
                            resultadosBusqueda.style.display = 'none';
                        }

                        const resultadosSalidas = document.getElementById('resultados_salidas');
                        const buscadorSalidas = document.getElementById('buscador_salidas');
                        if (resultadosSalidas && !buscadorSalidas.contains(event.target) && !resultadosSalidas.contains(event.target)) {
                            resultadosSalidas.style.display = 'none';
                        }
                    });

                    // Funciones de validación
                    function validarSalida() {
                        const idProducto = document.getElementById('id_producto_seleccionado').value;
                        if (!idProducto) {
                            alert('Debe seleccionar un producto de la búsqueda');
                            return false;
                        }

                        const destino = document.getElementById('destino').value.trim();
                        if (!destino) {
                            alert('Debe ingresar un destino');
                            return false;
                        }

                        const grandes = parseInt(document.getElementById('cantidad_grande_salida').value) || 0;
                        const sueltas = parseInt(document.getElementById('cantidad_unidad_salida').value) || 0;

                        if (grandes === 0 && sueltas === 0) {
                            alert('Debe ingresar al menos una cantidad');
                            return false;
                        }

                        const producto = productosDisponibles.find(p => p.id_producto == idProducto);
                        if (!producto) return false;

                        const unidadesPorModulo = producto.cantidad_unidad || 1;
                        const unidadesSalida = (grandes * unidadesPorModulo) + sueltas;

                        if (unidadesSalida > producto.stock_total) {
                            alert(`No hay suficiente stock. Disponible: ${producto.stock_total} unidades`);
                            return false;
                        }

                        return confirm(`¿Confirmar la salida de ${unidadesSalida} unidades?`);
                    }

                    function validarDevolucion() {
                        const idHistorial = document.getElementById('id_historial_seleccionado').value;
                        if (!idHistorial) {
                            alert('Debe seleccionar un registro de salida');
                            return false;
                        }

                        const motivo = document.getElementById('motivo_devolucion').value;
                        if (!motivo) {
                            alert('Debe seleccionar un motivo de devolución');
                            return false;
                        }

                        const grandes = parseInt(document.getElementById('cantidad_grande_devuelta').value) || 0;
                        const sueltas = parseInt(document.getElementById('cantidad_unidad_devuelta').value) || 0;

                        if (grandes === 0 && sueltas === 0) {
                            alert('Debe ingresar al menos una cantidad a devolver');
                            return false;
                        }

                        const salida = historialSalidas.find(s => s.id_historial_salida == idHistorial);
                        if (!salida) return false;

                        const unidadesPorModulo = salida.unidades_por_modulo || 1;
                        const unidadesDevueltas = (grandes * unidadesPorModulo) + sueltas;

                        return confirm(`¿Confirmar la devolución de ${unidadesDevueltas} unidades?`);
                    }

                    // Función para filtrar tablas
                    function filtrarTabla(tablaId, texto) {
                        let input = texto.toUpperCase();
                        let tabla = document.getElementById(tablaId);
                        if (!tabla) return;

                        let filas = tabla.getElementsByTagName('tr');

                        for (let i = 1; i < filas.length; i++) {
                            let celdas = filas[i].getElementsByTagName('td');
                            let mostrar = false;

                            for (let j = 0; j < celdas.length - 1; j++) {
                                if (celdas[j]) {
                                    let textoCelda = celdas[j].textContent || celdas[j].innerText;
                                    if (textoCelda.toUpperCase().indexOf(input) > -1) {
                                        mostrar = true;
                                        break;
                                    }
                                }
                            }

                            filas[i].style.display = mostrar ? '' : 'none';
                        }
                    }
                </script>
            <?php elseif ($tab == 'ventas'): ?>
                <!-- ========== SECCIÓN DE VENTAS CORREGIDA ========== -->

                <!-- Resumen del Día -->
                <div class="form-container" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <div>
                            <h2 style="color: white; border-bottom-color: white;">📊 Resumen del Día</h2>
                            <p style="font-size: 1.2em;"><?php echo date('d/m/Y'); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 2.5em; font-weight: bold;">$<?php echo number_format($total_hoy, 0); ?></div>
                            <div><?php echo count($ventas_hoy); ?> ventas realizadas</div>
                            <div class="btn-group" style="margin-top: 10px;">
                                <button class="btn btn-primary" onclick="previsualizarReporte()">👁️ Previsualizar</button>
                                <button class="btn btn-success" onclick="generarPDF()">📄 Generar PDF</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Punto de Venta (POS) Mejorado -->
                <div class="form-container">
                    <h2>💰 Registrar Venta</h2>

                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px;">
                        <!-- Panel izquierdo - Búsqueda y productos -->
                        <div>
                            <div style="margin-bottom: 20px;">
                                <label for="buscador_productos_venta">Buscar Producto</label>
                                <div style="position: relative;">
                                    <input type="text"
                                        id="buscador_productos_venta"
                                        class="form-control"
                                        placeholder="Escriba para buscar productos..."
                                        autocomplete="off"
                                        onkeyup="buscarProductosVenta(this.value)">
                                    <div id="resultados_venta" class="resultados-busqueda"></div>
                                </div>
                            </div>

                            <!-- Carrito de Compras Mejorado -->
                            <div id="carrito" class="carrito-container">
                                <h3>🛒 Carrito de Compras</h3>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <table class="carrito-tabla" id="tablaCarrito">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Presentación</th>
                                                <th>Cantidad</th>
                                                <th>Precio</th>
                                                <th>Subtotal</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="carrito-body">
                                            <tr>
                                                <td colspan="6" style="text-align: center;">El carrito está vacío</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
                                                <td colspan="2"><strong id="total-carrito">$0</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Panel derecho - Pago y métodos CORREGIDO -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                            <h3>💳 Procesar Pago</h3>

                            <form action="CRUD/crud_ventas.php" method="POST" id="formVenta" onsubmit="return procesarVenta()">
                                <input type="hidden" name="action" value="registrar_venta">
                                <input type="hidden" name="items" id="items-json">
                                <input type="hidden" name="total_venta" id="total-venta-hidden">

                                <!-- IMPORTANTE: Estos campos ocultos enviarán los montos al servidor -->
                                <input type="hidden" name="monto_efectivo" id="monto_efectivo_hidden" value="0">
                                <input type="hidden" name="monto_nequi" id="monto_nequi_hidden" value="0">

                                <!-- Selector de Método de Pago -->
                                <div class="form-group">
                                    <label>Método de Pago *</label>
                                    <select name="metodo_pago" id="metodo_pago_select" class="form-control" onchange="cambiarMetodoPago()" required>
                                        <option value="">Seleccione método de pago</option>
                                        <option value="efectivo">💵 Efectivo</option>
                                        <option value="nequi">📱 Nequi</option>
                                        <option value="mixto">🔄 Mixto (Efectivo + Nequi)</option>
                                    </select>
                                </div>

                                <!-- Panel Efectivo -->
                                <div id="panel-efectivo" style="display: none;">
                                    <div class="form-group">
                                        <label>Monto en Efectivo</label>
                                        <input type="number" id="monto_efectivo" class="form-control" min="0" step="100" value="0" oninput="actualizarMontos()">
                                    </div>
                                </div>

                                <!-- Panel Nequi -->
                                <div id="panel-nequi" style="display: none;">
                                    <div class="form-group">
                                        <label>Monto en Nequi</label>
                                        <input type="number" id="monto_nequi" class="form-control" min="0" step="100" value="0" oninput="actualizarMontos()">
                                    </div>
                                </div>

                                <!-- Panel Mixto -->
                                <div id="panel-mixto" style="display: none;">
                                    <div class="form-group">
                                        <label>Efectivo</label>
                                        <input type="number" id="monto_efectivo_mixto" class="form-control" min="0" step="100" value="0" oninput="actualizarMontosMixto()">
                                    </div>
                                    <div class="form-group">
                                        <label>Nequi</label>
                                        <input type="number" id="monto_nequi_mixto" class="form-control" min="0" step="100" value="0" oninput="actualizarMontosMixto()">
                                    </div>
                                </div>

                                <!-- Información de Cambio -->
                                <div id="info-cambio" style="margin: 15px 0; padding: 15px; border-radius: 5px; display: none; background: #e8f4fd; border-left: 4px solid #17a2b8;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong>Total a pagar:</strong>
                                        <span id="total-pagar">$0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                        <strong>Recibido:</strong>
                                        <span id="total-recibido">$0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 1.2em;">
                                        <strong>Cambio:</strong>
                                        <strong id="cambio-valor">$0</strong>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Observaciones</label>
                                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas adicionales..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 10px; font-size: 1.2em; padding: 15px;" id="btn-pagar" disabled>
                                    💰 Pagar $<span id="btn-total">0</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal para Detalle de Venta y Devoluciones -->
                <div id="modalDetalle" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" onclick="cerrarModal()">&times;</span>
                        <h2 id="modal-titulo">Detalle de Venta</h2>
                        <div id="modal-contenido"></div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button class="btn btn-info" onclick="procesarCambio()">🔄 Procesar Cambio</button>
                            <button class="btn btn-warning" onclick="procesarDevolucion()">↩️ Procesar Devolución</button>
                            <button class="btn btn-secondary" onclick="cerrarModal()">Cerrar</button>
                        </div>
                    </div>
                </div>

                <!-- Ventas del Día -->
                <div class="table-container">
                    <h2>📋 Ventas del Día</h2>
                    <table id="tablaVentasHoy">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Producto</th>
                                <th>Presentación</th>
                                <th>Cantidad</th>
                                <th>Método Pago</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ventas_hoy)): ?>
                                <?php foreach ($ventas_hoy as $venta):
                                    $total_unidades = ($venta['cantidad_grande'] * 1) + $venta['cantidad_unidad'];

                                ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($venta['fecha_hora'])); ?></td>
                                        <td><?php echo htmlspecialchars($venta['nombre_producto']); ?></td>
                                        <td><?php echo $venta['modulo']; ?></td>
                                        <td>
                                            <?php if ($venta['cantidad_grande'] > 0): ?>
                                                <?php echo $venta['cantidad_grande']; ?> <?php echo strtolower($venta['modulo']); ?>
                                                <?php if ($venta['cantidad_unidad'] > 0): ?> + <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($venta['cantidad_unidad'] > 0): ?>
                                                <?php echo $venta['cantidad_unidad']; ?> und
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($venta['metodo_pago'] == 'efectivo') echo '💵 Efectivo';
                                            elseif ($venta['metodo_pago'] == 'nequi') echo '📱 Nequi';
                                            elseif ($venta['metodo_pago'] == 'mixto') echo '🔄 Mixto';
                                            else echo $venta['metodo_pago'];
                                            ?>
                                        </td>

                                        <td>$<?php echo number_format($venta['precio'], 0); ?></td>
                                        <td class="action-buttons">
                                            <button class="action-btn view" onclick="verDetalleVenta(<?php echo $venta['id_historial_venta']; ?>, '<?php echo htmlspecialchars($venta['nombre_producto']); ?>', <?php echo $venta['cantidad_grande']; ?>, <?php echo $venta['cantidad_unidad']; ?>, '<?php echo $venta['modulo']; ?>', <?php echo $venta['precio']; ?>, '<?php echo $venta['metodo_pago']; ?>')">👁️ Ver</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No hay ventas registradas hoy</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Historial de Ventas -->
                <div class="table-container">
                    <h2>📋 Historial de Ventas</h2>
                    <div class="search-box">
                        <input type="text" id="buscadorVentas" placeholder="Buscar en historial..." onkeyup="filtrarTabla('tablaHistorialVentas', this.value)">
                    </div>
                    <table id="tablaHistorialVentas">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Método Pago</th>
                                <th>Total</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($historial_ventas)): ?>
                                <?php foreach ($historial_ventas as $reg):
                                    $tipo_style = ($reg['tipo'] == 'Devolución') ? 'background-color: #dc3545; color: white;' : 'background-color: #28a745; color: white;';
                                ?>
                                    <tr>
                                        <td><?php echo $reg['id_historial_venta']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($reg['fecha_hora'])); ?></td>
                                        <td><?php echo htmlspecialchars($reg['nombre_producto']); ?></td>
                                        <td>
                                            <?php if ($reg['cantidad_grande'] > 0): ?>
                                                <?php echo $reg['cantidad_grande']; ?> <?php echo strtolower($reg['modulo']); ?>
                                                <?php if ($reg['cantidad_unidad'] > 0): ?> + <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($reg['cantidad_unidad'] > 0): ?>
                                                <?php echo $reg['cantidad_unidad']; ?> und
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $reg['metodo_pago']; ?></td>
                                        <td>$<?php echo number_format($reg['precio'], 0); ?></td>
                                        <td><span style="<?php echo $tipo_style; ?> padding: 3px 8px; border-radius: 4px;"><?php echo $reg['tipo']; ?></span></td>
                                        <td class="action-buttons">
                                            <a href="CRUD/crud_ventas.php?action=eliminar&id=<?php echo $reg['id_historial_venta']; ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('¿Está seguro de eliminar este registro?')">🗑️ Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No hay registros en el historial</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <style>
                    .carrito-container {
                        background: white;
                        border-radius: 10px;
                        padding: 15px;
                        margin-top: 20px;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    }

                    .carrito-tabla {
                        width: 100%;
                        font-size: 0.9em;
                    }

                    .carrito-tabla th {
                        background: #667eea;
                        color: white;
                        padding: 8px;
                        position: sticky;
                        top: 0;
                    }

                    .carrito-tabla td {
                        padding: 8px;
                        border-bottom: 1px solid #eee;
                    }

                    .btn-eliminar-item {
                        background: #dc3545;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        padding: 4px 8px;
                        cursor: pointer;
                        font-size: 0.8em;
                    }

                    .btn-eliminar-item:hover {
                        opacity: 0.8;
                    }

                    .cantidad-input,
                    .presentacion-select {
                        width: 80px;
                        padding: 4px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        text-align: center;
                        margin: 2px;
                    }

                    .presentacion-select {
                        width: 100px;
                    }

                    /* Modal Styles */
                    .modal {
                        display: none;
                        position: fixed;
                        z-index: 1000;
                        left: 0;
                        top: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0, 0, 0, 0.5);
                    }

                    .modal-content {
                        background-color: white;
                        margin: 10% auto;
                        padding: 30px;
                        border-radius: 10px;
                        width: 50%;
                        max-width: 500px;
                        position: relative;
                    }

                    .close {
                        position: absolute;
                        right: 20px;
                        top: 10px;
                        font-size: 28px;
                        font-weight: bold;
                        cursor: pointer;
                    }

                    .close:hover {
                        color: #666;
                    }

                    .detalle-item {
                        padding: 10px;
                        margin: 5px 0;
                        background: #f8f9fa;
                        border-radius: 5px;
                    }

                    .badge-success {
                        background: #28a745;
                        color: white;
                        padding: 3px 8px;
                        border-radius: 4px;
                    }

                    .badge-warning {
                        background: #ffc107;
                        color: black;
                        padding: 3px 8px;
                        border-radius: 4px;
                    }

                    .resultados-busqueda {
                        position: absolute;
                        background: white;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        max-height: 300px;
                        overflow-y: auto;
                        width: 100%;
                        z-index: 1000;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        display: none;
                    }

                    .resultado-item {
                        padding: 12px 15px;
                        cursor: pointer;
                        border-bottom: 1px solid #eee;
                        transition: background-color 0.2s;
                    }

                    .resultado-item:hover {
                        background-color: #f0f0f0;
                    }

                    .resultado-item .producto-nombre {
                        font-weight: bold;
                        color: #333;
                    }

                    .resultado-item .producto-detalle {
                        font-size: 0.85em;
                        color: #666;
                        margin-top: 3px;
                    }

                    .resultado-item .producto-stock {
                        font-size: 0.85em;
                        color: #28a745;
                        margin-top: 3px;
                    }

                    .form-control {
                        width: 100%;
                        padding: 12px 15px;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 1em;
                        transition: border-color 0.3s;
                    }

                    .form-control:focus {
                        outline: none;
                        border-color: #667eea;
                    }
                </style>

                <script>
                    // ========== VARIABLES GLOBALES ==========
                    let carrito = [];
                    let ventaSeleccionada = null;
                    const productosVenta = <?php echo json_encode($productos_venta); ?>;

                    // ========== FUNCIONES DE BÚSQUEDA ==========
                    function buscarProductosVenta(termino) {
                        const resultadosDiv = document.getElementById('resultados_venta');

                        if (termino.length < 2) {
                            resultadosDiv.style.display = 'none';
                            return;
                        }

                        termino = termino.toLowerCase();
                        const resultados = productosVenta.filter(producto => {
                            const nombreCompleto = (producto.nombre_producto + ' ' + (producto.marca || '') + ' ' + (producto.nombre_proveedor || '')).toLowerCase();
                            return nombreCompleto.includes(termino);
                        });

                        if (resultados.length > 0) {
                            let html = '';
                            resultados.slice(0, 8).forEach(producto => {
                                const precioUnidad = producto.precio_venta_unidad;
                                const precioGrande = producto.precio_venta_cantidad_grande;
                                const stockTotal = producto.stock_total;
                                const unidadesPorModulo = producto.cantidad_unidad;

                                html += `
                        <div class="resultado-item" onclick="mostrarOpcionesProducto(${producto.id_producto})">
                            <div class="producto-nombre">${producto.nombre_producto} ${producto.marca ? '- ' + producto.marca : ''}</div>
                            <div class="producto-detalle">
                                💵 Unidad: $${precioUnidad} | 📦 ${producto.modulo} (${unidadesPorModulo} und): $${precioGrande}
                            </div>
                            <div class="producto-stock">
                                Stock: ${producto.cantidad_grande} ${producto.modulo}s + ${producto.unidades_sueltas} und = ${stockTotal} und
                            </div>
                        </div>
                    `;
                            });
                            resultadosDiv.innerHTML = html;
                            resultadosDiv.style.display = 'block';
                        } else {
                            resultadosDiv.innerHTML = '<div class="resultado-item">No se encontraron productos</div>';
                            resultadosDiv.style.display = 'block';
                        }
                    }

                    // ========== FUNCIONES DEL CARRITO ==========
                    function mostrarOpcionesProducto(idProducto) {
                        const producto = productosVenta.find(p => p.id_producto == idProducto);
                        if (!producto) return;

                        const presentacion = prompt(
                            `¿Cómo desea vender ${producto.nombre_producto}?\n\n` +
                            `1. Por Unidad ($${producto.precio_venta_unidad} c/u)\n` +
                            `2. Por ${producto.modulo} ($${producto.precio_venta_cantidad_grande} por ${producto.cantidad_unidad} und)\n\n` +
                            `Stock disponible: ${producto.cantidad_grande} ${producto.modulo}s + ${producto.unidades_sueltas} und`,
                            "1"
                        );

                        if (presentacion === "1") {
                            const cantidad = parseInt(prompt("¿Cuántas unidades desea vender?", "1"));
                            if (cantidad && cantidad > 0) {
                                if (cantidad > producto.stock_total) {
                                    alert(`Solo hay ${producto.stock_total} unidades disponibles`);
                                    return;
                                }
                                agregarAlCarrito(producto, 'unidad', cantidad, 0);
                            }
                        } else if (presentacion === "2") {
                            const cantidad = parseInt(prompt(`¿Cuántos ${producto.modulo}s desea vender?`, "1"));
                            if (cantidad && cantidad > 0) {
                                if (cantidad > producto.cantidad_grande) {
                                    alert(`Solo hay ${producto.cantidad_grande} ${producto.modulo}s disponibles`);
                                    return;
                                }
                                agregarAlCarrito(producto, 'grande', 0, cantidad);
                            }
                        }

                        document.getElementById('buscador_productos_venta').value = '';
                        document.getElementById('resultados_venta').style.display = 'none';
                    }

                    function agregarAlCarrito(producto, tipo, cantidadUnidad, cantidadGrande) {
                        const existente = carrito.find(item =>
                            item.id === producto.id_producto &&
                            item.tipo === tipo
                        );

                        if (existente) {
                            if (tipo === 'unidad') {
                                if (existente.cantidad_unidad + cantidadUnidad > producto.stock_total) {
                                    alert('No hay suficiente stock');
                                    return;
                                }
                                existente.cantidad_unidad += cantidadUnidad;
                            } else {
                                if (existente.cantidad_grande + cantidadGrande > producto.cantidad_grande) {
                                    alert('No hay suficiente stock');
                                    return;
                                }
                                existente.cantidad_grande += cantidadGrande;
                            }
                            existente.subtotal = (existente.cantidad_grande * existente.precio_grande) +
                                (existente.cantidad_unidad * existente.precio_unitario);
                        } else {
                            carrito.push({
                                id: producto.id_producto,
                                nombre: producto.nombre_producto,
                                modulo: producto.modulo,
                                tipo: tipo,
                                cantidad_grande: tipo === 'grande' ? cantidadGrande : 0,
                                cantidad_unidad: tipo === 'unidad' ? cantidadUnidad : 0,
                                precio_unitario: producto.precio_venta_unidad,
                                precio_grande: producto.precio_venta_cantidad_grande,
                                unidades_por_modulo: producto.cantidad_unidad,
                                subtotal: (tipo === 'grande' ? cantidadGrande * producto.precio_venta_cantidad_grande : 0) +
                                    (tipo === 'unidad' ? cantidadUnidad * producto.precio_venta_unidad : 0)
                            });
                        }

                        actualizarCarrito();
                    }

                    function actualizarCarrito() {
                        const tbody = document.getElementById('carrito-body');
                        let total = 0;

                        if (carrito.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">El carrito está vacío</td></tr>';
                            document.getElementById('btn-pagar').disabled = true;
                        } else {
                            let html = '';
                            carrito.forEach((item, index) => {
                                total += item.subtotal;
                                html += `
                <tr>
                    <td>${item.nombre}</td>
                    <td>
                        ${item.tipo === 'unidad' ? 'Unidad' : item.modulo}
                        ${item.tipo === 'grande' ? `<br><small>${item.unidades_por_modulo} und c/u</small>` : ''}
                    </td>
                    <td>
                        ${item.tipo === 'unidad' ? 
                            `${item.cantidad_unidad} und` : 
                            `${item.cantidad_grande} ${item.modulo}s`
                        }
                    </td>
                    <td>
                        ${item.tipo === 'unidad' ? 
                            `$${item.precio_unitario}` : 
                            `$${item.precio_grande}`
                        }
                    </td>
                    <td>$${item.subtotal.toLocaleString()}</td>
                    <td>
                        <button class="btn-eliminar-item" onclick="eliminarDelCarrito(${index})">✕</button>
                    </td>
                </tr>
            `;
                            });
                            tbody.innerHTML = html;
                            document.getElementById('btn-pagar').disabled = false;
                        }

                        document.getElementById('total-carrito').textContent = '$' + total.toLocaleString();
                        document.getElementById('btn-total').textContent = total.toLocaleString();
                        document.getElementById('total-venta-hidden').value = total;
                        document.getElementById('items-json').value = JSON.stringify(carrito);
                        document.getElementById('total-pagar').textContent = '$' + total.toLocaleString();

                        // Recalcular cambio
                        const metodo = document.getElementById('metodo_pago_select').value;
                        if (metodo === 'efectivo' || metodo === 'nequi') {
                            calcularCambio();
                        } else if (metodo === 'mixto') {
                            calcularCambioMixto();
                        }
                    }

                    function eliminarDelCarrito(index) {
                        carrito.splice(index, 1);
                        actualizarCarrito();
                    }

                    // ========== FUNCIONES DE PAGO CORREGIDAS ==========
                    function cambiarMetodoPago() {
                        const metodo = document.getElementById('metodo_pago_select').value;

                        document.getElementById('panel-efectivo').style.display = 'none';
                        document.getElementById('panel-nequi').style.display = 'none';
                        document.getElementById('panel-mixto').style.display = 'none';

                        // Resetear campos ocultos
                        document.getElementById('monto_efectivo_hidden').value = 0;
                        document.getElementById('monto_nequi_hidden').value = 0;

                        if (metodo === 'efectivo') {
                            document.getElementById('panel-efectivo').style.display = 'block';
                            document.getElementById('monto_efectivo').value = 0;
                        } else if (metodo === 'nequi') {
                            document.getElementById('panel-nequi').style.display = 'block';
                            document.getElementById('monto_nequi').value = 0;
                        } else if (metodo === 'mixto') {
                            document.getElementById('panel-mixto').style.display = 'block';
                            document.getElementById('monto_efectivo_mixto').value = 0;
                            document.getElementById('monto_nequi_mixto').value = 0;
                        }

                        calcularCambio();
                    }

                    function actualizarMontos() {
                        const montoEfectivo = parseFloat(document.getElementById('monto_efectivo').value) || 0;
                        const montoNequi = parseFloat(document.getElementById('monto_nequi').value) || 0;

                        // Actualizar campos ocultos
                        document.getElementById('monto_efectivo_hidden').value = montoEfectivo;
                        document.getElementById('monto_nequi_hidden').value = montoNequi;

                        calcularCambio();
                    }

                    function actualizarMontosMixto() {
                        const montoEfectivo = parseFloat(document.getElementById('monto_efectivo_mixto').value) || 0;
                        const montoNequi = parseFloat(document.getElementById('monto_nequi_mixto').value) || 0;

                        // Actualizar campos ocultos
                        document.getElementById('monto_efectivo_hidden').value = montoEfectivo;
                        document.getElementById('monto_nequi_hidden').value = montoNequi;

                        // Actualizar también los inputs simples para que calcularCambio() funcione
                        document.getElementById('monto_efectivo').value = montoEfectivo;
                        document.getElementById('monto_nequi').value = montoNequi;

                        calcularCambioMixto();
                    }

                    function calcularCambio() {
                        const metodo = document.getElementById('metodo_pago_select').value;
                        const total = parseFloat(document.getElementById('total-venta-hidden').value) || 0;

                        if (!metodo || total === 0) {
                            document.getElementById('info-cambio').style.display = 'none';
                            return;
                        }

                        let recibido = 0;

                        if (metodo === 'efectivo') {
                            recibido = parseFloat(document.getElementById('monto_efectivo').value) || 0;
                        } else if (metodo === 'nequi') {
                            recibido = parseFloat(document.getElementById('monto_nequi').value) || 0;
                        }

                        const cambio = recibido - total;

                        document.getElementById('total-recibido').textContent = '$' + recibido.toLocaleString();

                        if (recibido > 0 && total > 0) {
                            document.getElementById('info-cambio').style.display = 'block';

                            if (recibido >= total) {
                                document.getElementById('cambio-valor').textContent = '$' + cambio.toLocaleString();
                                document.getElementById('cambio-valor').style.color = '#28a745';
                            } else {
                                document.getElementById('cambio-valor').textContent = 'Faltan $' + Math.abs(cambio).toLocaleString();
                                document.getElementById('cambio-valor').style.color = '#dc3545';
                            }
                        } else {
                            document.getElementById('info-cambio').style.display = 'none';
                        }
                    }

                    function calcularCambioMixto() {
                        const efectivo = parseFloat(document.getElementById('monto_efectivo_mixto').value) || 0;
                        const nequi = parseFloat(document.getElementById('monto_nequi_mixto').value) || 0;
                        const total = parseFloat(document.getElementById('total-venta-hidden').value) || 0;

                        const totalPagado = efectivo + nequi;
                        const cambio = totalPagado - total;

                        document.getElementById('total-recibido').textContent = '$' + totalPagado.toLocaleString();

                        if (totalPagado > 0 && total > 0) {
                            document.getElementById('info-cambio').style.display = 'block';

                            if (totalPagado >= total) {
                                document.getElementById('cambio-valor').textContent = '$' + cambio.toLocaleString();
                                document.getElementById('cambio-valor').style.color = '#28a745';
                            } else {
                                document.getElementById('cambio-valor').textContent = 'Faltan $' + Math.abs(cambio).toLocaleString();
                                document.getElementById('cambio-valor').style.color = '#dc3545';
                            }
                        } else {
                            document.getElementById('info-cambio').style.display = 'none';
                        }
                    }

                    function procesarVenta() {
                        if (carrito.length === 0) {
                            alert('Agregue productos al carrito');
                            return false;
                        }

                        const metodo = document.getElementById('metodo_pago_select').value;
                        if (!metodo) {
                            alert('Seleccione un método de pago');
                            return false;
                        }

                        const total = parseFloat(document.getElementById('total-venta-hidden').value) || 0;
                        let montoEfectivo = parseFloat(document.getElementById('monto_efectivo_hidden').value) || 0;
                        let montoNequi = parseFloat(document.getElementById('monto_nequi_hidden').value) || 0;

                        if (metodo === 'efectivo') {
                            if (montoEfectivo < total) {
                                alert(`El monto en efectivo es insuficiente. Faltan $${(total - montoEfectivo).toLocaleString()}`);
                                return false;
                            }
                        } else if (metodo === 'nequi') {
                            if (montoNequi < total) {
                                alert(`El monto en Nequi es insuficiente. Faltan $${(total - montoNequi).toLocaleString()}`);
                                return false;
                            }
                        } else if (metodo === 'mixto') {
                            const totalPagado = montoEfectivo + montoNequi;
                            if (totalPagado < total) {
                                alert(`El monto total es insuficiente. Faltan $${(total - totalPagado).toLocaleString()}`);
                                return false;
                            }
                        }

                        // Mostrar resumen con desglose
                        let resumen = "🔍 CONFIRMAR VENTA\n\n";
                        resumen += "Productos:\n";
                        carrito.forEach(item => {
                            if (item.tipo === 'unidad') {
                                resumen += `• ${item.nombre}: ${item.cantidad_unidad} und = $${item.subtotal.toLocaleString()}\n`;
                            } else {
                                resumen += `• ${item.nombre}: ${item.cantidad_grande} ${item.modulo}s = $${item.subtotal.toLocaleString()}\n`;
                            }
                        });

                        resumen += `\n💰 TOTAL: $${total.toLocaleString()}`;
                        resumen += `\n💳 Método de pago: ${metodo.toUpperCase()}`;

                        if (metodo === 'efectivo') {
                            resumen += `\n💵 Efectivo: $${montoEfectivo.toLocaleString()}`;
                            if (montoEfectivo > total) {
                                resumen += `\n🔄 Cambio: $${(montoEfectivo - total).toLocaleString()}`;
                            }
                        } else if (metodo === 'nequi') {
                            resumen += `\n📱 Nequi: $${montoNequi.toLocaleString()}`;
                        } else if (metodo === 'mixto') {
                            resumen += `\n💵 Efectivo: $${montoEfectivo.toLocaleString()}`;
                            resumen += `\n📱 Nequi: $${montoNequi.toLocaleString()}`;
                            resumen += `\n🔄 Total pagado: $${(montoEfectivo + montoNequi).toLocaleString()}`;
                            if (montoEfectivo + montoNequi > total) {
                                resumen += `\n🔄 Cambio: $${((montoEfectivo + montoNequi) - total).toLocaleString()}`;
                            }
                        }

                        return confirm(resumen);
                    }

                    // ========== FUNCIONES DEL MODAL ==========
                    function verDetalleVenta(id, producto, grandes, unidades, modulo, total, metodo) {
                        ventaSeleccionada = {
                            id: id,
                            producto: producto,
                            grandes: grandes,
                            unidades: unidades,
                            modulo: modulo,
                            total: total,
                            metodo: metodo
                        };

                        const modal = document.getElementById('modalDetalle');
                        const contenido = document.getElementById('modal-contenido');

                        contenido.innerHTML = `
        <div class="detalle-item">
            <strong>Producto:</strong> ${producto}
        </div>
        <div class="detalle-item">
            <strong>Cantidad:</strong> 
            ${grandes > 0 ? grandes + ' ' + modulo + (grandes > 1 ? 's' : '') : ''}
            ${grandes > 0 && unidades > 0 ? ' + ' : ''}
            ${unidades > 0 ? unidades + ' unidad(es)' : ''}
        </div>
        <div class="detalle-item">
            <strong>Total:</strong> $${total.toLocaleString()}
        </div>
        <div class="detalle-item">
            <strong>Método de pago:</strong> ${metodo}
        </div>
    `;

                        modal.style.display = 'block';
                    }

                    function cerrarModal() {
                        document.getElementById('modalDetalle').style.display = 'none';
                        ventaSeleccionada = null;
                    }

                    function procesarDevolucion() {
                        if (!ventaSeleccionada) {
                            alert('No hay venta seleccionada');
                            return;
                        }

                        const motivo = prompt("Motivo de la devolución:", "Producto defectuoso");
                        if (!motivo) return;

                        const unidades = ventaSeleccionada.grandes * 1 + ventaSeleccionada.unidades;
                        const mensaje =
                            "¿Confirmar devolución?\n\n" +
                            `Producto: ${ventaSeleccionada.producto}\n` +
                            `Cantidad: ${ventaSeleccionada.grandes > 0 ? ventaSeleccionada.grandes + ' ' + ventaSeleccionada.modulo + 's' : ''} ${ventaSeleccionada.grandes > 0 && ventaSeleccionada.unidades > 0 ? '+' : ''} ${ventaSeleccionada.unidades > 0 ? ventaSeleccionada.unidades + ' und' : ''}\n` +
                            `Total: $${ventaSeleccionada.total.toLocaleString()}\n` +
                            `Motivo: ${motivo}\n\n` +
                            `Los productos volverán al inventario.`;

                        if (confirm(mensaje)) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = 'CRUD/crud_ventas.php';

                            form.innerHTML = `
            <input type="hidden" name="action" value="devolucion">
            <input type="hidden" name="id_venta" value="${ventaSeleccionada.id}">
            <input type="hidden" name="motivo_devolucion" value="${motivo}">
        `;

                            document.body.appendChild(form);
                            form.submit();
                        }
                    }

                    function procesarCambio() {
                        if (!ventaSeleccionada) {
                            alert('No hay venta seleccionada');
                            return;
                        }

                        alert('Función de cambio en desarrollo. Por ahora, procese como devolución y nueva venta.');
                    }

                    function generarReporteDiario() {
                        window.open('generar_reporte_diario.php', '_blank');
                    }

                    // ========== FUNCIÓN PARA FILTRAR TABLAS ==========
                    function filtrarTabla(tablaId, texto) {
                        let input = texto.toUpperCase();
                        let tabla = document.getElementById(tablaId);
                        if (!tabla) return;

                        let filas = tabla.getElementsByTagName('tr');

                        for (let i = 1; i < filas.length; i++) {
                            let celdas = filas[i].getElementsByTagName('td');
                            let mostrar = false;

                            for (let j = 0; j < celdas.length - 1; j++) {
                                if (celdas[j]) {
                                    let textoCelda = celdas[j].textContent || celdas[j].innerText;
                                    if (textoCelda.toUpperCase().indexOf(input) > -1) {
                                        mostrar = true;
                                        break;
                                    }
                                }
                            }

                            filas[i].style.display = mostrar ? '' : 'none';
                        }
                    }

                    // ========== EVENT LISTENERS ==========
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('monto_efectivo')?.addEventListener('input', calcularCambio);
                        document.getElementById('monto_nequi')?.addEventListener('input', calcularCambio);
                        document.getElementById('monto_efectivo_mixto')?.addEventListener('input', calcularCambioMixto);
                        document.getElementById('monto_nequi_mixto')?.addEventListener('input', calcularCambioMixto);

                        document.getElementById('metodo_pago_select')?.addEventListener('change', function() {
                            cambiarMetodoPago();
                            calcularCambio();
                        });
                    });

                    document.addEventListener('click', function(event) {
                        const resultados = document.getElementById('resultados_venta');
                        const buscador = document.getElementById('buscador_productos_venta');
                        if (resultados && !buscador?.contains(event.target) && !resultados.contains(event.target)) {
                            resultados.style.display = 'none';
                        }
                    });
                </script>
            <?php endif; ?>


        </div>
    </div>
    <script>
        function previsualizarReporte() {
            window.open('previsualizar_ventas.php', '_blank', 'width=1200,height=600,scrollbars=yes');
        }

        function generarPDF() {
            window.open('generar_pdf_ventas.php', '_blank');
        }
    </script>

    <script>
        // Función para filtrar tablas
        function filtrarTabla(tablaId, texto) {
            let input = texto.toUpperCase();
            let tabla = document.getElementById(tablaId);
            let filas = tabla.getElementsByTagName('tr');

            for (let i = 1; i < filas.length; i++) {
                let celdas = filas[i].getElementsByTagName('td');
                let mostrar = false;

                for (let j = 0; j < celdas.length - 1; j++) {
                    if (celdas[j]) {
                        let textoCelda = celdas[j].textContent || celdas[j].innerText;
                        if (textoCelda.toUpperCase().indexOf(input) > -1) {
                            mostrar = true;
                            break;
                        }
                    }
                }

                filas[i].style.display = mostrar ? '' : 'none';
            }
        }

        // Actualizar el total de unidades cuando cambian cantidades
        document.addEventListener('DOMContentLoaded', function() {
            const cantGrande = document.getElementById('cantidad_grande');
            const cantUnidad = document.getElementById('cantidad_unidad');
            const sueltas = document.getElementById('unidades_sueltas');

            if (cantGrande && cantUnidad && sueltas) {
                function actualizarStock() {
                    // Este es solo un indicador visual, no afecta el envío del formulario
                    let total = (parseInt(cantGrande.value) || 0) * (parseInt(cantUnidad.value) || 0) + (parseInt(sueltas.value) || 0);
                    // Podrías mostrar esto en algún lado si quieres
                }

                cantGrande.addEventListener('input', actualizarStock);
                cantUnidad.addEventListener('input', actualizarStock);
                sueltas.addEventListener('input', actualizarStock);
            }
        });
    </script>
</body>

</html>