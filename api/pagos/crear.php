<?php
/**
 * SHALOM FACTURA - API Crear Pago
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Pagos\Pago;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('pagos.crear')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Helpers::json(['success' => false, 'message' => 'Datos inválidos'], 400);
}

$pagoModel = new Pago();
$result = $pagoModel->create($input);

Helpers::json($result);
