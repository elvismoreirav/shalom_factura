<?php
/**
 * SHALOM FACTURA - Modelo de Pago
 */

namespace Shalom\Modules\Pagos;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Core\Helpers;

class Pago
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * Obtener todos los pagos
     */
    public function getAll(array $filters = [], int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $where = ['p.empresa_id = :empresa_id', 'p.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];
        
        if (!empty($filters['cliente_id'])) {
            $where[] = 'p.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }
        
        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }
        
        if (!empty($filters['forma_pago_id'])) {
            $where[] = 'p.forma_pago_id = :forma_pago_id';
            $params[':forma_pago_id'] = $filters['forma_pago_id'];
        }
        
        if (!empty($filters['fecha_desde'])) {
            $where[] = 'p.fecha >= :fecha_desde';
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'p.fecha <= :fecha_hasta';
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(cl.razon_social LIKE :buscar OR p.numero_recibo LIKE :buscar2 OR p.referencia LIKE :buscar3)';
            $params[':buscar'] = $search;
            $params[':buscar2'] = $search;
            $params[':buscar3'] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->query("
            SELECT COUNT(*) FROM pagos p
            JOIN clientes cl ON p.cliente_id = cl.id
            WHERE $whereClause
        ")->fetchColumn($params);
        
        $pagos = $this->db->query("
            SELECT 
                p.*,
                cl.razon_social as cliente_nombre,
                cl.identificacion as cliente_identificacion,
                fp.nombre as forma_pago_nombre,
                (SELECT COUNT(*) FROM pago_facturas pf WHERE pf.pago_id = p.id) as facturas_count
            FROM pagos p
            JOIN clientes cl ON p.cliente_id = cl.id
            JOIN cat_formas_pago fp ON p.forma_pago_id = fp.id
            WHERE $whereClause
            ORDER BY p.fecha DESC, p.id DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll($params);
        
        return [
            'data' => $pagos,
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener pago por ID
     */
    public function getById(int $id): ?array
    {
        $pago = $this->db->query("
            SELECT 
                p.*,
                cl.razon_social as cliente_nombre,
                cl.identificacion as cliente_identificacion,
                cl.email as cliente_email,
                fp.nombre as forma_pago_nombre
            FROM pagos p
            JOIN clientes cl ON p.cliente_id = cl.id
            JOIN cat_formas_pago fp ON p.forma_pago_id = fp.id
            WHERE p.id = :id AND p.empresa_id = :empresa_id AND p.deleted_at IS NULL
        ")->fetch([
            ':id' => $id,
            ':empresa_id' => $this->auth->empresaId()
        ]);
        
        if (!$pago) {
            return null;
        }
        
        // Obtener facturas aplicadas
        $pago['facturas'] = $this->getFacturasAplicadas($id);
        
        return $pago;
    }
    
    /**
     * Obtener facturas aplicadas a un pago
     */
    public function getFacturasAplicadas(int $pagoId): array
    {
        return $this->db->query("
            SELECT 
                pf.*,
                f.secuencial,
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero_factura,
                f.total as factura_total,
                f.fecha_emision
            FROM pago_facturas pf
            JOIN facturas f ON pf.factura_id = f.id
            JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE pf.pago_id = :pago_id
        ")->fetchAll([':pago_id' => $pagoId]);
    }
    
    /**
     * Obtener facturas pendientes de un cliente
     */
    public function getFacturasPendientes(int $clienteId): array
    {
        return $this->db->query("
            SELECT 
                f.id,
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero,
                f.fecha_emision,
                f.fecha_vencimiento,
                f.total,
                f.estado_pago,
                f.total - COALESCE((
                    SELECT SUM(pf.monto) 
                    FROM pago_facturas pf 
                    JOIN pagos p ON pf.pago_id = p.id 
                    WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
                ), 0) as saldo_pendiente
            FROM facturas f
            JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE f.cliente_id = :cliente_id 
            AND f.empresa_id = :empresa_id
            AND f.estado = 'emitida'
            AND f.estado_pago IN ('pendiente', 'parcial')
            AND f.deleted_at IS NULL
            HAVING saldo_pendiente > 0
            ORDER BY f.fecha_emision ASC
        ")->fetchAll([
            ':cliente_id' => $clienteId,
            ':empresa_id' => $this->auth->empresaId()
        ]);
    }
    
    /**
     * Generar número de recibo
     */
    private function generarNumeroRecibo(): string
    {
        $año = date('Y');
        $empresaId = $this->auth->empresaId();
        
        $ultimo = $this->db->query("
            SELECT MAX(CAST(SUBSTRING_INDEX(numero_recibo, '-', -1) AS UNSIGNED)) as ultimo
            FROM pagos
            WHERE empresa_id = :empresa_id AND numero_recibo LIKE :prefijo
        ")->fetch([
            ':empresa_id' => $empresaId,
            ':prefijo' => "REC-$año-%"
        ]);
        
        $siguiente = ($ultimo['ultimo'] ?? 0) + 1;
        
        return sprintf("REC-%s-%05d", $año, $siguiente);
    }
    
    /**
     * Registrar pago
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
            $numeroRecibo = $this->generarNumeroRecibo();
            
            // Verificar que el monto no exceda el total de facturas
            $totalFacturas = array_sum(array_column($data['facturas'] ?? [], 'monto'));
            if (abs($totalFacturas - $data['monto']) > 0.01) {
                throw new \Exception('El monto del pago no coincide con la suma aplicada a las facturas');
            }
            
            // Insertar pago
            $pagoId = $this->db->insert('pagos', [
                'uuid' => Helpers::uuid(),
                'empresa_id' => $empresaId,
                'cliente_id' => $data['cliente_id'],
                'forma_pago_id' => $data['forma_pago_id'],
                'numero_recibo' => $numeroRecibo,
                'fecha' => $data['fecha'],
                'monto' => $data['monto'],
                'referencia' => $data['referencia'] ?? null,
                'banco' => $data['banco'] ?? null,
                'numero_cheque' => $data['numero_cheque'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'confirmado',
                'created_by' => $this->auth->id()
            ]);
            
            // Aplicar a facturas
            foreach ($data['facturas'] as $factura) {
                $this->db->insert('pago_facturas', [
                    'pago_id' => $pagoId,
                    'factura_id' => $factura['factura_id'],
                    'monto' => $factura['monto']
                ]);
                
                // Actualizar estado de la factura
                $this->actualizarEstadoFactura($factura['factura_id']);
            }
            
            $this->db->commit();
            
            $this->auth->logActivity('crear', 'pagos', $pagoId, [], $data);
            
            return [
                'success' => true,
                'message' => 'Pago registrado correctamente',
                'id' => $pagoId,
                'numero' => $numeroRecibo
            ];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Actualizar estado de factura según pagos
     */
    private function actualizarEstadoFactura(int $facturaId): void
    {
        $factura = $this->db->query("
            SELECT total FROM facturas WHERE id = :id
        ")->fetch([':id' => $facturaId]);
        
        $totalPagado = $this->db->query("
            SELECT COALESCE(SUM(pf.monto), 0)
            FROM pago_facturas pf
            JOIN pagos p ON pf.pago_id = p.id
            WHERE pf.factura_id = :factura_id AND p.estado = 'confirmado'
        ")->fetchColumn([':factura_id' => $facturaId]);
        
        $estado = 'pendiente';
        if ($totalPagado >= $factura['total']) {
            $estado = 'pagado';
        } elseif ($totalPagado > 0) {
            $estado = 'parcial';
        }
        
        $this->db->update('facturas', [
            'estado_pago' => $estado
        ], 'id = :id', [':id' => $facturaId]);
    }
    
    /**
     * Anular pago
     */
    public function anular(int $id, string $motivo): array
    {
        $pago = $this->getById($id);
        
        if (!$pago) {
            return ['success' => false, 'message' => 'Pago no encontrado'];
        }
        
        if ($pago['estado'] === 'anulado') {
            return ['success' => false, 'message' => 'El pago ya está anulado'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // Anular pago
            $this->db->update('pagos', [
                'estado' => 'anulado',
                'anulado_at' => date('Y-m-d H:i:s'),
                'anulado_por' => $this->auth->id(),
                'motivo_anulacion' => $motivo
            ], 'id = :id', [':id' => $id]);
            
            // Recalcular estados de facturas
            foreach ($pago['facturas'] as $factura) {
                $this->actualizarEstadoFactura($factura['factura_id']);
            }
            
            $this->db->commit();
            
            $this->auth->logActivity('anular', 'pagos', $id, $pago, ['motivo' => $motivo]);
            
            return ['success' => true, 'message' => 'Pago anulado correctamente'];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error al anular el pago'];
        }
    }
    
    /**
     * Validar datos
     */
    private function validate(array $data): array
    {
        if (empty($data['cliente_id'])) {
            return ['success' => false, 'message' => 'Debe seleccionar un cliente'];
        }
        
        if (empty($data['forma_pago_id'])) {
            return ['success' => false, 'message' => 'Debe seleccionar una forma de pago'];
        }
        
        if (empty($data['fecha'])) {
            return ['success' => false, 'message' => 'La fecha es requerida'];
        }
        
        if (empty($data['monto']) || $data['monto'] <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a 0'];
        }
        
        if (empty($data['facturas']) || !is_array($data['facturas'])) {
            return ['success' => false, 'message' => 'Debe aplicar el pago a al menos una factura'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtener formas de pago
     */
    public function getFormasPago(): array
    {
        return $this->db->select('cat_formas_pago', ['*'], 'activo = 1', [], 'nombre');
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
                COUNT(*) as total_pagos,
                COALESCE(SUM(CASE WHEN estado = 'confirmado' THEN monto ELSE 0 END), 0) as total_cobrado,
                COALESCE(SUM(CASE WHEN estado = 'anulado' THEN monto ELSE 0 END), 0) as total_anulado
            FROM pagos
            WHERE empresa_id = :empresa_id AND fecha >= :fecha_inicio AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':fecha_inicio' => $fechaInicio]);
        
        // Por forma de pago
        $porFormaPago = $this->db->query("
            SELECT fp.nombre, COUNT(p.id) as cantidad, COALESCE(SUM(p.monto), 0) as total
            FROM pagos p
            JOIN cat_formas_pago fp ON p.forma_pago_id = fp.id
            WHERE p.empresa_id = :empresa_id AND p.fecha >= :fecha_inicio 
            AND p.estado = 'confirmado' AND p.deleted_at IS NULL
            GROUP BY fp.id
            ORDER BY total DESC
        ")->fetchAll([':empresa_id' => $empresaId, ':fecha_inicio' => $fechaInicio]);
        
        $stats['por_forma_pago'] = $porFormaPago;
        
        return $stats;
    }
    
    /**
     * Reporte de cuentas por cobrar
     */
    public function getCuentasPorCobrar(): array
    {
        $empresaId = $this->auth->empresaId();
        
        return $this->db->query("
            SELECT 
                cl.id as cliente_id,
                cl.razon_social,
                cl.identificacion,
                COUNT(f.id) as facturas_pendientes,
                COALESCE(SUM(f.total), 0) as total_facturado,
                COALESCE(SUM(f.total), 0) - COALESCE(SUM((
                    SELECT COALESCE(SUM(pf.monto), 0) 
                    FROM pago_facturas pf 
                    JOIN pagos p ON pf.pago_id = p.id 
                    WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
                )), 0) as saldo_pendiente,
                MIN(f.fecha_vencimiento) as proxima_vencimiento,
                SUM(CASE WHEN f.fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) as facturas_vencidas
            FROM facturas f
            JOIN clientes cl ON f.cliente_id = cl.id
            WHERE f.empresa_id = :empresa_id 
            AND f.estado = 'emitida'
            AND f.estado_pago IN ('pendiente', 'parcial')
            AND f.deleted_at IS NULL
            GROUP BY cl.id
            HAVING saldo_pendiente > 0
            ORDER BY saldo_pendiente DESC
        ")->fetchAll([':empresa_id' => $empresaId]);
    }
}
