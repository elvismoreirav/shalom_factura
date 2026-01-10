<?php
/**
 * SHALOM FACTURA - Configuración Principal
 * Sistema de Facturación Electrónica
 * Desarrollado por Shalom - Soluciones Digitales con Propósito
 */

// Prevenir acceso directo
if (!defined('SHALOM_FACTURA')) {
    die('Acceso no permitido');
}

// =====================================================
// CONFIGURACIÓN DE ENTORNO
// =====================================================
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // development, production
define('APP_DEBUG', APP_ENV === 'development');
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'Shalom Factura');

// =====================================================
// RUTAS DEL SISTEMA
// =====================================================
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('API_PATH', ROOT_PATH . '/api');

// =====================================================
// URLs BASE
// =====================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Método robusto: usar DOCUMENT_ROOT para calcular la ruta relativa
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$rootPath = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);

if (!empty($documentRoot) && strpos($rootPath, $documentRoot) === 0) {
    // El proyecto está dentro del DOCUMENT_ROOT
    $basePath = substr($rootPath, strlen($documentRoot));
} else {
    // Fallback: extraer del SCRIPT_NAME buscando el directorio del proyecto
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $projectDir = basename(ROOT_PATH); // nombre del directorio del proyecto
    
    if (preg_match('#^(.*/' . preg_quote($projectDir, '#') . ')#', $scriptName, $matches)) {
        $basePath = $matches[1];
    } else {
        // Último recurso: ir al padre del directorio actual del script
        $basePath = dirname(dirname($scriptName));
        if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }
    }
}

$basePath = rtrim($basePath, '/');

define('BASE_URL', $protocol . '://' . $host . $basePath);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// =====================================================
// CONFIGURACIÓN DE BASE DE DATOS
// =====================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'shalom_factura');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '12345678');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// CONFIGURACIÓN DE SESIÓN
// =====================================================
define('SESSION_NAME', 'shalom_session');
define('SESSION_LIFETIME', 7200); // 2 horas
define('SESSION_PATH', '/');
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTPONLY', true);

// =====================================================
// CONFIGURACIÓN DE SEGURIDAD
// =====================================================
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'shalom-factura-2024-secret-key-change-in-production');
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// =====================================================
// CONFIGURACIÓN DE ZONA HORARIA
// =====================================================
define('TIMEZONE', 'America/Guayaquil');
date_default_timezone_set(TIMEZONE);

// =====================================================
// CONFIGURACIÓN DE ERRORES
// =====================================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Log de errores
ini_set('log_errors', 1);
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// =====================================================
// CONFIGURACIÓN DE UPLOADS
// =====================================================
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/xml']);
define('FIRMA_PATH', UPLOADS_PATH . '/firmas');
define('COMPROBANTES_PATH', UPLOADS_PATH . '/comprobantes');
define('LOGOS_PATH', UPLOADS_PATH . '/logos');

// =====================================================
// CONFIGURACIÓN SRI
// =====================================================
define('SRI_AMBIENTE_PRUEBAS', '1');
define('SRI_AMBIENTE_PRODUCCION', '2');

// WSDLs de pruebas
define('SRI_WSDL_RECEPCION_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl');
define('SRI_WSDL_AUTORIZACION_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl');

// WSDLs de producción
define('SRI_WSDL_RECEPCION_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl');
define('SRI_WSDL_AUTORIZACION_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl');

// =====================================================
// CONFIGURACIÓN DE EMAIL
// =====================================================
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'smtp');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@shalom.ec');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// =====================================================
// COLORES DE LA MARCA SHALOM
// =====================================================
define('COLOR_PRIMARY', '#1e4d39');      // Verde oscuro principal
define('COLOR_SECONDARY', '#f9f8f4');    // Blanco marfil
define('COLOR_ACCENT', '#A3B7A5');       // Verde oliva claro
define('COLOR_MUTED', '#73796F');        // Gris cálido suave
define('COLOR_GOLD', '#D6C29A');         // Dorado premium

// =====================================================
// PAGINACIÓN
// =====================================================
define('ITEMS_PER_PAGE', 25);
define('MAX_ITEMS_PER_PAGE', 100);

// =====================================================
// FORMATOS
// =====================================================
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');
define('DECIMAL_SEPARATOR', '.');
define('THOUSAND_SEPARATOR', ',');
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_CODE', 'USD');
