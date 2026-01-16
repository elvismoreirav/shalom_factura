<?php
/**
 * SHALOM FACTURA - API Consultar Nota de Crédito en SRI
 */

ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\NotasCredito\NotaCredito;

ob_end_clean();

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('notas_credito.ver')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos para consultar notas de crédito'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    Helpers::json(['success' => false, 'message' => 'ID requerido'], 400);
}

try {
    $notaModel = new NotaCredito();
    $result = $notaModel->consultarEstadoSri((int) $input['id']);
    Helpers::json($result);
} catch (\Exception $e) {
    Helpers::json(['success' => false, 'message' => 'Error al consultar la nota de crédito: ' . $e->getMessage()], 500);
}
