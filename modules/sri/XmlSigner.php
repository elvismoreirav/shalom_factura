<?php
/**
 * SHALOM FACTURA - Firmador de XML con XAdES-BES
 * Implementa firma electrónica según estándar XAdES-BES requerido por el SRI Ecuador
 * 
 * Especificaciones técnicas (Ficha Técnica SRI):
 * - Estándar: XAdES_BES
 * - Versión esquema: 1.3.2
 * - Codificación: UTF-8
 * - Tipo de firma: ENVELOPED
 * - Algoritmo de firmado: RSA-SHA1
 * - Longitud de clave: 2048 bits
 * - Archivo de intercambio: PKCS12 (.p12)
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMXPath;
use Exception;

class XmlSigner
{
    private string $certificadoPath;
    private string $certificadoPassword;
    private ?array $certificadoInfo = null;
    
    // Namespaces para firma XAdES
    const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    const NS_ETSI = 'http://uri.etsi.org/01903/v1.3.2#';
    const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    
    // Algoritmos
    const ALGO_DIGEST = 'http://www.w3.org/2000/09/xmldsig#sha1';
    const ALGO_SIGNATURE = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    const ALGO_C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    const ALGO_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    
    public function __construct(string $certificadoPath, string $certificadoPassword)
    {
        $this->certificadoPath = $certificadoPath;
        $this->certificadoPassword = $certificadoPassword;
    }
    
    /**
     * Firmar XML con XAdES-BES
     * 
     * @param string $xml XML sin firmar
     * @return string XML firmado
     * @throws Exception Si hay errores de firma
     */
    public function firmar(string $xml): string
    {
        // Cargar certificado
        $certData = $this->cargarCertificado();
        
        // Preparar DOM
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        
        if (!$doc->loadXML($xml)) {
            throw new Exception('Error al cargar XML para firmar');
        }
        
        // Obtener elemento raíz (factura, notaCredito, etc.)
        $root = $doc->documentElement;
        
        // Generar IDs únicos para la firma
        $signatureId = 'Signature' . $this->generarId();
        $signedPropertiesId = 'SignedProperties' . $this->generarId();
        $signatureValueId = 'SignatureValue' . $this->generarId();
        $certificateId = 'Certificate' . $this->generarId();
        
        // Crear estructura de firma
        $signatureNode = $this->crearEstructuraFirma(
            $doc, 
            $certData, 
            $signatureId, 
            $signedPropertiesId, 
            $signatureValueId,
            $certificateId
        );
        
        // Calcular digest del documento (referencia principal)
        $docC14N = $this->canonicalize($doc, $root);
        $docDigest = base64_encode(sha1($docC14N, true));
        
        // Calcular digest de SignedProperties
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::NS_DS);
        $xpath->registerNamespace('etsi', self::NS_ETSI);
        
        // Insertar firma antes de cerrar el documento
        $root->appendChild($signatureNode);
        
        // Obtener SignedProperties después de insertar
        $signedPropsNodes = $xpath->query("//etsi:SignedProperties[@Id='$signedPropertiesId']");
        if ($signedPropsNodes->length > 0) {
            $signedPropsC14N = $this->canonicalizeNode($signedPropsNodes->item(0));
            $signedPropsDigest = base64_encode(sha1($signedPropsC14N, true));
        } else {
            $signedPropsDigest = '';
        }
        
        // Actualizar DigestValue del documento
        $docDigestNodes = $xpath->query("//ds:Reference[@URI='#comprobante']/ds:DigestValue");
        if ($docDigestNodes->length > 0) {
            $docDigestNodes->item(0)->nodeValue = $docDigest;
        }
        
        // Actualizar DigestValue de SignedProperties
        $propsDigestNodes = $xpath->query("//ds:Reference[@URI='#$signedPropertiesId']/ds:DigestValue");
        if ($propsDigestNodes->length > 0 && $signedPropsDigest) {
            $propsDigestNodes->item(0)->nodeValue = $signedPropsDigest;
        }
        
        // Calcular SignedInfo y firmar
        $signedInfoNodes = $xpath->query("//ds:SignedInfo");
        if ($signedInfoNodes->length > 0) {
            $signedInfoC14N = $this->canonicalizeNode($signedInfoNodes->item(0));
            
            // Firmar con clave privada
            $signature = '';
            if (!openssl_sign($signedInfoC14N, $signature, $certData['privateKey'], OPENSSL_ALGO_SHA1)) {
                throw new Exception('Error al firmar: ' . openssl_error_string());
            }
            
            // Actualizar SignatureValue
            $sigValueNodes = $xpath->query("//ds:SignatureValue[@Id='$signatureValueId']");
            if ($sigValueNodes->length > 0) {
                $sigValueNodes->item(0)->nodeValue = base64_encode($signature);
            }
        }
        
        return $doc->saveXML();
    }
    
    /**
     * Crear estructura completa de firma XAdES-BES
     */
    private function crearEstructuraFirma(
        DOMDocument $doc, 
        array $certData, 
        string $signatureId,
        string $signedPropertiesId,
        string $signatureValueId,
        string $certificateId
    ): \DOMElement {
        // Crear nodo Signature
        $signature = $doc->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);
        $signature->setAttribute('xmlns:ds', self::NS_DS);
        $signature->setAttribute('xmlns:etsi', self::NS_ETSI);
        
        // SignedInfo
        $signedInfo = $doc->createElementNS(self::NS_DS, 'ds:SignedInfo');
        
        // CanonicalizationMethod
        $c14nMethod = $doc->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', self::ALGO_C14N);
        $signedInfo->appendChild($c14nMethod);
        
        // SignatureMethod
        $sigMethod = $doc->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::ALGO_SIGNATURE);
        $signedInfo->appendChild($sigMethod);
        
        // Reference al documento
        $refDoc = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $refDoc->setAttribute('Id', 'Reference-' . $this->generarId());
        $refDoc->setAttribute('URI', '#comprobante');
        
        $transforms = $doc->createElementNS(self::NS_DS, 'ds:Transforms');
        
        $transform1 = $doc->createElementNS(self::NS_DS, 'ds:Transform');
        $transform1->setAttribute('Algorithm', self::ALGO_ENVELOPED);
        $transforms->appendChild($transform1);
        
        $refDoc->appendChild($transforms);
        
        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::ALGO_DIGEST);
        $refDoc->appendChild($digestMethod);
        
        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue');
        $refDoc->appendChild($digestValue);
        
        $signedInfo->appendChild($refDoc);
        
        // Reference a SignedProperties
        $refProps = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $refProps->setAttribute('Type', self::NS_XADES . '#SignedProperties');
        $refProps->setAttribute('URI', '#' . $signedPropertiesId);
        
        $digestMethod2 = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod2->setAttribute('Algorithm', self::ALGO_DIGEST);
        $refProps->appendChild($digestMethod2);
        
        $digestValue2 = $doc->createElementNS(self::NS_DS, 'ds:DigestValue');
        $refProps->appendChild($digestValue2);
        
        $signedInfo->appendChild($refProps);
        
        $signature->appendChild($signedInfo);
        
        // SignatureValue
        $sigValue = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue');
        $sigValue->setAttribute('Id', $signatureValueId);
        $signature->appendChild($sigValue);
        
        // KeyInfo
        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $certificateId);
        
        $x509Data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        $x509Cert = $doc->createElementNS(self::NS_DS, 'ds:X509Certificate');
        $x509Cert->nodeValue = $certData['certificateClean'];
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        
        $keyValue = $doc->createElementNS(self::NS_DS, 'ds:KeyValue');
        $rsaKeyValue = $doc->createElementNS(self::NS_DS, 'ds:RSAKeyValue');
        $modulus = $doc->createElementNS(self::NS_DS, 'ds:Modulus');
        $modulus->nodeValue = $certData['modulus'];
        $rsaKeyValue->appendChild($modulus);
        $exponent = $doc->createElementNS(self::NS_DS, 'ds:Exponent');
        $exponent->nodeValue = $certData['exponent'];
        $rsaKeyValue->appendChild($exponent);
        $keyValue->appendChild($rsaKeyValue);
        $keyInfo->appendChild($keyValue);
        
        $signature->appendChild($keyInfo);
        
        // Object (contiene QualifyingProperties con SignedProperties)
        $object = $doc->createElementNS(self::NS_DS, 'ds:Object');
        $object->setAttribute('Id', 'XadesObject-' . $this->generarId());
        
        $qualProps = $doc->createElementNS(self::NS_ETSI, 'etsi:QualifyingProperties');
        $qualProps->setAttribute('Target', '#' . $signatureId);
        
        $signedProps = $doc->createElementNS(self::NS_ETSI, 'etsi:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropertiesId);
        
        // SignedSignatureProperties
        $signedSigProps = $doc->createElementNS(self::NS_ETSI, 'etsi:SignedSignatureProperties');
        
        $signingTime = $doc->createElementNS(self::NS_ETSI, 'etsi:SigningTime');
        $signingTime->nodeValue = date('c'); // ISO 8601
        $signedSigProps->appendChild($signingTime);
        
        // SigningCertificate
        $signingCert = $doc->createElementNS(self::NS_ETSI, 'etsi:SigningCertificate');
        $cert = $doc->createElementNS(self::NS_ETSI, 'etsi:Cert');
        
        $certDigest = $doc->createElementNS(self::NS_ETSI, 'etsi:CertDigest');
        $certDigestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $certDigestMethod->setAttribute('Algorithm', self::ALGO_DIGEST);
        $certDigest->appendChild($certDigestMethod);
        $certDigestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue');
        $certDigestValue->nodeValue = $certData['certDigest'];
        $certDigest->appendChild($certDigestValue);
        $cert->appendChild($certDigest);
        
        $issuerSerial = $doc->createElementNS(self::NS_ETSI, 'etsi:IssuerSerial');
        $issuerName = $doc->createElementNS(self::NS_DS, 'ds:X509IssuerName');
        $issuerName->nodeValue = $certData['issuer'];
        $issuerSerial->appendChild($issuerName);
        $serialNumber = $doc->createElementNS(self::NS_DS, 'ds:X509SerialNumber');
        $serialNumber->nodeValue = $certData['serialNumber'];
        $issuerSerial->appendChild($serialNumber);
        $cert->appendChild($issuerSerial);
        
        $signingCert->appendChild($cert);
        $signedSigProps->appendChild($signingCert);
        
        $signedProps->appendChild($signedSigProps);
        
        // SignedDataObjectProperties (opcional)
        $signedDataProps = $doc->createElementNS(self::NS_ETSI, 'etsi:SignedDataObjectProperties');
        $dataObjectFormat = $doc->createElementNS(self::NS_ETSI, 'etsi:DataObjectFormat');
        $dataObjectFormat->setAttribute('ObjectReference', '#Reference-' . $this->generarId());
        $description = $doc->createElementNS(self::NS_ETSI, 'etsi:Description');
        $description->nodeValue = 'contenido comprobante';
        $dataObjectFormat->appendChild($description);
        $mimeType = $doc->createElementNS(self::NS_ETSI, 'etsi:MimeType');
        $mimeType->nodeValue = 'text/xml';
        $dataObjectFormat->appendChild($mimeType);
        $signedDataProps->appendChild($dataObjectFormat);
        $signedProps->appendChild($signedDataProps);
        
        $qualProps->appendChild($signedProps);
        $object->appendChild($qualProps);
        $signature->appendChild($object);
        
        return $signature;
    }
    
    /**
     * Cargar y extraer información del certificado PKCS12
     */
    private function cargarCertificado(): array
    {
        if ($this->certificadoInfo !== null) {
            return $this->certificadoInfo;
        }
        
        if (!file_exists($this->certificadoPath)) {
            throw new Exception("Certificado no encontrado: {$this->certificadoPath}");
        }
        
        $pkcs12 = file_get_contents($this->certificadoPath);
        $certs = [];
        
        if (!openssl_pkcs12_read($pkcs12, $certs, $this->certificadoPassword)) {
            throw new Exception('Error al leer certificado P12. Verifique la contraseña.');
        }
        
        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new Exception('Certificado P12 inválido o incompleto');
        }
        
        // Extraer información del certificado
        $certResource = openssl_x509_read($certs['cert']);
        $certData = openssl_x509_parse($certResource);
        $pubKey = openssl_pkey_get_public($certResource);
        $pubKeyDetails = openssl_pkey_get_details($pubKey);
        
        // Limpiar certificado (quitar headers PEM)
        $certClean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\n|\r/', '', $certs['cert']);
        
        // Calcular digest del certificado
        $certDer = base64_decode($certClean);
        $certDigest = base64_encode(sha1($certDer, true));
        
        // Obtener módulo y exponente RSA
        $modulus = base64_encode($pubKeyDetails['rsa']['n']);
        $exponent = base64_encode($pubKeyDetails['rsa']['e']);
        
        // Construir nombre del emisor
        $issuerParts = [];
        if (!empty($certData['issuer']['CN'])) $issuerParts[] = 'CN=' . $certData['issuer']['CN'];
        if (!empty($certData['issuer']['O'])) $issuerParts[] = 'O=' . $certData['issuer']['O'];
        if (!empty($certData['issuer']['C'])) $issuerParts[] = 'C=' . $certData['issuer']['C'];
        $issuer = implode(',', $issuerParts);
        
        $this->certificadoInfo = [
            'certificate' => $certs['cert'],
            'certificateClean' => $certClean,
            'privateKey' => $certs['pkey'],
            'certDigest' => $certDigest,
            'modulus' => $modulus,
            'exponent' => $exponent,
            'issuer' => $issuer,
            'serialNumber' => $certData['serialNumber'] ?? '',
            'subject' => $certData['subject'] ?? [],
            'validFrom' => date('Y-m-d H:i:s', $certData['validFrom_time_t']),
            'validTo' => date('Y-m-d H:i:s', $certData['validTo_time_t'])
        ];
        
        return $this->certificadoInfo;
    }
    
    /**
     * Canonicalizar documento completo
     */
    private function canonicalize(DOMDocument $doc, \DOMElement $element): string
    {
        return $element->C14N(true, false);
    }
    
    /**
     * Canonicalizar nodo específico
     */
    private function canonicalizeNode(\DOMNode $node): string
    {
        return $node->C14N(true, false);
    }
    
    /**
     * Generar ID único
     */
    private function generarId(): string
    {
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Verificar validez del certificado
     */
    public function verificarCertificado(): array
    {
        try {
            $certData = $this->cargarCertificado();
            
            $ahora = time();
            $validFrom = strtotime($certData['validFrom']);
            $validTo = strtotime($certData['validTo']);
            
            $esValido = ($ahora >= $validFrom && $ahora <= $validTo);
            $diasRestantes = floor(($validTo - $ahora) / 86400);
            
            return [
                'valido' => $esValido,
                'subject' => $certData['subject'],
                'issuer' => $certData['issuer'],
                'valid_from' => $certData['validFrom'],
                'valid_to' => $certData['validTo'],
                'dias_restantes' => $diasRestantes,
                'mensaje' => $esValido 
                    ? "Certificado válido. Expira en $diasRestantes días."
                    : ($ahora < $validFrom ? 'Certificado aún no válido' : 'Certificado expirado')
            ];
        } catch (Exception $e) {
            return [
                'valido' => false,
                'mensaje' => 'Error al verificar certificado: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener información del propietario del certificado
     */
    public function getInfoPropietario(): array
    {
        $certData = $this->cargarCertificado();
        return $certData['subject'];
    }
}
