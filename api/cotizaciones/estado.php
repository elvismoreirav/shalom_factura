<?php
/**
 * SHALOM FACTURA - API Cambiar Estado de Cotización
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Cotizaciones\Cotizacion;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('cotizaciones.editar')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$estado = $input['estado'] ?? '';
$motivo = $input['motivo'] ?? null;

if (!$id || !$estado) {
    Helpers::json(['success' => false, 'message' => 'Datos incompletos'], 400);
}

$cotizacionModel = new Cotizacion();
$result = $cotizacionModel->cambiarEstado($id, $estado, $motivo);

Helpers::json($result);
