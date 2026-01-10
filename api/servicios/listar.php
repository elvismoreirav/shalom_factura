<?php
/**
 * SHALOM FACTURA - API Listar Servicios
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shalom\Core\Helpers;

// Verificar autenticación
if (!auth()->check()) {
    Helpers::json(['success' => false, 'message' => 'No autorizado'], 401);
}

$db = db();
$empresaId = auth()->empresaId();

$servicios = $db->query("
    SELECT 
        s.id,
        s.uuid,
        s.codigo,
        s.nombre,
        s.descripcion,
        s.precio_unitario,
        s.tipo,
        s.unidad_medida,
        cs.nombre as categoria_nombre,
        ci.id as impuesto_id,
        ci.nombre as impuesto_nombre,
        ci.codigo as codigo_iva,
        ci.codigo_porcentaje as codigo_porcentaje_iva,
        ci.porcentaje as porcentaje_iva
    FROM servicios s
    LEFT JOIN categorias_servicio cs ON s.categoria_id = cs.id
    JOIN cat_impuestos ci ON s.impuesto_id = ci.id
    WHERE s.empresa_id = :empresa_id 
    AND s.activo = 1
    AND s.deleted_at IS NULL
    ORDER BY s.nombre
")->fetchAll([':empresa_id' => $empresaId]);

Helpers::json(['success' => true, 'data' => $servicios]);
