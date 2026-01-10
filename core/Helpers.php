<?php
/**
 * SHALOM FACTURA - Helpers
 * Funciones auxiliares del sistema
 */

namespace Shalom\Core;

class Helpers
{
    /**
     * Generar UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Sanitizar entrada
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }
    
    /**
     * Escapar HTML
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Formatear número
     */
    public static function number(float|int|null $value, int $decimals = 2): string
    {
        return number_format($value ?? 0, $decimals, DECIMAL_SEPARATOR, THOUSAND_SEPARATOR);
    }
    
    /**
     * Formatear moneda
     */
    public static function currency(float|int|null $value, string $symbol = CURRENCY_SYMBOL): string
    {
        return $symbol . ' ' . self::number($value);
    }
    
    /**
     * Formatear fecha
     */
    public static function date(?string $date, string $format = DATE_FORMAT): string
    {
        if (!$date) return '';
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    }
    
    /**
     * Formatear fecha y hora
     */
    public static function datetime(?string $datetime, string $format = DATETIME_FORMAT): string
    {
        return self::date($datetime, $format);
    }
    
    /**
     * Fecha en formato ISO
     */
    public static function dateISO(?string $date): string
    {
        if (!$date) return '';
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }
    
    /**
     * Fecha relativa (hace X tiempo)
     */
    public static function timeAgo(?string $datetime): string
    {
        if (!$datetime) return '';
        
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'hace un momento';
        }
        
        $intervals = [
            31536000 => ['año', 'años'],
            2592000 => ['mes', 'meses'],
            604800 => ['semana', 'semanas'],
            86400 => ['día', 'días'],
            3600 => ['hora', 'horas'],
            60 => ['minuto', 'minutos']
        ];
        
        foreach ($intervals as $seconds => $labels) {
            $count = floor($diff / $seconds);
            if ($count > 0) {
                $label = $count == 1 ? $labels[0] : $labels[1];
                return "hace $count $label";
            }
        }
        
        return 'hace un momento';
    }
    
    /**
     * Truncar texto
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Generar slug
     */
    public static function slug(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return strtolower(trim($text, '-'));
    }
    
    /**
     * Validar cédula ecuatoriana
     */
    public static function validarCedula(string $cedula): bool
    {
        $cedula = preg_replace('/[^0-9]/', '', $cedula);
        
        if (strlen($cedula) !== 10) {
            return false;
        }
        
        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            return false;
        }
        
        $tercerDigito = (int) $cedula[2];
        if ($tercerDigito > 5) {
            return false;
        }
        
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $resultado = (int) $cedula[$i] * $coeficientes[$i];
            if ($resultado > 9) {
                $resultado -= 9;
            }
            $suma += $resultado;
        }
        
        $digitoVerificador = (10 - ($suma % 10)) % 10;
        
        return $digitoVerificador === (int) $cedula[9];
    }
    
    /**
     * Validar RUC ecuatoriano
     */
    public static function validarRuc(string $ruc): bool
    {
        $ruc = preg_replace('/[^0-9]/', '', $ruc);
        
        if (strlen($ruc) !== 13) {
            return false;
        }
        
        // RUC de persona natural
        if ((int) $ruc[2] < 6) {
            if (substr($ruc, 10) !== '001') {
                return false;
            }
            return self::validarCedula(substr($ruc, 0, 10));
        }
        
        // RUC de sociedad privada
        if ((int) $ruc[2] === 9) {
            $coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
            $suma = 0;
            
            for ($i = 0; $i < 9; $i++) {
                $suma += (int) $ruc[$i] * $coeficientes[$i];
            }
            
            $residuo = $suma % 11;
            $verificador = $residuo === 0 ? 0 : 11 - $residuo;
            
            return $verificador === (int) $ruc[9];
        }
        
        // RUC de sociedad pública
        if ((int) $ruc[2] === 6) {
            $coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
            $suma = 0;
            
            for ($i = 0; $i < 8; $i++) {
                $suma += (int) $ruc[$i] * $coeficientes[$i];
            }
            
            $residuo = $suma % 11;
            $verificador = $residuo === 0 ? 0 : 11 - $residuo;
            
            return $verificador === (int) $ruc[8];
        }
        
        return false;
    }
    
    /**
     * Validar email
     */
    public static function validarEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Formatear identificación
     */
    public static function formatIdentificacion(string $tipo, string $numero): string
    {
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        return match ($tipo) {
            '05' => substr($numero, 0, 10),
            '04' => substr($numero, 0, 13),
            default => $numero
        };
    }
    
    /**
     * Generar clave de acceso SRI
     */
    public static function generarClaveAcceso(
        string $fechaEmision,
        string $tipoComprobante,
        string $ruc,
        string $ambiente,
        string $establecimiento,
        string $puntoEmision,
        string $secuencial,
        string $tipoEmision = '1'
    ): string {
        // Formato: ddmmaaaa
        $fecha = date('dmY', strtotime($fechaEmision));
        
        // Código numérico aleatorio (8 dígitos)
        $codigoNumerico = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Concatenar sin el dígito verificador
        $clave = $fecha .
            str_pad($tipoComprobante, 2, '0', STR_PAD_LEFT) .
            $ruc .
            $ambiente .
            str_pad($establecimiento, 3, '0', STR_PAD_LEFT) .
            str_pad($puntoEmision, 3, '0', STR_PAD_LEFT) .
            str_pad($secuencial, 9, '0', STR_PAD_LEFT) .
            $codigoNumerico .
            $tipoEmision;
        
        // Calcular dígito verificador (módulo 11)
        $digitoVerificador = self::calcularModulo11($clave);
        
        return $clave . $digitoVerificador;
    }
    
    /**
     * Calcular módulo 11
     */
    public static function calcularModulo11(string $cadena): int
    {
        $factor = 2;
        $suma = 0;
        
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int) $cadena[$i] * $factor;
            $factor++;
            if ($factor > 7) {
                $factor = 2;
            }
        }
        
        $resto = $suma % 11;
        $resultado = 11 - $resto;
        
        if ($resultado === 11) {
            return 0;
        }
        if ($resultado === 10) {
            return 1;
        }
        
        return $resultado;
    }
    
    /**
     * Número a letras (para facturas)
     */
    public static function numeroALetras(float $numero, string $moneda = 'DÓLARES'): string
    {
        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $especiales = [
            11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            21 => 'VEINTIUNO', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE'
        ];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
        
        $partes = explode('.', number_format($numero, 2, '.', ''));
        $entero = (int) $partes[0];
        $centavos = (int) ($partes[1] ?? 0);
        
        $resultado = '';
        
        if ($entero == 0) {
            $resultado = 'CERO';
        } elseif ($entero == 1) {
            $resultado = 'UN';
        } else {
            // Millones
            if ($entero >= 1000000) {
                $millones = (int) ($entero / 1000000);
                if ($millones == 1) {
                    $resultado .= 'UN MILLÓN ';
                } else {
                    $resultado .= self::convertirGrupo($millones, $unidades, $decenas, $especiales, $centenas) . ' MILLONES ';
                }
                $entero %= 1000000;
            }
            
            // Miles
            if ($entero >= 1000) {
                $miles = (int) ($entero / 1000);
                if ($miles == 1) {
                    $resultado .= 'MIL ';
                } else {
                    $resultado .= self::convertirGrupo($miles, $unidades, $decenas, $especiales, $centenas) . ' MIL ';
                }
                $entero %= 1000;
            }
            
            // Centenas, decenas, unidades
            if ($entero > 0) {
                $resultado .= self::convertirGrupo($entero, $unidades, $decenas, $especiales, $centenas);
            }
        }
        
        $resultado = trim($resultado) . ' ' . $moneda;
        
        if ($centavos > 0) {
            $resultado .= ' CON ' . str_pad($centavos, 2, '0', STR_PAD_LEFT) . '/100';
        }
        
        return $resultado;
    }
    
    /**
     * Convertir grupo de 3 dígitos a letras
     */
    private static function convertirGrupo(int $numero, array $unidades, array $decenas, array $especiales, array $centenas): string
    {
        $resultado = '';
        
        if ($numero == 100) {
            return 'CIEN';
        }
        
        if ($numero >= 100) {
            $resultado .= $centenas[(int)($numero / 100)] . ' ';
            $numero %= 100;
        }
        
        if (isset($especiales[$numero])) {
            return trim($resultado . $especiales[$numero]);
        }
        
        if ($numero >= 10) {
            $resultado .= $decenas[(int)($numero / 10)];
            $numero %= 10;
            if ($numero > 0) {
                $resultado .= ' Y ';
            }
        }
        
        if ($numero > 0) {
            $resultado .= $unidades[$numero];
        }
        
        return trim($resultado);
    }
    
    /**
     * Obtener input GET sanitizado
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::sanitize($_GET[$key] ?? $default);
    }
    
    /**
     * Obtener input POST sanitizado
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        return self::sanitize($_POST[$key] ?? $default);
    }
    
    /**
     * Obtener input REQUEST sanitizado
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        return self::sanitize($_REQUEST[$key] ?? $default);
    }
    
    /**
     * Verificar si es petición AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Verificar si es petición POST
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Respuesta JSON
     */
    public static function json(array $data, int $statusCode = 200): never
    {
        // Limpiar cualquier output previo
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            // Error de encoding, enviar error genérico
            echo json_encode(['success' => false, 'message' => 'Error de codificación JSON']);
        } else {
            echo $json;
        }
        
        exit;
    }
    
    /**
     * Redireccionar
     */
    public static function redirect(string $url, int $statusCode = 302): never
    {
        header("Location: $url", true, $statusCode);
        exit;
    }
    
    /**
     * Generar URL
     */
    public static function url(string $path = ''): string
    {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Generar URL de asset
     */
    public static function asset(string $path): string
    {
        return rtrim(ASSETS_URL, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Flash message
     */
    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
    
    /**
     * Obtener valor antiguo de formulario
     */
    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
    
    /**
     * Guardar valores antiguos
     */
    public static function setOld(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
    
    /**
     * Limpiar valores antiguos
     */
    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }
    
    /**
     * Generar contraseña segura
     */
    public static function generatePassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Formatear bytes a unidades legibles
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Obtener extensión de archivo
     */
    public static function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Verificar si es imagen válida
     */
    public static function isValidImage(string $mimeType): bool
    {
        return in_array($mimeType, ALLOWED_IMAGE_TYPES);
    }
}