<?php
/**
 * SHALOM FACTURA - API Consultar Estado SRI
 * Consulta el estado de autorización de una factura en el SRI
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

    if (!auth()->can('facturas.ver')) {
        throw new Exception('Sin permisos', 403);
    }

    // Aceptar GET o POST
    $id = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
    } else {
        $id = (int)($_GET['id'] ?? 0);
    }

    if (!$id) {
        throw new Exception('ID de factura requerido', 400);
    }

    $facturaModel = new Factura();
    $result = $facturaModel->consultarEstadoSri($id);
    
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
