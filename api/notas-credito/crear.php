<?php
/**
 * SHALOM FACTURA - API Crear Nota de Crédito
 */

ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\NotasCredito\NotaCredito;

ob_end_clean();

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('notas_credito.crear')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos para crear notas de crédito'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Helpers::json(['success' => false, 'message' => 'Datos inválidos o JSON mal formado'], 400);
}

if (empty($input['factura_id'])) {
    Helpers::json(['success' => false, 'message' => 'Debe seleccionar una factura'], 400);
}

if (empty($input['motivo'])) {
    Helpers::json(['success' => false, 'message' => 'Debe ingresar un motivo'], 400);
}

try {
    $notaModel = new NotaCredito();
    $result = $notaModel->create($input);

    if ($result['success'] && ($input['accion'] ?? '') === 'emitir') {
        $emitirResult = $notaModel->emitir($result['id']);
        if (!$emitirResult['success']) {
            $result['message'] .= '. Nota: ' . $emitirResult['message'];
        } else {
            $result['message'] .= ' y emitida correctamente';
        }
    }

    Helpers::json($result);
} catch (\Exception $e) {
    error_log('Error creando nota de crédito: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
    Helpers::json(['success' => false, 'message' => 'Error al crear la nota de crédito: ' . $e->getMessage()], 500);
}
