<?php
/**
 * SHALOM FACTURA - Diagnóstico SRI
 * Coloca este archivo en la raíz y ejecuta desde el navegador
 * URL: http://tu-dominio.com/sri_diagnostico.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Diagnóstico SRI</title>";
echo "<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}
.ok{color:#4ec9b0;}.error{color:#f44747;}.warn{color:#dcdcaa;}
pre{background:#2d2d2d;padding:15px;overflow-x:auto;}</style></head><body>";

echo "<h1>🔍 Diagnóstico SRI - SHALOM FACTURA</h1>";

// 1. Verificar extensiones PHP
echo "<h2>1. Extensiones PHP</h2>";
$extensions = ['soap', 'openssl', 'dom', 'xml'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<p class='" . ($loaded ? 'ok' : 'error') . "'>$ext: " . ($loaded ? '✓ OK' : '✗ FALTA') . "</p>";
}

// 2. Verificar SOAP
echo "<h2>2. Conexión SOAP al SRI</h2>";
$wsdlRecepcion = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
$wsdlAutorizacion = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

try {
    $clienteRecepcion = new SoapClient($wsdlRecepcion, [
        'trace' => true,
        'exceptions' => true,
        'connection_timeout' => 30,
        'cache_wsdl' => WSDL_CACHE_NONE
    ]);
    echo "<p class='ok'>✓ Conexión a Recepción (Pruebas): OK</p>";
    
    $funciones = $clienteRecepcion->__getFunctions();
    echo "<pre>Funciones disponibles:\n" . implode("\n", $funciones) . "</pre>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error conexión Recepción: " . $e->getMessage() . "</p>";
}

// 3. Verificar último XML firmado
echo "<h2>3. Último XML Firmado</h2>";
$logDir = sys_get_temp_dir();
$files = glob($logDir . '/sri_xml_firmado_*.xml');

if (empty($files)) {
    echo "<p class='warn'>⚠ No se encontraron XMLs firmados en $logDir</p>";
} else {
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $lastFile = $files[0];
    echo "<p class='ok'>Último archivo: " . basename($lastFile) . "</p>";
    echo "<p>Tamaño: " . filesize($lastFile) . " bytes</p>";
    echo "<p>Modificado: " . date('Y-m-d H:i:s', filemtime($lastFile)) . "</p>";
    
    $xml = file_get_contents($lastFile);
    
    // Verificar sintaxis XML
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loadResult = $dom->loadXML($xml);
    
    if ($loadResult) {
        echo "<p class='ok'>✓ XML sintácticamente válido</p>";
        
        // Verificar estructura básica
        $factura = $dom->getElementsByTagName('factura')->item(0);
        if ($factura) {
            echo "<p class='ok'>✓ Elemento raíz 'factura' encontrado</p>";
            
            $infoTrib = $dom->getElementsByTagName('infoTributaria')->item(0);
            $infoFact = $dom->getElementsByTagName('infoFactura')->item(0);
            $detalles = $dom->getElementsByTagName('detalles')->item(0);
            $signature = $dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
            
            echo "<p>" . ($infoTrib ? '✓' : '✗') . " infoTributaria</p>";
            echo "<p>" . ($infoFact ? '✓' : '✗') . " infoFactura</p>";
            echo "<p>" . ($detalles ? '✓' : '✗') . " detalles</p>";
            echo "<p>" . ($signature ? '✓' : '✗') . " ds:Signature</p>";
            
            // Extraer clave de acceso
            $claveAcceso = $dom->getElementsByTagName('claveAcceso')->item(0);
            if ($claveAcceso) {
                $clave = $claveAcceso->textContent;
                echo "<h3>Clave de Acceso: $clave</h3>";
                echo "<p>Longitud: " . strlen($clave) . " caracteres (debe ser 49)</p>";
                
                // Desglosar clave
                if (strlen($clave) == 49) {
                    echo "<pre>";
                    echo "Fecha: " . substr($clave, 0, 8) . "\n";
                    echo "Tipo Doc: " . substr($clave, 8, 2) . "\n";
                    echo "RUC: " . substr($clave, 10, 13) . "\n";
                    echo "Ambiente: " . substr($clave, 23, 1) . "\n";
                    echo "Serie: " . substr($clave, 24, 6) . "\n";
                    echo "Secuencial: " . substr($clave, 30, 9) . "\n";
                    echo "Código: " . substr($clave, 39, 8) . "\n";
                    echo "Tipo Emisión: " . substr($clave, 47, 1) . "\n";
                    echo "Dígito Verificador: " . substr($clave, 48, 1) . "\n";
                    echo "</pre>";
                }
            }
        }
    } else {
        echo "<p class='error'>✗ XML NO es válido</p>";
        foreach (libxml_get_errors() as $error) {
            echo "<p class='error'>Línea {$error->line}: {$error->message}</p>";
        }
    }
    
    // Mostrar primeras y últimas líneas
    echo "<h3>Vista previa del XML:</h3>";
    echo "<pre>" . htmlspecialchars(substr($xml, 0, 1500)) . "\n...\n" . htmlspecialchars(substr($xml, -500)) . "</pre>";
}

// 4. Probar envío de XML de prueba mínimo
echo "<h2>4. Prueba de Envío (XML mínimo)</h2>";
echo "<p class='warn'>⚠ Esta prueba enviará un XML inválido al SRI para verificar la respuesta</p>";

if (isset($_GET['test_send']) && $_GET['test_send'] == '1') {
    // XML mínimo de prueba (obviamente inválido pero sirve para probar conectividad)
    $xmlPrueba = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"><test>prueba</test></factura>';
    
    try {
        $cliente = new SoapClient($wsdlRecepcion, [
            'trace' => true,
            'exceptions' => true
        ]);
        
        $response = $cliente->validarComprobante(['xml' => base64_encode($xmlPrueba)]);
        
        echo "<p class='ok'>✓ El SRI respondió</p>";
        echo "<pre>" . print_r($response, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><a href='?test_send=1' style='color:#569cd6;'>Clic aquí para ejecutar prueba de envío</a></p>";
}

// 5. Verificar certificado
echo "<h2>5. Certificado</h2>";
echo "<p class='warn'>Para verificar el certificado, agrega ?cert_path=/ruta/al/certificado.p12&cert_pass=contraseña a la URL</p>";

if (isset($_GET['cert_path']) && isset($_GET['cert_pass'])) {
    $certPath = $_GET['cert_path'];
    $certPass = $_GET['cert_pass'];
    
    if (file_exists($certPath)) {
        $certs = [];
        $p12 = file_get_contents($certPath);
        if (openssl_pkcs12_read($p12, $certs, $certPass)) {
            $certInfo = openssl_x509_parse($certs['cert']);
            echo "<p class='ok'>✓ Certificado válido</p>";
            echo "<pre>";
            echo "CN: " . ($certInfo['subject']['CN'] ?? 'N/A') . "\n";
            echo "Válido desde: " . date('Y-m-d H:i:s', $certInfo['validFrom_time_t']) . "\n";
            echo "Válido hasta: " . date('Y-m-d H:i:s', $certInfo['validTo_time_t']) . "\n";
            
            $ahora = time();
            if ($ahora < $certInfo['validFrom_time_t']) {
                echo "Estado: TODAVÍA NO VÁLIDO\n";
            } elseif ($ahora > $certInfo['validTo_time_t']) {
                echo "Estado: EXPIRADO\n";
            } else {
                echo "Estado: VIGENTE\n";
            }
            echo "</pre>";
        } else {
            echo "<p class='error'>✗ No se pudo leer el certificado (contraseña incorrecta?)</p>";
        }
    } else {
        echo "<p class='error'>✗ Archivo no encontrado: $certPath</p>";
    }
}

echo "</body></html>";
