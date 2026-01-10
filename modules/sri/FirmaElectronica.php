<?php
/**
 * SHALOM FACTURA - Firma Electrónica XAdES-BES (SRI Ecuador)
 *
 * Corrige:
 * - [39] FIRMA INVALIDA (digest/c14n consistente, no formatear)
 * - Error local DOMDocument::loadXML(): Attribute xmlns:etsi redefined
 *   (declaración xmlns correcta usando setAttributeNS XMLNS, no setAttribute)
 */

namespace Shalom\Modules\Sri;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;

class FirmaElectronica
{
    private string $certificadoPath;
    private string $password;

    /** @var string OpenSSL private key (PEM) */
    private string $privateKeyPem;

    /** @var string Cert PEM */
    private string $certPem;

    /** @var string Cert en base64 (sin headers/linebreaks) */
    private string $certBase64;

    /** @var array Info del certificado */
    private array $certificadoInfo = [];

    /** @var string Modulus/Exponent base64 */
    private string $modulusB64 = '';
    private string $exponentB64 = '';

    /** @var string Digest SHA1 del certificado DER en base64 */
    private string $certSha1B64 = '';

    public function __construct(string $certificadoPath, string $password)
    {
        $this->certificadoPath = $certificadoPath;
        $this->password = $password;
        $this->cargarCertificado();
    }

    private function cargarCertificado(): void
    {
        if (!file_exists($this->certificadoPath)) {
            throw new Exception('Certificado no encontrado');
        }

        $p12Content = file_get_contents($this->certificadoPath);
        $certs = [];

        if (!openssl_pkcs12_read($p12Content, $certs, $this->password)) {
            throw new Exception('No se pudo leer el certificado. Verifique la contraseña.');
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new Exception('Certificado P12 inválido o incompleto');
        }

        $this->privateKeyPem = $certs['pkey'];
        $this->certPem = $certs['cert'];

        $this->certBase64 = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r", " ", "\t"],
            '',
            $this->certPem
        );

        $certDer = base64_decode($this->certBase64);
        $this->certSha1B64 = base64_encode(sha1($certDer, true));

        $certInfo = openssl_x509_parse($this->certPem);
        if (!$certInfo) {
            throw new Exception('No se pudo parsear el certificado');
        }

        $this->certificadoInfo = [
            'subject' => $certInfo['subject'] ?? [],
            'issuer' => $certInfo['issuer'] ?? [],
            'serial' => $certInfo['serialNumber'] ?? '',
            'valid_from' => isset($certInfo['validFrom_time_t']) ? date('Y-m-d H:i:s', $certInfo['validFrom_time_t']) : '',
            'valid_to' => isset($certInfo['validTo_time_t']) ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : '',
            'cn' => $certInfo['subject']['CN'] ?? ''
        ];

        $pubKey = openssl_pkey_get_public($this->certPem);
        if (!$pubKey) {
            throw new Exception('No se pudo obtener clave pública del certificado');
        }
        $pubDetails = openssl_pkey_get_details($pubKey);
        if (empty($pubDetails['rsa']['n']) || empty($pubDetails['rsa']['e'])) {
            throw new Exception('No se pudo extraer módulo/exponente RSA del certificado');
        }
        $this->modulusB64 = base64_encode($pubDetails['rsa']['n']);
        $this->exponentB64 = base64_encode($pubDetails['rsa']['e']);
    }

    public function firmarXml(string $xml): string
    {
        // Mantener el XML "tal cual" pero sin BOM y sin espacios externos
        $xml = ltrim($xml, "\xEF\xBB\xBF");
        $xml = trim($xml);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!$doc->loadXML($xml, LIBXML_NOBLANKS)) {
            throw new Exception('Error cargando XML para firmar');
        }

        $root = $doc->documentElement;
        if (!$root instanceof DOMElement) {
            throw new Exception('XML inválido: sin elemento raíz');
        }

        // Evitar firmar 2 veces: si ya existe ds:Signature, eliminarla
        $dsNS = 'http://www.w3.org/2000/09/xmldsig#';
        $etsiNS = 'http://uri.etsi.org/01903/v1.3.2#';
        $xpath0 = new DOMXPath($doc);
        $xpath0->registerNamespace('ds', $dsNS);
        foreach (iterator_to_array($xpath0->query('//ds:Signature')) as $oldSig) {
            if ($oldSig && $oldSig->parentNode) {
                $oldSig->parentNode->removeChild($oldSig);
            }
        }

        $rootId = $root->getAttribute('id');
        if ($rootId === '') {
            $rootId = 'comprobante';
            $root->setAttribute('id', $rootId);
        }

        $claveAcceso = '';
        $nodesClave = $doc->getElementsByTagName('claveAcceso');
        if ($nodesClave->length > 0) {
            $claveAcceso = trim((string)$nodesClave->item(0)->nodeValue);
        }

        $uid = (string)random_int(100000, 999999);
        $signatureId = 'Signature' . $uid;
        $signedInfoId = 'Signature-SignedInfo' . $uid;
        $signatureValueId = 'SignatureValue' . $uid;
        $keyInfoId = 'Certificate' . $uid;
        $signedPropertiesId = $signatureId . '-SignedProperties' . (string)random_int(10000, 99999);
        $signedPropsRefId = 'SignedPropertiesID' . (string)random_int(100000, 999999);
        $docRefId = 'Reference-ID-' . (string)random_int(100000, 999999);

        // Digest del comprobante (sin firma) usando C14N inclusive
        $docDigestB64 = base64_encode(sha1($root->C14N(false, false), true));

        $sig = $doc->createElementNS($dsNS, 'ds:Signature');
        $sig->setAttribute('Id', $signatureId);

        // Declarar namespaces de forma CORRECTA (evita xmlns:etsi duplicado)
        $xmlnsNS = 'http://www.w3.org/2000/xmlns/';
        $sig->setAttributeNS($xmlnsNS, 'xmlns:ds', $dsNS);
        $sig->setAttributeNS($xmlnsNS, 'xmlns:etsi', $etsiNS);

        $signedInfo = $doc->createElementNS($dsNS, 'ds:SignedInfo');
        $signedInfo->setAttribute('Id', $signedInfoId);

        $canonMethod = $doc->createElementNS($dsNS, 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $doc->createElementNS($dsNS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sigMethod);

        // Reference: SignedProperties
        $refSignedProps = $doc->createElementNS($dsNS, 'ds:Reference');
        $refSignedProps->setAttribute('Id', $signedPropsRefId);
        $refSignedProps->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $refSignedProps->setAttribute('URI', '#' . $signedPropertiesId);

        $dm1 = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dm1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $refSignedProps->appendChild($dm1);

        $dv1 = $doc->createElementNS($dsNS, 'ds:DigestValue');
        $dv1->nodeValue = '';
        $refSignedProps->appendChild($dv1);
        $signedInfo->appendChild($refSignedProps);

        // Reference: KeyInfo
        $refCert = $doc->createElementNS($dsNS, 'ds:Reference');
        $refCert->setAttribute('URI', '#' . $keyInfoId);

        $dm2 = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dm2->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $refCert->appendChild($dm2);

        $dv2 = $doc->createElementNS($dsNS, 'ds:DigestValue');
        $dv2->nodeValue = '';
        $refCert->appendChild($dv2);
        $signedInfo->appendChild($refCert);

        // Reference: Documento
        $refDoc = $doc->createElementNS($dsNS, 'ds:Reference');
        $refDoc->setAttribute('Id', $docRefId);
        $refDoc->setAttribute('URI', '#' . $rootId);

        $transforms = $doc->createElementNS($dsNS, 'ds:Transforms');
        $t1 = $doc->createElementNS($dsNS, 'ds:Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($t1);
        $refDoc->appendChild($transforms);

        $dm3 = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $dm3->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $refDoc->appendChild($dm3);

        $dv3 = $doc->createElementNS($dsNS, 'ds:DigestValue');
        $dv3->nodeValue = $docDigestB64;
        $refDoc->appendChild($dv3);

        $signedInfo->appendChild($refDoc);

        $sig->appendChild($signedInfo);

        $sigValue = $doc->createElementNS($dsNS, 'ds:SignatureValue');
        $sigValue->setAttribute('Id', $signatureValueId);
        $sigValue->nodeValue = '';
        $sig->appendChild($sigValue);

        // KeyInfo
        $keyInfo = $doc->createElementNS($dsNS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        $x509Data = $doc->createElementNS($dsNS, 'ds:X509Data');
        $x509Cert = $doc->createElementNS($dsNS, 'ds:X509Certificate');
        $x509Cert->nodeValue = $this->certBase64;
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);

        $keyValue = $doc->createElementNS($dsNS, 'ds:KeyValue');
        $rsaKeyValue = $doc->createElementNS($dsNS, 'ds:RSAKeyValue');

        $modulus = $doc->createElementNS($dsNS, 'ds:Modulus');
        $modulus->nodeValue = $this->modulusB64;
        $rsaKeyValue->appendChild($modulus);

        $exponent = $doc->createElementNS($dsNS, 'ds:Exponent');
        $exponent->nodeValue = $this->exponentB64;
        $rsaKeyValue->appendChild($exponent);

        $keyValue->appendChild($rsaKeyValue);
        $keyInfo->appendChild($keyValue);

        $sig->appendChild($keyInfo);

        // Object / XAdES
        $object = $doc->createElementNS($dsNS, 'ds:Object');
        $object->setAttribute('Id', $signatureId . '-Object');

        $qualProps = $doc->createElementNS($etsiNS, 'etsi:QualifyingProperties');
        $qualProps->setAttribute('Target', '#' . $signatureId);

        $signedProps = $doc->createElementNS($etsiNS, 'etsi:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropertiesId);

        $ssp = $doc->createElementNS($etsiNS, 'etsi:SignedSignatureProperties');

        $signingTime = $doc->createElementNS($etsiNS, 'etsi:SigningTime');
        $signingTime->nodeValue = date('c');
        $ssp->appendChild($signingTime);

        $signingCert = $doc->createElementNS($etsiNS, 'etsi:SigningCertificate');
        $cert = $doc->createElementNS($etsiNS, 'etsi:Cert');

        $certDigest = $doc->createElementNS($etsiNS, 'etsi:CertDigest');
        $cdm = $doc->createElementNS($dsNS, 'ds:DigestMethod');
        $cdm->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $certDigest->appendChild($cdm);

        $cdv = $doc->createElementNS($dsNS, 'ds:DigestValue');
        $cdv->nodeValue = $this->certSha1B64;
        $certDigest->appendChild($cdv);
        $cert->appendChild($certDigest);

        $issuerSerial = $doc->createElementNS($etsiNS, 'etsi:IssuerSerial');
        $issuerName = $doc->createElementNS($dsNS, 'ds:X509IssuerName');
        $issuerName->nodeValue = $this->formatIssuerName($this->certificadoInfo['issuer'] ?? []);
        $issuerSerial->appendChild($issuerName);

        $serialNumber = $doc->createElementNS($dsNS, 'ds:X509SerialNumber');
        $serialNumber->nodeValue = (string)($this->certificadoInfo['serial'] ?? '');
        $issuerSerial->appendChild($serialNumber);

        $cert->appendChild($issuerSerial);
        $signingCert->appendChild($cert);
        $ssp->appendChild($signingCert);

        $signedProps->appendChild($ssp);

        $sdop = $doc->createElementNS($etsiNS, 'etsi:SignedDataObjectProperties');
        $dof = $doc->createElementNS($etsiNS, 'etsi:DataObjectFormat');
        $dof->setAttribute('ObjectReference', '#' . $docRefId);

        $desc = $doc->createElementNS($etsiNS, 'etsi:Description');
        $desc->nodeValue = 'contenido comprobante';
        $dof->appendChild($desc);

        $mime = $doc->createElementNS($etsiNS, 'etsi:MimeType');
        $mime->nodeValue = 'text/xml';
        $dof->appendChild($mime);

        $sdop->appendChild($dof);
        $signedProps->appendChild($sdop);

        $qualProps->appendChild($signedProps);
        $object->appendChild($qualProps);
        $sig->appendChild($object);

        $root->appendChild($sig);

        // Recalcular digests usando el nodo real y C14N inclusive
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', $dsNS);
        $xpath->registerNamespace('etsi', $etsiNS);

        $spNode = $xpath->query("//etsi:SignedProperties[@Id='{$signedPropertiesId}']")->item(0);
        if (!$spNode) throw new Exception('No se pudo ubicar SignedProperties para digest');
        $dv1->nodeValue = base64_encode(sha1($spNode->C14N(false, false), true));

        $kiNode = $xpath->query("//ds:KeyInfo[@Id='{$keyInfoId}']")->item(0);
        if (!$kiNode) throw new Exception('No se pudo ubicar KeyInfo para digest');
        $dv2->nodeValue = base64_encode(sha1($kiNode->C14N(false, false), true));

        // Firmar SignedInfo C14N inclusive (según CanonicalizationMethod)
        $siNode = $xpath->query("//ds:SignedInfo[@Id='{$signedInfoId}']")->item(0);
        if (!$siNode) throw new Exception('No se pudo ubicar SignedInfo para firmar');

        $signedInfoC14N = $siNode->C14N(false, false);
        $signatureBytes = '';
        if (!openssl_sign($signedInfoC14N, $signatureBytes, $this->privateKeyPem, OPENSSL_ALGO_SHA1)) {
            throw new Exception('Error al firmar SignedInfo: ' . (openssl_error_string() ?: 'openssl_sign falló'));
        }
        $sigValue->nodeValue = base64_encode($signatureBytes);

        $xmlFirmado = $doc->saveXML();

        // Debug: guardar firmado
        if ($claveAcceso !== '') {
            $suffix = substr($claveAcceso, -8);
            @file_put_contents($this->getDebugDir() . "/sri_xml_firmado_{$suffix}.xml", $xmlFirmado);
        }

        return $xmlFirmado;
    }

    public function getDebugDir(): string
    {
        $dir = getenv('SRI_DEBUG_DIR') ?: '/var/tmp/shalom_sri';

        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'shalom_sri';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
        }

        return rtrim($dir, DIRECTORY_SEPARATOR);
    }

    private function formatIssuerName(array $issuer): string
    {
        $order = ['CN', 'OU', 'O', 'L', 'ST', 'C'];
        $parts = [];

        foreach ($order as $k) {
            if (!isset($issuer[$k])) continue;
            $val = is_array($issuer[$k]) ? ($issuer[$k][0] ?? '') : (string)$issuer[$k];
            if ($val !== '') $parts[] = $k . '=' . $val;
        }

        foreach ($issuer as $k => $v) {
            if (in_array($k, $order, true)) continue;
            $val = is_array($v) ? ($v[0] ?? '') : (string)$v;
            if ($val !== '') $parts[] = $k . '=' . $val;
        }

        return implode(',', $parts);
    }

    public function getInfo(): array
    {
        return $this->certificadoInfo;
    }

    public function proximoAVencer(int $dias = 30): bool
    {
        $validTo = $this->certificadoInfo['valid_to'] ?? '';
        if ($validTo === '') return false;

        $ts = strtotime($validTo);
        if ($ts === false) return false;

        $diffDays = (int)floor(($ts - time()) / 86400);
        return $diffDays <= $dias;
    }
}
