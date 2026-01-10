<?php
/**
 * SHALOM FACTURA - API Crear Cotización
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Cotizaciones\Cotizacion;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('cotizaciones.crear')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Helpers::json(['success' => false, 'message' => 'Datos inválidos'], 400);
}

$cotizacionModel = new Cotizacion();
$result = $cotizacionModel->create($input);

// Si se solicitó marcar como enviada
if ($result['success'] && ($input['accion'] ?? '') === 'enviar') {
    $cotizacionModel->cambiarEstado($result['id'], 'enviada');
    $result['message'] .= ' y marcada como enviada';
}

Helpers::json($result);
