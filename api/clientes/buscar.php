<?php
/**
 * SHALOM FACTURA - API Buscar Clientes
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;

// Verificar autenticación
if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    Helpers::json(['success' => true, 'data' => []]);
}

$db = db();
$empresaId = auth()->empresaId();
$search = '%' . $db->escapeLike($query) . '%';

$clientes = $db->query("
    SELECT 
        c.id,
        c.uuid,
        c.razon_social,
        c.identificacion,
        c.email,
        c.telefono,
        c.direccion,
        ti.nombre as tipo_identificacion
    FROM clientes c
    JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
    WHERE c.empresa_id = :empresa_id 
    AND c.estado = 'activo'
    AND c.deleted_at IS NULL
    AND (
        c.razon_social LIKE :search1 
        OR c.identificacion LIKE :search2 
        OR c.email LIKE :search3
    )
    ORDER BY c.razon_social
    LIMIT 10
")->fetchAll([
    ':empresa_id' => $empresaId,
    ':search1' => $search,
    ':search2' => $search,
    ':search3' => $search
]);

Helpers::json(['success' => true, 'data' => $clientes]);
