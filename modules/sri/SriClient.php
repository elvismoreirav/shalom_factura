<?php
/**
 * SHALOM FACTURA - Cliente SRI (Recepción + Autorización)
 *
 * FIX CRÍTICO (Error [35] ARCHIVO NO CUMPLE ESTRUCTURA XML):
 * - Envío del XML como XSD_BASE64BINARY usando SoapVar (evita doble base64).
 * - Logging opcional en /var/tmp/shalom_sri para request/response y XML.
 *
 * Nota: Esta clase se usa en FacturacionElectronica.php (flujo coordinado).
 */

namespace Shalom\Modules\Sri;

use SoapClient;
use SoapFault;
use SoapVar;
use Exception;
use DOMDocument;

class SriClient
{
    const AMBIENTE_PRUEBAS = '1';
    const AMBIENTE_PRODUCCION = '2';

    // OFFLINE
    const WS_PRUEBAS_RECEPCION = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    const WS_PRUEBAS_AUTORIZACION = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
    const WS_PRODUCCION_RECEPCION = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    const WS_PRODUCCION_AUTORIZACION = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    private string $ambiente;
    private int $timeout;
    private ?SoapClient $clienteRecepcion = null;
    private ?SoapClient $clienteAutorizacion = null;

    public function __construct(string $ambiente = self::AMBIENTE_PRUEBAS, int $timeout = 30)
    {
        $this->ambiente = $ambiente;
        $this->timeout = $timeout;
    }

    private function getDebugDir(): string
    {
        $preferred = '/var/tmp';
        $dir = is_dir($preferred) && is_writable($preferred) ? $preferred : sys_get_temp_dir();
        $dir = rtrim($dir, '/');
        $final = $dir . '/shalom_sri';
        if (!is_dir($final)) {
            @mkdir($final, 0777, true);
        }
        return $final;
    }

    private function writeDebugFile(string $filename, string $content): void
    {
        @file_put_contents($this->getDebugDir() . '/' . $filename, $content);
    }

    private function logSoapLastExchange(SoapClient $client, string $prefix): void
    {
        try {
            $this->writeDebugFile($prefix . '_request_headers.txt', (string)$client->__getLastRequestHeaders());
            $this->writeDebugFile($prefix . '_request.xml', (string)$client->__getLastRequest());
            $this->writeDebugFile($prefix . '_response_headers.txt', (string)$client->__getLastResponseHeaders());
            $this->writeDebugFile($prefix . '_response.xml', (string)$client->__getLastResponse());
        } catch (Exception $e) {
            // Silencioso
        }
    }

    private function crearClienteSoap(string $wsdl): SoapClient
    {
        return new SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'connection_timeout' => $this->timeout,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ],
                'http' => [
                    'timeout' => $this->timeout
                ]
            ])
        ]);
    }

    private function getClienteRecepcion(): SoapClient
    {
        if ($this->clienteRecepcion === null) {
            $wsdl = ($this->ambiente === self::AMBIENTE_PRODUCCION)
                ? self::WS_PRODUCCION_RECEPCION
                : self::WS_PRUEBAS_RECEPCION;
            $this->clienteRecepcion = $this->crearClienteSoap($wsdl);
        }
        return $this->clienteRecepcion;
    }

    private function getClienteAutorizacion(): SoapClient
    {
        if ($this->clienteAutorizacion === null) {
            $wsdl = ($this->ambiente === self::AMBIENTE_PRODUCCION)
                ? self::WS_PRODUCCION_AUTORIZACION
                : self::WS_PRUEBAS_AUTORIZACION;
            $this->clienteAutorizacion = $this->crearClienteSoap($wsdl);
        }
        return $this->clienteAutorizacion;
    }

    /**
     * Enviar comprobante al SRI (Recepción).
     */
    public function enviarComprobante(string $xmlFirmado): array
    {
        try {
            $xml = ltrim($xmlFirmado, "\xEF\xBB\xBF");
            $xml = trim($xml);

            // Validación rápida
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            if (!$doc->loadXML($xml)) {
                return [
                    'success' => false,
                    'estado' => 'ERROR_ESTRUCTURA',
                    'mensaje' => 'XML inválido (no se pudo parsear localmente).',
                    'codigo' => 'XML_INVALIDO'
                ];
            }

            $claveAcceso = '';
            $n = $doc->getElementsByTagName('claveAcceso');
            if ($n->length > 0) $claveAcceso = trim((string)$n->item(0)->nodeValue);
            $suffix = $claveAcceso ? substr($claveAcceso, -8) : date('YmdHis');

            // Guardar XML enviado
            $this->writeDebugFile("sri_xml_enviado_client_{$suffix}.xml", $xml);

            $cliente = $this->getClienteRecepcion();

            // FIX: enviar como base64Binary real usando SoapVar
            $xmlVar = new SoapVar($xml, XSD_BASE64BINARY);
            $respuesta = $cliente->validarComprobante(['xml' => $xmlVar]);

            $this->logSoapLastExchange($cliente, "recepcion_client_{$suffix}");

            return $this->procesarRespuestaRecepcion($respuesta);

        } catch (SoapFault $e) {
            if ($this->clienteRecepcion) $this->logSoapLastExchange($this->clienteRecepcion, 'recepcion_client_error');
            return [
                'success' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error de comunicación con el SRI: ' . $e->getMessage(),
                'codigo' => 'SOAP_ERROR'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error: ' . $e->getMessage(),
                'codigo' => 'GENERAL_ERROR'
            ];
        }
    }

    /**
     * Consultar autorización por clave de acceso.
     */
    public function consultarAutorizacion(string $claveAcceso): array
    {
        try {
            $cliente = $this->getClienteAutorizacion();
            $respuesta = $cliente->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            $this->logSoapLastExchange($cliente, 'autorizacion_client_' . substr($claveAcceso, -8));

            return $this->procesarRespuestaAutorizacion($respuesta);

        } catch (SoapFault $e) {
            if ($this->clienteAutorizacion) $this->logSoapLastExchange($this->clienteAutorizacion, 'autorizacion_client_error');
            return [
                'success' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error de comunicación con el SRI: ' . $e->getMessage(),
                'codigo' => 'SOAP_ERROR'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error: ' . $e->getMessage(),
                'codigo' => 'GENERAL_ERROR'
            ];
        }
    }

    /**
     * Flujo completo: envía, luego consulta hasta autorización o rechazo.
     */
    public function procesarComprobante(string $xmlFirmado, int $maxIntentos = 5, int $esperaSegundos = 3): array
    {
        // Extraer claveAcceso desde el XML
        $claveAcceso = '';
        $doc = new DOMDocument('1.0', 'UTF-8');
        if ($doc->loadXML($xmlFirmado)) {
            $n = $doc->getElementsByTagName('claveAcceso');
            if ($n->length > 0) $claveAcceso = trim((string)$n->item(0)->nodeValue);
        }

        $recepcion = $this->enviarComprobante($xmlFirmado);
        if (!$recepcion['success']) {
            return $recepcion;
        }

        $intento = 0;
        while ($intento < $maxIntentos) {
            $intento++;
            if ($intento > 1) sleep($esperaSegundos);

            $auth = $this->consultarAutorizacion($claveAcceso);
            if ($auth['success']) {
                return $auth;
            }
            if (isset($auth['estado']) && in_array($auth['estado'], ['RECHAZADO', 'NO AUTORIZADO'], true)) {
                return $auth;
            }
        }

        return [
            'success' => false,
            'estado' => 'EN PROCESO',
            'mensaje' => 'Comprobante en procesamiento. Intente nuevamente.',
            'clave_acceso' => $claveAcceso
        ];
    }

    // =========================================================
    // Respuesta Recepción
    // =========================================================

    private function procesarRespuestaRecepcion($respuesta): array
    {
        $resultado = [
            'success' => false,
            'estado' => 'DESCONOCIDO',
            'mensaje' => '',
            'comprobantes' => []
        ];

        if (!isset($respuesta->RespuestaRecepcionComprobante)) {
            $resultado['mensaje'] = 'Respuesta del SRI no válida';
            return $resultado;
        }

        $r = $respuesta->RespuestaRecepcionComprobante;
        $resultado['estado'] = $r->estado ?? 'DESCONOCIDO';

        if ($resultado['estado'] === 'RECIBIDA') {
            $resultado['success'] = true;
            $resultado['mensaje'] = 'RECIBIDA';
            return $resultado;
        }

        if ($resultado['estado'] === 'DEVUELTA') {
            $errores = [];
            if (isset($r->comprobantes->comprobante)) {
                $comps = is_array($r->comprobantes->comprobante) ? $r->comprobantes->comprobante : [$r->comprobantes->comprobante];
                foreach ($comps as $c) {
                    if (!isset($c->mensajes->mensaje)) continue;
                    $msgs = is_array($c->mensajes->mensaje) ? $c->mensajes->mensaje : [$c->mensajes->mensaje];
                    foreach ($msgs as $m) {
                        $ident = $m->identificador ?? '';
                        $msg = $m->mensaje ?? '';
                        $add = !empty($m->informacionAdicional) ? " - {$m->informacionAdicional}" : '';
                        $errores[] = "[{$ident}] {$msg}{$add}";
                    }
                }
            }
            $resultado['mensaje'] = !empty($errores) ? implode('. ', $errores) : 'DEVUELTA';
            return $resultado;
        }

        $resultado['mensaje'] = 'Estado: ' . $resultado['estado'];
        return $resultado;
    }

    // =========================================================
    // Respuesta Autorización
    // =========================================================

    private function procesarRespuestaAutorizacion($respuesta): array
    {
        $resultado = [
            'success' => false,
            'estado' => 'EN PROCESO',
            'mensaje' => '',
            'numero_autorizacion' => '',
            'fecha_autorizacion' => '',
            'comprobante' => ''
        ];

        if (!isset($respuesta->RespuestaAutorizacionComprobante)) {
            $resultado['mensaje'] = 'Respuesta del SRI no válida';
            return $resultado;
        }

        $r = $respuesta->RespuestaAutorizacionComprobante;
        if (!isset($r->autorizaciones->autorizacion)) {
            $resultado['mensaje'] = 'Sin autorizaciones';
            return $resultado;
        }

        $auths = is_array($r->autorizaciones->autorizacion) ? $r->autorizaciones->autorizacion : [$r->autorizaciones->autorizacion];
        if (empty($auths)) {
            $resultado['mensaje'] = 'Sin autorizaciones';
            return $resultado;
        }

        $auth = end($auths);
        $resultado['estado'] = $auth->estado ?? 'SIN_ESTADO';

        if ($resultado['estado'] === 'AUTORIZADO') {
            $resultado['success'] = true;
            $resultado['mensaje'] = 'AUTORIZADO';
            $resultado['numero_autorizacion'] = $auth->numeroAutorizacion ?? '';
            $resultado['fecha_autorizacion'] = $auth->fechaAutorizacion ?? '';
            $resultado['comprobante'] = $auth->comprobante ?? '';
            return $resultado;
        }

        // NO AUTORIZADO / RECHAZADO
        $errores = [];
        if (isset($auth->mensajes->mensaje)) {
            $msgs = is_array($auth->mensajes->mensaje) ? $auth->mensajes->mensaje : [$auth->mensajes->mensaje];
            foreach ($msgs as $m) {
                $ident = $m->identificador ?? '';
                $msg = $m->mensaje ?? '';
                $add = !empty($m->informacionAdicional) ? " - {$m->informacionAdicional}" : '';
                $errores[] = "[{$ident}] {$msg}{$add}";
            }
        }

        $resultado['mensaje'] = !empty($errores) ? implode('. ', $errores) : $resultado['estado'];
        if ($this->esClaveEnProcesamiento($errores)) {
            $resultado['estado'] = 'EN PROCESO';
        }
        return $resultado;
    }

    private function esClaveEnProcesamiento(array $errores): bool
    {
        foreach ($errores as $error) {
            $texto = strtoupper((string) $error);
            if (str_contains($texto, 'CLAVE DE ACCESO EN PROCESAMIENTO') || str_contains($texto, '[70]')) {
                return true;
            }
        }

        return false;
    }
}
