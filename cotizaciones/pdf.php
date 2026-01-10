<?php
/**
 * SHALOM FACTURA - Generar PDF de Cotización
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Cotizaciones\Cotizacion;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('cotizaciones.ver')) {
    die('No tiene permisos para ver cotizaciones');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('Cotización no especificada');
}

$cotizacionModel = new Cotizacion();
$cotizacion = $cotizacionModel->getById($id);

if (!$cotizacion) {
    die('Cotización no encontrada');
}

$detalles = $cotizacionModel->getDetalles($id);
$empresa = auth()->empresa();

// Generar PDF HTML (para imprimir o descargar)
$html = generarHtmlCotizacion($cotizacion, $detalles, $empresa);

// Si se solicita descarga directa
if (isset($_GET['download'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="Cotizacion_' . $cotizacion['numero'] . '.html"');
    echo $html;
    exit;
}

// Mostrar para imprimir
echo $html;

function generarHtmlCotizacion($cotizacion, $detalles, $empresa) {
    $estadoNombres = [
        'borrador' => 'BORRADOR',
        'enviada' => 'ENVIADA',
        'aceptada' => 'ACEPTADA',
        'rechazada' => 'RECHAZADA',
        'vencida' => 'VENCIDA',
        'facturada' => 'FACTURADA'
    ];
    
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización <?= e($cotizacion['numero']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e4d39;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .logo-section h1 {
            color: #1e4d39;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .logo-section p {
            color: #666;
            font-size: 11px;
        }
        
        .document-info {
            text-align: right;
        }
        
        .document-info h2 {
            color: #1e4d39;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .document-info .numero {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .estado-borrador { background: #e5e7eb; color: #374151; }
        .estado-enviada { background: #dbeafe; color: #1e40af; }
        .estado-aceptada { background: #d1fae5; color: #065f46; }
        .estado-rechazada { background: #fee2e2; color: #991b1b; }
        .estado-vencida { background: #ffedd5; color: #9a3412; }
        .estado-facturada { background: #e9d5ff; color: #6b21a8; }
        
        .info-section {
            display: flex;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .info-box {
            flex: 1;
        }
        
        .info-box h3 {
            color: #1e4d39;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .info-box p {
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .info-box .label {
            color: #666;
            display: inline-block;
            width: 80px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th {
            background: #1e4d39;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
        }
        
        table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        
        table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals {
            margin-left: auto;
            width: 300px;
        }
        
        .totals table {
            margin-bottom: 0;
        }
        
        .totals td {
            border: none;
            padding: 5px 10px;
        }
        
        .totals tr.total {
            background: #1e4d39;
            color: white;
            font-weight: bold;
        }
        
        .totals tr.total td {
            padding: 12px 10px;
            font-size: 14px;
        }
        
        .conditions {
            margin-top: 30px;
            padding: 15px;
            background: #f9f8f4;
            border-radius: 8px;
        }
        
        .conditions h4 {
            color: #1e4d39;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .conditions p {
            font-size: 10px;
            line-height: 1.5;
            color: #666;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #999;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1e4d39;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #163a2b;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimir</button>
    
    <div class="container">
        <div class="header">
            <div class="logo-section">
                <h1><?= e($empresa['razon_social']) ?></h1>
                <p>RUC: <?= e($empresa['ruc']) ?></p>
                <p><?= e($empresa['direccion_matriz']) ?></p>
                <?php if (!empty($empresa['telefono'])): ?>
                <p>Tel: <?= e($empresa['telefono']) ?></p>
                <?php endif; ?>
                <?php if (!empty($empresa['email'])): ?>
                <p>Email: <?= e($empresa['email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="document-info">
                <h2>COTIZACIÓN</h2>
                <p class="numero">N° <?= e($cotizacion['numero']) ?></p>
                <span class="estado estado-<?= $cotizacion['estado'] ?>">
                    <?= $estadoNombres[$cotizacion['estado']] ?? strtoupper($cotizacion['estado']) ?>
                </span>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Cliente</h3>
                <p><strong><?= e($cotizacion['cliente_nombre']) ?></strong></p>
                <p><span class="label">RUC/CI:</span> <?= e($cotizacion['cliente_identificacion']) ?></p>
                <?php if (!empty($cotizacion['cliente_direccion'])): ?>
                <p><span class="label">Dirección:</span> <?= e($cotizacion['cliente_direccion']) ?></p>
                <?php endif; ?>
                <?php if (!empty($cotizacion['cliente_email'])): ?>
                <p><span class="label">Email:</span> <?= e($cotizacion['cliente_email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h3>Información</h3>
                <p><span class="label">Fecha:</span> <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></p>
                <p><span class="label">Válida hasta:</span> <?= date('d/m/Y', strtotime($cotizacion['fecha_validez'])) ?></p>
                <?php if (!empty($cotizacion['asunto'])): ?>
                <p><span class="label">Asunto:</span> <?= e($cotizacion['asunto']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($cotizacion['introduccion'])): ?>
        <p style="margin-bottom: 20px; color: #666; font-size: 11px;"><?= nl2br(e($cotizacion['introduccion'])) ?></p>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 80px;">Código</th>
                    <th>Descripción</th>
                    <th class="text-right" style="width: 60px;">Cant.</th>
                    <th class="text-right" style="width: 80px;">P. Unit.</th>
                    <th class="text-right" style="width: 60px;">Desc.</th>
                    <th class="text-right" style="width: 90px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $i => $d): ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= e($d['codigo'] ?? '-') ?></td>
                    <td><?= e($d['descripcion']) ?></td>
                    <td class="text-right"><?= number_format($d['cantidad'], 2) ?></td>
                    <td class="text-right">$<?= number_format($d['precio_unitario'], 2) ?></td>
                    <td class="text-right"><?= number_format($d['descuento'] ?? 0, 2) ?>%</td>
                    <td class="text-right">$<?= number_format($d['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">$<?= number_format($cotizacion['subtotal'], 2) ?></td>
                </tr>
                <?php if (($cotizacion['total_descuento'] ?? 0) > 0): ?>
                <tr>
                    <td>Descuento:</td>
                    <td class="text-right">-$<?= number_format($cotizacion['total_descuento'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>IVA (<?= $cotizacion['porcentaje_iva'] ?? 15 ?>%):</td>
                    <td class="text-right">$<?= number_format($cotizacion['total_iva'], 2) ?></td>
                </tr>
                <tr class="total">
                    <td>TOTAL:</td>
                    <td class="text-right">$<?= number_format($cotizacion['total'], 2) ?></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($cotizacion['condiciones'])): ?>
        <div class="conditions">
            <h4>Términos y Condiciones</h4>
            <p><?= nl2br(e($cotizacion['condiciones'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Documento generado por <strong>Shalom Factura</strong></p>
            <p>Este documento no tiene validez fiscal - Es una cotización</p>
            <p><?= date('d/m/Y H:i') ?></p>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}