<?php
/**
 * SHALOM FACTURA - API Crear Categoría de Servicios
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;
use Shalom\Modules\Servicios\Servicio;

if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

if (!auth()->can('servicios.crear')) {
    Helpers::json(['success' => false, 'message' => 'Sin permisos'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::json(['success' => false, 'message' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$servicioModel = new Servicio();
$result = $servicioModel->createCategoria($input);

Helpers::json($result);
