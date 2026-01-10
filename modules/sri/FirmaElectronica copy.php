<?php
/**
 * SHALOM FACTURA - Firma Electrónica XAdES-BES
 * Versión: 4.0 - Namespaces únicos, estructura limpia para SRI
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;
use Exception;

class FirmaElectronica
{
    private string $certificadoPath;
    private string $password;
    private array $certificadoInfo;
    private $privateKey;
    private string $certPem;
    private string $certBase64;
    
    public function __construct(string $certificadoPath, string $password)
    {
        $this->certificadoPath = $certificadoPath;
        $this->password = $password;
        $this->cargarCertificado();
    }
    
    private function cargarCertificado(): void
    {
        if (!file_exists($this->certificadoPath)) {
            throw new Exception('Archivo de certificado no encontrado');
        }
        
        $p12Content = file_get_contents($this->certificadoPath);
        $certs = [];
        
        if (!openssl_pkcs12_read($p12Content, $certs, $this->password)) {
            throw new Exception('No se pudo leer el certificado. Verifique la contraseña.');
        }
        
        $this->privateKey = $certs['pkey'];
        $this->certPem = $certs['cert'];
        $this->certBase64 = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r", ' '], 
            '', 
            $this->certPem
        );
        
        $certInfo = openssl_x509_parse($certs['cert']);
        if (!$certInfo) {
            throw new Exception('No se pudo parsear el certificado');
        }
        
        $ahora = time();
        if ($ahora < $certInfo['validFrom_time_t'] || $ahora > $certInfo['validTo_time_t']) {
            throw new Exception('El certificado ha expirado o aún no es válido');
        }
        
        $this->certificadoInfo = [
            'subject' => $certInfo['subject'],
            'issuer' => $certInfo['issuer'],
            'serial' => $certInfo['serialNumber'],
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            'cn' => $certInfo['subject']['CN'] ?? ''
        ];
    }
    
    /**
     * Firmar documento XML - Versión 4.0 con namespaces únicos
     */
    public function firmarXml(string $xml): string
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF");
        $xml = trim($xml);
        
        // Cargar documento
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        
        if (!$doc->loadXML($xml)) {
            throw new Exception('No se pudo cargar el XML');
        }
        
        // IDs únicos
        $uniqueId = bin2hex(random_bytes(8));
        $signatureId = 'Signature' . $uniqueId;
        $signedInfoId = 'Signature-SignedInfo' . $uniqueId;
        $signedPropertiesId = 'SignedProperties' . $uniqueId;
        $keyInfoId = 'Certificate' . $uniqueId;
        $referenceId = 'Reference' . $uniqueId;
        $objectId = $signatureId . '-Object';
        
        // Elemento raíz
        $root = $doc->documentElement;
        $rootId = $root->getAttribute('id');
        if (empty($rootId)) {
            $root->setAttribute('id', 'comprobante');
            $rootId = 'comprobante';
        }
        
        // Digest del documento
        $docDigest = base64_encode(hash('sha256', $root->C14N(false, false), true));
        
        // Datos del certificado
        $certDer = base64_decode($this->certBase64);
        $certDigest = base64_encode(hash('sha256', $certDer, true));
        
        $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($this->certPem));
        $modulus = base64_encode($pubKeyDetails['rsa']['n']);
        $exponent = base64_encode($pubKeyDetails['rsa']['e']);
        
        $signingTime = gmdate('Y-m-d\TH:i:s\Z');
        $issuerName = $this->formatIssuerName($this->certificadoInfo['issuer']);
        $serialNumber = $this->certificadoInfo['serial'];
        
        // =====================================================
        // CONSTRUIR SIGNED PROPERTIES (para calcular digest)
        // Sin declaración de namespace (se hereda del padre)
        // =====================================================
        $signedPropsContent = 
            '<etsi:SignedSignatureProperties>' .
                '<etsi:SigningTime>' . $signingTime . '</etsi:SigningTime>' .
                '<etsi:SigningCertificate>' .
                    '<etsi:Cert>' .
                        '<etsi:CertDigest>' .
                            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
                            '<ds:DigestValue>' . $certDigest . '</ds:DigestValue>' .
                        '</etsi:CertDigest>' .
                        '<etsi:IssuerSerial>' .
                            '<ds:X509IssuerName>' . htmlspecialchars($issuerName, ENT_XML1, 'UTF-8') . '</ds:X509IssuerName>' .
                            '<ds:X509SerialNumber>' . $serialNumber . '</ds:X509SerialNumber>' .
                        '</etsi:IssuerSerial>' .
                    '</etsi:Cert>' .
                '</etsi:SigningCertificate>' .
            '</etsi:SignedSignatureProperties>' .
            '<etsi:SignedDataObjectProperties>' .
                '<etsi:DataObjectFormat ObjectReference="#' . $referenceId . '">' .
                    '<etsi:Description>contenido comprobante</etsi:Description>' .
                    '<etsi:MimeType>text/xml</etsi:MimeType>' .
                '</etsi:DataObjectFormat>' .
            '</etsi:SignedDataObjectProperties>';
        
        // Para el digest, necesitamos el XML con namespaces
        $signedPropsForDigest = '<etsi:SignedProperties xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#" Id="' . $signedPropertiesId . '">' . $signedPropsContent . '</etsi:SignedProperties>';
        $signedPropsDigest = base64_encode(hash('sha256', $signedPropsForDigest, true));
        
        // =====================================================
        // CONSTRUIR KEY INFO (para calcular digest)
        // =====================================================
        $keyInfoContent = 
            '<ds:X509Data>' .
                '<ds:X509Certificate>' . $this->certBase64 . '</ds:X509Certificate>' .
            '</ds:X509Data>' .
            '<ds:KeyValue>' .
                '<ds:RSAKeyValue>' .
                    '<ds:Modulus>' . $modulus . '</ds:Modulus>' .
                    '<ds:Exponent>' . $exponent . '</ds:Exponent>' .
                '</ds:RSAKeyValue>' .
            '</ds:KeyValue>';
        
        $keyInfoForDigest = '<ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="' . $keyInfoId . '">' . $keyInfoContent . '</ds:KeyInfo>';
        $keyInfoDigest = base64_encode(hash('sha256', $keyInfoForDigest, true));
        
        // =====================================================
        // CONSTRUIR SIGNED INFO (para firmar)
        // =====================================================
        $signedInfoContent = 
            '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>' .
            '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>' .
            '<ds:Reference Id="' . $referenceId . '" URI="#' . $rootId . '">' .
                '<ds:Transforms>' .
                    '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>' .
                '</ds:Transforms>' .
                '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
                '<ds:DigestValue>' . $docDigest . '</ds:DigestValue>' .
            '</ds:Reference>' .
            '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#' . $signedPropertiesId . '">' .
                '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
                '<ds:DigestValue>' . $signedPropsDigest . '</ds:DigestValue>' .
            '</ds:Reference>' .
            '<ds:Reference URI="#' . $keyInfoId . '">' .
                '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
                '<ds:DigestValue>' . $keyInfoDigest . '</ds:DigestValue>' .
            '</ds:Reference>';
        
        // SignedInfo con namespace para canonicalización
        $signedInfoForSign = '<ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="' . $signedInfoId . '">' . $signedInfoContent . '</ds:SignedInfo>';
        
        // Canonicalizar y firmar
        $tempDoc = new DOMDocument();
        $tempDoc->loadXML($signedInfoForSign);
        $signedInfoC14n = $tempDoc->documentElement->C14N(false, false);
        
        $signatureBytes = '';
        if (!openssl_sign($signedInfoC14n, $signatureBytes, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Error al firmar');
        }
        $signatureValue = base64_encode($signatureBytes);
        
        // =====================================================
        // CONSTRUIR FIRMA COMPLETA - SIN NAMESPACES DUPLICADOS
        // Namespaces SOLO en ds:Signature
        // =====================================================
        $signatureXml = 
            '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#" Id="' . $signatureId . '">' .
                '<ds:SignedInfo Id="' . $signedInfoId . '">' . $signedInfoContent . '</ds:SignedInfo>' .
                '<ds:SignatureValue>' . $signatureValue . '</ds:SignatureValue>' .
                '<ds:KeyInfo Id="' . $keyInfoId . '">' . $keyInfoContent . '</ds:KeyInfo>' .
                '<ds:Object Id="' . $objectId . '">' .
                    '<etsi:QualifyingProperties Target="#' . $signatureId . '">' .
                        '<etsi:SignedProperties Id="' . $signedPropertiesId . '">' . $signedPropsContent . '</etsi:SignedProperties>' .
                    '</etsi:QualifyingProperties>' .
                '</ds:Object>' .
            '</ds:Signature>';
        
        // MÉTODO ALTERNATIVO: Insertar firma como string directamente
        // Esto evita que DOM agregue namespaces duplicados
        $xmlOriginal = $doc->saveXML();
        
        // Encontrar la etiqueta de cierre del elemento raíz
        $tagName = $root->tagName;
        $closingTag = '</' . $tagName . '>';
        
        // Insertar la firma justo antes de la etiqueta de cierre
        $result = str_replace($closingTag, $signatureXml . $closingTag, $xmlOriginal);
        
        // DEBUG
        $this->guardarXmlDebug($result, $uniqueId);
        
        return $result;
    }
    
    private function guardarXmlDebug(string $xml, string $id): void
    {
        $logDir = sys_get_temp_dir();
        $filename = $logDir . '/sri_xml_firmado_' . date('Y-m-d_His') . '_' . $id . '.xml';
        file_put_contents($filename, $xml);
        error_log("SRI XML Firmado guardado en: $filename");
    }
    
    private function formatIssuerName(array $issuer): string
    {
        $parts = [];
        foreach (['CN', 'OU', 'O', 'C'] as $key) {
            if (isset($issuer[$key])) {
                $value = is_array($issuer[$key]) ? $issuer[$key][0] : $issuer[$key];
                $parts[] = "$key=$value";
            }
        }
        return implode(',', $parts);
    }
    
    public function getInfo(): array
    {
        return $this->certificadoInfo;
    }
    
    public function proximoAVencer(int $dias = 30): bool
    {
        $fechaVencimiento = strtotime($this->certificadoInfo['valid_to']);
        return $fechaVencimiento <= time() + ($dias * 86400);
    }
    
    public static function verificarCertificado(string $path, string $password): array
    {
        if (!file_exists($path)) {
            return ['valid' => false, 'error' => 'Archivo no encontrado'];
        }
        
        $certs = [];
        if (!openssl_pkcs12_read(file_get_contents($path), $certs, $password)) {
            return ['valid' => false, 'error' => 'Contraseña incorrecta'];
        }
        
        $certInfo = openssl_x509_parse($certs['cert']);
        return [
            'valid' => true,
            'expired' => time() > $certInfo['validTo_time_t'],
            'cn' => $certInfo['subject']['CN'] ?? '',
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t'])
        ];
    }
}
