<?php
/**
 * SHALOM FACTURA - Generador de XML para SRI
 * Versión: 11.0 - FINAL: 2 Decimales y Atributos Limpios
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
    
    const TIPO_EMISION_NORMAL = '1';
    
    const TIPOS_COMPROBANTE = [
        'FACTURA' => '01', 'LIQUIDACION_COMPRA' => '03', 'NOTA_CREDITO' => '04', 
        'NOTA_DEBITO' => '05', 'GUIA_REMISION' => '06', 'RETENCION' => '07'
    ];
    
    public function __construct(array $empresa, array $establecimiento, array $puntoEmision)
    {
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->puntoEmision = $puntoEmision;
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }
    
    public function generarFactura(array $factura): string
    {
        $this->validarDatosFactura($factura);
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        
        $root = $this->dom->createElement('factura');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $this->dom->appendChild($root);
        
        $root->appendChild($this->crearInfoTributaria($factura, 'FACTURA'));
        $root->appendChild($this->crearInfoFactura($factura));
        $root->appendChild($this->crearDetalles($factura['detalles']));
        
        if (!empty($factura['info_adicional'])) {
            $root->appendChild($this->crearInfoAdicional($factura['info_adicional']));
        }
        
        $xml = $this->dom->saveXML();
        @file_put_contents(sys_get_temp_dir() . "/sri_xml_gen_" . substr($factura['clave_acceso'] ?? 'gen', -6) . ".xml", $xml);
        return $xml;
    }
    
    private function crearInfoTributaria(array $doc, string $tipo): DOMElement {
        $info = $this->dom->createElement('infoTributaria');
        $this->addElement($info, 'ambiente', $this->empresa['ambiente_sri'] ?? '1');
        $this->addElement($info, 'tipoEmision', self::TIPO_EMISION_NORMAL);
        $this->addElement($info, 'razonSocial', $this->limpiarTexto($this->empresa['razon_social']));
        if (!empty($this->empresa['nombre_comercial'])) $this->addElement($info, 'nombreComercial', $this->limpiarTexto($this->empresa['nombre_comercial']));
        $this->addElement($info, 'ruc', $this->empresa['ruc']);
        $this->addElement($info, 'claveAcceso', $doc['clave_acceso']);
        $this->addElement($info, 'codDoc', self::TIPOS_COMPROBANTE[$tipo] ?? '01');
        $this->addElement($info, 'estab', $this->establecimiento['codigo']);
        $this->addElement($info, 'ptoEmi', $this->puntoEmision['codigo']);
        $this->addElement($info, 'secuencial', str_pad($doc['secuencial'], 9, '0', STR_PAD_LEFT));
        $this->addElement($info, 'dirMatriz', $this->limpiarTexto($this->empresa['direccion_matriz']));
        if (!empty($this->empresa['agente_retencion'])) $this->addElement($info, 'agenteRetencion', $this->empresa['agente_retencion']);
        if (!empty($this->empresa['contribuyente_rimpe'])) $this->addElement($info, 'contribuyenteRimpe', $this->empresa['contribuyente_rimpe']);
        return $info;
    }
    
    private function crearInfoFactura(array $factura): DOMElement {
        $info = $this->dom->createElement('infoFactura');
        $this->addElement($info, 'fechaEmision', date('d/m/Y', strtotime($factura['fecha_emision'])));
        $this->addElement($info, 'dirEstablecimiento', $this->limpiarTexto($this->establecimiento['direccion']));
        if (!empty($this->empresa['contribuyente_especial'])) $this->addElement($info, 'contribuyenteEspecial', $this->empresa['contribuyente_especial']);
        $this->addElement($info, 'obligadoContabilidad', $this->empresa['obligado_contabilidad'] ?? 'NO');
        $this->addElement($info, 'tipoIdentificacionComprador', $factura['cliente']['tipo_identificacion_codigo']);
        if (!empty($factura['guia_remision'])) $this->addElement($info, 'guiaRemision', $factura['guia_remision']);
        $this->addElement($info, 'razonSocialComprador', $this->limpiarTexto($factura['cliente']['razon_social']));
        $this->addElement($info, 'identificacionComprador', $factura['cliente']['identificacion']);
        
        $totales = $factura['totales'];
        $this->addElement($info, 'totalSinImpuestos', $this->formatNumber($totales['subtotal_sin_impuestos']));
        $this->addElement($info, 'totalDescuento', $this->formatSmart($totales['total_descuento']));
        
        $totalConImpuestos = $this->dom->createElement('totalConImpuestos');
        $impuestosMap = [];
        foreach ($factura['detalles'] as $det) {
            foreach ($det['impuestos'] as $imp) {
                $key = $imp['codigo'] . '-' . $imp['codigo_porcentaje'];
                if (!isset($impuestosMap[$key])) {
                    $impuestosMap[$key] = ['codigo' => $imp['codigo'], 'codigoPorcentaje' => $imp['codigo_porcentaje'], 'baseImponible' => 0, 'valor' => 0];
                }
                $impuestosMap[$key]['baseImponible'] += $imp['base_imponible'];
                $impuestosMap[$key]['valor'] += $imp['valor'];
            }
        }
        
        if (empty($impuestosMap)) {
             if (($totales['subtotal_iva'] ?? 0) > 0) $impuestosMap['2-4'] = ['codigo'=>'2', 'codigoPorcentaje'=>'4', 'baseImponible'=>$totales['subtotal_iva'], 'valor'=>$totales['total_iva']];
             if (($totales['subtotal_iva_0'] ?? 0) > 0) $impuestosMap['2-0'] = ['codigo'=>'2', 'codigoPorcentaje'=>'0', 'baseImponible'=>$totales['subtotal_iva_0'], 'valor'=>0];
        }

        foreach ($impuestosMap as $imp) {
            $tImp = $this->dom->createElement('totalImpuesto');
            $this->addElement($tImp, 'codigo', $imp['codigo']);
            $this->addElement($tImp, 'codigoPorcentaje', $imp['codigoPorcentaje']);
            $this->addElement($tImp, 'baseImponible', $this->formatNumber($imp['baseImponible']));
            $this->addElement($tImp, 'valor', $this->formatNumber($imp['valor']));
            $totalConImpuestos->appendChild($tImp);
        }
        $info->appendChild($totalConImpuestos);
        
        $this->addElement($info, 'propina', $this->formatNumber($totales['propina']));
        $this->addElement($info, 'importeTotal', $this->formatSmart($totales['total']));
        $this->addElement($info, 'moneda', 'DOLAR');
        
        $pagos = $this->dom->createElement('pagos');
        if (!empty($factura['formas_pago'])) {
            foreach ($factura['formas_pago'] as $pago) {
                $pEl = $this->dom->createElement('pago');
                $this->addElement($pEl, 'formaPago', $pago['forma_pago'] ?? $pago['codigo'] ?? '01');
                $this->addElement($pEl, 'total', $this->formatSmart($pago['total']));
                if (!empty($pago['plazo']) && $pago['plazo'] > 0) {
                    $this->addElement($pEl, 'plazo', $this->formatSmart($pago['plazo']));
                    $this->addElement($pEl, 'unidadTiempo', $pago['unidad_tiempo'] ?? 'dias');
                }
                $pagos->appendChild($pEl);
            }
        } else {
            $pEl = $this->dom->createElement('pago');
            $this->addElement($pEl, 'formaPago', '01');
            $this->addElement($pEl, 'total', $this->formatSmart($totales['total']));
            $pagos->appendChild($pEl);
        }
        $info->appendChild($pagos);
        return $info;
    }

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

        $this->addElement($info, 'codDocModificado', $notaCredito['documento_modificado']['codigo'] ?? '01');
        $this->addElement($info, 'numDocModificado', $notaCredito['documento_modificado']['numero'] ?? '');
        $this->addElement($info, 'fechaEmisionDocSustento', date('d/m/Y', strtotime($notaCredito['documento_modificado']['fecha'] ?? 'now')));

        $totales = $notaCredito['totales'] ?? [];
        $this->addElement($info, 'totalSinImpuestos', $this->formatNumber($totales['subtotal_sin_impuestos'] ?? 0));
        $this->addElement($info, 'valorModificacion', $this->formatNumber($totales['total'] ?? 0));
        $this->addElement($info, 'moneda', 'DOLAR');

        $totalConImpuestos = $this->dom->createElement('totalConImpuestos');
        $impuestosMap = [];

        foreach ($notaCredito['detalles'] ?? [] as $det) {
            foreach ($det['impuestos'] ?? [] as $imp) {
                $key = $imp['codigo'] . '-' . $imp['codigo_porcentaje'];
                if (!isset($impuestosMap[$key])) {
                    $impuestosMap[$key] = [
                        'codigo' => $imp['codigo'],
                        'codigoPorcentaje' => $imp['codigo_porcentaje'],
                        'baseImponible' => 0,
                        'valor' => 0
                    ];
                }
                $impuestosMap[$key]['baseImponible'] += $imp['base_imponible'];
                $impuestosMap[$key]['valor'] += $imp['valor'];
            }
        }

        if (empty($impuestosMap)) {
            if (($totales['subtotal_iva'] ?? 0) > 0) {
                $impuestosMap['2-4'] = [
                    'codigo' => '2',
                    'codigoPorcentaje' => '4',
                    'baseImponible' => $totales['subtotal_iva'],
                    'valor' => $totales['total_iva'] ?? 0
                ];
            }
            if (($totales['subtotal_iva_0'] ?? 0) > 0) {
                $impuestosMap['2-0'] = [
                    'codigo' => '2',
                    'codigoPorcentaje' => '0',
                    'baseImponible' => $totales['subtotal_iva_0'],
                    'valor' => 0
                ];
            }
        }

        foreach ($impuestosMap as $imp) {
            $tImp = $this->dom->createElement('totalImpuesto');
            $this->addElement($tImp, 'codigo', $imp['codigo']);
            $this->addElement($tImp, 'codigoPorcentaje', $imp['codigoPorcentaje']);
            $this->addElement($tImp, 'baseImponible', $this->formatNumber($imp['baseImponible']));
            $this->addElement($tImp, 'valor', $this->formatNumber($imp['valor']));
            $totalConImpuestos->appendChild($tImp);
        }

        if ($totalConImpuestos->hasChildNodes()) {
            $info->appendChild($totalConImpuestos);
        }

        $this->addElement($info, 'motivo', $this->limpiarTexto($notaCredito['motivo'] ?? 'Devolución'));

        return $info;
    }
    
    private function crearDetalles(array $detalles): DOMElement {
        $detallesEl = $this->dom->createElement('detalles');
        foreach ($detalles as $i => $det) {
            $dEl = $this->dom->createElement('detalle');
            $this->addElement($dEl, 'codigoPrincipal', $det['codigo_principal'] ?? 'PROD'.($i+1));
            if (!empty($det['codigo_auxiliar'])) $this->addElement($dEl, 'codigoAuxiliar', $det['codigo_auxiliar']);
            $this->addElement($dEl, 'descripcion', $this->limpiarTexto($det['descripcion']));
            $this->addElement($dEl, 'cantidad', $this->formatNumber($det['cantidad'], 2)); 
            $this->addElement($dEl, 'precioUnitario', $this->formatNumber($det['precio_unitario'], 2));
            $this->addElement($dEl, 'descuento', $this->formatSmart($det['descuento'])); 
            $this->addElement($dEl, 'precioTotalSinImpuesto', $this->formatNumber($det['precio_total_sin_impuesto']));
            
            $impsEl = $this->dom->createElement('impuestos');
            foreach ($det['impuestos'] as $imp) {
                $iEl = $this->dom->createElement('impuesto');
                $this->addElement($iEl, 'codigo', $imp['codigo']);
                $this->addElement($iEl, 'codigoPorcentaje', $imp['codigo_porcentaje']);
                $this->addElement($iEl, 'tarifa', $this->formatNumber($imp['tarifa'], 2)); 
$this->addElement($iEl, 'baseImponible', $this->formatNumber($imp['base_imponible']));
                $this->addElement($iEl, 'valor', $this->formatNumber($imp['valor']));
                $impsEl->appendChild($iEl);
            }
            $dEl->appendChild($impsEl);
            $detallesEl->appendChild($dEl);
        }
        return $detallesEl;
    }
    
    private function crearInfoAdicional(array $campos): DOMElement {
        $infoAdicional = $this->dom->createElement('infoAdicional');
        foreach ($campos as $c) {
            if (!empty($c['nombre']) && isset($c['valor'])) {
                $ca = $this->dom->createElement('campoAdicional', $this->limpiarTexto($c['valor']));
                $ca->setAttribute('nombre', $this->limpiarAtributo($c['nombre']));
                $infoAdicional->appendChild($ca);
            }
        }
        return $infoAdicional;
    }
    
    private function formatNumber($val, $dec = 2): string { return number_format((float)($val ?? 0), $dec, '.', ''); }
    private function formatSmart($val): string {
        $v = (float)($val ?? 0);
        return (abs($v - round($v)) < 0.00001) ? number_format($v, 0, '.', '') : number_format($v, 2, '.', '');
    }
    private function limpiarTexto($str): string { return mb_substr(preg_replace('/[^\p{L}\p{N}\s\.\,\-\_\@]/u', ' ', trim($str ?? '')), 0, 300); }
    private function limpiarAtributo($str): string {
        $str = $this->limpiarTexto($str);
        $search = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'];
        $replace = ['a','e','i','o','u','A','E','I','O','U','n','N'];
        return str_replace(' ', '', str_replace($search, $replace, $str));
    }
    private function addElement($parent, $name, $val): void {
        $el = $this->dom->createElement($name);
        $el->appendChild($this->dom->createTextNode($val));
        $parent->appendChild($el);
    }
    private function validarDatosFactura($data): void {
        if (empty($data['clave_acceso']) || empty($data['detalles'])) throw new \Exception("Datos incompletos XML");
    }
    public function generarNotaCredito(array $notaCredito): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        $root = $this->dom->createElement('notaCredito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $this->dom->appendChild($root);

        $root->appendChild($this->crearInfoTributaria($notaCredito, 'NOTA_CREDITO'));
        $root->appendChild($this->crearInfoNotaCredito($notaCredito));
        $root->appendChild($this->crearDetalles($notaCredito['detalles']));

        if (!empty($notaCredito['info_adicional'])) {
            $root->appendChild($this->crearInfoAdicional($notaCredito['info_adicional']));
        }

        $xml = $this->dom->saveXML();
        @file_put_contents(sys_get_temp_dir() . "/sri_xml_nc_" . substr($notaCredito['clave_acceso'] ?? 'gen', -6) . ".xml", $xml);
        return $xml;
    }
    public function validarXml($xml, $tipo): array { return ['valid'=>true, 'errors'=>[]]; }
}
