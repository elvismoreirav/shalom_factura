<?php
/**
 * SHALOM FACTURA - API Crear Factura
 */

// Asegurar que no hay output previo
ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Facturas\Factura;

// Limpiar cualquier output del bootstrap
ob_end_clean();

// Verificar autenticación
if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

// Verificar permisos
if (!auth()->can('facturas.crear')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos para crear facturas'], 403);
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Helpers::json(['success' => false, 'message' => 'Datos inválidos o JSON mal formado'], 400);
}

// Validaciones básicas
if (empty($input['cliente_id'])) {
    Helpers::json(['success' => false, 'message' => 'Debe seleccionar un cliente'], 400);
}

if (empty($input['establecimiento_id'])) {
    Helpers::json(['success' => false, 'message' => 'Debe seleccionar un establecimiento'], 400);
}

if (empty($input['punto_emision_id'])) {
    Helpers::json(['success' => false, 'message' => 'Debe seleccionar un punto de emisión'], 400);
}

if (empty($input['detalles']) || !is_array($input['detalles'])) {
    Helpers::json(['success' => false, 'message' => 'Debe agregar al menos un detalle'], 400);
}

try {
    // Crear factura
    $facturaModel = new Factura();
    $result = $facturaModel->create($input);
    
    if ($result['success']) {
        // Si se solicitó emitir
        if (($input['accion'] ?? '') === 'emitir') {
            $emitirResult = $facturaModel->emitir($result['id']);
            if (!$emitirResult['success']) {
                $result['message'] .= '. Nota: ' . $emitirResult['message'];
            } else {
                $result['message'] .= ' y emitida correctamente';
            }
        }
    }
    
    Helpers::json($result);
    
} catch (\Exception $e) {
    error_log('Error creando factura: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
    Helpers::json([
        'success' => false, 
        'message' => 'Error al crear la factura: ' . $e->getMessage()
    ], 500);
}
