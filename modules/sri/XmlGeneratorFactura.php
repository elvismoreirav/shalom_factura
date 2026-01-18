<?php
/**
 * SHALOM FACTURA - Generador de XML para FACTURAS
 * Versión: 11.0 - FINAL: 2 Decimales y Atributos Limpios
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;

class XmlGeneratorFactura extends XmlGeneratorBase
{
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
        $root->appendChild($this->crearDetallesFactura($factura['detalles']));

        if (!empty($factura['info_adicional'])) {
            $root->appendChild($this->crearInfoAdicional($factura['info_adicional']));
        }

        $xml = $this->dom->saveXML();
        @file_put_contents(sys_get_temp_dir() . "/sri_xml_gen_" . substr($factura['clave_acceso'] ?? 'gen', -6) . ".xml", $xml);
        return $xml;
    }

    private function crearInfoFactura(array $factura): DOMElement
    {
        $info = $this->dom->createElement('infoFactura');
        $this->addElement($info, 'fechaEmision', date('d/m/Y', strtotime($factura['fecha_emision'])));
        $this->addElement($info, 'dirEstablecimiento', $this->limpiarTexto($this->establecimiento['direccion']));
        if (!empty($this->empresa['contribuyente_especial'])) {
            $this->addElement($info, 'contribuyenteEspecial', $this->empresa['contribuyente_especial']);
        }
        $this->addElement($info, 'obligadoContabilidad', $this->empresa['obligado_contabilidad'] ?? 'NO');
        $this->addElement($info, 'tipoIdentificacionComprador', $factura['cliente']['tipo_identificacion_codigo']);
        if (!empty($factura['guia_remision'])) {
            $this->addElement($info, 'guiaRemision', $factura['guia_remision']);
        }
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
                    'valor' => $totales['total_iva']
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

    private function crearDetallesFactura(array $detalles): DOMElement
    {
        $detallesEl = $this->dom->createElement('detalles');
        foreach ($detalles as $i => $det) {
            $dEl = $this->dom->createElement('detalle');
            $this->addElement($dEl, 'codigoPrincipal', $det['codigo_principal'] ?? 'PROD' . ($i + 1));
            if (!empty($det['codigo_auxiliar'])) {
                $this->addElement($dEl, 'codigoAuxiliar', $det['codigo_auxiliar']);
            }
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

    private function validarDatosFactura($data): void
    {
        if (empty($data['clave_acceso']) || empty($data['detalles'])) {
            throw new \Exception("Datos incompletos XML");
        }
    }
}
