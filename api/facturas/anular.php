<?php
/**
 * SHALOM FACTURA - API Anular Factura
 * Anula una factura (solo si no está autorizada o si se puede anular en SRI)
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

ob_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ob_end_clean();

use Shalom\Core\Helpers;
use Shalom\Modules\Facturas\Factura;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    if (!auth()->check()) {
        throw new Exception('No autorizado', 401);
    }

    if (!auth()->can('facturas.anular')) {
        throw new Exception('Sin permisos para anular facturas', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $motivo = trim($input['motivo'] ?? '');

    if (!$id) {
        throw new Exception('ID de factura requerido', 400);
    }

    if (empty($motivo)) {
        throw new Exception('El motivo de anulación es requerido', 400);
    }

    $facturaModel = new Factura();
    
    // Verificar que la factura existe
    $factura = $facturaModel->getById($id);
    if (!$factura) {
        throw new Exception('Factura no encontrada', 404);
    }
    
    // Si está autorizada por el SRI, no se puede anular directamente
    // Se debe emitir una Nota de Crédito
    if ($factura['estado_sri'] === 'autorizada') {
        throw new Exception(
            'No se puede anular una factura autorizada por el SRI. ' .
            'Debe emitir una Nota de Crédito para anular la transacción.',
            400
        );
    }
    
    $result = $facturaModel->anular($id, $motivo);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $code = $e->getCode();
    $httpCode = ($code >= 400 && $code < 600) ? $code : 500;
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
