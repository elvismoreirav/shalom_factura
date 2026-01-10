<?php
/**
 * SHALOM FACTURA - Generador PDF (RIDE)
 * Versión Final: Corrección UTF-8 y soporte IVA 15%
 */

namespace Shalom\Modules\Pdf;

use TCPDF;

class GeneradorPdf
{
    private TCPDF $pdf;
    private array $empresa;
    
    // Colores Shalom
    const HEX_PRIMARY = '#1e4d39';
    const HEX_SECONDARY = '#f9f8f4';
    
    public function __construct(array $empresa)
    {
        $this->empresa = $empresa;
        $this->inicializarPdf();
    }
    
    private function inicializarPdf(): void
    {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->pdf->SetCreator('Shalom Factura');
        $this->pdf->SetAuthor($this->empresa['razon_social']);
        $this->pdf->SetTitle('RIDE');
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetAutoPageBreak(TRUE, 10);
        $this->pdf->SetFont('helvetica', '', 8);
    }
    
    public function generarFactura(array $datos): string
    {
        $this->pdf->AddPage();
        
        $this->dibujarCabecera($datos);
        $this->dibujarDatosCliente($datos);
        $this->dibujarDetalles($datos['detalles']);
        $this->dibujarPieDePagina($datos);
        
        return $this->pdf->Output('factura.pdf', 'S');
    }

    private function dibujarCabecera(array $datos): void
    {
        // 1. LOGO
        $logoHtml = '<h2 style="color:red; text-align:center;">SIN LOGO</h2>';
        $logoPath = defined('ROOT_PATH') ? ROOT_PATH . '/assets/img/logo-full.png' : 'assets/img/logo-full.png';
        
        if (file_exists($logoPath)) {
            $logoHtml = '<img src="'.$logoPath.'" width="150">';
        } elseif (!empty($this->empresa['logo_path']) && file_exists($this->empresa['logo_path'])) {
             $logoHtml = '<img src="'.$this->empresa['logo_path'].'" width="150">';
        }

        // 2. INFO EMISOR (Izquierda)
        $obligado = $this->empresa['obligado_contabilidad'] ?? 'NO';
        $especial = $this->empresa['contribuyente_especial'] ?? '';
        $txtEspecial = $especial ? "Contribuyente Especial Nro: $especial<br>" : "";

        $htmlIzq = <<<EOD
        <table cellpadding="2">
            <tr><td align="center">{$logoHtml}</td></tr>
            <tr><td></td></tr>
            <tr>
                <td style="border: 1px solid #333; border-radius: 8px; padding: 10px;">
                    <br>
                    <b style="font-size: 10px;">{$this->empresa['razon_social']}</b><br><br>
                    <b>Dirección Matriz:</b> {$this->empresa['direccion_matriz']}<br>
                    <b>Dirección Sucursal:</b> {$datos['establecimiento_direccion']}<br><br>
                    {$txtEspecial}
                    <b>OBLIGADO A LLEVAR CONTABILIDAD:</b> {$obligado}
                </td>
            </tr>
        </table>
        EOD;

        // 3. INFO TRIBUTARIA (Derecha) - LÓGICA DINÁMICA
        $ambiente = ($datos['ambiente'] == 2) ? 'PRODUCCIÓN' : 'PRUEBAS';
        $emision = ($datos['tipo_emision'] == 1) ? 'NORMAL' : 'INDISPONIBILIDAD';
        $fechaAuth = $datos['fecha_autorizacion'];
        $claveAcceso = $datos['clave_acceso'];

        // Sin borde CSS para dibujarlo manualmente después
        // Agregamos <br> suficientes para crear el "hueco" del código de barras
        $htmlDer = <<<EOD
        <table cellpadding="3" cellspacing="0">
            <tr>
                <td style="padding: 5px;">
                    <b>R.U.C.:</b> {$this->empresa['ruc']}<br>
                    <b style="font-size: 14px; color: #1e4d39;">FACTURA</b><br><br>
                    No. {$datos['numero']}<br><br>
                    <b>NÚMERO DE AUTORIZACIÓN</b><br>
                    <span style="font-size: 8px;">{$datos['numero_autorizacion']}</span><br><br>
                    <b>FECHA Y HORA DE AUTORIZACIÓN:</b><br>
                    {$fechaAuth}<br><br>
                    <b>AMBIENTE:</b> {$ambiente}<br>
                    <b>EMISIÓN:</b> {$emision}<br><br>
                    <b>CLAVE DE ACCESO</b><br>
                    <br><br><br><br> <span style="font-size: 8px; font-family: courier;">{$claveAcceso}</span>
                </td>
            </tr>
        </table>
        EOD;

        // A. Dibujar Izquierda
        $this->pdf->writeHTMLCell(90, 0, 10, 10, $htmlIzq, 0, 0, 0, true, 'L', true);

        // B. Dibujar Derecha (SIN BORDE AÚN)
        // Guardamos la Y inicial
        $yStart = 10;
        $xStart = 105;
        $width = 95;
        
        // Escribimos el contenido. border=0.
        $this->pdf->writeHTMLCell($width, 0, $xStart, $yStart, $htmlDer, 0, 1, 0, true, 'L', true);
        
        // C. CÁLCULO DINÁMICO
        // Obtenemos dónde terminó realmente el texto
        $yEnd = $this->pdf->GetY();
        $height = $yEnd - $yStart;

        // D. Dibujar el Borde (RoundedRect) alrededor del contenido real
        $this->pdf->RoundedRect($xStart, $yStart, $width, $height, 3, '1111', '');

        // E. Dibujar el Barcode relativo al FINAL de la caja
        $barcodeY = $yEnd - 19; // 19mm hacia arriba desde el borde inferior
        
        $styleBarcode = [
            'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 
            'border' => false, 'hpadding' => 'auto', 'vpadding' => 'auto', 
            'fgcolor' => [0,0,0], 'bgcolor' => false, 'text' => false, 
            'font' => 'helvetica', 'fontsize' => 8
        ];
        
        // Dibujamos el barcode "flotando" en el hueco que creamos con los <br>
        $this->pdf->write1DBarcode($claveAcceso, 'C128', 108, $barcodeY, 89, 13, 0.4, $styleBarcode, 'N');
    }
    
    private function dibujarDatosCliente(array $datos): void
    {
        $this->pdf->Ln(5);
        $c = $datos['cliente'];
        $fecha = date('d/m/Y', strtotime($datos['fecha_emision']));
        
        $html = <<<EOD
        <table cellpadding="3" border="0" style="border-top: 1px solid #333; border-bottom: 1px solid #333;">
            <tr>
                <td width="60%"><b>Razón Social / Nombres y Apellidos:</b><br> {$c['razon_social']}</td>
                <td width="40%"><b>Identificación:</b> {$c['identificacion']}</td>
            </tr>
            <tr>
                <td><b>Fecha Emisión:</b> {$fecha}</td>
                <td><b>Guía de Remisión:</b> {$datos['guia_remision']}</td>
            </tr>
             <tr>
                <td colspan="2"><b>Dirección:</b> {$c['direccion']}</td>
            </tr>
        </table>
        EOD;
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarDetalles(array $detalles): void
    {
        $this->pdf->Ln(5);
        
        // Tabla con anchos definidos en thead Y tbody para alineación perfecta
        $html = '
        <table border="1" cellpadding="3" cellspacing="0">
            <thead>
                <tr style="font-weight:bold; background-color:#1e4d39; color: white;">
                    <td width="12%" align="center">Cod. Principal</td>
                    <td width="10%" align="center">Cant.</td>
                    <td width="40%" align="center">Descripción</td>
                    <td width="13%" align="center">Precio Unitario</td>
                    <td width="10%" align="center">Descuento</td>
                    <td width="15%" align="center">Precio Total</td>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($detalles as $det) {
            $codigo = $det['codigo_principal'] ?? $det['codigo'] ?? '';
            $precioTotal = $det['precio_total_sin_impuesto'] ?? $det['subtotal'] ?? 0;
            
            $html .= '<tr>
                <td width="12%" align="center">'.$codigo.'</td>
                <td width="10%" align="right">'.number_format($det['cantidad'], 2).'</td>
                <td width="40%">'.$det['descripcion'].'</td>
                <td width="13%" align="right">'.number_format($det['precio_unitario'], 4).'</td>
                <td width="10%" align="right">'.number_format($det['descuento'] ?? 0, 2).'</td>
                <td width="15%" align="right">'.number_format($precioTotal, 2).'</td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarPieDePagina(array $datos): void
    {
        $this->pdf->Ln(5);
        
        // --- 1. IZQUIERDA (Info + Pagos) ---
        $htmlIzq = '';
        
        // Info Adicional
        $htmlIzq .= '<table border="1" cellpadding="2">
            <tr><td colspan="2" style="background-color:#f0f0f0;"><b>Información Adicional</b></td></tr>';
        if (!empty($datos['info_adicional'])) {
            foreach ($datos['info_adicional'] as $info) {
                $htmlIzq .= '<tr>
                    <td width="40%">'.$info['nombre'].'</td>
                    <td width="60%">'.$info['valor'].'</td>
                </tr>';
            }
        } else {
             $htmlIzq .= '<tr><td colspan="2">-</td></tr>';
        }
        $htmlIzq .= '</table><br>';

        // Formas de Pago
        $htmlIzq .= '<table border="1" cellpadding="2">
            <tr style="background-color:#f0f0f0;">
                <td width="65%"><b>Forma de Pago</b></td>
                <td width="35%"><b>Valor</b></td>
            </tr>';
        if (!empty($datos['formas_pago'])) {
            foreach ($datos['formas_pago'] as $pago) {
                $val = $pago['valor'] ?? $pago['total'] ?? 0;
                $desc = $pago['descripcion'] ?? $pago['forma_pago_nombre'] ?? 'Otros';
                $htmlIzq .= '<tr>
                    <td>'.$desc.'</td>
                    <td align="right">'.number_format($val, 2).'</td>
                </tr>';
            }
        } else {
             $htmlIzq .= '<tr><td>Sin utilización del sistema financiero</td><td align="right">'.number_format($datos['total'], 2).'</td></tr>';
        }
        $htmlIzq .= '</table>';


        // --- 2. DERECHA (Totales IVA 15%) ---
        $subtotalIva = number_format($datos['subtotal_iva_actual'] ?? $datos['subtotal_iva'] ?? 0, 2); 
        
        $htmlDer = '<table border="1" cellpadding="2">
            <tr>
                <td width="60%">SUBTOTAL 15%</td>
                <td width="40%" align="right">'.$subtotalIva.'</td>
            </tr>
            <tr>
                <td>SUBTOTAL 0%</td>
                <td align="right">'.number_format($datos['subtotal_0'] ?? $datos['subtotal_iva_0'] ?? 0, 2).'</td>
            </tr>
            <tr>
                <td>SUBTOTAL NO OBJETO DE IVA</td>
                <td align="right">'.number_format($datos['subtotal_no_objeto'] ?? 0, 2).'</td>
            </tr>
            <tr>
                <td>SUBTOTAL EXENTO DE IVA</td>
                <td align="right">'.number_format($datos['subtotal_exento'] ?? 0, 2).'</td>
            </tr>
            <tr>
                <td>SUBTOTAL SIN IMPUESTOS</td>
                <td align="right">'.number_format($datos['subtotal_sin_impuestos'], 2).'</td>
            </tr>
             <tr>
                <td>TOTAL DESCUENTO</td>
                <td align="right">'.number_format($datos['total_descuento'] ?? 0, 2).'</td>
            </tr>
             <tr>
                <td>ICE</td>
                <td align="right">'.number_format($datos['total_ice'] ?? 0, 2).'</td>
            </tr>
             <tr>
                <td>IVA 15%</td>
                <td align="right">'.number_format($datos['total_iva'], 2).'</td>
            </tr>
             <tr>
                <td>PROPINA</td>
                <td align="right">'.number_format($datos['propina'] ?? 0, 2).'</td>
            </tr>
             <tr style="background-color:#1e4d39; color:white;">
                <td><b>VALOR TOTAL</b></td>
                <td align="right"><b>$ '.number_format($datos['total'], 2).'</b></td>
            </tr>
        </table>';

        $this->pdf->writeHTMLCell(100, 0, 10, '', $htmlIzq, 0, 0, 0, true, 'L', true);
        $this->pdf->writeHTMLCell(85, 0, 115, '', $htmlDer, 0, 1, 0, true, 'R', true);
        
        $this->pdf->Ln(5);
        $this->pdf->SetFont('helvetica', '', 7);
        $this->pdf->Cell(0, 0, 'Documento electrónico generado por Shalom Facturación', 0, 1, 'C');
    }
}
