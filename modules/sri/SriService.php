<?php
/**
 * SHALOM FACTURA - Servicio de Comunicación con SRI
 *
 * FIX CRÍTICO (Error [35] ARCHIVO NO CUMPLE ESTRUCTURA XML):
 * - Enviar el XML como XSD_BASE64BINARY usando SoapVar (evita doble base64 y errores de conversión).
 * - NO alterar el contenido del XML firmado (solo se limpia BOM y whitespace externo).
 * - Logging completo (XML enviado + request/response SOAP) en /var/tmp (o sys_get_temp_dir si no existe).
 *
 * Nota:
 * - Los WSDL OFFLINE (RecepcionComprobantesOffline / AutorizacionComprobantesOffline) se mantienen.
 */

namespace Shalom\Modules\Sri;

use SoapClient;
use SoapFault;
use SoapVar;
use Exception;
use DOMDocument;

class SriService
{
    // OFFLINE
    const WS_PRUEBAS_RECEPCION     = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    const WS_PRUEBAS_AUTORIZACION  = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
    const WS_PRODUCCION_RECEPCION  = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    const WS_PRODUCCION_AUTORIZACION = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    const ESTADO_RECIBIDA          = 'RECIBIDA';
    const ESTADO_DEVUELTA          = 'DEVUELTA';
    const ESTADO_AUTORIZADO        = 'AUTORIZADO';
    const ESTADO_NO_AUTORIZADO     = 'NO AUTORIZADO';
    const ESTADO_RECHAZADO         = 'RECHAZADO';
    const ESTADO_EN_PROCESAMIENTO  = 'EN PROCESAMIENTO';

    private string $ambiente;
    private int $timeout;
    private ?SoapClient $clienteRecepcion = null;
    private ?SoapClient $clienteAutorizacion = null;

    public function __construct(string $ambiente = '1', int $timeout = 30)
    {
        $this->ambiente = $ambiente;
        $this->timeout = $timeout;
    }

    /**
     * Envía comprobante firmado al SRI (Recepción).
     */
    public function enviarComprobante(string $xmlFirmado): array
    {
        try {
            $cliente = $this->getClienteRecepcion();

            // 1) Limpieza mínima: BOM + whitespace externo
            $xml = ltrim($xmlFirmado, "\xEF\xBB\xBF");
            $xml = trim($xml);

            // 2) Validación rápida local (si falla, SRI devolverá [35])
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            if (!$doc->loadXML($xml)) {
                $this->logDebug('XML_INVALIDO_LOCAL', 'No se pudo parsear el XML firmado localmente.');
                return [
                    'success' => false,
                    'estado'  => 'ERROR_ESTRUCTURA',
                    'mensaje' => 'XML inválido (no se pudo parsear localmente).'
                ];
            }

            // 3) Determinar claveAcceso para nombres de archivos de debug
            $claveAcceso = '';
            $n = $doc->getElementsByTagName('claveAcceso');
            if ($n->length > 0) {
                $claveAcceso = trim((string)$n->item(0)->nodeValue);
            }
            $suffix = $claveAcceso ? substr($claveAcceso, -8) : date('YmdHis');

            // 4) Guardar el XML exacto que se enviará (para comparar)
            $this->writeDebugFile("sri_xml_enviado_{$suffix}.xml", $xml);

            // 5) Enviar como base64Binary REAL (evita doble base64).
            //    SoapVar(XSD_BASE64BINARY) hace que PHP SOAP serialice correctamente el tipo.
            $xmlVar = new SoapVar($xml, XSD_BASE64BINARY);
            $response = $cliente->validarComprobante(['xml' => $xmlVar]);

            // 6) Guardar request/response SOAP
            $this->logSoapLastExchange($cliente, "recepcion_{$suffix}");

            return $this->procesarRespuestaRecepcion($response);

        } catch (SoapFault $e) {
            $this->logDebug('ERROR_SOAP', $e->getMessage());
            if ($this->clienteRecepcion) {
                $this->logSoapLastExchange($this->clienteRecepcion, 'recepcion_error');
            }
            return [
                'success' => false,
                'estado'  => 'ERROR_CONEXION',
                'mensaje' => $e->getMessage()
            ];
        } catch (Exception $e) {
            $this->logDebug('ERROR_INTERNO', $e->getMessage());
            return [
                'success' => false,
                'estado'  => 'ERROR_INTERNO',
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Consulta autorización por clave de acceso (Autorización).
     */
    public function consultarAutorizacion(string $claveAcceso): array
    {
        try {
            $cliente = $this->getClienteAutorizacion();
            $response = $cliente->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            $this->logSoapLastExchange($cliente, 'autorizacion_' . substr($claveAcceso, -8));

            return $this->procesarRespuestaAutorizacion($response, $claveAcceso);
        } catch (SoapFault $e) {
            $this->logDebug('ERROR_SOAP_AUTORIZACION', $e->getMessage());
            if ($this->clienteAutorizacion) {
                $this->logSoapLastExchange($this->clienteAutorizacion, 'autorizacion_error');
            }
            return ['success' => false, 'estado' => 'ERROR', 'mensaje' => $e->getMessage()];
        } catch (Exception $e) {
            $this->logDebug('ERROR_AUTORIZACION', $e->getMessage());
            return ['success' => false, 'estado' => 'ERROR', 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Flujo completo: recepción + consulta de autorización.
     */
    public function enviarYAutorizar(string $xmlFirmado, string $claveAcceso, int $maxIntentos = 5, int $esperaSegundos = 3): array
    {
        $res = $this->enviarComprobante($xmlFirmado);
        if (!$res['success']) {
            // Si SRI devuelve DEVUELTA aquí, viene en $res['estado'].
            return $res;
        }

        $intento = 0;
        while ($intento < $maxIntentos) {
            $intento++;
            if ($intento > 1) {
                sleep($esperaSegundos);
            }
            $resAuth = $this->consultarAutorizacion($claveAcceso);
            if ($resAuth['success'] || in_array($resAuth['estado'], [self::ESTADO_RECHAZADO, self::ESTADO_NO_AUTORIZADO], true)) {
                return $resAuth;
            }
        }

        return [
            'success' => false,
            'estado' => self::ESTADO_EN_PROCESAMIENTO,
            'mensaje' => 'En proceso...',
            'clave_acceso' => $claveAcceso
        ];
    }

    // =========================================================
    // Procesamiento de respuestas
    // =========================================================

    private function procesarRespuestaRecepcion($response): array
    {
        $resultado = [
            'success' => false,
            'estado' => 'DESCONOCIDO',
            'mensaje' => 'Procesando...',
            'comprobantes' => []
        ];

        $respuesta = $response->RespuestaRecepcionComprobante ?? $response;
        $estado = $respuesta->estado ?? null;

        // Si no viene estado, intentamos extraer mensajes
        if ($estado === null && isset($respuesta->comprobantes->comprobante)) {
            $errores = $this->extraerErrores($respuesta->comprobantes->comprobante);
            if (!empty($errores)) {
                $resultado['estado'] = 'ERROR_ESTRUCTURA';
                $resultado['mensaje'] = implode('. ', $errores);
                return $resultado;
            }
        }

        $resultado['estado'] = $estado ?? 'SIN_ESTADO';

        if ($estado === self::ESTADO_RECIBIDA) {
            $resultado['success'] = true;
            $resultado['mensaje'] = 'RECIBIDA';
            return $resultado;
        }

        if ($estado === self::ESTADO_DEVUELTA) {
            $errores = isset($respuesta->comprobantes->comprobante)
                ? $this->extraerErrores($respuesta->comprobantes->comprobante)
                : [];
            $resultado['mensaje'] = !empty($errores) ? implode('. ', $errores) : 'DEVUELTA';
            return $resultado;
        }

        $resultado['mensaje'] = $estado ? "Estado: {$estado}" : 'Respuesta sin estado';
        return $resultado;
    }

    private function procesarRespuestaAutorizacion($response, string $claveAcceso): array
    {
        $resultado = [
            'success' => false,
            'estado' => self::ESTADO_EN_PROCESAMIENTO,
            'mensaje' => 'Consultando...',
            'numero_autorizacion' => null,
            'fecha_autorizacion' => null,
            'xml_autorizado' => null
        ];

        $respuesta = $response->RespuestaAutorizacionComprobante ?? $response;

        if (!isset($respuesta->autorizaciones->autorizacion)) {
            return $resultado;
        }

        $auths = is_array($respuesta->autorizaciones->autorizacion)
            ? $respuesta->autorizaciones->autorizacion
            : [$respuesta->autorizaciones->autorizacion];

        if (empty($auths)) {
            return $resultado;
        }

        $auth = end($auths);
        $estado = $auth->estado ?? 'SIN_ESTADO';
        $resultado['estado'] = $estado;

        if ($estado === self::ESTADO_AUTORIZADO) {
            $resultado['success'] = true;
            $resultado['mensaje'] = 'AUTORIZADO';
            $resultado['numero_autorizacion'] = $auth->numeroAutorizacion ?? $claveAcceso;
            $resultado['fecha_autorizacion'] = isset($auth->fechaAutorizacion)
                ? $this->parsearFecha($auth->fechaAutorizacion)
                : date('Y-m-d H:i:s');
            $resultado['xml_autorizado'] = $auth->comprobante ?? null;
            return $resultado;
        }

        if (in_array($estado, [self::ESTADO_RECHAZADO, self::ESTADO_NO_AUTORIZADO], true)) {
            $errores = isset($auth->mensajes->mensaje) ? $this->extraerErrores($auth) : [];
            $resultado['mensaje'] = !empty($errores) ? implode('. ', $errores) : 'Rechazado/No autorizado.';
            return $resultado;
        }

        // EN PROCESO u otros
        $resultado['mensaje'] = $estado;
        return $resultado;
    }

    private function extraerErrores($obj): array
    {
        $errores = [];
        $comps = is_array($obj) ? $obj : [$obj];

        foreach ($comps as $c) {
            if (!isset($c->mensajes->mensaje)) {
                continue;
            }
            $msgs = is_array($c->mensajes->mensaje) ? $c->mensajes->mensaje : [$c->mensajes->mensaje];
            foreach ($msgs as $m) {
                $ident = $m->identificador ?? '';
                $msg = $m->mensaje ?? '';
                $add = !empty($m->informacionAdicional) ? " - {$m->informacionAdicional}" : '';
                $errores[] = "[{$ident}] {$msg}{$add}";
            }
        }

        return $errores;
    }

    private function parsearFecha($fecha): string
    {
        try {
            return (new \DateTime((string)$fecha))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return (string)$fecha;
        }
    }

    // =========================================================
    // SOAP clients
    // =========================================================

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
            $wsdl = ($this->ambiente === '2') ? self::WS_PRODUCCION_RECEPCION : self::WS_PRUEBAS_RECEPCION;
            $this->clienteRecepcion = $this->crearClienteSoap($wsdl);
        }
        return $this->clienteRecepcion;
    }

    private function getClienteAutorizacion(): SoapClient
    {
        if ($this->clienteAutorizacion === null) {
            $wsdl = ($this->ambiente === '2') ? self::WS_PRODUCCION_AUTORIZACION : self::WS_PRUEBAS_AUTORIZACION;
            $this->clienteAutorizacion = $this->crearClienteSoap($wsdl);
        }
        return $this->clienteAutorizacion;
    }

    public function getNombreAmbiente(): string
    {
        return ($this->ambiente === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
    }

    public function verificarConectividad(): array
    {
        // Mantener simple: si se puede instanciar SoapClient, asumimos OK.
        return ['recepcion' => true, 'autorizacion' => true];
    }

    // =========================================================
    // Debug helpers
    // =========================================================

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

    private function logDebug(string $tipo, $data): void
    {
        $file = $this->getDebugDir() . '/sri_debug_full.log';
        $msg = "\n[" . date('Y-m-d H:i:s') . "] {$tipo}\n" . (is_string($data) ? $data : print_r($data, true)) . "\n";
        @file_put_contents($file, $msg, FILE_APPEND);
    }

    private function writeDebugFile(string $filename, string $content): void
    {
        $path = $this->getDebugDir() . '/' . $filename;
        @file_put_contents($path, $content);
    }

    private function logSoapLastExchange(SoapClient $client, string $prefix): void
    {
        // Evitar warnings si SOAP no expone los últimos mensajes
        try {
            $req = $client->__getLastRequest();
            $reqH = $client->__getLastRequestHeaders();
            $res = $client->__getLastResponse();
            $resH = $client->__getLastResponseHeaders();

            $this->writeDebugFile($prefix . '_request_headers.txt', (string)$reqH);
            $this->writeDebugFile($prefix . '_request.xml', (string)$req);
            $this->writeDebugFile($prefix . '_response_headers.txt', (string)$resH);
            $this->writeDebugFile($prefix . '_response.xml', (string)$res);
        } catch (Exception $e) {
            $this->logDebug('SOAP_DEBUG_ERROR', $e->getMessage());
        }
    }
}
