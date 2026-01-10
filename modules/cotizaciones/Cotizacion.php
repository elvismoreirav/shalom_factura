<?php
/**
 * SHALOM FACTURA - Modelo de Cotización
 */

namespace Shalom\Modules\Cotizaciones;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Core\Helpers;

class Cotizacion
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * Obtener todas las cotizaciones
     */
    public function getAll(array $filters = [], int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $where = ['c.empresa_id = :empresa_id', 'c.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];
        
        if (!empty($filters['cliente_id'])) {
            $where[] = 'c.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }
        
        if (!empty($filters['estado'])) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }
        
        if (!empty($filters['fecha_desde'])) {
            $where[] = 'c.fecha >= :fecha_desde';
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'c.fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(cl.razon_social LIKE :buscar OR c.numero LIKE :buscar2 OR c.asunto LIKE :buscar3)';
            $params[':buscar'] = $search;
            $params[':buscar2'] = $search;
            $params[':buscar3'] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->query("
            SELECT COUNT(*) FROM cotizaciones c
            JOIN clientes cl ON c.cliente_id = cl.id
            WHERE $whereClause
        ")->fetchColumn($params);
        
        $cotizaciones = $this->db->query("
            SELECT 
                c.*,
                cl.razon_social as cliente_nombre,
                cl.identificacion as cliente_identificacion,
                cl.email as cliente_email
            FROM cotizaciones c
            JOIN clientes cl ON c.cliente_id = cl.id
            WHERE $whereClause
            ORDER BY c.fecha DESC, c.id DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll($params);
        
        return [
            'data' => $cotizaciones,
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener cotización por ID
     */
    public function getById(int $id): ?array
    {
        $cotizacion = $this->db->query("
            SELECT 
                c.*,
                cl.razon_social as cliente_nombre,
                cl.identificacion as cliente_identificacion,
                cl.email as cliente_email,
                cl.telefono as cliente_telefono,
                cl.direccion as cliente_direccion
            FROM cotizaciones c
            JOIN clientes cl ON c.cliente_id = cl.id
            WHERE c.id = :id AND c.empresa_id = :empresa_id AND c.deleted_at IS NULL
        ")->fetch([
            ':id' => $id,
            ':empresa_id' => $this->auth->empresaId()
        ]);
        
        if (!$cotizacion) {
            return null;
        }
        
        // Obtener detalles
        $cotizacion['detalles'] = $this->getDetalles($id);
        
        return $cotizacion;
    }
    
    /**
     * Obtener detalles de cotización
     */
    public function getDetalles(int $cotizacionId): array
    {
        return $this->db->query("
            SELECT 
                cd.*,
                s.nombre as servicio_nombre
            FROM cotizacion_detalles cd
            LEFT JOIN servicios s ON cd.servicio_id = s.id
            WHERE cd.cotizacion_id = :cotizacion_id
            ORDER BY cd.orden
        ")->fetchAll([':cotizacion_id' => $cotizacionId]);
    }
    
    /**
     * Generar número de cotización
     */
    private function generarNumero(): string
    {
        $año = date('Y');
        $empresaId = $this->auth->empresaId();
        
        $ultimo = $this->db->query("
            SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) as ultimo
            FROM cotizaciones
            WHERE empresa_id = :empresa_id AND numero LIKE :prefijo
        ")->fetch([
            ':empresa_id' => $empresaId,
            ':prefijo' => "COT-$año-%"
        ]);
        
        $siguiente = ($ultimo['ultimo'] ?? 0) + 1;
        
        return sprintf("COT-%s-%05d", $año, $siguiente);
    }
    
    /**
     * Crear cotización
     */
    public function create(array $data): array
    {
        $this->db->beginTransaction();
        
        try {
            $validation = $this->validate($data);
            if (!$validation['success']) {
                throw new \Exception($validation['message']);
            }
            
            $empresaId = $this->auth->empresaId();
            $numero = $this->generarNumero();
            
            // Calcular totales
            $totales = $this->calcularTotales($data['detalles']);
            
            // Insertar cotización
            $cotizacionId = $this->db->insert('cotizaciones', [
                'uuid' => Helpers::uuid(),
                'empresa_id' => $empresaId,
                'cliente_id' => $data['cliente_id'],
                'numero' => $numero,
                'fecha' => $data['fecha'],
                'fecha_validez' => $data['fecha_validez'] ?? null,
                'asunto' => $data['asunto'] ?? null,
                'introduccion' => $data['introduccion'] ?? null,
                'condiciones' => $data['condiciones'] ?? null,
                'notas' => $data['notas'] ?? null,
                'subtotal' => $totales['subtotal'],
                'total_descuento' => $totales['total_descuento'],
                'subtotal_sin_impuestos' => $totales['subtotal_sin_impuestos'],
                'total_iva' => $totales['total_iva'],
                'total' => $totales['total'],
                'estado' => 'borrador',
                'created_by' => $this->auth->id()
            ]);
            
            // Insertar detalles
            foreach ($data['detalles'] as $index => $detalle) {
                $this->db->insert('cotizacion_detalles', [
                    'cotizacion_id' => $cotizacionId,
                    'servicio_id' => $detalle['servicio_id'] ?? null,
                    'codigo' => $detalle['codigo'],
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'subtotal' => $detalle['subtotal'],
                    'porcentaje_iva' => $detalle['porcentaje_iva'] ?? 15,
                    'valor_iva' => $detalle['valor_iva'] ?? 0,
                    'total' => $detalle['total'],
                    'orden' => $index
                ]);
            }
            
            $this->db->commit();
            
            $this->auth->logActivity('crear', 'cotizaciones', $cotizacionId, [], $data);
            
            return [
                'success' => true,
                'message' => 'Cotización creada correctamente',
                'id' => $cotizacionId,
                'numero' => $numero
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Actualizar cotización
     */
    public function update(int $id, array $data): array
    {
        $cotizacion = $this->getById($id);
        
        if (!$cotizacion) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        
        if (!in_array($cotizacion['estado'], ['borrador', 'enviada'])) {
            return ['success' => false, 'message' => 'Solo se pueden editar cotizaciones en borrador o enviadas'];
        }
        
        $this->db->beginTransaction();
        
        try {
            $validation = $this->validate($data, $id);
            if (!$validation['success']) {
                throw new \Exception($validation['message']);
            }
            
            $totales = $this->calcularTotales($data['detalles']);
            
            // Actualizar cotización
            $this->db->update('cotizaciones', [
                'cliente_id' => $data['cliente_id'],
                'fecha' => $data['fecha'],
                'fecha_validez' => $data['fecha_validez'] ?? null,
                'asunto' => $data['asunto'] ?? null,
                'introduccion' => $data['introduccion'] ?? null,
                'condiciones' => $data['condiciones'] ?? null,
                'notas' => $data['notas'] ?? null,
                'subtotal' => $totales['subtotal'],
                'total_descuento' => $totales['total_descuento'],
                'subtotal_sin_impuestos' => $totales['subtotal_sin_impuestos'],
                'total_iva' => $totales['total_iva'],
                'total' => $totales['total']
            ], 'id = :id', [':id' => $id]);
            
            // Eliminar detalles anteriores
            $this->db->query("DELETE FROM cotizacion_detalles WHERE cotizacion_id = :id")
                     ->execute([':id' => $id]);
            
            // Insertar nuevos detalles
            foreach ($data['detalles'] as $index => $detalle) {
                $this->db->insert('cotizacion_detalles', [
                    'cotizacion_id' => $id,
                    'servicio_id' => $detalle['servicio_id'] ?? null,
                    'codigo' => $detalle['codigo'],
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'subtotal' => $detalle['subtotal'],
                    'porcentaje_iva' => $detalle['porcentaje_iva'] ?? 15,
                    'valor_iva' => $detalle['valor_iva'] ?? 0,
                    'total' => $detalle['total'],
                    'orden' => $index
                ]);
            }
            
            $this->db->commit();
            
            $this->auth->logActivity('actualizar', 'cotizaciones', $id, $cotizacion, $data);
            
            return ['success' => true, 'message' => 'Cotización actualizada correctamente'];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Cambiar estado de cotización
     */
    public function cambiarEstado(int $id, string $estado, ?string $motivo = null): array
    {
        $cotizacion = $this->getById($id);
        
        if (!$cotizacion) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        
        $estadosValidos = ['borrador', 'enviada', 'aceptada', 'rechazada', 'vencida', 'facturada'];
        if (!in_array($estado, $estadosValidos)) {
            return ['success' => false, 'message' => 'Estado no válido'];
        }
        
        // Validar transiciones
        $transicionesValidas = [
            'borrador' => ['enviada'],
            'enviada' => ['aceptada', 'rechazada', 'vencida', 'borrador'],
            'aceptada' => ['facturada'],
            'rechazada' => ['borrador'],
            'vencida' => ['borrador']
        ];
        
        if (!in_array($estado, $transicionesValidas[$cotizacion['estado']] ?? [])) {
            return ['success' => false, 'message' => "No se puede cambiar de '{$cotizacion['estado']}' a '$estado'"];
        }
        
        $updateData = ['estado' => $estado];
        
        if ($estado === 'enviada') {
            $updateData['enviado_at'] = date('Y-m-d H:i:s');
            $updateData['enviado_por'] = $this->auth->id();
        } elseif ($estado === 'aceptada') {
            $updateData['aceptado_at'] = date('Y-m-d H:i:s');
        } elseif ($estado === 'rechazada') {
            $updateData['rechazado_at'] = date('Y-m-d H:i:s');
            $updateData['motivo_rechazo'] = $motivo;
        }
        
        $this->db->update('cotizaciones', $updateData, 'id = :id', [':id' => $id]);
        
        $this->auth->logActivity('actualizar_estado', 'cotizaciones', $id, 
            ['estado' => $cotizacion['estado']], 
            ['estado' => $estado, 'motivo' => $motivo]
        );
        
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    }
    
    /**
     * Convertir cotización a factura
     */
    public function convertirAFactura(int $id, array $datosFactura): array
    {
        $cotizacion = $this->getById($id);
        
        if (!$cotizacion) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        
        if ($cotizacion['estado'] !== 'aceptada') {
            return ['success' => false, 'message' => 'Solo se pueden facturar cotizaciones aceptadas'];
        }
        
        // Preparar datos para factura
        $datosFactura['cliente_id'] = $cotizacion['cliente_id'];
        $datosFactura['cotizacion_id'] = $id;
        $datosFactura['tipo_comprobante_id'] = 1; // Factura
        $datosFactura['fecha_emision'] = $datosFactura['fecha_emision'] ?? date('Y-m-d');
        
        // Convertir detalles de cotización a formato de factura
        $datosFactura['detalles'] = [];
        foreach ($cotizacion['detalles'] as $detalle) {
            $datosFactura['detalles'][] = [
                'servicio_id' => $detalle['servicio_id'],
                'codigo' => $detalle['codigo'],
                'descripcion' => $detalle['descripcion'],
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio_unitario'],
                'descuento' => $detalle['descuento'],
                'subtotal' => $detalle['subtotal'],
                'impuestos' => [[
                    'impuesto_id' => $detalle['porcentaje_iva'] == 15 ? 4 : 1,
                    'codigo' => '2',
                    'codigo_porcentaje' => $detalle['porcentaje_iva'] == 15 ? '4' : '0',
                    'tarifa' => $detalle['porcentaje_iva'],
                    'base_imponible' => $detalle['subtotal'],
                    'valor' => $detalle['valor_iva']
                ]]
            ];
        }
        
        return $datosFactura;
    }
    
    /**
     * Duplicar cotización
     */
    public function duplicar(int $id): array
    {
        $cotizacion = $this->getById($id);
        
        if (!$cotizacion) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        
        $nuevaData = [
            'cliente_id' => $cotizacion['cliente_id'],
            'fecha' => date('Y-m-d'),
            'fecha_validez' => date('Y-m-d', strtotime('+30 days')),
            'asunto' => $cotizacion['asunto'] . ' (Copia)',
            'introduccion' => $cotizacion['introduccion'],
            'condiciones' => $cotizacion['condiciones'],
            'notas' => $cotizacion['notas'],
            'detalles' => array_map(function($d) {
                return [
                    'servicio_id' => $d['servicio_id'],
                    'codigo' => $d['codigo'],
                    'descripcion' => $d['descripcion'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'descuento' => $d['descuento'],
                    'subtotal' => $d['subtotal'],
                    'porcentaje_iva' => $d['porcentaje_iva'],
                    'valor_iva' => $d['valor_iva'],
                    'total' => $d['total']
                ];
            }, $cotizacion['detalles'])
        ];
        
        return $this->create($nuevaData);
    }
    
    /**
     * Eliminar cotización
     */
    public function delete(int $id): array
    {
        $cotizacion = $this->getById($id);
        
        if (!$cotizacion) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        
        if ($cotizacion['estado'] === 'facturada') {
            return ['success' => false, 'message' => 'No se puede eliminar una cotización facturada'];
        }
        
        $this->db->softDelete('cotizaciones', $id);
        $this->auth->logActivity('eliminar', 'cotizaciones', $id, $cotizacion);
        
        return ['success' => true, 'message' => 'Cotización eliminada correctamente'];
    }
    
    /**
     * Validar datos
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        if (empty($data['cliente_id'])) {
            return ['success' => false, 'message' => 'Debe seleccionar un cliente'];
        }
        
        if (empty($data['fecha'])) {
            return ['success' => false, 'message' => 'La fecha es requerida'];
        }
        
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            return ['success' => false, 'message' => 'Debe agregar al menos un detalle'];
        }
        
        foreach ($data['detalles'] as $index => $detalle) {
            if (empty($detalle['descripcion'])) {
                return ['success' => false, 'message' => "La descripción es requerida en la línea " . ($index + 1)];
            }
            if (empty($detalle['cantidad']) || $detalle['cantidad'] <= 0) {
                return ['success' => false, 'message' => "La cantidad debe ser mayor a 0 en la línea " . ($index + 1)];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Calcular totales
     */
    private function calcularTotales(array $detalles): array
    {
        $subtotal = 0;
        $totalDescuento = 0;
        $totalIva = 0;
        
        foreach ($detalles as $detalle) {
            $subtotalLinea = $detalle['cantidad'] * $detalle['precio_unitario'];
            $descuento = $detalle['descuento'] ?? 0;
            $subtotalConDescuento = $subtotalLinea - $descuento;
            $ivaLinea = $subtotalConDescuento * (($detalle['porcentaje_iva'] ?? 15) / 100);
            
            $subtotal += $subtotalLinea;
            $totalDescuento += $descuento;
            $totalIva += $ivaLinea;
        }
        
        $subtotalSinImpuestos = $subtotal - $totalDescuento;
        
        return [
            'subtotal' => round($subtotal, 2),
            'total_descuento' => round($totalDescuento, 2),
            'subtotal_sin_impuestos' => round($subtotalSinImpuestos, 2),
            'total_iva' => round($totalIva, 2),
            'total' => round($subtotalSinImpuestos + $totalIva, 2)
        ];
    }
    
    /**
     * Obtener estadísticas
     */
    public function getEstadisticas(string $periodo = 'mes'): array
    {
        $empresaId = $this->auth->empresaId();
        
        $fechaInicio = match($periodo) {
            'dia' => date('Y-m-d'),
            'semana' => date('Y-m-d', strtotime('-7 days')),
            'mes' => date('Y-m-01'),
            'anio' => date('Y-01-01'),
            default => date('Y-m-01')
        };
        
        $stats = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'borrador' THEN 1 ELSE 0 END) as borradores,
                SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
                SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
                SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                SUM(CASE WHEN estado = 'facturada' THEN 1 ELSE 0 END) as facturadas,
                COALESCE(SUM(CASE WHEN estado = 'aceptada' THEN total ELSE 0 END), 0) as valor_aceptadas,
                COALESCE(SUM(total), 0) as valor_total
            FROM cotizaciones
            WHERE empresa_id = :empresa_id AND fecha >= :fecha_inicio AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':fecha_inicio' => $fechaInicio]);
        
        // Tasa de conversión
        $enviadas = (int)$stats['enviadas'] + (int)$stats['aceptadas'] + (int)$stats['rechazadas'] + (int)$stats['facturadas'];
        $convertidas = (int)$stats['aceptadas'] + (int)$stats['facturadas'];
        $tasaConversion = $enviadas > 0 ? ($convertidas / $enviadas) * 100 : 0;
        
        $stats['tasa_conversion'] = round($tasaConversion, 1);
        
        return $stats;
    }
}
