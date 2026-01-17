<?php
/**
 * SHALOM FACTURA - Modelo de Factura
 * Gestión de facturas electrónicas con integración SRI completa
 * Versión: 2.6 - FIX: Mensaje nunca vacío en respuesta de emisión
 * 
 * CAMBIOS:
 * - emitir(): Fallback para mensaje vacío
 * - Nuevo método: obtenerMensajeEstadoSri()
 * - prepararDatosXml(): Corregido mapeo de campos
 */

namespace Shalom\Modules\Facturas;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Core\Helpers;
use Shalom\Modules\Sri\ClaveAcceso;
use Shalom\Modules\Sri\XmlGenerator;
use Shalom\Modules\Sri\FirmaElectronica;
use Shalom\Modules\Sri\SriService;

class Factura
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    public function getAll(array $filters = [], int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $where = ['f.empresa_id = :empresa_id', 'f.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];
        
        if (!empty($filters['cliente_id'])) { $where[] = 'f.cliente_id = :cliente_id'; $params[':cliente_id'] = $filters['cliente_id']; }
        if (!empty($filters['estado'])) { $where[] = 'f.estado = :estado'; $params[':estado'] = $filters['estado']; }
        if (!empty($filters['estado_sri'])) { $where[] = 'f.estado_sri = :estado_sri'; $params[':estado_sri'] = $filters['estado_sri']; }
        if (!empty($filters['estado_pago'])) { $where[] = 'f.estado_pago = :estado_pago'; $params[':estado_pago'] = $filters['estado_pago']; }
        if (!empty($filters['fecha_desde'])) { $where[] = 'f.fecha_emision >= :fecha_desde'; $params[':fecha_desde'] = $filters['fecha_desde']; }
        if (!empty($filters['fecha_hasta'])) { $where[] = 'f.fecha_emision <= :fecha_hasta'; $params[':fecha_hasta'] = $filters['fecha_hasta']; }
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(c.razon_social LIKE :buscar OR c.identificacion LIKE :buscar2 OR f.numero_autorizacion LIKE :buscar3)';
            $params[':buscar'] = $search; $params[':buscar2'] = $search; $params[':buscar3'] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        $total = $this->db->query("SELECT COUNT(*) FROM facturas f JOIN clientes c ON f.cliente_id = c.id WHERE $whereClause")->fetchColumn($params);
        $facturas = $this->db->query("
            SELECT f.*, c.razon_social as cliente_nombre, c.identificacion as cliente_identificacion, tc.nombre as tipo_comprobante_nombre,
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero_documento
            FROM facturas f JOIN clientes c ON f.cliente_id = c.id JOIN cat_tipos_comprobante tc ON f.tipo_comprobante_id = tc.id
            JOIN establecimientos e ON f.establecimiento_id = e.id JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE $whereClause ORDER BY f.fecha_emision DESC, f.secuencial DESC LIMIT $limit OFFSET $offset
        ")->fetchAll($params);
        
        return ['data' => $facturas, 'total' => (int) $total, 'pages' => ceil($total / $limit)];
    }

    public function getById(int $id): ?array
    {
        $factura = $this->db->query("
            SELECT f.*, c.razon_social as cliente_nombre, c.identificacion as cliente_identificacion,
                c.email as cliente_email, c.direccion as cliente_direccion, c.telefono as cliente_telefono,
                ti.nombre as tipo_identificacion_nombre, tc.nombre as tipo_comprobante_nombre,
                e.codigo as establecimiento_codigo, e.nombre as establecimiento_nombre, e.direccion as establecimiento_direccion,
                pe.codigo as punto_emision_codigo, CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero_documento
            FROM facturas f JOIN clientes c ON f.cliente_id = c.id JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            JOIN cat_tipos_comprobante tc ON f.tipo_comprobante_id = tc.id JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE f.id = :id AND f.empresa_id = :empresa_id AND f.deleted_at IS NULL
        ")->fetch([':id' => $id, ':empresa_id' => $this->auth->empresaId()]);
        
        if (!$factura) return null;
        $factura['detalles'] = $this->getDetalles($id);
        $factura['impuestos'] = $this->getImpuestos($id);
        $factura['formas_pago'] = $this->getFormasPago($id);
        $factura['info_adicional'] = $this->getInfoAdicional($id);
        $factura['pagos'] = $this->getPagosAplicados($id);
        return $factura;
    }

    public function getDetalles(int $facturaId): array {
        return $this->db->query("SELECT fd.*, s.nombre as servicio_nombre FROM factura_detalles fd LEFT JOIN servicios s ON fd.servicio_id = s.id WHERE fd.factura_id = :factura_id ORDER BY fd.orden")->fetchAll([':factura_id' => $facturaId]);
    }
    public function getImpuestos(int $facturaId): array {
        return $this->db->query("SELECT fi.*, ci.nombre as impuesto_nombre, ci.codigo as impuesto_codigo, ci.codigo_porcentaje FROM factura_impuestos fi JOIN cat_impuestos ci ON fi.impuesto_id = ci.id WHERE fi.factura_id = :factura_id")->fetchAll([':factura_id' => $facturaId]);
    }
    public function getFormasPago(int $facturaId): array {
        return $this->db->query("SELECT fpf.*, fp.nombre as forma_pago_nombre, fp.codigo as forma_pago_codigo FROM factura_pagos_forma fpf JOIN cat_formas_pago fp ON fpf.forma_pago_id = fp.id WHERE fpf.factura_id = :factura_id")->fetchAll([':factura_id' => $facturaId]);
    }
    public function getInfoAdicional(int $facturaId): array {
        return $this->db->query("SELECT * FROM factura_info_adicional WHERE factura_id = :factura_id ORDER BY orden")->fetchAll([':factura_id' => $facturaId]);
    }
    public function getPagosAplicados(int $facturaId): array {
        return $this->db->query("SELECT pf.*, p.numero_recibo, p.fecha as pago_fecha, fp.nombre as forma_pago_nombre FROM pago_facturas pf JOIN pagos p ON pf.pago_id = p.id JOIN cat_formas_pago fp ON p.forma_pago_id = fp.id WHERE pf.factura_id = :factura_id AND p.estado = 'confirmado'")->fetchAll([':factura_id' => $facturaId]);
    }

    public function create(array $data): array
    {
        $this->db->beginTransaction();
        try {
            $empresa = $this->db->query("SELECT * FROM empresas WHERE id = :id")->fetch([':id' => $this->auth->empresaId()]);
            $establecimiento = $this->db->query("SELECT * FROM establecimientos WHERE id = :id")->fetch([':id' => $data['establecimiento_id']]);
            $puntoEmision = $this->db->query("SELECT * FROM puntos_emision WHERE id = :id")->fetch([':id' => $data['punto_emision_id']]);
            
            $tipoComprobanteId = $data['tipo_comprobante_id'] ?? 1;
            $secuencial = $this->getNextSecuencial($data['punto_emision_id'], $tipoComprobanteId);
            $totales = $this->calcularTotales($data['detalles']);
            $uuid = $this->generateUuid();
            
            $facturaId = $this->db->insert('facturas', [
                'uuid' => $uuid, 'empresa_id' => $this->auth->empresaId(), 'establecimiento_id' => $data['establecimiento_id'],
                'punto_emision_id' => $data['punto_emision_id'], 'cliente_id' => $data['cliente_id'], 'tipo_comprobante_id' => $tipoComprobanteId,
                'cotizacion_id' => $data['cotizacion_id'] ?? null, 'secuencial' => $secuencial, 'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null, 'subtotal_sin_impuestos' => $totales['subtotal_sin_impuestos'],
                'total_descuento' => $totales['total_descuento'], 'subtotal_iva' => $totales['subtotal_iva'], 'subtotal_iva_0' => $totales['subtotal_iva_0'],
                'total_iva' => $totales['total_iva'], 'total_ice' => $totales['total_ice'], 'total' => $totales['total'],
                'estado' => 'borrador', 'estado_sri' => 'pendiente', 'estado_pago' => 'pendiente', 'created_by' => $this->auth->id()
            ]);
            
            $orden = 1;
            foreach ($data['detalles'] as $detalle) {
                $cantidad = (float)($detalle['cantidad'] ?? 1);
                $precioUnitario = (float)($detalle['precio_unitario'] ?? 0);
                $descuento = (float)($detalle['descuento'] ?? 0);
                
                // CORREGIDO: Calcular subtotal siempre, usar valor del front solo si es > 0
                $subtotalCalculado = round($cantidad * $precioUnitario - $descuento, 2);
                $subtotalFromInput = (float)($detalle['subtotal'] ?? 0);
                $subtotal = $subtotalFromInput > 0 ? $subtotalFromInput : $subtotalCalculado;
                
                // precio_total_sin_impuesto debe ser igual al subtotal
                $precioTotalFromInput = (float)($detalle['precio_total_sin_impuesto'] ?? 0);
                $precioTotalSinImpuesto = $precioTotalFromInput > 0 ? $precioTotalFromInput : $subtotal;
                
                $codigoPrincipal = $detalle['codigo'] ?? $detalle['codigo_principal'] ?? 'SRV' . str_pad($orden, 6, '0', STR_PAD_LEFT);
                
                // Calcular impuesto si viene en el detalle
                $impuestoValor = 0;
                if (!empty($detalle['impuestos'][0]['valor'])) {
                    $impuestoValor = (float)$detalle['impuestos'][0]['valor'];
                }
                $total = $subtotal + $impuestoValor;
                
                $detalleId = $this->db->insert('factura_detalles', [
                    'factura_id' => $facturaId,
                    'servicio_id' => $detalle['servicio_id'] ?? null,
                    'codigo_principal' => $codigoPrincipal,
                    'codigo_auxiliar' => $detalle['codigo_auxiliar'] ?? '',
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                    'precio_total_sin_impuesto' => $precioTotalSinImpuesto,
                    'impuesto_valor' => $impuestoValor,
                    'total' => $total,
                    'orden' => $orden++
                ]);
                
                if (!empty($detalle['impuestos'])) {
                    foreach ($detalle['impuestos'] as $impuesto) {
                        $this->db->insert('factura_detalle_impuestos', [
                            'factura_detalle_id' => $detalleId, 'impuesto_id' => $impuesto['impuesto_id'], 'codigo' => $impuesto['codigo'],
                            'codigo_porcentaje' => $impuesto['codigo_porcentaje'], 'tarifa' => $impuesto['tarifa'] ?? 15,
                            'base_imponible' => $impuesto['base_imponible'], 'valor' => $impuesto['valor']
                        ]);
                    }
                }
            }
            
            foreach ($totales['impuestos'] as $impuesto) {
                $this->db->insert('factura_impuestos', [
                    'factura_id' => $facturaId,
                    'impuesto_id' => $impuesto['impuesto_id'],
                    'codigo' => $impuesto['codigo'],
                    'codigo_porcentaje' => $impuesto['codigo_porcentaje'],
                    'base_imponible' => $impuesto['base_imponible'],
                    'valor' => $impuesto['valor']
                ]);
            }
            
            if (!empty($data['formas_pago'])) {
                foreach ($data['formas_pago'] as $pago) {
                    $this->db->insert('factura_pagos_forma', ['factura_id' => $facturaId, 'forma_pago_id' => $pago['forma_pago_id'], 'total' => $pago['total'], 'plazo' => $pago['plazo'] ?? 0, 'unidad_tiempo' => $pago['unidad_tiempo'] ?? 'dias']);
                }
            }
            
            if (!empty($data['info_adicional'])) {
                $ordenInfo = 1;
                foreach ($data['info_adicional'] as $info) {
                    if (!empty($info['nombre']) && !empty($info['valor'])) {
                        $this->db->insert('factura_info_adicional', ['factura_id' => $facturaId, 'nombre' => $info['nombre'], 'valor' => $info['valor'], 'orden' => $ordenInfo++]);
                    }
                }
            }
            
            $this->updateSecuencial($data['punto_emision_id'], $tipoComprobanteId, $secuencial);
            $numero = sprintf('%s-%s-%s', $establecimiento['codigo'], $puntoEmision['codigo'], str_pad($secuencial, 9, '0', STR_PAD_LEFT));
            $this->db->commit();
            try { $this->auth->logActivity('crear', 'facturas', $facturaId); } catch (\Exception $e) {}
            return ['success' => true, 'message' => 'Factura creada correctamente', 'id' => $facturaId, 'numero' => $numero];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error al crear la factura: ' . $e->getMessage()];
        }
    }

    private function calcularTotales(array $detalles): array
    {
        $subtotalSinImpuestos = 0; $totalDescuento = 0; $subtotalIva = 0; $subtotalIva0 = 0; $impuestosAgrupados = [];
        foreach ($detalles as $detalle) {
            $subtotalLinea = $detalle['cantidad'] * $detalle['precio_unitario'];
            $descuento = $detalle['descuento'] ?? 0;
            $subtotalConDescuento = $subtotalLinea - $descuento;
            $subtotalSinImpuestos += $subtotalConDescuento;
            $totalDescuento += $descuento;
            foreach ($detalle['impuestos'] as $impuesto) {
                $key = $impuesto['codigo'] . '-' . $impuesto['codigo_porcentaje'];
                if (!isset($impuestosAgrupados[$key])) {
                    $impuestosAgrupados[$key] = ['impuesto_id' => $impuesto['impuesto_id'], 'codigo' => $impuesto['codigo'], 'codigo_porcentaje' => $impuesto['codigo_porcentaje'], 'base_imponible' => 0, 'valor' => 0];
                }
                $impuestosAgrupados[$key]['base_imponible'] += $impuesto['base_imponible'];
                $impuestosAgrupados[$key]['valor'] += $impuesto['valor'];
                if ($impuesto['codigo'] === '2') {
                    if ($impuesto['tarifa'] > 0) $subtotalIva += $impuesto['base_imponible'];
                    else $subtotalIva0 += $impuesto['base_imponible'];
                }
            }
        }
        $totalIva = 0; $totalIce = 0;
        foreach ($impuestosAgrupados as $impuesto) {
            if ($impuesto['codigo'] === '2') $totalIva += $impuesto['valor'];
            elseif ($impuesto['codigo'] === '3') $totalIce += $impuesto['valor'];
        }
        return ['subtotal_sin_impuestos' => round($subtotalSinImpuestos, 2), 'total_descuento' => round($totalDescuento, 2), 'subtotal_iva' => round($subtotalIva, 2), 'subtotal_iva_0' => round($subtotalIva0, 2), 'total_iva' => round($totalIva, 2), 'total_ice' => round($totalIce, 2), 'total' => round($subtotalSinImpuestos + $totalIva + $totalIce, 2), 'impuestos' => array_values($impuestosAgrupados)];
    }

    private function getNextSecuencial(int $puntoEmisionId, int $tipoComprobanteId): int {
        $result = $this->db->query("SELECT secuencial_actual FROM secuenciales WHERE punto_emision_id = :punto_emision_id AND tipo_comprobante_id = :tipo_comprobante_id")->fetch([':punto_emision_id' => $puntoEmisionId, ':tipo_comprobante_id' => $tipoComprobanteId]);
        if (!$result) { $this->db->insert('secuenciales', ['punto_emision_id' => $puntoEmisionId, 'tipo_comprobante_id' => $tipoComprobanteId, 'secuencial_actual' => 1]); return 1; }
        return (int)$result['secuencial_actual'] + 1;
    }

    private function updateSecuencial(int $puntoEmisionId, int $tipoComprobanteId, int $secuencial): void {
        $this->db->update('secuenciales', ['secuencial_actual' => $secuencial], 'punto_emision_id = :punto_emision_id AND tipo_comprobante_id = :tipo_comprobante_id', [':punto_emision_id' => $puntoEmisionId, ':tipo_comprobante_id' => $tipoComprobanteId]);
    }

    // =========================================================================
    // NUEVO: Método para obtener mensaje descriptivo por estado SRI
    // =========================================================================
    private function obtenerMensajeEstadoSri(string $estado): string
    {
        return match(strtolower(trim($estado))) {
            'enviado' => 'Comprobante enviado al SRI. Está en cola de procesamiento. Puede consultar el estado en unos minutos usando el botón "Consultar SRI".',
            'recibida' => 'Comprobante recibido por el SRI. Esperando autorización.',
            'autorizada' => '¡Comprobante AUTORIZADO por el SRI!',
            'rechazada' => 'Comprobante rechazado por el SRI. Revise los errores en el mensaje.',
            'pendiente' => 'Comprobante pendiente de envío al SRI.',
            'en procesamiento', 'en_procesamiento' => 'Comprobante en procesamiento por el SRI. Consulte nuevamente en unos minutos.',
            'no autorizado' => 'Comprobante NO autorizado por el SRI.',
            'devuelta' => 'Comprobante devuelto por el SRI. Hay errores en la estructura del documento.',
            default => "Estado actual del SRI: $estado. Utilice el botón 'Consultar SRI' para obtener más detalles."
        };
    }

    // =========================================================================
    // MÉTODO EMITIR CORREGIDO - Garantiza mensaje NUNCA vacío
    // =========================================================================
    public function emitir(int $id): array
    {
        $factura = $this->getById($id);
        if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];
        if (!in_array($factura['estado'], ['borrador', 'emitida'])) return ['success' => false, 'message' => 'Esta factura no puede ser emitida (estado: ' . $factura['estado'] . ')'];
        if ($factura['estado_sri'] === 'autorizada') return ['success' => false, 'message' => 'Esta factura ya está autorizada por el SRI'];
        
        try {
            $empresa = $this->getEmpresaData();
            $establecimiento = $this->getEstablecimientoData($factura['establecimiento_id']);
            $puntoEmision = $this->getPuntoEmisionData($factura['punto_emision_id']);
            
            $certPath = $empresa['firma_electronica_path'] ?? '';
            if (!empty($certPath) && !file_exists($certPath)) $certPath = UPLOADS_PATH . '/' . $certPath;
            if (empty($certPath) || !file_exists($certPath)) return ['success' => false, 'message' => 'No se encontró el certificado de firma electrónica. Configure su firma en Mi Empresa > Firma Electrónica.'];
            
            // 1. Generar Clave de Acceso
            if (empty($factura['clave_acceso'])) {
                $claveAcceso = ClaveAcceso::generar($factura['fecha_emision'], '01', $empresa['ruc'], $empresa['ambiente_sri'], $establecimiento['codigo'], $puntoEmision['codigo'], $factura['secuencial']);
                $this->db->update('facturas', ['clave_acceso' => $claveAcceso], 'id = :id', [':id' => $id]);
                $factura['clave_acceso'] = $claveAcceso;
            }
            
            // 2. Generar XML
            $xmlGenerator = new XmlGenerator($empresa, $establecimiento, $puntoEmision);
            $facturaXml = $this->prepararDatosXml($factura);
            $xml = $xmlGenerator->generarFactura($facturaXml);
            $xmlPath = $this->guardarXml($id, $xml, 'generado');
            $this->db->update('facturas', ['xml_path' => $xmlPath], 'id = :id', [':id' => $id]);
            
            // 3. Firmar XML
            $firma = new FirmaElectronica($certPath, $empresa['firma_electronica_password']);
            if ($firma->proximoAVencer(0)) return ['success' => false, 'message' => 'El certificado de firma electrónica ha expirado. Por favor renuévelo.'];
            
            $xmlFirmado = $firma->firmarXml($xml);
            $xmlFirmadoPath = $this->guardarXml($id, $xmlFirmado, 'firmado');
            $this->db->update('facturas', ['xml_firmado_path' => $xmlFirmadoPath, 'estado' => 'emitida', 'estado_sri' => 'enviado'], 'id = :id', [':id' => $id]);
            
            // 4. Enviar al SRI
            $sriService = new SriService($empresa['ambiente_sri']);
            $resultado = $sriService->enviarYAutorizar($xmlFirmado, $factura['clave_acceso'], 5, 3);
            
            // Log para debugging
            error_log("Factura $id - Resultado SRI: " . json_encode($resultado));
            
            if ($resultado['success']) {
                // AUTORIZADO
                $fechaAuth = $resultado['fecha_autorizacion'] ?? date('Y-m-d H:i:s');
                $numeroAuth = $resultado['numero_autorizacion'] ?? $factura['clave_acceso'];
                $mensajeSri = !empty($resultado['mensaje']) ? $resultado['mensaje'] : '¡Comprobante AUTORIZADO por el SRI!';
                
                $this->db->update('facturas', ['estado_sri' => 'autorizada', 'numero_autorizacion' => $numeroAuth, 'fecha_autorizacion' => $fechaAuth, 'mensaje_sri' => $mensajeSri], 'id = :id', [':id' => $id]);
                if (!empty($resultado['xml_autorizado'])) $this->guardarXml($id, $resultado['xml_autorizado'], 'autorizado');
                try { $this->auth->logActivity('emitir_sri', 'facturas', $id); } catch (\Exception $e) {}
                
                return ['success' => true, 'message' => $mensajeSri, 'numero_autorizacion' => $numeroAuth, 'fecha_autorizacion' => $fechaAuth, 'clave_acceso' => $factura['clave_acceso']];
            } else {
                // NO AUTORIZADO o EN PROCESO
                $estadoSri = $resultado['estado'] ?? 'DESCONOCIDO';
                $nuevoEstadoSri = match(strtoupper($estadoSri)) {
                    'RECHAZADO', 'NO AUTORIZADO' => 'rechazada',
                    'EN PROCESAMIENTO' => 'pendiente',
                    'ERROR', 'ERROR_CONEXION', 'ERROR_INTERNO' => 'pendiente',
                    default => 'enviado'
                };
                
                // FIX PRINCIPAL: Obtener mensaje, con fallback si está vacío
                $mensaje = $resultado['mensaje'] ?? '';
                if (empty(trim($mensaje))) {
                    $mensaje = $this->obtenerMensajeEstadoSri($nuevoEstadoSri);
                }
                
                $this->db->update('facturas', ['estado_sri' => $nuevoEstadoSri, 'mensaje_sri' => $mensaje], 'id = :id', [':id' => $id]);
                
                return ['success' => false, 'message' => $mensaje, 'estado_sri' => $nuevoEstadoSri, 'clave_acceso' => $factura['clave_acceso']];
            }
        } catch (\Exception $e) {
            error_log("Error emitiendo factura $id: " . $e->getMessage());
            $this->db->update('facturas', ['estado_sri' => 'pendiente', 'mensaje_sri' => 'Error: ' . $e->getMessage()], 'id = :id', [':id' => $id]);
            return ['success' => false, 'message' => 'Error al emitir la factura: ' . $e->getMessage()];
        }
    }

    public function reenviar(int $id): array {
        $factura = $this->getById($id);
        if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];
        if ($factura['estado_sri'] === 'autorizada') return ['success' => false, 'message' => 'Esta factura ya está autorizada'];
        if (empty($factura['xml_firmado_path']) || !file_exists($factura['xml_firmado_path'])) {
            $this->db->update('facturas', ['clave_acceso' => null, 'xml_path' => null, 'xml_firmado_path' => null], 'id = :id', [':id' => $id]);
        }
        return $this->emitir($id);
    }

    public function consultarEstadoSri(int $id): array {
        $factura = $this->getById($id);
        if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];
        if (empty($factura['clave_acceso'])) return ['success' => false, 'message' => 'Esta factura no tiene clave de acceso'];
        
        try {
            $empresa = $this->getEmpresaData();
            $sriService = new SriService($empresa['ambiente_sri']);
            $resultado = $sriService->consultarAutorizacion($factura['clave_acceso']);
            
            if ($resultado['success'] && $resultado['estado'] === 'AUTORIZADO') {
                $this->db->update('facturas', ['estado_sri' => 'autorizada', 'numero_autorizacion' => $resultado['numero_autorizacion'] ?? $factura['clave_acceso'], 'fecha_autorizacion' => $resultado['fecha_autorizacion'] ?? date('Y-m-d H:i:s'), 'mensaje_sri' => $resultado['mensaje']], 'id = :id', [':id' => $id]);
            }
            
            if (empty($resultado['mensaje'])) $resultado['mensaje'] = $this->obtenerMensajeEstadoSri($resultado['estado'] ?? 'desconocido');
            return $resultado;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al consultar el SRI: ' . $e->getMessage()];
        }
    }

    private function prepararDatosXml(array $factura): array {
        $cliente = $this->db->query("SELECT c.*, ti.codigo as tipo_identificacion_codigo FROM clientes c LEFT JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id WHERE c.id = :id")->fetch([':id' => $factura['cliente_id']]);
        $detalles = $this->getDetalles($factura['id']);
        
        $detallesXml = [];
        foreach ($detalles as $det) {
            $impuestosDetalle = $this->db->query("SELECT * FROM factura_detalle_impuestos WHERE factura_detalle_id = :id")->fetchAll([':id' => $det['id']]);
            $impuestosXml = [];
            
            // CORREGIDO: Calcular baseImponible correctamente cuando los valores en BD son 0
            $subtotalFromDb = (float)($det['subtotal'] ?? 0);
            $precioTotalFromDb = (float)($det['precio_total_sin_impuesto'] ?? 0);
            $cantidad = (float)($det['cantidad'] ?? 1);
            $precioUnitario = (float)($det['precio_unitario'] ?? 0);
            $descuento = (float)($det['descuento'] ?? 0);
            
            // Si el subtotal de la BD es > 0, usarlo; sino calcular
            if ($subtotalFromDb > 0) {
                $baseImponible = $subtotalFromDb;
            } elseif ($precioTotalFromDb > 0) {
                $baseImponible = $precioTotalFromDb;
            } else {
                // Calcular: cantidad * precio_unitario - descuento
                $baseImponible = round($cantidad * $precioUnitario - $descuento, 2);
            }
            
            if (!empty($impuestosDetalle)) {
                foreach ($impuestosDetalle as $imp) {
                    $impuestosXml[] = ['codigo' => $imp['codigo'], 'codigo_porcentaje' => $imp['codigo_porcentaje'], 'tarifa' => $imp['tarifa'], 'base_imponible' => $imp['base_imponible'], 'valor' => $imp['valor']];
                }
            } else {
                // Fallback: calcular IVA 15%
                $impuestosXml[] = ['codigo' => '2', 'codigo_porcentaje' => '4', 'tarifa' => 15, 'base_imponible' => $baseImponible, 'valor' => round($baseImponible * 0.15, 2)];
            }
            
            $detallesXml[] = [
                'codigo_principal' => $det['codigo_principal'] ?: 'SRV' . str_pad($det['id'], 6, '0', STR_PAD_LEFT),
                'codigo_auxiliar' => $det['codigo_auxiliar'] ?? '',
                'descripcion' => $det['descripcion'],
                'cantidad' => $det['cantidad'],
                'precio_unitario' => $det['precio_unitario'],
                'descuento' => $det['descuento'] ?? 0,
                'precio_total_sin_impuesto' => $baseImponible,
                'impuestos' => $impuestosXml
            ];
        }
        
        $formasPago = $this->getFormasPago($factura['id']);
        $pagosXml = [];
        foreach ($formasPago as $fp) { $pagosXml[] = ['forma_pago' => $fp['forma_pago_codigo'] ?? '20', 'total' => $fp['total'], 'plazo' => $fp['plazo'] ?? 0, 'unidad_tiempo' => $fp['unidad_tiempo'] ?? 'dias']; }
        if (empty($pagosXml)) $pagosXml[] = ['forma_pago' => '01', 'total' => $factura['total'], 'plazo' => 0, 'unidad_tiempo' => 'dias'];
        
        $infoAdicional = $this->getInfoAdicional($factura['id']);
        if (!empty($cliente['email'])) $infoAdicional[] = ['nombre' => 'Email', 'valor' => $cliente['email']];
        if (!empty($cliente['direccion'])) $infoAdicional[] = ['nombre' => 'Dirección', 'valor' => $cliente['direccion']];
        if (!empty($cliente['telefono'])) $infoAdicional[] = ['nombre' => 'Teléfono', 'valor' => $cliente['telefono']];
        
        return [
            'clave_acceso' => $factura['clave_acceso'], 'secuencial' => $factura['secuencial'], 'fecha_emision' => $factura['fecha_emision'], 'guia_remision' => $factura['guia_remision'] ?? '',
            'cliente' => ['tipo_identificacion_codigo' => $cliente['tipo_identificacion_codigo'] ?? '05', 'identificacion' => $cliente['identificacion'], 'razon_social' => $cliente['razon_social'], 'direccion' => $cliente['direccion'] ?? 'S/N', 'email' => $cliente['email'] ?? ''],
            'totales' => ['subtotal_sin_impuestos' => $factura['subtotal_sin_impuestos'], 'total_descuento' => $factura['total_descuento'], 'subtotal_iva' => $factura['subtotal_iva'] ?? $factura['subtotal_sin_impuestos'], 'subtotal_iva_0' => $factura['subtotal_iva_0'] ?? 0, 'total_iva' => $factura['total_iva'], 'total_ice' => $factura['total_ice'] ?? 0, 'propina' => $factura['propina'] ?? 0, 'total' => $factura['total']],
            'detalles' => $detallesXml, 'formas_pago' => $pagosXml, 'info_adicional' => $infoAdicional
        ];
    }

    private function guardarXml(int $facturaId, string $xml, string $tipo): string {
        $baseDir = UPLOADS_PATH . '/facturas/xml/' . date('Y/m');
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
        $filename = "factura_{$facturaId}_{$tipo}_" . date('YmdHis') . '.xml';
        $path = $baseDir . '/' . $filename;
        file_put_contents($path, $xml);
        return $path;
    }

    private function getEmpresaData(): array { $empresa = $this->db->query("SELECT * FROM empresas WHERE id = :id")->fetch([':id' => $this->auth->empresaId()]); if (!$empresa) throw new \Exception('Empresa no encontrada'); return $empresa; }
    private function getEstablecimientoData(int $id): array { $est = $this->db->query("SELECT * FROM establecimientos WHERE id = :id")->fetch([':id' => $id]); if (!$est) throw new \Exception('Establecimiento no encontrado'); return $est; }
    private function getPuntoEmisionData(int $id): array { $pe = $this->db->query("SELECT * FROM puntos_emision WHERE id = :id")->fetch([':id' => $id]); if (!$pe) throw new \Exception('Punto de emisión no encontrado'); return $pe; }

    public function anular(int $id, string $motivo): array {
        $factura = $this->getById($id);
        if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];
        if ($factura['estado'] === 'anulada') return ['success' => false, 'message' => 'Ya está anulada'];
        if ($factura['estado_sri'] === 'autorizada') return ['success' => false, 'message' => 'No se puede anular una autorizada. Emita Nota de Crédito.'];
        $this->db->update('facturas', ['estado' => 'anulada', 'anulado_by' => $this->auth->id(), 'anulado_at' => date('Y-m-d H:i:s'), 'motivo_anulacion' => $motivo], 'id = :id', [':id' => $id]);
        $this->auth->logActivity('anular', 'facturas', $id, [], ['motivo' => $motivo]);
        return ['success' => true, 'message' => 'Anulada correctamente'];
    }

    public function getEstadisticas(string $periodo = 'mes'): array {
        $empresaId = $this->auth->empresaId();
        $fechaInicio = match($periodo) { 'dia' => date('Y-m-d'), 'semana' => date('Y-m-d', strtotime('-7 days')), 'mes' => date('Y-m-01'), 'anio' => date('Y-01-01'), default => date('Y-m-01') };
        $totalFacturado = $this->db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE empresa_id = :e AND estado = 'emitida' AND fecha_emision >= :f AND deleted_at IS NULL")->fetchColumn([':e' => $empresaId, ':f' => $fechaInicio]);
        $totalPendiente = $this->db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE empresa_id = :e AND estado = 'emitida' AND estado_pago IN ('pendiente', 'parcial') AND deleted_at IS NULL")->fetchColumn([':e' => $empresaId]);
        $cantidadFacturas = $this->db->query("SELECT COUNT(*) FROM facturas WHERE empresa_id = :e AND estado = 'emitida' AND fecha_emision >= :f AND deleted_at IS NULL")->fetchColumn([':e' => $empresaId, ':f' => $fechaInicio]);
        $porEstadoSri = $this->db->query("SELECT estado_sri, COUNT(*) as cantidad FROM facturas WHERE empresa_id = :e AND estado = 'emitida' AND fecha_emision >= :f AND deleted_at IS NULL GROUP BY estado_sri")->fetchAll([':e' => $empresaId, ':f' => $fechaInicio]);
        return ['total_facturado' => (float) $totalFacturado, 'total_pendiente' => (float) $totalPendiente, 'cantidad_facturas' => (int) $cantidadFacturas, 'por_estado_sri' => $porEstadoSri];
    }

    private function generateUuid(): string { return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)); }
}
