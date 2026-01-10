<?php
/**
 * SHALOM FACTURA - Servicio de Facturación Electrónica
 * Coordina todo el proceso: XML, firma, envío y autorización
 */

namespace Shalom\Modules\Sri;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Exception;

class FacturacionElectronica
{
    private Database $db;
    private Auth $auth;
    private XmlGenerator $xmlGenerator;
    private ?FirmaElectronica $firma = null;
    private SriClient $sriClient;
    
    private array $empresa;
    private array $establecimiento;
    private array $puntoEmision;
    
    public function __construct(int $establecimientoId, int $puntoEmisionId)
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
        
        $this->cargarConfiguracion($establecimientoId, $puntoEmisionId);
        
        $this->xmlGenerator = new XmlGenerator($this->empresa, $this->establecimiento, $this->puntoEmision);
        $this->sriClient = new SriClient($this->empresa['ambiente_sri']);
    }
    
    /**
     * Cargar configuración de empresa y establecimiento
     */
    private function cargarConfiguracion(int $establecimientoId, int $puntoEmisionId): void
    {
        // Obtener empresa
        $this->empresa = $this->db->query("
            SELECT * FROM empresas WHERE id = :id
        ")->fetch([':id' => $this->auth->empresaId()]);
        
        if (!$this->empresa) {
            throw new Exception('Empresa no configurada');
        }
        
        // Obtener establecimiento
        $this->establecimiento = $this->db->query("
            SELECT * FROM establecimientos WHERE id = :id AND empresa_id = :empresa_id
        ")->fetch([':id' => $establecimientoId, ':empresa_id' => $this->auth->empresaId()]);
        
        if (!$this->establecimiento) {
            throw new Exception('Establecimiento no encontrado');
        }
        
        // Obtener punto de emisión
        $this->puntoEmision = $this->db->query("
            SELECT * FROM puntos_emision WHERE id = :id AND establecimiento_id = :est_id
        ")->fetch([':id' => $puntoEmisionId, ':est_id' => $establecimientoId]);
        
        if (!$this->puntoEmision) {
            throw new Exception('Punto de emisión no encontrado');
        }
    }
    
    /**
     * Cargar firma electrónica
     */
    private function cargarFirma(): void
    {
        if ($this->firma !== null) {
            return;
        }
        
        if (empty($this->empresa['firma_electronica_path'])) {
            throw new Exception('No hay firma electrónica configurada');
        }
        
        $firmaPath = UPLOADS_PATH . '/firmas/' . basename($this->empresa['firma_electronica_path']);
        
        if (!file_exists($firmaPath)) {
            throw new Exception('Archivo de firma no encontrado');
        }
        
        $this->firma = new FirmaElectronica($firmaPath, $this->empresa['firma_password']);
        
        // Validar vigencia
        if ($this->firma->proximoAVencer(7)) {
            // Log warning
            error_log('Advertencia: La firma electrónica vence pronto');
        }
    }
    
    /**
     * Emitir factura electrónica
     */
    public function emitirFactura(int $facturaId): array
    {
        $this->db->beginTransaction();
        
        try {
            // Obtener datos de la factura
            $factura = $this->obtenerDatosFactura($facturaId);
            
            if (!$factura) {
                throw new Exception('Factura no encontrada');
            }
            
            if ($factura['estado_sri'] === 'autorizada') {
                throw new Exception('La factura ya está autorizada');
            }
            
            // Generar clave de acceso si no existe
            if (empty($factura['clave_acceso'])) {
                $factura['clave_acceso'] = $this->generarClaveAcceso($factura);
                
                $this->db->update('facturas', 
                    ['clave_acceso' => $factura['clave_acceso']], 
                    'id = :id', 
                    [':id' => $facturaId]
                );
            }
            
            // Generar XML
            $xml = $this->xmlGenerator->generarFactura($factura);
            
            // Guardar XML sin firma
            $this->guardarXml($facturaId, 'factura', $xml, 'sin_firmar');
            
            // Firmar XML
            $this->cargarFirma();
            $xmlFirmado = $this->firma->firmarXml($xml);
            
            // Guardar XML firmado
            $this->guardarXml($facturaId, 'factura', $xmlFirmado, 'firmado');
            
            // Actualizar estado
            $this->db->update('facturas', [
                'estado_sri' => 'pendiente',
                'xml_generado' => 1
            ], 'id = :id', [':id' => $facturaId]);
            
            // Enviar al SRI
            $resultado = $this->sriClient->procesarComprobante($xmlFirmado);
            
            // Procesar resultado
            if ($resultado['success']) {
                // Guardar XML autorizado
                if (!empty($resultado['comprobante'])) {
                    $this->guardarXml($facturaId, 'factura', $resultado['comprobante'], 'autorizado');
                }
                
                $this->db->update('facturas', [
                    'estado_sri' => 'autorizada',
                    'estado' => 'emitida',
                    'numero_autorizacion' => $resultado['numero_autorizacion'],
                    'fecha_autorizacion' => $resultado['fecha_autorizacion'],
                    'xml_autorizado' => 1
                ], 'id = :id', [':id' => $facturaId]);
                
                // Guardar log de SRI
                $this->guardarLogSri($facturaId, 'factura', 'AUTORIZADO', $resultado);
                
            } else {
                $estadoSri = $resultado['estado'] === 'EN PROCESO' ? 'pendiente' : 'rechazada';
                
                $this->db->update('facturas', [
                    'estado_sri' => $estadoSri
                ], 'id = :id', [':id' => $facturaId]);
                
                $this->guardarLogSri($facturaId, 'factura', $resultado['estado'], $resultado);
            }
            
            $this->db->commit();
            
            return [
                'success' => $resultado['success'],
                'estado' => $resultado['estado'],
                'mensaje' => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion' => $resultado['fecha_autorizacion'] ?? '',
                'clave_acceso' => $factura['clave_acceso']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            // Guardar error
            $this->guardarLogSri($facturaId, 'factura', 'ERROR', [
                'mensaje' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'estado' => 'ERROR',
                'mensaje' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reenviar factura al SRI
     */
    public function reenviarFactura(int $facturaId): array
    {
        $factura = $this->db->query("
            SELECT * FROM facturas WHERE id = :id AND empresa_id = :empresa_id
        ")->fetch([':id' => $facturaId, ':empresa_id' => $this->auth->empresaId()]);
        
        if (!$factura) {
            return ['success' => false, 'mensaje' => 'Factura no encontrada'];
        }
        
        if ($factura['estado_sri'] === 'autorizada') {
            return ['success' => false, 'mensaje' => 'La factura ya está autorizada'];
        }
        
        // Si no tiene XML firmado, volver a emitir
        if (!$factura['xml_generado']) {
            return $this->emitirFactura($facturaId);
        }
        
        // Leer XML firmado existente
        $xmlPath = UPLOADS_PATH . "/xml/facturas/{$facturaId}_firmado.xml";
        
        if (!file_exists($xmlPath)) {
            return $this->emitirFactura($facturaId);
        }
        
        $xmlFirmado = file_get_contents($xmlPath);
        
        // Reenviar al SRI
        $resultado = $this->sriClient->procesarComprobante($xmlFirmado);
        
        // Actualizar estado
        if ($resultado['success']) {
            $this->db->update('facturas', [
                'estado_sri' => 'autorizada',
                'numero_autorizacion' => $resultado['numero_autorizacion'],
                'fecha_autorizacion' => $resultado['fecha_autorizacion'],
                'xml_autorizado' => 1
            ], 'id = :id', [':id' => $facturaId]);
            
            if (!empty($resultado['comprobante'])) {
                $this->guardarXml($facturaId, 'factura', $resultado['comprobante'], 'autorizado');
            }
        } else {
            $estadoSri = $resultado['estado'] === 'EN PROCESO' ? 'pendiente' : 'rechazada';
            $this->db->update('facturas', ['estado_sri' => $estadoSri], 'id = :id', [':id' => $facturaId]);
        }
        
        $this->guardarLogSri($facturaId, 'factura', $resultado['estado'], $resultado);
        
        return $resultado;
    }
    
    /**
     * Consultar estado de factura en SRI
     */
    public function consultarEstadoFactura(int $facturaId): array
    {
        $factura = $this->db->query("
            SELECT clave_acceso FROM facturas WHERE id = :id AND empresa_id = :empresa_id
        ")->fetch([':id' => $facturaId, ':empresa_id' => $this->auth->empresaId()]);
        
        if (!$factura || empty($factura['clave_acceso'])) {
            return ['success' => false, 'mensaje' => 'Factura no encontrada o sin clave de acceso'];
        }
        
        return $this->sriClient->consultarAutorizacion($factura['clave_acceso']);
    }
    
    /**
     * Obtener datos completos de factura para XML
     */
    private function obtenerDatosFactura(int $facturaId): ?array
    {
        // Factura principal
        $factura = $this->db->query("
            SELECT f.*,
                   c.razon_social as cliente_razon_social,
                   c.identificacion as cliente_identificacion,
                   c.direccion as cliente_direccion,
                   c.email as cliente_email,
                   ti.codigo as cliente_tipo_identificacion_codigo
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            WHERE f.id = :id AND f.empresa_id = :empresa_id
        ")->fetch([':id' => $facturaId, ':empresa_id' => $this->auth->empresaId()]);
        
        if (!$factura) {
            return null;
        }
        
        // Estructurar datos del cliente
        $factura['cliente'] = [
            'razon_social' => $factura['cliente_razon_social'],
            'identificacion' => $factura['cliente_identificacion'],
            'direccion' => $factura['cliente_direccion'],
            'email' => $factura['cliente_email'],
            'tipo_identificacion_codigo' => $factura['cliente_tipo_identificacion_codigo']
        ];
        
        // Detalles
        $factura['detalles'] = $this->db->query("
            SELECT fd.*,
                   fd.codigo_principal,
                   fd.descripcion,
                   fd.cantidad,
                   fd.precio_unitario,
                   fd.descuento,
                   fd.precio_total_sin_impuesto
            FROM factura_detalles fd
            WHERE fd.factura_id = :factura_id
            ORDER BY fd.orden
        ")->fetchAll([':factura_id' => $facturaId]);
        
        // Impuestos por detalle
        foreach ($factura['detalles'] as &$detalle) {
            $detalle['impuestos'] = $this->db->query("
                SELECT fdi.*, ci.codigo, ci.codigo_porcentaje
                FROM factura_detalle_impuestos fdi
                JOIN cat_impuestos ci ON fdi.impuesto_id = ci.id
                WHERE fdi.factura_detalle_id = :detalle_id
            ")->fetchAll([':detalle_id' => $detalle['id']]);
        }
        
        // Impuestos totales
        $factura['impuestos'] = $this->db->query("
            SELECT fi.*, ci.codigo, ci.codigo_porcentaje
            FROM factura_impuestos fi
            JOIN cat_impuestos ci ON fi.impuesto_id = ci.id
            WHERE fi.factura_id = :factura_id
        ")->fetchAll([':factura_id' => $facturaId]);
        
        // Formas de pago
        $factura['formas_pago'] = $this->db->query("
            SELECT ffp.*, cfp.codigo
            FROM factura_formas_pago ffp
            JOIN cat_formas_pago cfp ON ffp.forma_pago_id = cfp.id
            WHERE ffp.factura_id = :factura_id
        ")->fetchAll([':factura_id' => $facturaId]);
        
        // Información adicional
        $factura['info_adicional'] = $this->db->query("
            SELECT nombre, valor FROM factura_info_adicional WHERE factura_id = :factura_id
        ")->fetchAll([':factura_id' => $facturaId]);
        
        // Agregar email del cliente como info adicional si existe
        if (!empty($factura['cliente']['email'])) {
            $factura['info_adicional'][] = [
                'nombre' => 'Email',
                'valor' => $factura['cliente']['email']
            ];
        }
        
        return $factura;
    }
    
    /**
     * Generar clave de acceso
     */
    private function generarClaveAcceso(array $factura): string
    {
        $fecha = date('dmY', strtotime($factura['fecha_emision']));
        $tipoComprobante = '01'; // Factura
        $ruc = $this->empresa['ruc'];
        $ambiente = $this->empresa['ambiente_sri'];
        $serie = $this->establecimiento['codigo'] . $this->puntoEmision['codigo'];
        $secuencial = str_pad($factura['secuencial'], 9, '0', STR_PAD_LEFT);
        $codigoNumerico = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1';
        
        $clave = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . $secuencial . $codigoNumerico . $tipoEmision;
        
        // Calcular dígito verificador (módulo 11)
        $digitoVerificador = $this->calcularModulo11($clave);
        
        return $clave . $digitoVerificador;
    }
    
    /**
     * Calcular módulo 11
     */
    private function calcularModulo11(string $cadena): int
    {
        $coeficientes = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        $j = 0;
        
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int)$cadena[$i] * $coeficientes[$j];
            $j = ($j + 1) % 6;
        }
        
        $residuo = $suma % 11;
        $resultado = 11 - $residuo;
        
        if ($resultado == 11) return 0;
        if ($resultado == 10) return 1;
        
        return $resultado;
    }
    
    /**
     * Guardar XML en el sistema de archivos
     */
    private function guardarXml(int $documentoId, string $tipo, string $xml, string $estado): void
    {
        $dir = UPLOADS_PATH . "/xml/{$tipo}s";
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filename = "{$documentoId}_{$estado}.xml";
        file_put_contents("{$dir}/{$filename}", $xml);
    }
    
    /**
     * Guardar log de interacción con SRI
     */
    private function guardarLogSri(int $documentoId, string $tipo, string $estado, array $respuesta): void
    {
        $this->db->insert('log_sri', [
            'empresa_id' => $this->auth->empresaId(),
            'tipo_documento' => $tipo,
            'documento_id' => $documentoId,
            'estado' => $estado,
            'mensaje' => $respuesta['mensaje'] ?? '',
            'respuesta_completa' => json_encode($respuesta),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Obtener información de la firma
     */
    public function getInfoFirma(): ?array
    {
        try {
            $this->cargarFirma();
            return $this->firma->getInfo();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Verificar configuración completa
     */
    public function verificarConfiguracion(): array
    {
        $errores = [];
        
        if (empty($this->empresa['ruc']) || strlen($this->empresa['ruc']) !== 13) {
            $errores[] = 'RUC de empresa no válido';
        }
        
        if (empty($this->empresa['direccion_matriz'])) {
            $errores[] = 'Dirección matriz no configurada';
        }
        
        if (empty($this->empresa['firma_electronica_path'])) {
            $errores[] = 'Firma electrónica no configurada';
        } else {
            try {
                $this->cargarFirma();
                if ($this->firma->proximoAVencer(7)) {
                    $errores[] = 'La firma electrónica vence en menos de 7 días';
                }
            } catch (Exception $e) {
                $errores[] = 'Error en firma electrónica: ' . $e->getMessage();
            }
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'ambiente' => $this->empresa['ambiente_sri'] === '2' ? 'Producción' : 'Pruebas'
        ];
    }
}
