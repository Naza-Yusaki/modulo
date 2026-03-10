<?php
// previsualizar_ventas.php
session_start();
require_once 'conexion.php';

// Obtener fecha del día (por defecto hoy)
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_inicio = $fecha . ' 00:00:00';
$fecha_fin = $fecha . ' 23:59:59';

// Obtener ventas del día
$sql_ventas = "SELECT hv.*, 
               CASE 
                  WHEN hv.metodo_pago LIKE 'DEVUELTA%' THEN 'Anulada'
                  WHEN hv.metodo_pago LIKE 'DEVOLUCION%' THEN 'Devolución'
                  WHEN hv.precio < 0 THEN 'Ajuste'
                  ELSE 'Venta'
               END as tipo_venta
               FROM historial_venta hv
               WHERE hv.fecha_hora BETWEEN ? AND ?
               ORDER BY hv.fecha_hora ASC";

$stmt = $conn->prepare($sql_ventas);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$result_ventas = $stmt->get_result();

$ventas_efectivo = [];
$ventas_nequi = []; // Aquí irán tanto nequi puro como mixto
$devoluciones = [];

$total_efectivo = 0;
$total_nequi = 0;
$total_mixto = 0;

while ($row = $result_ventas->fetch_assoc()) {
    if ($row['tipo_venta'] == 'Venta') {
        if ($row['metodo_pago'] == 'efectivo') {
            $ventas_efectivo[] = $row;
            $total_efectivo += $row['precio'];
        } elseif ($row['metodo_pago'] == 'nequi') {
            $ventas_nequi[] = $row;
            $total_nequi += $row['precio'];
        } elseif ($row['metodo_pago'] == 'mixto') {
            $ventas_nequi[] = $row; // Las mixtas van a la tabla de nequi
            $total_nequi += $row['precio'];
            $total_mixto += $row['precio'];
        }
    } elseif ($row['tipo_venta'] == 'Devolución') {
        $devoluciones[] = $row;
    }
}

$total_general = $total_efectivo + $total_nequi;
$conn->close();

// Función para renderizar tabla
function renderTabla($ventas, $titulo, $color, $icono, $mostrar_mixto = false)
{
    if (empty($ventas)) {
        return '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; text-align: center; border-radius: 5px;">No hay ventas en ' . $titulo . '</div>';
    }

    $total = array_sum(array_column($ventas, 'precio'));

    $html = '
    <div style="margin-top: 25px; margin-bottom: 20px;">
        <div style="background: ' . $color . '; color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4em;">
                ' . $icono . ' ' . $titulo . '
            </h2>
            <div style="font-size: 1.5em; font-weight: bold;">Total: $' . number_format($total, 0) . '</div>
        </div>
        <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 0.95em;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Hora</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Producto</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Presentación</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Cantidad</th>';

    if ($mostrar_mixto) {
        $html .= '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo Pago</th>';
    }

    $html .= '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($ventas as $venta) {
        $estilo_fila = '';
        if ($venta['metodo_pago'] == 'mixto') {
            $estilo_fila = ' style="background-color: #f3e8ff;"'; // Color lavanda para mixtas
        }

        $html .= '<tr' . $estilo_fila . '>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . date('H:i', strtotime($venta['fecha_hora'])) . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($venta['nombre_producto']) . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . $venta['modulo'] . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">';

        if ($venta['cantidad_grande'] > 0) {
            $html .= $venta['cantidad_grande'] . ' ' . strtolower($venta['modulo']) . 's';
        }
        if ($venta['cantidad_grande'] > 0 && $venta['cantidad_unidad'] > 0) {
            $html .= ' + ';
        }
        if ($venta['cantidad_unidad'] > 0) {
            $html .= $venta['cantidad_unidad'] . ' und';
        }

        $html .= '</td>';

        if ($mostrar_mixto) {
            $tipo_pago = $venta['metodo_pago'] == 'mixto' ? '🔄 Mixto' : '📱 Nequi Puro';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . $tipo_pago . '</td>';
        }

        $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold; color: #28a745;">$' . number_format($venta['precio'], 0) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="' . ($mostrar_mixto ? '5' : '4') . '" style="padding: 12px; text-align: right;">TOTAL ' . $titulo . ':</td>
                    <td style="padding: 12px; text-align: right; color: #28a745; font-size: 1.1em;">$' . number_format($total, 0) . '</td>
                </tr>
            </tfoot>
        </table>
    </div>';

    return $html;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - <?php echo date('d/m/Y', strtotime($fecha)); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .header h1 {
            color: #333;
            font-size: 2em;
        }

        .header h1 small {
            font-size: 0.6em;
            color: #666;
            display: block;
        }

        .selector-fecha {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .selector-fecha input {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .resumen-general {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-total {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-total .label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .card-total .value {
            font-size: 2em;
            font-weight: bold;
        }

        .resumen-metodos {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .metodo-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .metodo-card.efectivo {
            border-left: 4px solid #ffc107;
        }

        .metodo-card.nequi {
            border-left: 4px solid #17a2b8;
        }

        .metodo-icono {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .metodo-titulo {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .metodo-monto {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .metodo-monto.efectivo {
            color: #ffc107;
        }

        .metodo-monto.nequi {
            color: #17a2b8;
        }

        .metodo-detalle {
            color: #666;
            font-size: 0.9em;
        }

        .info-mixto {
            background: #f3e8ff;
            border-left: 4px solid #6f42c1;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-size: 0.95em;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-general {
            font-size: 1.5em;
            font-weight: bold;
            color: #28a745;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header no-print">
            <h1>
                📊 Reporte de Ventas
                <small><?php echo date('d/m/Y', strtotime($fecha)); ?></small>
            </h1>
            <div class="selector-fecha">
                <input type="date" id="selector_fecha" value="<?php echo $fecha; ?>" onchange="cambiarFecha(this.value)">
                <a href="generar_pdf_ventas.php?fecha=<?php echo $fecha; ?>" class="btn btn-success" target="_blank">
                    📄 Descargar PDF
                </a>
                <button onclick="window.print()" class="btn btn-info">🖨️ Imprimir</button>
            </div>
        </div>

        <!-- Resumen General -->
        <div class="resumen-general">
            <div class="card-total">
                <div class="label">Total Ventas</div>
                <div class="value">$<?php echo number_format($total_general, 0); ?></div>
                <div><?php echo count($ventas_efectivo) + count($ventas_nequi); ?> transacciones</div>
            </div>
        </div>

        <!-- Resumen por Método -->
        <div class="resumen-metodos">
            <div class="metodo-card efectivo">
                <div class="metodo-icono">💵</div>
                <div class="metodo-titulo">EFECTIVO</div>
                <div class="metodo-monto efectivo">$<?php echo number_format($total_efectivo, 0); ?></div>
                <div class="metodo-detalle"><?php echo count($ventas_efectivo); ?> ventas</div>
            </div>
            <div class="metodo-card nequi">
                <div class="metodo-icono">📱</div>
                <div class="metodo-titulo">NEQUI (Incluye Mixto)</div>
                <div class="metodo-monto nequi">$<?php echo number_format($total_nequi, 0); ?></div>
                <div class="metodo-detalle">
                    <?php
                    $nequi_puro = count(array_filter($ventas_nequi, function ($v) {
                        return $v['metodo_pago'] == 'nequi';
                    }));
                    $mixto_count = count(array_filter($ventas_nequi, function ($v) {
                        return $v['metodo_pago'] == 'mixto';
                    }));
                    echo $nequi_puro . ' nequi puro + ' . $mixto_count . ' mixto';
                    ?>
                </div>
            </div>
        </div>

        <!-- Info de Ventas Mixtas -->
        <?php if ($total_mixto > 0): ?>
            <div class="info-mixto">
                <strong>🔄 Ventas Mixtas:</strong> Se han registrado $<?php echo number_format($total_mixto, 0); ?> en ventas mixtas (combinación efectivo + nequi). Estos montos están incluidos en la tabla de NEQUI.
            </div>
        <?php endif; ?>

        <!-- Tabla de Efectivo -->
        <?php echo renderTabla($ventas_efectivo, 'VENTAS EN EFECTIVO', '#ffc107', '💵', false); ?>

        <!-- Tabla de Nequi (incluye mixto) -->
        <?php echo renderTabla($ventas_nequi, 'VENTAS EN NEQUI (Puro + Mixto)', '#17a2b8', '📱', true); ?>

        <!-- Footer -->
        <div class="footer">
            <div>
                <strong>Resumen:</strong>
                Efectivo: <?php echo count($ventas_efectivo); ?> ventas |
                Nequi: <?php echo count($ventas_nequi); ?> ventas
            </div>
            <div class="total-general">
                Total: $<?php echo number_format($total_general, 0); ?>
            </div>
        </div>

        <div class="action-buttons no-print" style="justify-content: center; margin-top: 20px;">
            <button onclick="window.history.back()" class="btn btn-primary">← Volver</button>
        </div>
    </div>

    <script>
        function cambiarFecha(fecha) {
            window.location.href = 'previsualizar_ventas.php?fecha=' + fecha;
        }
    </script>
</body>

</html>