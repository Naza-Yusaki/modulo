<?php
// generar_pdf_ventas.php
require_once 'libs/fpdf/fpdf.php';
require_once 'conexion.php';

// Obtener fecha
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_inicio = $fecha . ' 00:00:00';
$fecha_fin = $fecha . ' 23:59:59';

// Obtener datos de ventas
// En la consulta SQL de ventas
$sql_ventas = "SELECT hv.*, 
               descuento_aplicado,
               precio_original,
               motivo_descuento,
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
            $ventas_nequi[] = $row;
            $total_nequi += $row['precio'];
            $total_mixto += $row['precio'];
        }
    } elseif ($row['tipo_venta'] == 'Devolución') {
        $devoluciones[] = $row;
    }
}

$total_general = $total_efectivo + $total_nequi;
$conn->close();

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('REPORTE DE VENTAS'), 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Fecha: ' . date('d/m/Y', strtotime($GLOBALS['fecha'])), 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, utf8_decode('SISTEMA DE GESTIÓN DE BODEGA'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function formatearCantidad($venta)
    {
        $cantidad = '';
        if ($venta['cantidad_grande'] > 0) {
            $cantidad .= $venta['cantidad_grande'] . ' ' . strtolower($venta['modulo']) . 's';
        }
        if ($venta['cantidad_grande'] > 0 && $venta['cantidad_unidad'] > 0) {
            $cantidad .= ' + ';
        }
        if ($venta['cantidad_unidad'] > 0) {
            $cantidad .= $venta['cantidad_unidad'] . ' und';
        }
        return $cantidad;
    }

    function TablaEfectivo($ventas)
    {
        if (empty($ventas)) return;

        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(255, 193, 7);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, utf8_decode('💵 VENTAS EN EFECTIVO'), 1, 1, 'L', true);
        $this->Ln(2);

        $total = array_sum(array_column($ventas, 'precio'));

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0, 0, 0);

        $this->Cell(20, 7, 'Hora', 1, 0, 'C', true);
        $this->Cell(65, 7, utf8_decode('Producto'), 1, 0, 'C', true);
        $this->Cell(20, 7, 'Present.', 1, 0, 'C', true);
        $this->Cell(45, 7, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Total', 1, 1, 'C', true);

        $this->SetFont('Arial', '', 7);

        foreach ($ventas as $venta) {
            if ($this->GetY() > 250) $this->AddPage();

            $hora = date('H:i', strtotime($venta['fecha_hora']));
            $producto = utf8_decode(substr($venta['nombre_producto'], 0, 30));
            $cantidad = $this->formatearCantidad($venta);

            $this->Cell(20, 6, $hora, 1);
            $this->Cell(65, 6, $producto, 1);
            $this->Cell(20, 6, $venta['modulo'], 1);
            $this->Cell(45, 6, utf8_decode($cantidad), 1);
            $this->Cell(30, 6, '$' . number_format($venta['precio'], 0), 1, 1, 'R');
        }

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(255, 193, 7);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(150, 7, 'TOTAL EFECTIVO:', 1, 0, 'R', true);
        $this->Cell(30, 7, '$' . number_format($total, 0), 1, 1, 'R', true);
        $this->Ln(5);
    }

    function TablaNequi($ventas)
    {
        if (empty($ventas)) return;

        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(23, 162, 184);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, utf8_decode('📱 VENTAS EN NEQUI (Puro + Mixto)'), 1, 1, 'L', true);
        $this->Ln(2);

        $total = array_sum(array_column($ventas, 'precio'));

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0, 0, 0);

        $this->Cell(20, 7, 'Hora', 1, 0, 'C', true);
        $this->Cell(55, 7, utf8_decode('Producto'), 1, 0, 'C', true);
        $this->Cell(20, 7, 'Present.', 1, 0, 'C', true);
        $this->Cell(40, 7, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Tipo', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Total', 1, 1, 'C', true);

        $this->SetFont('Arial', '', 7);

        foreach ($ventas as $venta) {
            if ($this->GetY() > 250) $this->AddPage();

            $hora = date('H:i', strtotime($venta['fecha_hora']));
            $producto = utf8_decode(substr($venta['nombre_producto'], 0, 25));
            $cantidad = $this->formatearCantidad($venta);
            $tipo = ($venta['metodo_pago'] == 'mixto') ? '🔄 Mixto' : '📱 Nequi';

            // Color de fondo para mixtas
            if ($venta['metodo_pago'] == 'mixto') {
                $this->SetFillColor(243, 232, 255);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->Cell(20, 6, $hora, 1, 0, 'C', ($venta['metodo_pago'] == 'mixto'));
            $this->Cell(55, 6, $producto, 1, 0, 'L', ($venta['metodo_pago'] == 'mixto'));
            $this->Cell(20, 6, $venta['modulo'], 1, 0, 'C', ($venta['metodo_pago'] == 'mixto'));
            $this->Cell(40, 6, utf8_decode($cantidad), 1, 0, 'L', ($venta['metodo_pago'] == 'mixto'));
            $this->Cell(25, 6, utf8_decode($tipo), 1, 0, 'C', ($venta['metodo_pago'] == 'mixto'));
            $this->Cell(30, 6, '$' . number_format($venta['precio'], 0), 1, 1, 'R', ($venta['metodo_pago'] == 'mixto'));
        }

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(23, 162, 184);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(160, 7, 'TOTAL NEQUI:', 1, 0, 'R', true);
        $this->Cell(30, 7, '$' . number_format($total, 0), 1, 1, 'R', true);
        $this->Ln(5);
    }
}

// Crear PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Resumen General
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('RESUMEN GENERAL'), 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Total Ventas: $' . number_format($total_general, 0), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, 'Efectivo: $' . number_format($total_efectivo, 0), 0, 1);
$pdf->Cell(0, 6, 'Nequi (Incluye Mixto): $' . number_format($total_nequi, 0), 0, 1);
$pdf->Cell(0, 6, 'Ventas Mixtas: $' . number_format($total_mixto, 0), 0, 1);
$pdf->Ln(10);

// Tablas
$pdf->TablaEfectivo($ventas_efectivo);
$pdf->TablaNequi($ventas_nequi);

// Información adicional
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8_decode('RESUMEN FINAL'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('• Ventas en efectivo: ' . count($ventas_efectivo)), 0, 1);
$pdf->Cell(0, 5, utf8_decode('• Ventas en nequi puro: ' . count(array_filter($ventas_nequi, function ($v) {
    return $v['metodo_pago'] == 'nequi';
}))), 0, 1);
$pdf->Cell(0, 5, utf8_decode('• Ventas mixtas: ' . count(array_filter($ventas_nequi, function ($v) {
    return $v['metodo_pago'] == 'mixto';
}))), 0, 1);

$pdf->Output('I', 'reporte_ventas_' . $fecha . '.pdf');
