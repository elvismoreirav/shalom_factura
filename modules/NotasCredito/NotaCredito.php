<?php
/**
 * SHALOM FACTURA - Modelo de Nota de Crédito
 */

namespace Shalom\Modules\NotasCredito;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Modules\Sri\ClaveAcceso;
use Shalom\Modules\Sri\XmlGenerator;
use Shalom\Modules\Sri\FirmaElectronica;
use Shalom\Modules\Sri\SriService;

class NotaCredito
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
        $where = ['nc.empresa_id = :empresa_id', 'nc.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];

        if (!empty($filters['estado'])) { $where[] = 'nc.estado = :estado'; $params[':estado'] = $filters['estado']; }
        if (!empty($filters['estado_sri'])) { $where[] = 'nc.estado_sri = :estado_sri'; $params[':estado_sri'] = $filters['estado_sri']; }
        if (!empty($filters['fecha_desde'])) { $where[] = 'nc.fecha_emision >= :fecha_desde'; $params[':fecha_desde'] = $filters['fecha_desde']; }
        if (!empty($filters['fecha_hasta'])) { $where[] = 'nc.fecha_emision <= :fecha_hasta'; $params[':fecha_hasta'] = $filters['fecha_hasta']; }
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(c.razon_social LIKE :buscar OR c.identificacion LIKE :buscar2 OR nc.numero_autorizacion LIKE :buscar3)';
            $params[':buscar'] = $search;
            $params[':buscar2'] = $search;
            $params[':buscar3'] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $total = $this->db->query("
            SELECT COUNT(*)
            FROM notas_credito nc
            JOIN clientes c ON nc.cliente_id = c.id
            WHERE $whereClause
        ")->fetchColumn($params);

        $notas = $this->db->query("
            SELECT nc.*,
                c.razon_social as cliente_nombre,
                c.identificacion as cliente_identificacion,
                f.secuencial as factura_secuencial,
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(nc.secuencial, 9, '0')) as numero_documento
            FROM notas_credito nc
            JOIN clientes c ON nc.cliente_id = c.id
            JOIN facturas f ON nc.factura_id = f.id
            JOIN establecimientos e ON nc.establecimiento_id = e.id
            JOIN puntos_emision pe ON nc.punto_emision_id = pe.id
            WHERE $whereClause
            ORDER BY nc.fecha_emision DESC, nc.secuencial DESC
            LIMIT $limit OFFSET $offset
        ")->fetchAll($params);

        return ['data' => $notas, 'total' => (int) $total, 'pages' => ceil($total / $limit)];
    }

    public function getById(int $id): ?array
    {
        $nota = $this->db->query("
            SELECT nc.*,
                c.razon_social as cliente_nombre,
                c.identificacion as cliente_identificacion,
                c.email as cliente_email,
                c.direccion as cliente_direccion,
                c.telefono as cliente_telefono,
                ti.nombre as tipo_identificacion_nombre,
                e.codigo as establecimiento_codigo,
                e.nombre as establecimiento_nombre,
                e.direccion as establecimiento_direccion,
                pe.codigo as punto_emision_codigo,
                f.fecha_emision as factura_fecha_emision,
                CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(nc.secuencial, 9, '0')) as numero_documento,
                CONCAT(ef.codigo, '-', pf.codigo, '-', LPAD(f.secuencial, 9, '0')) as factura_numero
            FROM notas_credito nc
            JOIN clientes c ON nc.cliente_id = c.id
            JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            JOIN establecimientos e ON nc.establecimiento_id = e.id
            JOIN puntos_emision pe ON nc.punto_emision_id = pe.id
            JOIN facturas f ON nc.factura_id = f.id
            JOIN establecimientos ef ON f.establecimiento_id = ef.id
            JOIN puntos_emision pf ON f.punto_emision_id = pf.id
            WHERE nc.id = :id AND nc.empresa_id = :empresa_id AND nc.deleted_at IS NULL
        ")->fetch([':id' => $id, ':empresa_id' => $this->auth->empresaId()]);

        if (!$nota) {
            return null;
        }

        $nota['detalles'] = $this->getDetalles($id);
        $nota['impuestos'] = $this->getImpuestos($id);
        $nota['info_adicional'] = $this->getInfoAdicional($id);

        return $nota;
    }

    public function getDetalles(int $notaId): array
    {
        return $this->db->query("
            SELECT ncd.*, s.nombre as servicio_nombre
            FROM nota_credito_detalles ncd
            LEFT JOIN servicios s ON ncd.servicio_id = s.id
            WHERE ncd.nota_credito_id = :nota_id
            ORDER BY ncd.orden
        ")->fetchAll([':nota_id' => $notaId]);
    }

    public function getImpuestos(int $notaId): array
    {
        return $this->db->query("
            SELECT nci.*, ci.nombre as impuesto_nombre, ci.codigo as impuesto_codigo, ci.codigo_porcentaje
            FROM nota_credito_impuestos nci
            JOIN cat_impuestos ci ON nci.impuesto_id = ci.id
            WHERE nci.nota_credito_id = :nota_id
        ")->fetchAll([':nota_id' => $notaId]);
    }

    public function getInfoAdicional(int $notaId): array
    {
        return $this->db->query("
            SELECT * FROM nota_credito_info_adicional
            WHERE nota_credito_id = :nota_id
            ORDER BY orden
        ")->fetchAll([':nota_id' => $notaId]);
    }

    public function create(array $data): array
    {
        $this->db->beginTransaction();

        try {
            if (empty($data['factura_id'])) {
                throw new \Exception('Debe seleccionar una factura');
            }

            if (empty($data['motivo'])) {
                throw new \Exception('Debe ingresar el motivo de la nota de crédito');
            }

            $factura = $this->db->query("
                SELECT f.*,
                    c.email as cliente_email,
                    c.direccion as cliente_direccion,
                    e.codigo as establecimiento_codigo,
                    pe.codigo as punto_emision_codigo
                FROM facturas f
                JOIN clientes c ON f.cliente_id = c.id
                JOIN establecimientos e ON f.establecimiento_id = e.id
                JOIN puntos_emision pe ON f.punto_emision_id = pe.id
                WHERE f.id = :id AND f.empresa_id = :empresa_id AND f.deleted_at IS NULL
            ")->fetch([':id' => $data['factura_id'], ':empresa_id' => $this->auth->empresaId()]);

            if (!$factura) {
                throw new \Exception('Factura no encontrada');
            }

            if ($factura['estado_sri'] !== 'autorizada') {
                throw new \Exception('La factura debe estar autorizada para emitir una nota de crédito');
            }

            $detalles = $this->obtenerDetallesFactura($factura['id']);
            if (empty($detalles)) {
                throw new \Exception('La factura no tiene detalles para acreditar');
            }

            $tipoComprobanteId = $this->getTipoComprobanteId();
            $secuencial = $this->getNextSecuencial($factura['punto_emision_id'], $tipoComprobanteId);
            $totales = $this->calcularTotales($detalles);
            $uuid = $this->generateUuid();

            $notaId = $this->db->insert('notas_credito', [
                'uuid' => $uuid,
                'empresa_id' => $this->auth->empresaId(),
                'establecimiento_id' => $factura['establecimiento_id'],
                'punto_emision_id' => $factura['punto_emision_id'],
                'factura_id' => $factura['id'],
                'cliente_id' => $factura['cliente_id'],
                'tipo_comprobante_id' => $tipoComprobanteId,
                'secuencial' => $secuencial,
                'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d'),
                'subtotal_sin_impuestos' => $totales['subtotal_sin_impuestos'],
                'total_descuento' => $totales['total_descuento'],
                'subtotal_iva' => $totales['subtotal_iva'],
                'subtotal_iva_0' => $totales['subtotal_iva_0'],
                'total_iva' => $totales['total_iva'],
                'total_ice' => $totales['total_ice'],
                'total' => $totales['total'],
                'motivo' => $data['motivo'],
                'estado' => 'borrador',
                'estado_sri' => 'pendiente',
                'created_by' => $this->auth->id()
            ]);

            $orden = 1;
            foreach ($detalles as $detalle) {
                $detalleId = $this->db->insert('nota_credito_detalles', [
                    'nota_credito_id' => $notaId,
                    'servicio_id' => $detalle['servicio_id'] ?? null,
                    'codigo_principal' => $detalle['codigo_principal'],
                    'codigo_auxiliar' => $detalle['codigo_auxiliar'] ?? '',
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'precio_total_sin_impuesto' => $detalle['precio_total_sin_impuesto'],
                    'orden' => $orden++
                ]);

                foreach ($detalle['impuestos'] as $impuesto) {
                    $this->db->insert('nota_credito_detalle_impuestos', [
                        'nota_credito_detalle_id' => $detalleId,
                        'impuesto_id' => $impuesto['impuesto_id'],
                        'codigo' => $impuesto['codigo'],
                        'codigo_porcentaje' => $impuesto['codigo_porcentaje'],
                        'tarifa' => $impuesto['tarifa'] ?? 0,
                        'base_imponible' => $impuesto['base_imponible'],
                        'valor' => $impuesto['valor']
                    ]);
                }
            }

            foreach ($totales['impuestos'] as $impuesto) {
                $this->db->insert('nota_credito_impuestos', [
                    'nota_credito_id' => $notaId,
                    'impuesto_id' => $impuesto['impuesto_id'],
                    'codigo' => $impuesto['codigo'],
                    'codigo_porcentaje' => $impuesto['codigo_porcentaje'],
                    'base_imponible' => $impuesto['base_imponible'],
                    'valor' => $impuesto['valor']
                ]);
            }

            $this->db->insert('nota_credito_info_adicional', [
                'nota_credito_id' => $notaId,
                'nombre' => 'Factura',
                'valor' => $this->obtenerNumeroFactura($factura['id']),
                'orden' => 1
            ]);

            if (!empty($factura['cliente_email'])) {
                $this->db->insert('nota_credito_info_adicional', [
                    'nota_credito_id' => $notaId,
                    'nombre' => 'Email',
                    'valor' => $factura['cliente_email'],
                    'orden' => 2
                ]);
            }

            if (!empty($factura['cliente_direccion'])) {
                $this->db->insert('nota_credito_info_adicional', [
                    'nota_credito_id' => $notaId,
                    'nombre' => 'Dirección',
                    'valor' => $factura['cliente_direccion'],
                    'orden' => 3
                ]);
            }

            $this->updateSecuencial($factura['punto_emision_id'], $tipoComprobanteId, $secuencial);
            $numero = sprintf('%s-%s-%s', $factura['establecimiento_codigo'], $factura['punto_emision_codigo'], str_pad($secuencial, 9, '0', STR_PAD_LEFT));

            $this->db->commit();
            try { $this->auth->logActivity('crear', 'notas_credito', $notaId); } catch (\Exception $e) {}
            return ['success' => true, 'message' => 'Nota de crédito creada correctamente', 'id' => $notaId, 'numero' => $numero];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error al crear la nota de crédito: ' . $e->getMessage()];
        }
    }

    public function emitir(int $id): array
    {
        $nota = $this->getById($id);
        if (!$nota) {
            return ['success' => false, 'message' => 'Nota de crédito no encontrada'];
        }
        if (!in_array($nota['estado'], ['borrador', 'emitida'])) {
            return ['success' => false, 'message' => 'Esta nota de crédito no puede ser emitida (estado: ' . $nota['estado'] . ')'];
        }
        if ($nota['estado_sri'] === 'autorizada') {
            return ['success' => false, 'message' => 'Esta nota de crédito ya está autorizada por el SRI'];
        }

        try {
            $empresa = $this->getEmpresaData();
            $establecimiento = $this->getEstablecimientoData($nota['establecimiento_id']);
            $puntoEmision = $this->getPuntoEmisionData($nota['punto_emision_id']);

            $certPath = $empresa['firma_electronica_path'] ?? '';
            if (!empty($certPath) && !file_exists($certPath)) {
                $certPath = UPLOADS_PATH . '/' . $certPath;
            }
            if (empty($certPath) || !file_exists($certPath)) {
                return ['success' => false, 'message' => 'No se encontró el certificado de firma electrónica. Configure su firma en Mi Empresa > Firma Electrónica.'];
            }

            if (empty($nota['clave_acceso'])) {
                $claveAcceso = ClaveAcceso::generar($nota['fecha_emision'], '04', $empresa['ruc'], $empresa['ambiente_sri'], $establecimiento['codigo'], $puntoEmision['codigo'], $nota['secuencial']);
                $this->db->update('notas_credito', ['clave_acceso' => $claveAcceso], 'id = :id', [':id' => $id]);
                $nota['clave_acceso'] = $claveAcceso;
            }

            $xmlGenerator = new XmlGenerator($empresa, $establecimiento, $puntoEmision);
            $notaXml = $this->prepararDatosXml($nota);
            $xml = $xmlGenerator->generarNotaCredito($notaXml);
            $xmlPath = $this->guardarXml($id, $xml, 'generado');
            $this->db->update('notas_credito', ['xml_path' => $xmlPath], 'id = :id', [':id' => $id]);

            $firma = new FirmaElectronica($certPath, $empresa['firma_electronica_password']);
            if ($firma->proximoAVencer(0)) {
                return ['success' => false, 'message' => 'El certificado de firma electrónica ha expirado. Por favor renuévelo.'];
            }

            $xmlFirmado = $firma->firmarXml($xml);
            $xmlFirmadoPath = $this->guardarXml($id, $xmlFirmado, 'firmado');
            $this->db->update('notas_credito', ['xml_firmado_path' => $xmlFirmadoPath, 'estado' => 'emitida', 'estado_sri' => 'enviado'], 'id = :id', [':id' => $id]);

            $sriService = new SriService($empresa['ambiente_sri']);
            $resultado = $sriService->enviarYAutorizar($xmlFirmado, $nota['clave_acceso'], 5, 3);

            if ($resultado['success']) {
                $fechaAuth = $resultado['fecha_autorizacion'] ?? date('Y-m-d H:i:s');
                $numeroAuth = $resultado['numero_autorizacion'] ?? $nota['clave_acceso'];
                $mensajeSri = !empty($resultado['mensaje']) ? $resultado['mensaje'] : '¡Comprobante AUTORIZADO por el SRI!';

                $this->db->update('notas_credito', [
                    'estado_sri' => 'autorizada',
                    'numero_autorizacion' => $numeroAuth,
                    'fecha_autorizacion' => $fechaAuth,
                    'mensaje_sri' => $mensajeSri
                ], 'id = :id', [':id' => $id]);

                if (!empty($resultado['xml_autorizado'])) {
                    $this->guardarXml($id, $resultado['xml_autorizado'], 'autorizado');
                }

                try { $this->auth->logActivity('emitir_sri', 'notas_credito', $id); } catch (\Exception $e) {}

                return [
                    'success' => true,
                    'message' => $mensajeSri,
                    'numero_autorizacion' => $numeroAuth,
                    'fecha_autorizacion' => $fechaAuth,
                    'clave_acceso' => $nota['clave_acceso']
                ];
            }

            $estadoSri = $resultado['estado'] ?? 'DESCONOCIDO';
            $nuevoEstadoSri = match(strtoupper($estadoSri)) {
                'RECHAZADO', 'NO AUTORIZADO', 'DEVUELTA', 'ERROR_ESTRUCTURA' => 'rechazada',
                'ERROR', 'ERROR_CONEXION', 'ERROR_INTERNO' => 'pendiente',
                default => 'enviado'
            };

            $mensaje = $resultado['mensaje'] ?? 'Comprobante en proceso';
            $this->db->update('notas_credito', ['estado_sri' => $nuevoEstadoSri, 'mensaje_sri' => $mensaje], 'id = :id', [':id' => $id]);

            return ['success' => false, 'message' => $mensaje, 'estado_sri' => $nuevoEstadoSri, 'clave_acceso' => $nota['clave_acceso']];
        } catch (\Exception $e) {
            $this->db->update('notas_credito', ['estado_sri' => 'pendiente', 'mensaje_sri' => 'Error: ' . $e->getMessage()], 'id = :id', [':id' => $id]);
            return ['success' => false, 'message' => 'Error al emitir la nota de crédito: ' . $e->getMessage()];
        }
    }

    public function reenviar(int $id): array
    {
        $nota = $this->getById($id);
        if (!$nota) {
            return ['success' => false, 'message' => 'Nota de crédito no encontrada'];
        }
        if ($nota['estado_sri'] === 'autorizada') {
            return ['success' => false, 'message' => 'Esta nota de crédito ya está autorizada'];
        }
        if (empty($nota['xml_firmado_path']) || !file_exists($nota['xml_firmado_path'])) {
            $this->db->update('notas_credito', ['clave_acceso' => null, 'xml_path' => null, 'xml_firmado_path' => null], 'id = :id', [':id' => $id]);
        }

        return $this->emitir($id);
    }

    public function consultarEstadoSri(int $id): array
    {
        $nota = $this->getById($id);
        if (!$nota) {
            return ['success' => false, 'message' => 'Nota de crédito no encontrada'];
        }
        if (empty($nota['clave_acceso'])) {
            return ['success' => false, 'message' => 'Esta nota de crédito no tiene clave de acceso'];
        }

        try {
            $empresa = $this->getEmpresaData();
            $sriService = new SriService($empresa['ambiente_sri']);
            $resultado = $sriService->consultarAutorizacion($nota['clave_acceso']);

            if ($resultado['success'] && $resultado['estado'] === 'AUTORIZADO') {
                $this->db->update('notas_credito', [
                    'estado_sri' => 'autorizada',
                    'numero_autorizacion' => $resultado['numero_autorizacion'] ?? $nota['clave_acceso'],
                    'fecha_autorizacion' => $resultado['fecha_autorizacion'] ?? date('Y-m-d H:i:s'),
                    'mensaje_sri' => $resultado['mensaje']
                ], 'id = :id', [':id' => $id]);
            }

            return $resultado;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al consultar el SRI: ' . $e->getMessage()];
        }
    }

    public function anular(int $id, string $motivo): array
    {
        $nota = $this->getById($id);
        if (!$nota) {
            return ['success' => false, 'message' => 'Nota de crédito no encontrada'];
        }
        if ($nota['estado'] === 'anulada') {
            return ['success' => false, 'message' => 'Ya está anulada'];
        }
        if ($nota['estado_sri'] === 'autorizada') {
            return ['success' => false, 'message' => 'No se puede anular una nota autorizada por el SRI.'];
        }

        $this->db->update('notas_credito', [
            'estado' => 'anulada',
            'anulado_by' => $this->auth->id(),
            'anulado_at' => date('Y-m-d H:i:s'),
            'motivo_anulacion' => $motivo
        ], 'id = :id', [':id' => $id]);

        try { $this->auth->logActivity('anular', 'notas_credito', $id, [], ['motivo' => $motivo]); } catch (\Exception $e) {}

        return ['success' => true, 'message' => 'Nota de crédito anulada correctamente'];
    }

    private function prepararDatosXml(array $nota): array
    {
        $cliente = $this->db->query("
            SELECT c.*, ti.codigo as tipo_identificacion_codigo
            FROM clientes c
            LEFT JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            WHERE c.id = :id
        ")->fetch([':id' => $nota['cliente_id']]);

        $factura = $this->db->query("
            SELECT f.*, e.codigo as establecimiento_codigo, pe.codigo as punto_emision_codigo
            FROM facturas f
            JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE f.id = :id
        ")->fetch([':id' => $nota['factura_id']]);

        $detalles = $this->getDetalles($nota['id']);
        $detallesXml = [];
        foreach ($detalles as $det) {
            $impuestosDetalle = $this->db->query("
                SELECT * FROM nota_credito_detalle_impuestos
                WHERE nota_credito_detalle_id = :id
            ")->fetchAll([':id' => $det['id']]);

            $impuestosXml = [];
            $baseImponible = (float)($det['precio_total_sin_impuesto'] ?? 0);
            if ($baseImponible <= 0) {
                $baseImponible = round($det['cantidad'] * $det['precio_unitario'] - ($det['descuento'] ?? 0), 2);
            }

            foreach ($impuestosDetalle as $imp) {
                $impuestosXml[] = [
                    'codigo' => $imp['codigo'],
                    'codigo_porcentaje' => $imp['codigo_porcentaje'],
                    'tarifa' => $imp['tarifa'],
                    'base_imponible' => $imp['base_imponible'],
                    'valor' => $imp['valor']
                ];
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

        $infoAdicional = $this->getInfoAdicional($nota['id']);
        if (!empty($cliente['email'])) {
            $infoAdicional[] = ['nombre' => 'Email', 'valor' => $cliente['email']];
        }

        return [
            'clave_acceso' => $nota['clave_acceso'],
            'secuencial' => $nota['secuencial'],
            'fecha_emision' => $nota['fecha_emision'],
            'cliente' => [
                'tipo_identificacion_codigo' => $cliente['tipo_identificacion_codigo'] ?? '05',
                'identificacion' => $cliente['identificacion'],
                'razon_social' => $cliente['razon_social'],
                'direccion' => $cliente['direccion'] ?? 'S/N',
                'email' => $cliente['email'] ?? ''
            ],
            'totales' => [
                'subtotal_sin_impuestos' => $nota['subtotal_sin_impuestos'],
                'total_descuento' => $nota['total_descuento'],
                'subtotal_iva' => $nota['subtotal_iva'] ?? $nota['subtotal_sin_impuestos'],
                'subtotal_iva_0' => $nota['subtotal_iva_0'] ?? 0,
                'total_iva' => $nota['total_iva'],
                'total_ice' => $nota['total_ice'] ?? 0,
                'total' => $nota['total']
            ],
            'detalles' => $detallesXml,
            'info_adicional' => $infoAdicional,
            'motivo' => $nota['motivo'] ?? 'Devolución',
            'documento_modificado' => [
                'codigo' => '01',
                'numero' => $this->obtenerNumeroFactura($factura['id']),
                'fecha' => $factura['fecha_emision'] ?? $nota['fecha_emision']
            ]
        ];
    }

    private function obtenerDetallesFactura(int $facturaId): array
    {
        $detalles = $this->db->query("
            SELECT fd.*
            FROM factura_detalles fd
            WHERE fd.factura_id = :factura_id
            ORDER BY fd.orden
        ")->fetchAll([':factura_id' => $facturaId]);

        foreach ($detalles as &$detalle) {
            $detalle['impuestos'] = $this->db->query("
                SELECT fdi.*, ci.codigo, ci.codigo_porcentaje
                FROM factura_detalle_impuestos fdi
                JOIN cat_impuestos ci ON fdi.impuesto_id = ci.id
                WHERE fdi.factura_detalle_id = :detalle_id
            ")->fetchAll([':detalle_id' => $detalle['id']]);
        }

        return $detalles;
    }

    private function calcularTotales(array $detalles): array
    {
        $subtotalSinImpuestos = 0;
        $totalDescuento = 0;
        $subtotalIva = 0;
        $subtotalIva0 = 0;
        $impuestosAgrupados = [];

        foreach ($detalles as $detalle) {
            $subtotalLinea = $detalle['cantidad'] * $detalle['precio_unitario'];
            $descuento = $detalle['descuento'] ?? 0;
            $subtotalConDescuento = $subtotalLinea - $descuento;
            $subtotalSinImpuestos += $subtotalConDescuento;
            $totalDescuento += $descuento;

            foreach ($detalle['impuestos'] as $impuesto) {
                $key = $impuesto['codigo'] . '-' . $impuesto['codigo_porcentaje'];
                if (!isset($impuestosAgrupados[$key])) {
                    $impuestosAgrupados[$key] = [
                        'impuesto_id' => $impuesto['impuesto_id'],
                        'codigo' => $impuesto['codigo'],
                        'codigo_porcentaje' => $impuesto['codigo_porcentaje'],
                        'base_imponible' => 0,
                        'valor' => 0
                    ];
                }
                $impuestosAgrupados[$key]['base_imponible'] += $impuesto['base_imponible'];
                $impuestosAgrupados[$key]['valor'] += $impuesto['valor'];
                if ($impuesto['codigo'] === '2') {
                    if ($impuesto['tarifa'] > 0) {
                        $subtotalIva += $impuesto['base_imponible'];
                    } else {
                        $subtotalIva0 += $impuesto['base_imponible'];
                    }
                }
            }
        }

        $totalIva = 0;
        $totalIce = 0;
        foreach ($impuestosAgrupados as $impuesto) {
            if ($impuesto['codigo'] === '2') {
                $totalIva += $impuesto['valor'];
            } elseif ($impuesto['codigo'] === '3') {
                $totalIce += $impuesto['valor'];
            }
        }

        return [
            'subtotal_sin_impuestos' => round($subtotalSinImpuestos, 2),
            'total_descuento' => round($totalDescuento, 2),
            'subtotal_iva' => round($subtotalIva, 2),
            'subtotal_iva_0' => round($subtotalIva0, 2),
            'total_iva' => round($totalIva, 2),
            'total_ice' => round($totalIce, 2),
            'total' => round($subtotalSinImpuestos + $totalIva + $totalIce, 2),
            'impuestos' => array_values($impuestosAgrupados)
        ];
    }

    private function getNextSecuencial(int $puntoEmisionId, int $tipoComprobanteId): int
    {
        $result = $this->db->query("
            SELECT secuencial_actual
            FROM secuenciales
            WHERE punto_emision_id = :punto_emision_id AND tipo_comprobante_id = :tipo_comprobante_id
        ")->fetch([':punto_emision_id' => $puntoEmisionId, ':tipo_comprobante_id' => $tipoComprobanteId]);

        if (!$result) {
            $this->db->insert('secuenciales', [
                'punto_emision_id' => $puntoEmisionId,
                'tipo_comprobante_id' => $tipoComprobanteId,
                'secuencial_actual' => 1
            ]);
            return 1;
        }

        return (int) $result['secuencial_actual'] + 1;
    }

    private function updateSecuencial(int $puntoEmisionId, int $tipoComprobanteId, int $secuencial): void
    {
        $this->db->update('secuenciales', ['secuencial_actual' => $secuencial], 'punto_emision_id = :punto_emision_id AND tipo_comprobante_id = :tipo_comprobante_id', [':punto_emision_id' => $puntoEmisionId, ':tipo_comprobante_id' => $tipoComprobanteId]);
    }

    private function guardarXml(int $notaId, string $xml, string $tipo): string
    {
        $baseDir = UPLOADS_PATH . '/notas_credito/xml/' . date('Y/m');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $filename = "nota_credito_{$notaId}_{$tipo}_" . date('YmdHis') . '.xml';
        $path = $baseDir . '/' . $filename;
        file_put_contents($path, $xml);
        return $path;
    }

    private function obtenerNumeroFactura(int $facturaId): string
    {
        $row = $this->db->query("
            SELECT CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero
            FROM facturas f
            JOIN establecimientos e ON f.establecimiento_id = e.id
            JOIN puntos_emision pe ON f.punto_emision_id = pe.id
            WHERE f.id = :id
        ")->fetch([':id' => $facturaId]);

        return $row['numero'] ?? '';
    }

    private function getTipoComprobanteId(): int
    {
        $row = $this->db->query("
            SELECT id FROM cat_tipos_comprobante WHERE codigo = '04' LIMIT 1
        ")->fetch();

        if (!$row) {
            throw new \Exception('No se encontró el tipo de comprobante Nota de Crédito');
        }

        return (int) $row['id'];
    }

    private function getEmpresaData(): array
    {
        $empresa = $this->db->query("SELECT * FROM empresas WHERE id = :id")->fetch([':id' => $this->auth->empresaId()]);
        if (!$empresa) {
            throw new \Exception('Empresa no encontrada');
        }
        return $empresa;
    }

    private function getEstablecimientoData(int $id): array
    {
        $est = $this->db->query("SELECT * FROM establecimientos WHERE id = :id")->fetch([':id' => $id]);
        if (!$est) {
            throw new \Exception('Establecimiento no encontrado');
        }
        return $est;
    }

    private function getPuntoEmisionData(int $id): array
    {
        $pe = $this->db->query("SELECT * FROM puntos_emision WHERE id = :id")->fetch([':id' => $id]);
        if (!$pe) {
            throw new \Exception('Punto de emisión no encontrado');
        }
        return $pe;
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
