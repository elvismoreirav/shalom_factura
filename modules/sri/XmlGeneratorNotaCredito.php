<?php
/**
 * SHALOM FACTURA - Generador de XML para NOTAS DE CRÉDITO
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;

class XmlGeneratorNotaCredito extends XmlGeneratorBase
{
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
        $root->appendChild($this->crearDetallesNotaCredito($notaCredito['detalles']));

        if (!empty($notaCredito['info_adicional'])) {
            $root->appendChild($this->crearInfoAdicional($notaCredito['info_adicional']));
        }

        $xml = $this->dom->saveXML();
        @file_put_contents(sys_get_temp_dir() . "/sri_xml_nc_" . substr($notaCredito['clave_acceso'] ?? 'gen', -6) . ".xml", $xml);
        return $xml;
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

        $info->appendChild($totalConImpuestos);
        $this->addElement($info, 'motivo', $this->limpiarTexto($notaCredito['motivo'] ?? 'Devolución'));

        return $info;
    }

    private function crearDetallesNotaCredito(array $detalles): DOMElement
    {
        $detallesEl = $this->dom->createElement('detalles');
        foreach ($detalles as $i => $det) {
            $dEl = $this->dom->createElement('detalle');
            $this->addElement($dEl, 'codigoInterno', $det['codigo_principal'] ?? 'PROD' . ($i + 1));
            if (!empty($det['codigo_auxiliar'])) {
                $this->addElement($dEl, 'codigoAdicional', $det['codigo_auxiliar']);
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
}
