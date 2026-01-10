<?php
/**
 * SHALOM FACTURA - Módulo de Reportes
 */

namespace Shalom\Modules\Reportes;

use Shalom\Core\Database;
use Shalom\Core\Auth;

class Reporte
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * Reporte de ventas
     */
    public function getVentas(string $fechaDesde, string $fechaHasta, array $filtros = []): array
    {
        $empresaId = $this->auth->empresaId();
        
        $where = [
            'f.empresa_id = :empresa_id',
            'f.estado = "emitida"',
            'f.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta',
            'f.deleted_at IS NULL'
        ];
        $params = [
            ':empresa_id' => $empresaId,
            ':fecha_desde' => $fechaDesde,
            ':fecha_hasta' => $fechaHasta
        ];
        
        if (!empty($filtros['cliente_id'])) {
            $where[] = 'f.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Resumen general
        $resumen = $this->db->query("
            SELECT 
                COUNT(*) as cantidad_facturas,
                COALESCE(SUM(subtotal_sin_impuestos), 0) as subtotal,
                COALESCE(SUM(total_descuento), 0) as total_descuento,
                COALESCE(SUM(total_iva), 0) as total_iva,
                COALESCE(SUM(total), 0) as total
            FROM facturas f
            WHERE $whereClause
        ")->fetch($params);
        
        // Detalle por día
        $porDia = $this->db->query("
            SELECT 
                DATE(f.fecha_emision) as fecha,
                COUNT(*) as cantidad,
                COALESCE(SUM(f.total), 0) as total
            FROM facturas f
            WHERE $whereClause
            GROUP BY DATE(f.fecha_emision)
            ORDER BY fecha
        ")->fetchAll($params);
        
        // Por cliente
        $porCliente = $this->db->query("
            SELECT 
                c.razon_social,
                c.identificacion,
                COUNT(f.id) as cantidad,
                COALESCE(SUM(f.total), 0) as total
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE $whereClause
            GROUP BY c.id
            ORDER BY total DESC
            LIMIT 20
        ")->fetchAll($params);
        
        // Por servicio
        $porServicio = $this->db->query("
            SELECT 
                fd.descripcion,
                SUM(fd.cantidad) as cantidad,
                COALESCE(SUM(fd.precio_total_sin_impuesto), 0) as total
            FROM factura_detalles fd
            JOIN facturas f ON fd.factura_id = f.id
            WHERE $whereClause
            GROUP BY fd.codigo_principal, fd.descripcion
            ORDER BY total DESC
            LIMIT 20
        ")->fetchAll($params);
        
        // Detalle de facturas
        $facturas = $this->db->query("
            SELECT 
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero,
                f.fecha_emision,
                f.numero_autorizacion,
                c.razon_social as cliente,
                c.identificacion,
                f.subtotal_sin_impuestos as subtotal,
                f.total_iva as iva,
                f.total,
                f.estado_pago
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE $whereClause
            ORDER BY f.fecha_emision, f.secuencial
        ")->fetchAll($params);
        
        return [
            'resumen' => $resumen,
            'por_dia' => $porDia,
            'por_cliente' => $porCliente,
            'por_servicio' => $porServicio,
            'facturas' => $facturas,
            'periodo' => [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta
            ]
        ];
    }
    
    /**
     * Reporte de impuestos (para declaración)
     */
    public function getImpuestos(string $mes, string $año): array
    {
        $empresaId = $this->auth->empresaId();
        $fechaDesde = "$año-$mes-01";
        $fechaHasta = date('Y-m-t', strtotime($fechaDesde));
        
        // Ventas gravadas con IVA
        $ventasIva = $this->db->query("
            SELECT 
                ci.codigo_porcentaje,
                ci.nombre as impuesto,
                ci.porcentaje,
                COALESCE(SUM(fi.base_imponible), 0) as base_imponible,
                COALESCE(SUM(fi.valor), 0) as valor_iva
            FROM factura_impuestos fi
            JOIN facturas f ON fi.factura_id = f.id
            JOIN cat_impuestos ci ON fi.impuesto_id = ci.id
            WHERE f.empresa_id = :empresa_id 
            AND f.estado = 'emitida'
            AND f.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
            AND f.deleted_at IS NULL
            GROUP BY ci.id
            ORDER BY ci.porcentaje DESC
        ")->fetchAll([
            ':empresa_id' => $empresaId,
            ':fecha_desde' => $fechaDesde,
            ':fecha_hasta' => $fechaHasta
        ]);
        
        // Retenciones de IVA recibidas
        $retencionesIva = $this->db->query("
            SELECT 
                cr.codigo,
                cr.descripcion,
                cr.porcentaje,
                COUNT(rd.id) as cantidad,
                COALESCE(SUM(rd.base_imponible), 0) as base_imponible,
                COALESCE(SUM(rd.valor_retenido), 0) as valor_retenido
            FROM retencion_detalles rd
            JOIN retenciones_recibidas rr ON rd.retencion_id = rr.id
            JOIN cat_codigos_retencion cr ON rd.codigo_retencion_id = cr.id
            WHERE rr.empresa_id = :empresa_id 
            AND rr.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
            AND cr.tipo = 'IVA'
            AND rr.deleted_at IS NULL
            GROUP BY cr.id
            ORDER BY cr.codigo
        ")->fetchAll([
            ':empresa_id' => $empresaId,
            ':fecha_desde' => $fechaDesde,
            ':fecha_hasta' => $fechaHasta
        ]);
        
        // Retenciones de Renta recibidas
        $retencionesRenta = $this->db->query("
            SELECT 
                cr.codigo,
                cr.descripcion,
                cr.porcentaje,
                COUNT(rd.id) as cantidad,
                COALESCE(SUM(rd.base_imponible), 0) as base_imponible,
                COALESCE(SUM(rd.valor_retenido), 0) as valor_retenido
            FROM retencion_detalles rd
            JOIN retenciones_recibidas rr ON rd.retencion_id = rr.id
            JOIN cat_codigos_retencion cr ON rd.codigo_retencion_id = cr.id
            WHERE rr.empresa_id = :empresa_id 
            AND rr.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
            AND cr.tipo = 'RENTA'
            AND rr.deleted_at IS NULL
            GROUP BY cr.id
            ORDER BY cr.codigo
        ")->fetchAll([
            ':empresa_id' => $empresaId,
            ':fecha_desde' => $fechaDesde,
            ':fecha_hasta' => $fechaHasta
        ]);
        
        // Totales
        $totalVentasGravadas = 0;
        $totalIvaCobrado = 0;
        $totalVentas0 = 0;
        
        foreach ($ventasIva as $v) {
            if ($v['porcentaje'] > 0) {
                $totalVentasGravadas += $v['base_imponible'];
                $totalIvaCobrado += $v['valor_iva'];
            } else {
                $totalVentas0 += $v['base_imponible'];
            }
        }
        
        $totalRetencionesIva = array_sum(array_column($retencionesIva, 'valor_retenido'));
        $totalRetencionesRenta = array_sum(array_column($retencionesRenta, 'valor_retenido'));
        
        return [
            'periodo' => [
                'mes' => $mes,
                'año' => $año,
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta
            ],
            'ventas' => [
                'detalle' => $ventasIva,
                'total_gravadas' => $totalVentasGravadas,
                'total_0' => $totalVentas0,
                'total_iva' => $totalIvaCobrado
            ],
            'retenciones_iva' => [
                'detalle' => $retencionesIva,
                'total' => $totalRetencionesIva
            ],
            'retenciones_renta' => [
                'detalle' => $retencionesRenta,
                'total' => $totalRetencionesRenta
            ],
            'resumen' => [
                'iva_cobrado' => $totalIvaCobrado,
                'iva_retenido' => $totalRetencionesIva,
                'iva_a_pagar' => $totalIvaCobrado - $totalRetencionesIva
            ]
        ];
    }
    
    /**
     * Reporte de cuentas por cobrar
     */
    public function getCuentasPorCobrar(): array
    {
        $empresaId = $this->auth->empresaId();
        $hoy = date('Y-m-d');
        
        // Por cliente
        $porCliente = $this->db->query("
            SELECT 
                c.id as cliente_id,
                c.razon_social,
                c.identificacion,
                c.email,
                c.telefono,
                COUNT(f.id) as facturas_pendientes,
                COALESCE(SUM(f.total), 0) as total_facturado,
                COALESCE(SUM(f.total), 0) - COALESCE(SUM((
                    SELECT COALESCE(SUM(pf.monto), 0) 
                    FROM pago_facturas pf 
                    JOIN pagos p ON pf.pago_id = p.id 
                    WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
                )), 0) as saldo_pendiente,
                MIN(f.fecha_vencimiento) as proxima_vencimiento,
                SUM(CASE WHEN f.fecha_vencimiento < :hoy THEN 1 ELSE 0 END) as facturas_vencidas
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE f.empresa_id = :empresa_id 
            AND f.estado = 'emitida'
            AND f.estado_pago IN ('pendiente', 'parcial')
            AND f.deleted_at IS NULL
            GROUP BY c.id
            HAVING saldo_pendiente > 0
            ORDER BY saldo_pendiente DESC
        ")->fetchAll([':empresa_id' => $empresaId, ':hoy' => $hoy]);
        
        // Antigüedad de saldos
        $antiguedad = $this->db->query("
            SELECT 
                CASE 
                    WHEN DATEDIFF(:hoy, f.fecha_vencimiento) <= 0 THEN 'vigente'
                    WHEN DATEDIFF(:hoy2, f.fecha_vencimiento) BETWEEN 1 AND 30 THEN '1-30'
                    WHEN DATEDIFF(:hoy3, f.fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(:hoy4, f.fecha_vencimiento) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '90+'
                END as rango,
                COUNT(*) as cantidad,
                COALESCE(SUM(f.total - COALESCE((
                    SELECT SUM(pf.monto) FROM pago_facturas pf JOIN pagos p ON pf.pago_id = p.id 
                    WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
                ), 0)), 0) as saldo
            FROM facturas f
            WHERE f.empresa_id = :empresa_id
            AND f.estado = 'emitida'
            AND f.estado_pago IN ('pendiente', 'parcial')
            AND f.deleted_at IS NULL
            GROUP BY rango
        ")->fetchAll([
            ':empresa_id' => $empresaId, 
            ':hoy' => $hoy, ':hoy2' => $hoy, ':hoy3' => $hoy, ':hoy4' => $hoy
        ]);
        
        // Totales
        $totalPendiente = array_sum(array_column($porCliente, 'saldo_pendiente'));
        $totalVencido = array_sum(array_filter(array_column($antiguedad, 'saldo'), function($k) use ($antiguedad) {
            return $antiguedad[$k]['rango'] !== 'vigente';
        }, ARRAY_FILTER_USE_KEY));
        
        return [
            'por_cliente' => $porCliente,
            'antiguedad' => $antiguedad,
            'resumen' => [
                'total_pendiente' => $totalPendiente,
                'total_vencido' => $totalVencido,
                'cantidad_clientes' => count($porCliente)
            ]
        ];
    }
    
    /**
     * Reporte de productos/servicios más vendidos
     */
    public function getProductosMasVendidos(string $fechaDesde, string $fechaHasta, int $limite = 20): array
    {
        $empresaId = $this->auth->empresaId();
        
        return $this->db->query("
            SELECT 
                fd.codigo_principal as codigo,
                fd.descripcion,
                SUM(fd.cantidad) as cantidad_vendida,
                COALESCE(SUM(fd.precio_total_sin_impuesto), 0) as total_vendido,
                COUNT(DISTINCT f.id) as facturas,
                COUNT(DISTINCT f.cliente_id) as clientes
            FROM factura_detalles fd
            JOIN facturas f ON fd.factura_id = f.id
            WHERE f.empresa_id = :empresa_id 
            AND f.estado = 'emitida'
            AND f.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
            AND f.deleted_at IS NULL
            GROUP BY fd.codigo_principal, fd.descripcion
            ORDER BY total_vendido DESC
            LIMIT $limite
        ")->fetchAll([
            ':empresa_id' => $empresaId,
            ':fecha_desde' => $fechaDesde,
            ':fecha_hasta' => $fechaHasta
        ]);
    }
    
    /**
     * Dashboard ejecutivo
     */
    public function getDashboardEjecutivo(): array
    {
        $empresaId = $this->auth->empresaId();
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01');
        $inicioMesAnterior = date('Y-m-01', strtotime('-1 month'));
        $finMesAnterior = date('Y-m-t', strtotime('-1 month'));
        
        // Ventas del mes actual
        $ventasMes = $this->db->query("
            SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as cantidad
            FROM facturas
            WHERE empresa_id = :empresa_id AND estado = 'emitida' 
            AND fecha_emision >= :inicio AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':inicio' => $inicioMes]);
        
        // Ventas del mes anterior
        $ventasMesAnterior = $this->db->query("
            SELECT COALESCE(SUM(total), 0) as total
            FROM facturas
            WHERE empresa_id = :empresa_id AND estado = 'emitida' 
            AND fecha_emision BETWEEN :inicio AND :fin AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':inicio' => $inicioMesAnterior, ':fin' => $finMesAnterior]);
        
        // Variación
        $variacion = $ventasMesAnterior['total'] > 0 
            ? (($ventasMes['total'] - $ventasMesAnterior['total']) / $ventasMesAnterior['total']) * 100 
            : 0;
        
        // Cobros del mes
        $cobrosMes = $this->db->query("
            SELECT COALESCE(SUM(monto), 0) as total
            FROM pagos
            WHERE empresa_id = :empresa_id AND estado = 'confirmado' 
            AND fecha >= :inicio AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':inicio' => $inicioMes]);
        
        // Por cobrar
        $porCobrar = $this->db->query("
            SELECT COALESCE(SUM(f.total), 0) - COALESCE(SUM((
                SELECT SUM(pf.monto) FROM pago_facturas pf JOIN pagos p ON pf.pago_id = p.id 
                WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
            )), 0) as total
            FROM facturas f
            WHERE f.empresa_id = :empresa_id AND f.estado = 'emitida' 
            AND f.estado_pago IN ('pendiente', 'parcial') AND f.deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId]);
        
        // Vencido
        $vencido = $this->db->query("
            SELECT COALESCE(SUM(f.total), 0) - COALESCE(SUM((
                SELECT SUM(pf.monto) FROM pago_facturas pf JOIN pagos p ON pf.pago_id = p.id 
                WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
            )), 0) as total
            FROM facturas f
            WHERE f.empresa_id = :empresa_id AND f.estado = 'emitida' 
            AND f.estado_pago IN ('pendiente', 'parcial') 
            AND f.fecha_vencimiento < :hoy AND f.deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId, ':hoy' => $hoy]);
        
        // Cotizaciones pendientes
        $cotizacionesPendientes = $this->db->query("
            SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as valor
            FROM cotizaciones
            WHERE empresa_id = :empresa_id AND estado IN ('borrador', 'enviada') AND deleted_at IS NULL
        ")->fetch([':empresa_id' => $empresaId]);
        
        // Ventas últimos 12 meses
        $ventas12Meses = $this->db->query("
            SELECT 
                DATE_FORMAT(fecha_emision, '%Y-%m') as mes,
                COALESCE(SUM(total), 0) as total
            FROM facturas
            WHERE empresa_id = :empresa_id AND estado = 'emitida' 
            AND fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            AND deleted_at IS NULL
            GROUP BY mes
            ORDER BY mes
        ")->fetchAll([':empresa_id' => $empresaId]);
        
        return [
            'ventas_mes' => [
                'total' => (float)$ventasMes['total'],
                'cantidad' => (int)$ventasMes['cantidad'],
                'variacion' => round($variacion, 1)
            ],
            'cobros_mes' => (float)$cobrosMes['total'],
            'por_cobrar' => (float)$porCobrar['total'],
            'vencido' => (float)$vencido['total'],
            'cotizaciones_pendientes' => [
                'cantidad' => (int)$cotizacionesPendientes['cantidad'],
                'valor' => (float)$cotizacionesPendientes['valor']
            ],
            'ventas_12_meses' => $ventas12Meses
        ];
    }
}
