<?php
/**
 * SHALOM FACTURA - Generador de Clave de Acceso SRI
 * Genera claves de acceso de 49 dígitos según especificación del SRI Ecuador
 * 
 * Estructura (Tabla 1 - Ficha Técnica SRI):
 * - Fecha emisión (ddmmaaaa): 8 dígitos
 * - Tipo comprobante: 2 dígitos
 * - RUC: 13 dígitos
 * - Tipo ambiente: 1 dígito
 * - Serie (establecimiento + punto emisión): 6 dígitos
 * - Secuencial: 9 dígitos
 * - Código numérico: 8 dígitos
 * - Tipo emisión: 1 dígito
 * - Dígito verificador (módulo 11): 1 dígito
 * 
 * Total: 49 dígitos
 */

namespace Shalom\Modules\Sri;

class ClaveAcceso
{
    // Tipos de comprobante (Tabla 3 SRI)
    const TIPO_FACTURA = '01';
    const TIPO_LIQUIDACION_COMPRA = '03';
    const TIPO_NOTA_CREDITO = '04';
    const TIPO_NOTA_DEBITO = '05';
    const TIPO_GUIA_REMISION = '06';
    const TIPO_RETENCION = '07';
    
    // Tipos de ambiente (Tabla 4 SRI)
    const AMBIENTE_PRUEBAS = '1';
    const AMBIENTE_PRODUCCION = '2';
    
    // Tipo de emisión (Tabla 2 SRI)
    const TIPO_EMISION_NORMAL = '1';
    
    /**
     * Generar clave de acceso completa
     */
    public static function generar(
        string $fechaEmision,      // Formato: Y-m-d o d/m/Y
        string $tipoComprobante,   // 01, 03, 04, 05, 06, 07
        string $ruc,               // 13 dígitos
        string $ambiente,          // 1 = pruebas, 2 = producción
        string $establecimiento,   // 3 dígitos
        string $puntoEmision,      // 3 dígitos
        int $secuencial,           // Número secuencial
        ?string $codigoNumerico = null // 8 dígitos aleatorios (opcional)
    ): string {
        // Formatear fecha a ddmmaaaa
        $fecha = self::formatearFecha($fechaEmision);
        
        // Validar y formatear campos
        $tipoComp = str_pad($tipoComprobante, 2, '0', STR_PAD_LEFT);
        $rucFormateado = str_pad($ruc, 13, '0', STR_PAD_LEFT);
        $amb = substr($ambiente, 0, 1);
        $serie = str_pad($establecimiento, 3, '0', STR_PAD_LEFT) . 
                 str_pad($puntoEmision, 3, '0', STR_PAD_LEFT);
        $sec = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        
        // Código numérico (8 dígitos aleatorios si no se proporciona)
        $codNum = $codigoNumerico ?? self::generarCodigoNumerico();
        
        // Tipo de emisión siempre normal (1) para offline
        $tipoEmision = self::TIPO_EMISION_NORMAL;
        
        // Construir clave sin dígito verificador (48 dígitos)
        $claveSinVerificador = $fecha . $tipoComp . $rucFormateado . $amb . 
                              $serie . $sec . $codNum . $tipoEmision;
        
        // Calcular dígito verificador con módulo 11
        $digitoVerificador = self::calcularModulo11($claveSinVerificador);
        
        // Clave completa (49 dígitos)
        return $claveSinVerificador . $digitoVerificador;
    }
    
    /**
     * Formatear fecha a formato ddmmaaaa
     */
    private static function formatearFecha(string $fecha): string
    {
        // Intentar parsear diferentes formatos
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            // Intentar formato d/m/Y
            $parts = explode('/', $fecha);
            if (count($parts) === 3) {
                $timestamp = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
            }
        }
        
        if ($timestamp === false) {
            throw new \InvalidArgumentException("Formato de fecha inválido: $fecha");
        }
        
        return date('dmY', $timestamp);
    }
    
    /**
     * Generar código numérico aleatorio de 8 dígitos
     */
    public static function generarCodigoNumerico(): string
    {
        return str_pad(random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }
    
    /**
     * Calcular dígito verificador usando Módulo 11
     * 
     * Algoritmo según ficha técnica SRI:
     * 1. Multiplicar cada dígito por factor (2,3,4,5,6,7) de derecha a izquierda
     * 2. Sumar todos los productos
     * 3. Calcular módulo 11 del resultado
     * 4. Restar de 11
     * 5. Si resultado es 11, dígito = 0; si es 10, dígito = 1
     */
    public static function calcularModulo11(string $cadena): string
    {
        $factores = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        $indiceFactor = 0;
        
        // Recorrer de derecha a izquierda
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $digito = (int) $cadena[$i];
            $suma += $digito * $factores[$indiceFactor];
            $indiceFactor = ($indiceFactor + 1) % 6; // Ciclar factores 2-7
        }
        
        $residuo = $suma % 11;
        $resultado = 11 - $residuo;
        
        // Casos especiales según SRI
        if ($resultado === 11) {
            return '0';
        } elseif ($resultado === 10) {
            return '1';
        }
        
        return (string) $resultado;
    }
    
    /**
     * Validar estructura de clave de acceso
     */
    public static function validar(string $claveAcceso): array
    {
        $errores = [];
        
        // Verificar longitud
        if (strlen($claveAcceso) !== 49) {
            $errores[] = 'La clave de acceso debe tener exactamente 49 dígitos';
            return ['valido' => false, 'errores' => $errores];
        }
        
        // Verificar que solo contenga números
        if (!ctype_digit($claveAcceso)) {
            $errores[] = 'La clave de acceso solo debe contener dígitos numéricos';
            return ['valido' => false, 'errores' => $errores];
        }
        
        // Extraer componentes
        $fecha = substr($claveAcceso, 0, 8);
        $tipoComprobante = substr($claveAcceso, 8, 2);
        $ruc = substr($claveAcceso, 10, 13);
        $ambiente = substr($claveAcceso, 23, 1);
        $establecimiento = substr($claveAcceso, 24, 3);
        $puntoEmision = substr($claveAcceso, 27, 3);
        $secuencial = substr($claveAcceso, 30, 9);
        $codigoNumerico = substr($claveAcceso, 39, 8);
        $tipoEmision = substr($claveAcceso, 47, 1);
        $digitoVerificador = substr($claveAcceso, 48, 1);
        
        // Verificar dígito verificador
        $claveSinVerificador = substr($claveAcceso, 0, 48);
        $digitoCalculado = self::calcularModulo11($claveSinVerificador);
        
        if ($digitoVerificador !== $digitoCalculado) {
            $errores[] = "Dígito verificador inválido. Esperado: $digitoCalculado, Recibido: $digitoVerificador";
        }
        
        // Verificar tipo de comprobante válido
        $tiposValidos = ['01', '03', '04', '05', '06', '07'];
        if (!in_array($tipoComprobante, $tiposValidos)) {
            $errores[] = "Tipo de comprobante inválido: $tipoComprobante";
        }
        
        // Verificar ambiente válido
        if (!in_array($ambiente, ['1', '2'])) {
            $errores[] = "Tipo de ambiente inválido: $ambiente";
        }
        
        // Verificar tipo de emisión
        if ($tipoEmision !== '1') {
            $errores[] = "Tipo de emisión inválido: $tipoEmision (debe ser 1)";
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'componentes' => [
                'fecha' => $fecha,
                'tipo_comprobante' => $tipoComprobante,
                'ruc' => $ruc,
                'ambiente' => $ambiente,
                'establecimiento' => $establecimiento,
                'punto_emision' => $puntoEmision,
                'secuencial' => $secuencial,
                'codigo_numerico' => $codigoNumerico,
                'tipo_emision' => $tipoEmision,
                'digito_verificador' => $digitoVerificador
            ]
        ];
    }
    
    /**
     * Obtener nombre del tipo de comprobante
     */
    public static function getNombreTipoComprobante(string $codigo): string
    {
        return match($codigo) {
            '01' => 'FACTURA',
            '03' => 'LIQUIDACIÓN DE COMPRA',
            '04' => 'NOTA DE CRÉDITO',
            '05' => 'NOTA DE DÉBITO',
            '06' => 'GUÍA DE REMISIÓN',
            '07' => 'COMPROBANTE DE RETENCIÓN',
            default => 'DESCONOCIDO'
        };
    }
}
