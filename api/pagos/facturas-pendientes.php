<?php
/**
 * SHALOM FACTURA - API Facturas Pendientes por Cliente
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Pagos\Pago;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

$clienteId = (int)($_GET['cliente_id'] ?? 0);

if (!$clienteId) {
    Helpers::json(['success' => true, 'data' => []]);
}

$pagoModel = new Pago();
$facturas = $pagoModel->getFacturasPendientes($clienteId);

Helpers::json(['success' => true, 'data' => $facturas]);
