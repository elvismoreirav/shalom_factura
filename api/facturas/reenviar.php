<?php
/**
 * SHALOM FACTURA - API Reenviar Factura al SRI
 * Para facturas que fallaron en el envío inicial o fueron rechazadas
 * 
 * Funcionalidad:
 * - Si el XML firmado existe, lo reenvía directamente
 * - Si no existe, regenera todo el proceso (XML + Firma + Envío)
 * - Consulta autorización y actualiza fecha_autorizacion del SRI
 */

// Configuración de errores
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

// Output buffering
ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Facturas\Factura;

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
    if (!auth()->can('facturas.crear')) {
        throw new Exception('Sin permisos', 403);
    }

    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('ID de factura requerido', 400);
    }

    // Crear instancia del modelo
    $facturaModel = new Factura();
    
    // Obtener factura para validaciones
    $factura = $facturaModel->getById($id);
    
    if (!$factura) {
        throw new Exception('Factura no encontrada', 404);
    }

    // Si ya está autorizada, no reenviar
    if ($factura['estado_sri'] === 'autorizada') {
        echo json_encode([
            'success' => false,
            'message' => 'La factura ya está autorizada por el SRI',
            'numero_autorizacion' => $factura['numero_autorizacion'],
            'fecha_autorizacion' => $factura['fecha_autorizacion']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ejecutar reenvío
    // El método reenviar() verifica si existe el XML firmado:
    // - Si existe: lo reenvía directamente al SRI
    // - Si no existe: regenera clave, XML, firma y envía
    $result = $facturaModel->reenviar($id);
    
    // Responder con resultado
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $code = $e->getCode();
    $httpCode = ($code >= 400 && $code < 600) ? $code : 500;
    
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
