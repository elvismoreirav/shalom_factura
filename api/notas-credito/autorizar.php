<?php
/**
 * SHALOM FACTURA - API Autorizar/Emitir Nota de Crédito
 * Proceso completo: Generar XML -> Firmar XAdES-BES -> Enviar al SRI -> Autorizar
 */

// Configuración de errores para no romper JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

// Output buffering para limpiar cualquier salida previa
ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Modules\NotasCredito\NotaCredito;

// Limpiar buffer
ob_end_clean();

// Header JSON obligatorio
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Verificar autenticación
    if (!auth()->check()) {
        throw new Exception('No autorizado', 401);
    }

    // Verificar permisos
    if (!auth()->can('notas_credito.crear')) {
        throw new Exception('Sin permisos para emitir notas de crédito', 403);
    }

    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('ID de nota de crédito requerido', 400);
    }

    // Crear instancia del modelo
    $notaModel = new NotaCredito();

    // Ejecutar proceso de autorización/emisión
    $result = $notaModel->emitir($id);

    // Responder con resultado
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
