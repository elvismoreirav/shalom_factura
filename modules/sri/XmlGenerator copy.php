<?php
/**
 * SHALOM FACTURA - Generador de XML para SRI
 * Versión: 2.1 - CORREGIDO: Manejo de valores nulos y debugging
 * 
 * CAMBIOS:
 * - addElement(): Manejo seguro de valores nulos
 * - formatNumber(): Manejo de valores nulos
 * - limpiarTexto(): Manejo de valores nulos
 * - Validación de datos antes de generar XML
 * - Debug: Guarda XML generado en /tmp para análisis
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;

class XmlGenerator
{
    private DOMDocument $dom;
    private array $empresa;
    private array $establecimiento;
    private array $puntoEmision;
    
    // Códigos SRI
    const TIPO_EMISION_NORMAL = '1';
    const AMBIENTE_PRUEBAS = '1';
    const AMBIENTE_PRODUCCION = '2';
    
    const TIPOS_COMPROBANTE = [
        'FACTURA' => '01',
        'LIQUIDACION_COMPRA' => '03',
        'NOTA_CREDITO' => '04',
        'NOTA_DEBITO' => '05',
        'GUIA_REMISION' => '06',
        'RETENCION' => '07'
    ];
    
    public function __construct(array $empresa, array $establecimiento, array $puntoEmision)
    {
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->puntoEmision = $puntoEmision;
        
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }
    
    /**
     * Generar XML de Factura
     */
    public function generarFactura(array $factura): string
    {
        // DEBUG: Log de datos recibidos
        $this->logDebug('FACTURA_DATOS_ENTRADA', [
            'clave_acceso' => $factura['clave_acceso'] ?? 'NO_EXISTE',
            'secuencial' => $factura['secuencial'] ?? 'NO_EXISTE',
            'fecha_emision' => $factura['fecha_emision'] ?? 'NO_EXISTE',
            'cliente' => $factura['cliente'] ?? 'NO_EXISTE',
            'totales' => $factura['totales'] ?? 'NO_EXISTE',
            'detalles_count' => isset($factura['detalles']) ? count($factura['detalles']) : 0,
            'formas_pago_count' => isset($factura['formas_pago']) ? count($factura['formas_pago']) : 0
        ]);
        
        // Validar datos obligatorios
        $this->validarDatosFactura($factura);
        
        // Resetear DOM para cada factura
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        
        // Elemento raíz
        $root = $this->dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.1.0');
        $this->dom->appendChild($root);
        
        // Información tributaria
        $infoTributaria = $this->crearInfoTributaria($factura, 'FACTURA');
        $root->appendChild($infoTributaria);
        
        // Información de la factura
        $infoFactura = $this->crearInfoFactura($factura);
        $root->appendChild($infoFactura);
        
        // Detalles
        $detalles = $this->crearDetalles($factura['detalles']);
        $root->appendChild($detalles);
        
        // Información adicional (opcional)
        if (!empty($factura['info_adicional'])) {
            $infoAdicional = $this->crearInfoAdicional($factura['info_adicional']);
            $root->appendChild($infoAdicional);
        }
        
        $xml = $this->dom->saveXML();
        
        // DEBUG: Guardar XML generado
        $this->guardarXmlDebug($xml, $factura['clave_acceso'] ?? 'sin_clave');
        
        return $xml;
    }
    
    /**
     * Validar datos obligatorios de factura
     */
    private function validarDatosFactura(array $factura): void
    {
        $errores = [];
        
        if (empty($factura['clave_acceso'])) {
            $errores[] = 'Falta clave_acceso';
        }
        if (empty($factura['secuencial'])) {
            $errores[] = 'Falta secuencial';
        }
        if (empty($factura['fecha_emision'])) {
            $errores[] = 'Falta fecha_emision';
        }
        if (empty($factura['cliente'])) {
            $errores[] = 'Falta información del cliente';
        } else {
            if (empty($factura['cliente']['identificacion'])) {
                $errores[] = 'Falta identificacion del cliente';
            }
            if (empty($factura['cliente']['razon_social'])) {
                $errores[] = 'Falta razon_social del cliente';
            }
            if (empty($factura['cliente']['tipo_identificacion_codigo'])) {
                $errores[] = 'Falta tipo_identificacion_codigo del cliente';
            }
        }
        if (empty($factura['detalles']) || !is_array($factura['detalles'])) {
            $errores[] = 'Faltan detalles de la factura';
        }
        
        if (!empty($errores)) {
            $this->logDebug('FACTURA_VALIDACION_ERROR', $errores);
            throw new \Exception('Datos de factura incompletos: ' . implode(', ', $errores));
        }
    }
    
    /**
     * DEBUG: Guardar XML para análisis
     */
    private function guardarXmlDebug(string $xml, string $identificador): void
    {
        $logDir = sys_get_temp_dir();
        $filename = $logDir . '/sri_xml_' . date('Y-m-d_His') . '_' . substr($identificador, -10) . '.xml';
        file_put_contents($filename, $xml);
        error_log("SRI XML Debug guardado en: $filename");
    }
    
    /**
     * DEBUG: Log de datos
     */
    private function logDebug(string $tipo, $data): void
    {
        $logFile = sys_get_temp_dir() . '/sri_debug_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logContent = "\n" . str_repeat('=', 80) . "\n";
        $logContent .= "[$timestamp] XML_GENERATOR - $tipo\n";
        $logContent .= str_repeat('-', 80) . "\n";
        $logContent .= print_r($data, true) . "\n";
        file_put_contents($logFile, $logContent, FILE_APPEND);
    }
    
    /**
     * Crear sección infoTributaria
     */
    private function crearInfoTributaria(array $documento, string $tipoComprobante): DOMElement
    {
        $info = $this->dom->createElement('infoTributaria');
        
        $this->addElement($info, 'ambiente', $this->empresa['ambiente_sri'] ?? '1');
        $this->addElement($info, 'tipoEmision', self::TIPO_EMISION_NORMAL);
        $this->addElement($info, 'razonSocial', $this->limpiarTexto($this->empresa['razon_social'] ?? ''));
        
        if (!empty($this->empresa['nombre_comercial'])) {
            $this->addElement($info, 'nombreComercial', $this->limpiarTexto($this->empresa['nombre_comercial']));
        }
        
        $this->addElement($info, 'ruc', $this->empresa['ruc'] ?? '');
        $this->addElement($info, 'claveAcceso', $documento['clave_acceso'] ?? '');
        $this->addElement($info, 'codDoc', self::TIPOS_COMPROBANTE[$tipoComprobante] ?? '01');
        $this->addElement($info, 'estab', $this->establecimiento['codigo'] ?? '001');
        $this->addElement($info, 'ptoEmi', $this->puntoEmision['codigo'] ?? '001');
        $this->addElement($info, 'secuencial', str_pad($documento['secuencial'] ?? '1', 9, '0', STR_PAD_LEFT));
        $this->addElement($info, 'dirMatriz', $this->limpiarTexto($this->empresa['direccion_matriz'] ?? $this->empresa['direccion'] ?? 'S/N'));
        
        // Agente de retención (opcional)
        if (!empty($this->empresa['agente_retencion'])) {
            $this->addElement($info, 'agenteRetencion', $this->empresa['agente_retencion']);
        }
        
        // Contribuyente RIMPE (opcional)
        if (!empty($this->empresa['contribuyente_rimpe'])) {
            $this->addElement($info, 'contribuyenteRimpe', $this->empresa['contribuyente_rimpe']);
        }
        
        return $info;
    }
    
    /**
     * Crear sección infoFactura
     */
    private function crearInfoFactura(array $factura): DOMElement
    {
        $info = $this->dom->createElement('infoFactura');
        
        // Fecha de emisión (obligatorio)
        $fechaEmision = $factura['fecha_emision'] ?? date('Y-m-d');
        $this->addElement($info, 'fechaEmision', date('d/m/Y', strtotime($fechaEmision)));
        
        // Dirección establecimiento (obligatorio)
        $dirEstab = $this->establecimiento['direccion'] ?? $this->empresa['direccion'] ?? 'S/N';
        $this->addElement($info, 'dirEstablecimiento', $this->limpiarTexto($dirEstab));
        
        // Contribuyente especial (opcional)
        if (!empty($this->empresa['contribuyente_especial'])) {
            $this->addElement($info, 'contribuyenteEspecial', $this->empresa['contribuyente_especial']);
        }
        
        // Obligado a llevar contabilidad (obligatorio)
        $this->addElement($info, 'obligadoContabilidad', $this->empresa['obligado_contabilidad'] ?? 'NO');
        
        // Tipo identificación comprador (obligatorio)
        $tipoIdComprador = $factura['cliente']['tipo_identificacion_codigo'] ?? '05';
        $this->addElement($info, 'tipoIdentificacionComprador', $tipoIdComprador);
        
        // Guía de remisión (opcional)
        if (!empty($factura['guia_remision'])) {
            $this->addElement($info, 'guiaRemision', $factura['guia_remision']);
        }
        
        // Razón social comprador (obligatorio)
        $razonSocial = $factura['cliente']['razon_social'] ?? 'CONSUMIDOR FINAL';
        $this->addElement($info, 'razonSocialComprador', $this->limpiarTexto($razonSocial));
        
        // Identificación comprador (obligatorio)
        $identificacion = $factura['cliente']['identificacion'] ?? '9999999999999';
        $this->addElement($info, 'identificacionComprador', $identificacion);
        
        // Dirección comprador (opcional pero recomendado)
        if (!empty($factura['cliente']['direccion'])) {
            $this->addElement($info, 'direccionComprador', $this->limpiarTexto($factura['cliente']['direccion']));
        }
        
        // Obtener totales (soportar ambas estructuras)
        $totales = $factura['totales'] ?? $factura;
        
        // Total sin impuestos (obligatorio)
        $totalSinImpuestos = $totales['subtotal_sin_impuestos'] ?? 0;
        $this->addElement($info, 'totalSinImpuestos', $this->formatNumber($totalSinImpuestos));
        
        // Total descuento (obligatorio)
        $totalDescuento = $totales['total_descuento'] ?? 0;
        $this->addElement($info, 'totalDescuento', $this->formatNumber($totalDescuento));
        
        // Total con impuestos (obligatorio)
        $totalConImpuestos = $this->dom->createElement('totalConImpuestos');
        
        // Generar impuestos
        $subtotalIva = (float)($totales['subtotal_iva'] ?? $totales['subtotal_sin_impuestos'] ?? 0);
        $subtotalIva0 = (float)($totales['subtotal_iva_0'] ?? 0);
        $totalIva = (float)($totales['total_iva'] ?? 0);
        
        // DEBUG: Log de totales
        $this->logDebug('FACTURA_TOTALES', [
            'subtotal_sin_impuestos' => $totalSinImpuestos,
            'subtotal_iva' => $subtotalIva,
            'subtotal_iva_0' => $subtotalIva0,
            'total_iva' => $totalIva,
            'total' => $totales['total'] ?? 0
        ]);
        
        // Siempre debe haber al menos un totalImpuesto
        $tieneImpuestos = false;
        
        // IVA 15% (código porcentaje 4)
        if ($subtotalIva > 0) {
            $totalImpuesto = $this->dom->createElement('totalImpuesto');
            $this->addElement($totalImpuesto, 'codigo', '2');
            $this->addElement($totalImpuesto, 'codigoPorcentaje', '4');
            $this->addElement($totalImpuesto, 'baseImponible', $this->formatNumber($subtotalIva));
            $this->addElement($totalImpuesto, 'valor', $this->formatNumber($totalIva));
            $totalConImpuestos->appendChild($totalImpuesto);
            $tieneImpuestos = true;
        }
        
        // IVA 0% (código porcentaje 0)
        if ($subtotalIva0 > 0) {
            $totalImpuesto = $this->dom->createElement('totalImpuesto');
            $this->addElement($totalImpuesto, 'codigo', '2');
            $this->addElement($totalImpuesto, 'codigoPorcentaje', '0');
            $this->addElement($totalImpuesto, 'baseImponible', $this->formatNumber($subtotalIva0));
            $this->addElement($totalImpuesto, 'valor', '0.00');
            $totalConImpuestos->appendChild($totalImpuesto);
            $tieneImpuestos = true;
        }
        
        // Si no hay impuestos definidos, crear uno por defecto con IVA 15%
        if (!$tieneImpuestos) {
            $totalImpuesto = $this->dom->createElement('totalImpuesto');
            $this->addElement($totalImpuesto, 'codigo', '2');
            $this->addElement($totalImpuesto, 'codigoPorcentaje', '4');
            $this->addElement($totalImpuesto, 'baseImponible', $this->formatNumber($totalSinImpuestos));
            $this->addElement($totalImpuesto, 'valor', $this->formatNumber($totalSinImpuestos * 0.15));
            $totalConImpuestos->appendChild($totalImpuesto);
        }
        
        $info->appendChild($totalConImpuestos);
        
        // Propina (obligatorio, puede ser 0)
        $this->addElement($info, 'propina', $this->formatNumber($totales['propina'] ?? 0));
        
        // Importe total (obligatorio)
        $this->addElement($info, 'importeTotal', $this->formatNumber($totales['total'] ?? 0));
        
        // Moneda (obligatorio)
        $this->addElement($info, 'moneda', 'DOLAR');
        
        // Pagos (obligatorio)
        $pagos = $this->dom->createElement('pagos');
        
        if (!empty($factura['formas_pago']) && is_array($factura['formas_pago'])) {
            foreach ($factura['formas_pago'] as $pago) {
                $pagoEl = $this->dom->createElement('pago');
                $formaPago = $pago['forma_pago'] ?? $pago['codigo'] ?? '01';
                $totalPago = $pago['total'] ?? $pago['valor'] ?? $totales['total'] ?? 0;
                
                $this->addElement($pagoEl, 'formaPago', $formaPago);
                $this->addElement($pagoEl, 'total', $this->formatNumber($totalPago));
                
                if (!empty($pago['plazo']) && $pago['plazo'] > 0) {
                    $this->addElement($pagoEl, 'plazo', (string)$pago['plazo']);
                    $this->addElement($pagoEl, 'unidadTiempo', $pago['unidad_tiempo'] ?? 'dias');
                }
                
                $pagos->appendChild($pagoEl);
            }
        } else {
            // Pago por defecto
            $pagoEl = $this->dom->createElement('pago');
            $this->addElement($pagoEl, 'formaPago', '01');
            $this->addElement($pagoEl, 'total', $this->formatNumber($totales['total'] ?? 0));
            $pagos->appendChild($pagoEl);
        }
        
        $info->appendChild($pagos);
        
        return $info;
    }
    
    /**
     * Crear sección detalles
     */
    private function crearDetalles(array $detalles): DOMElement
    {
        $detallesEl = $this->dom->createElement('detalles');
        
        foreach ($detalles as $index => $detalle) {
            // DEBUG: Log de cada detalle
            $this->logDebug("DETALLE_$index", $detalle);
            
            $detalleEl = $this->dom->createElement('detalle');
            
            // Código principal (obligatorio)
            $codigoPrincipal = $detalle['codigo_principal'] ?? $detalle['codigo'] ?? 'PROD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            $this->addElement($detalleEl, 'codigoPrincipal', $codigoPrincipal);
            
            // Código auxiliar (opcional)
            if (!empty($detalle['codigo_auxiliar'])) {
                $this->addElement($detalleEl, 'codigoAuxiliar', $detalle['codigo_auxiliar']);
            }
            
            // Descripción (obligatorio)
            $descripcion = $detalle['descripcion'] ?? 'Producto/Servicio';
            $this->addElement($detalleEl, 'descripcion', $this->limpiarTexto($descripcion));
            
            // Cantidad (obligatorio)
            $cantidad = $detalle['cantidad'] ?? 1;
            $this->addElement($detalleEl, 'cantidad', $this->formatNumber($cantidad, 6));
            
            // Precio unitario (obligatorio)
            $precioUnitario = $detalle['precio_unitario'] ?? 0;
            $this->addElement($detalleEl, 'precioUnitario', $this->formatNumber($precioUnitario, 6));
            
            // Descuento (obligatorio)
            $descuento = $detalle['descuento'] ?? 0;
            $this->addElement($detalleEl, 'descuento', $this->formatNumber($descuento));
            
            // Precio total sin impuesto (obligatorio)
            $precioTotalSinImpuesto = $detalle['precio_total_sin_impuesto'] ?? $detalle['subtotal'] ?? ($cantidad * $precioUnitario - $descuento);
            $this->addElement($detalleEl, 'precioTotalSinImpuesto', $this->formatNumber($precioTotalSinImpuesto));
            
            // Impuestos del detalle (obligatorio)
            $impuestosEl = $this->dom->createElement('impuestos');
            
            if (!empty($detalle['impuestos']) && is_array($detalle['impuestos'])) {
                foreach ($detalle['impuestos'] as $impuesto) {
                    $impuestoEl = $this->dom->createElement('impuesto');
                    $this->addElement($impuestoEl, 'codigo', $impuesto['codigo'] ?? '2');
                    $this->addElement($impuestoEl, 'codigoPorcentaje', $impuesto['codigo_porcentaje'] ?? '4');
                    $this->addElement($impuestoEl, 'tarifa', $this->formatNumber($impuesto['tarifa'] ?? 15));
                    $this->addElement($impuestoEl, 'baseImponible', $this->formatNumber($impuesto['base_imponible'] ?? $precioTotalSinImpuesto));
                    $this->addElement($impuestoEl, 'valor', $this->formatNumber($impuesto['valor'] ?? ($precioTotalSinImpuesto * 0.15)));
                    $impuestosEl->appendChild($impuestoEl);
                }
            } else {
                // Impuesto por defecto (IVA 15%)
                $impuestoEl = $this->dom->createElement('impuesto');
                $this->addElement($impuestoEl, 'codigo', '2');
                $this->addElement($impuestoEl, 'codigoPorcentaje', '4');
                $this->addElement($impuestoEl, 'tarifa', '15.00');
                $this->addElement($impuestoEl, 'baseImponible', $this->formatNumber($precioTotalSinImpuesto));
                $this->addElement($impuestoEl, 'valor', $this->formatNumber($precioTotalSinImpuesto * 0.15));
                $impuestosEl->appendChild($impuestoEl);
            }
            
            $detalleEl->appendChild($impuestosEl);
            $detallesEl->appendChild($detalleEl);
        }
        
        return $detallesEl;
    }
    
    /**
     * Crear información adicional
     */
    private function crearInfoAdicional(array $campos): DOMElement
    {
        $infoAdicional = $this->dom->createElement('infoAdicional');
        
        foreach ($campos as $campo) {
            if (!empty($campo['nombre']) && isset($campo['valor'])) {
                $valor = $this->limpiarTexto((string)$campo['valor']);
                if (!empty($valor)) {
                    $campoAdicional = $this->dom->createElement('campoAdicional', $valor);
                    $campoAdicional->setAttribute('nombre', $this->limpiarTexto($campo['nombre']));
                    $infoAdicional->appendChild($campoAdicional);
                }
            }
        }
        
        return $infoAdicional;
    }
    
    /**
     * Agregar elemento al DOM - CORREGIDO para manejar nulos
     */
    private function addElement(DOMElement $parent, string $name, ?string $value): void
    {
        // Asegurar que value nunca sea null
        $value = $value ?? '';
        
        // Escapar caracteres especiales XML de forma segura
        $value = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8', false);
        
        // Crear elemento
        $element = $this->dom->createElement($name);
        $element->appendChild($this->dom->createTextNode($value));
        $parent->appendChild($element);
    }
    
    /**
     * Formatear número para XML - CORREGIDO para manejar nulos
     */
    private function formatNumber($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }
        return number_format((float)$value, $decimals, '.', '');
    }
    
    /**
     * Limpiar texto para XML - CORREGIDO para manejar nulos
     */
    private function limpiarTexto(?string $texto): string
    {
        if ($texto === null) {
            return '';
        }
        
        // Remover caracteres especiales que pueden causar problemas en XML
        $texto = preg_replace('/[^\p{L}\p{N}\s\.\,\-\_\@\#\$\%\&\(\)\[\]\{\}\:\;\'\"\!\?\+\=\/\\\\]/u', '', $texto);
        $texto = trim($texto ?? '');
        
        // Limitar longitud
        return mb_substr($texto, 0, 300);
    }
    
    /**
     * Generar XML de Nota de Crédito
     */
    public function generarNotaCredito(array $notaCredito): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        
        $root = $this->dom->createElement('notaCredito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $this->dom->appendChild($root);
        
        $infoTributaria = $this->crearInfoTributaria($notaCredito, 'NOTA_CREDITO');
        $root->appendChild($infoTributaria);
        
        $infoNotaCredito = $this->crearInfoNotaCredito($notaCredito);
        $root->appendChild($infoNotaCredito);
        
        $detalles = $this->crearDetalles($notaCredito['detalles']);
        $root->appendChild($detalles);
        
        if (!empty($notaCredito['info_adicional'])) {
            $infoAdicional = $this->crearInfoAdicional($notaCredito['info_adicional']);
            $root->appendChild($infoAdicional);
        }
        
        $xml = $this->dom->saveXML();
        $this->guardarXmlDebug($xml, $notaCredito['clave_acceso'] ?? 'nc_sin_clave');
        
        return $xml;
    }
    
    /**
     * Crear sección infoNotaCredito
     */
    private function crearInfoNotaCredito(array $notaCredito): DOMElement
    {
        $info = $this->dom->createElement('infoNotaCredito');
        
        $this->addElement($info, 'fechaEmision', date('d/m/Y', strtotime($notaCredito['fecha_emision'] ?? 'now')));
        $this->addElement($info, 'dirEstablecimiento', $this->limpiarTexto($this->establecimiento['direccion'] ?? 'S/N'));
        $this->addElement($info, 'tipoIdentificacionComprador', $notaCredito['cliente']['tipo_identificacion_codigo'] ?? '05');
        $this->addElement($info, 'razonSocialComprador', $this->limpiarTexto($notaCredito['cliente']['razon_social'] ?? 'CONSUMIDOR FINAL'));
        $this->addElement($info, 'identificacionComprador', $notaCredito['cliente']['identificacion'] ?? '9999999999999');
        
        if (!empty($this->empresa['contribuyente_especial'])) {
            $this->addElement($info, 'contribuyenteEspecial', $this->empresa['contribuyente_especial']);
        }
        
        $this->addElement($info, 'obligadoContabilidad', $this->empresa['obligado_contabilidad'] ?? 'NO');
        $this->addElement($info, 'codDocModificado', '01');
        $this->addElement($info, 'numDocModificado', $notaCredito['documento_modificado']['numero'] ?? '');
        $this->addElement($info, 'fechaEmisionDocSustento', date('d/m/Y', strtotime($notaCredito['documento_modificado']['fecha'] ?? 'now')));
        $this->addElement($info, 'totalSinImpuestos', $this->formatNumber($notaCredito['totales']['subtotal_sin_impuestos'] ?? 0));
        $this->addElement($info, 'valorModificacion', $this->formatNumber($notaCredito['totales']['total'] ?? 0));
        $this->addElement($info, 'moneda', 'DOLAR');
        
        // Total con impuestos
        $totalConImpuestos = $this->dom->createElement('totalConImpuestos');
        $totalImpuesto = $this->dom->createElement('totalImpuesto');
        $this->addElement($totalImpuesto, 'codigo', '2');
        $this->addElement($totalImpuesto, 'codigoPorcentaje', '4');
        $this->addElement($totalImpuesto, 'baseImponible', $this->formatNumber($notaCredito['totales']['subtotal_iva'] ?? $notaCredito['totales']['subtotal_sin_impuestos'] ?? 0));
        $this->addElement($totalImpuesto, 'valor', $this->formatNumber($notaCredito['totales']['total_iva'] ?? 0));
        $totalConImpuestos->appendChild($totalImpuesto);
        $info->appendChild($totalConImpuestos);
        
        $this->addElement($info, 'motivo', $this->limpiarTexto($notaCredito['motivo'] ?? 'Devolución'));
        
        return $info;
    }
    
    /**
     * Validar XML contra esquema XSD
     */
    public function validarXml(string $xml, string $tipoComprobante): array
    {
        libxml_use_internal_errors(true);
        
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = "Línea {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
            return ['valid' => false, 'errors' => $errors];
        }
        
        $xsdPath = __DIR__ . '/xsd/' . strtolower($tipoComprobante) . '.xsd';
        
        if (!file_exists($xsdPath)) {
            return ['valid' => true, 'errors' => [], 'warning' => 'Esquema XSD no encontrado, no se pudo validar'];
        }
        
        if ($dom->schemaValidate($xsdPath)) {
            return ['valid' => true, 'errors' => []];
        }
        
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = "Línea {$error->line}: {$error->message}";
        }
        
        libxml_clear_errors();
        
        return ['valid' => false, 'errors' => $errors];
    }
}
