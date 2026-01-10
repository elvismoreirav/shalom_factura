<?php
/**
 * SHALOM FACTURA - API Anular Pago
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Pagos\Pago;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('pagos.anular')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$motivo = trim($input['motivo'] ?? '');

if (!$id) {
    Helpers::json(['success' => false, 'message' => 'ID de pago requerido'], 400);
}

if (empty($motivo)) {
    Helpers::json(['success' => false, 'message' => 'El motivo de anulación es requerido'], 400);
}

$pagoModel = new Pago();
$result = $pagoModel->anular($id, $motivo);

Helpers::json($result);
