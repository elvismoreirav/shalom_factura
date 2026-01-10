<?php
/**
 * SHALOM FACTURA - Generación de RIDE (PDF)
 * Controlador Actualizado: Soporte IVA 15% y fecha_autorizacion del SRI
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Carga segura del autoload
$autoloadPath = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    error_log("Advertencia: No se encontró vendor/autoload.php");
}

use Shalom\Modules\Pdf\GeneradorPdf;
use Shalom\Modules\Facturas\Factura;

// Auth Check
if (!function_exists('auth') || !auth()->check()) {
    http_response_code(401);
    exit('No autorizado');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) exit('ID requerido');

$facturaModel = new Factura();
$factura = $facturaModel->getById($id);
if (!$factura) exit('Factura no encontrada');

// ==========================================
// 1. OBTENCIÓN DE DATOS
// ==========================================

$empresa = auth()->empresa();

// Establecimiento y Punto de Emisión
$establecimiento = db()->query("SELECT * FROM establecimientos WHERE id = :id")
    ->fetch([':id' => $factura['establecimiento_id']]);

$puntoEmision = db()->query("SELECT * FROM puntos_emision WHERE id = :id")
    ->fetch([':id' => $factura['punto_emision_id']]);

// Formato de número de factura
$factura['numero'] = sprintf(
    '%s-%s-%s',
    $establecimiento['codigo'],
    $puntoEmision['codigo'],
    str_pad($factura['secuencial'], 9, '0', STR_PAD_LEFT)
);

// Datos tributarios
$factura['establecimiento_direccion'] = $establecimiento['direccion'] ?? '-';
$factura['ambiente'] = $empresa['ambiente_sri'] ?? $empresa['ambiente'] ?? 1;
$factura['tipo_emision'] = 1;
$factura['guia_remision'] = $factura['guia_remision'] ?? '-';

// IMPORTANTE: Fecha de autorización del SRI (no la fecha de emisión)
// Si existe fecha_autorizacion del SRI, usarla; si no, mostrar "Pendiente"
if (!empty($factura['fecha_autorizacion'])) {
    // Formatear fecha del SRI (viene en formato Y-m-d H:i:s o ISO 8601)
    $fechaAuth = $factura['fecha_autorizacion'];
    if (strpos($fechaAuth, 'T') !== false) {
        // Formato ISO 8601
        $fechaAuth = str_replace('T', ' ', substr($fechaAuth, 0, 19));
    }
    $factura['fecha_autorizacion'] = date('d/m/Y H:i:s', strtotime($fechaAuth));
} else {
    // No autorizada aún
    $factura['fecha_autorizacion'] = 'Pendiente de autorización';
}

// Número de autorización (clave de acceso si está autorizada)
$factura['numero_autorizacion'] = $factura['numero_autorizacion'] ?? $factura['clave_acceso'] ?? 'Pendiente';

// Clave de acceso
$factura['clave_acceso'] = $factura['clave_acceso'] ?? str_repeat('0', 49);

// Cliente
$factura['cliente'] = db()->query("
    SELECT c.*, ti.codigo as tipo_identificacion_codigo 
    FROM clientes c 
    JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id 
    WHERE c.id = :id
")->fetch([':id' => $factura['cliente_id']]);

// Detalles con normalización para el PDF
$detallesRaw = $facturaModel->getDetalles($id);
$factura['detalles'] = array_map(function($det) {
    return [
        'codigo_principal' => $det['codigo_principal'] ?: 'SRV' . str_pad($det['id'], 6, '0', STR_PAD_LEFT),
        'descripcion' => $det['descripcion'],
        'cantidad' => $det['cantidad'],
        'precio_unitario' => $det['precio_unitario'],
        'descuento' => $det['descuento'] ?? 0,
        'precio_total_sin_impuesto' => $det['subtotal'] ?? ($det['cantidad'] * $det['precio_unitario'] - ($det['descuento'] ?? 0))
    ];
}, $detallesRaw);

// Formas de Pago
try {
    $factura['formas_pago'] = db()->query("
        SELECT ffp.*, cfp.nombre as descripcion, cfp.codigo as forma_pago_codigo
        FROM factura_pagos_forma ffp 
        LEFT JOIN cat_formas_pago cfp ON ffp.forma_pago_id = cfp.id 
        WHERE ffp.factura_id = :id
    ")->fetchAll([':id' => $id]);
} catch (Exception $e) {
    $factura['formas_pago'] = []; 
}

// Info Adicional
$factura['info_adicional'] = db()->query("
    SELECT nombre, valor FROM factura_info_adicional WHERE factura_id = :id ORDER BY orden
")->fetchAll([':id' => $id]);

// Si no hay info adicional, agregar datos del cliente
if (empty($factura['info_adicional'])) {
    $cliente = $factura['cliente'];
    if (!empty($cliente['email'])) {
        $factura['info_adicional'][] = ['nombre' => 'Email', 'valor' => $cliente['email']];
    }
    if (!empty($cliente['direccion'])) {
        $factura['info_adicional'][] = ['nombre' => 'Dirección', 'valor' => $cliente['direccion']];
    }
    if (!empty($cliente['telefono'])) {
        $factura['info_adicional'][] = ['nombre' => 'Teléfono', 'valor' => $cliente['telefono']];
    }
}

// Impuestos (Desglose)
$impuestosFactura = db()->query("
    SELECT fi.*, ci.codigo_porcentaje, ci.codigo as impuesto_codigo
    FROM factura_impuestos fi 
    JOIN cat_impuestos ci ON fi.impuesto_id = ci.id 
    WHERE fi.factura_id = :id
")->fetchAll([':id' => $id]);


// ==========================================
// 2. CÁLCULOS Y NORMALIZACIÓN (IVA 15%)
// ==========================================

$subtotalIva = 0; // Subtotal gravado con IVA
$subtotal0 = 0;   // Subtotal 0%

if (!empty($impuestosFactura)) {
    foreach ($impuestosFactura as $imp) {
        // Códigos SRI (Tabla 17): 
        // 0 = 0%, 2 = 12%, 3 = 14%, 4 = 15%, 5 = 5%, 6 = No objeto, 7 = Exento
        if (in_array($imp['codigo_porcentaje'], ['2', '3', '4', '5'])) {
            $subtotalIva += $imp['base_imponible'];
        } elseif ($imp['codigo_porcentaje'] === '0') {
            $subtotal0 += $imp['base_imponible'];
        }
    }
} else {
    // Fallback simple si no hay desglose guardado
    if ($factura['total_iva'] > 0) {
        $subtotalIva = $factura['subtotal_sin_impuestos'];
    } else {
        $subtotal0 = $factura['subtotal_sin_impuestos'];
    }
}

// Asignamos variables para el Generador
$factura['subtotal_iva_actual'] = $subtotalIva;
$factura['subtotal_0'] = $subtotal0;
$factura['subtotal_no_objeto'] = 0;
$factura['subtotal_exento'] = 0;
$factura['total_descuento'] = $factura['total_descuento'] ?? 0;
$factura['propina'] = $factura['propina'] ?? 0;

// Normalización de "valor" en formas de pago
if (!empty($factura['formas_pago'])) {
    $factura['formas_pago'] = array_map(function($p) {
        $p['valor'] = $p['valor'] ?? $p['total'] ?? $p['monto'] ?? 0;
        return $p;
    }, $factura['formas_pago']);
}

// ==========================================
// 3. GENERACIÓN PDF
// ==========================================

if (!class_exists('TCPDF')) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error: Librería PDF no encontrada</h1>";
    echo "<p>Ejecute: <code>composer require tecnickcom/tcpdf</code></p>";
    exit;
}

try {
    $generador = new GeneradorPdf($empresa);
    $pdfContent = $generador->generarFactura($factura);
    
    $filename = "RIDE_{$factura['numero']}.pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    
    echo $pdfContent;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error generando PDF</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error generando RIDE: " . $e->getMessage());
}
