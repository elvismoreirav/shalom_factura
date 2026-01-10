<?php
/**
 * SHALOM FACTURA - Bootstrap
 * Inicialización del sistema
 */

// Definir constante de seguridad
define('SHALOM_FACTURA', true);

// Cargar configuración
require_once __DIR__ . '/config/config.php';

// Autoloader simple
spl_autoload_register(function (string $class) {
    // Mapeo de namespaces
    $namespaces = [
        'Shalom\\Core\\' => CORE_PATH . '/',
        'Shalom\\Modules\\' => MODULES_PATH . '/',
    ];
    
    foreach ($namespaces as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }
    
    return false;
});

// Crear directorios necesarios si no existen
$directories = [
    UPLOADS_PATH,
    UPLOADS_PATH . '/firmas',
    UPLOADS_PATH . '/comprobantes',
    UPLOADS_PATH . '/comprobantes/xml',
    UPLOADS_PATH . '/comprobantes/pdf',
    UPLOADS_PATH . '/logos',
    UPLOADS_PATH . '/temp',
    LOGS_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Funciones globales de conveniencia
if (!function_exists('e')) {
    function e(?string $value): string {
        return \Shalom\Core\Helpers::e($value);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        return \Shalom\Core\Helpers::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        return \Shalom\Core\Helpers::asset($path);
    }
}

if (!function_exists('auth')) {
    function auth(): \Shalom\Core\Auth {
        return \Shalom\Core\Auth::getInstance();
    }
}

if (!function_exists('db')) {
    function db(): \Shalom\Core\Database {
        return \Shalom\Core\Database::getInstance();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return auth()->generateCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed {
        return \Shalom\Core\Helpers::old($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string {
        return \Shalom\Core\Helpers::flash($key, $message);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $statusCode = 302): never {
        \Shalom\Core\Helpers::redirect($url, $statusCode);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): never {
        \Shalom\Core\Helpers::json($data, $statusCode);
    }
}

if (!function_exists('number_format_custom')) {
    function number_format_custom(float|int|null $value, int $decimals = 2): string {
        return \Shalom\Core\Helpers::number($value, $decimals);
    }
}

if (!function_exists('currency')) {
    function currency(float|int|null $value): string {
        return \Shalom\Core\Helpers::currency($value);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date): string {
        return \Shalom\Core\Helpers::date($date);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string {
        return \Shalom\Core\Helpers::datetime($datetime);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string {
        return \Shalom\Core\Helpers::timeAgo($datetime);
    }
}

// Manejador de errores personalizado
if (APP_DEBUG) {
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    
    set_exception_handler(function (\Throwable $e) {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ), 3, LOGS_PATH . '/exceptions.log');
        
        if (php_sapi_name() === 'cli') {
            echo "Error: " . $e->getMessage() . "\n";
        } else {
            http_response_code(500);
            if (\Shalom\Core\Helpers::isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor',
                    'debug' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ]);
            } else {
                echo "<h1>Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
        }
        exit(1);
    });
}

// Inicializar autenticación (inicia sesión si no está iniciada)
$auth = auth();
