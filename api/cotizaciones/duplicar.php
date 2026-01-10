<?php
/**
 * SHALOM FACTURA - API Duplicar Cotización
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
$id = (int)($input['id'] ?? 0);

if (!$id) {
    Helpers::json(['success' => false, 'message' => 'ID requerido'], 400);
}

$cotizacionModel = new Cotizacion();
$result = $cotizacionModel->duplicar($id);

Helpers::json($result);
