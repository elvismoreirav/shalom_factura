<?php
/**
 * SHALOM FACTURA - Base de generadores XML para SRI
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;

abstract class XmlGeneratorBase
{
    protected DOMDocument $dom;
    protected array $empresa;
    protected array $establecimiento;
    protected array $puntoEmision;

    protected const TIPO_EMISION_NORMAL = '1';

    protected const TIPOS_COMPROBANTE = [
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

    protected function crearInfoTributaria(array $doc, string $tipo): DOMElement
    {
        $info = $this->dom->createElement('infoTributaria');
        $this->addElement($info, 'ambiente', $this->empresa['ambiente_sri'] ?? '1');
        $this->addElement($info, 'tipoEmision', self::TIPO_EMISION_NORMAL);
        $this->addElement($info, 'razonSocial', $this->limpiarTexto($this->empresa['razon_social']));
        if (!empty($this->empresa['nombre_comercial'])) {
            $this->addElement($info, 'nombreComercial', $this->limpiarTexto($this->empresa['nombre_comercial']));
        }
        $this->addElement($info, 'ruc', $this->empresa['ruc']);
        $this->addElement($info, 'claveAcceso', $doc['clave_acceso']);
        $this->addElement($info, 'codDoc', self::TIPOS_COMPROBANTE[$tipo] ?? '01');
        $this->addElement($info, 'estab', $this->establecimiento['codigo']);
        $this->addElement($info, 'ptoEmi', $this->puntoEmision['codigo']);
        $this->addElement($info, 'secuencial', str_pad($doc['secuencial'], 9, '0', STR_PAD_LEFT));
        $this->addElement($info, 'dirMatriz', $this->limpiarTexto($this->empresa['direccion_matriz']));
        if (!empty($this->empresa['agente_retencion'])) {
            $this->addElement($info, 'agenteRetencion', $this->empresa['agente_retencion']);
        }
        if (!empty($this->empresa['contribuyente_rimpe'])) {
            $this->addElement($info, 'contribuyenteRimpe', $this->empresa['contribuyente_rimpe']);
        }
        return $info;
    }

    protected function crearInfoAdicional(array $campos): DOMElement
    {
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

    protected function formatNumber($val, $dec = 2): string
    {
        return number_format((float)($val ?? 0), $dec, '.', '');
    }

    protected function formatSmart($val): string
    {
        $v = (float)($val ?? 0);
        return (abs($v - round($v)) < 0.00001)
            ? number_format($v, 0, '.', '')
            : number_format($v, 2, '.', '');
    }

    protected function limpiarTexto($str): string
    {
        return mb_substr(preg_replace('/[^\p{L}\p{N}\s\.\,\-\_\@]/u', ' ', trim($str ?? '')), 0, 300);
    }

    protected function limpiarAtributo($str): string
    {
        $str = $this->limpiarTexto($str);
        $search = ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'];
        return str_replace(' ', '', str_replace($search, $replace, $str));
    }

    protected function addElement($parent, $name, $val): void
    {
        $el = $this->dom->createElement($name);
        $el->appendChild($this->dom->createTextNode($val));
        $parent->appendChild($el);
    }
}
