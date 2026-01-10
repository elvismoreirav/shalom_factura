<?php
/**
 * SHALOM FACTURA - Ver Log de Debugging SRI
 * Coloca este archivo en la raíz de tu proyecto y accede desde el navegador
 * URL: http://tu-dominio.com/sri_debug_viewer.php
 * 
 * ¡IMPORTANTE! Elimina este archivo después de debugear por seguridad.
 */

// Configuración
$logDir = sys_get_temp_dir();
$today = date('Y-m-d');
$logFile = $logDir . '/sri_debug_' . $today . '.log';

// Estilos
echo '<!DOCTYPE html>
<html>
<head>
    <title>SRI Debug Log Viewer</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        h1 { color: #569cd6; }
        h2 { color: #4ec9b0; margin-top: 30px; }
        .info { background: #264f78; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #6d5700; padding: 10px; border-radius: 5px; }
        .error { background: #6d1c1c; padding: 10px; border-radius: 5px; }
        .success { background: #1c6d1c; padding: 10px; border-radius: 5px; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .section { border: 1px solid #404040; margin: 20px 0; padding: 15px; border-radius: 5px; }
        .timestamp { color: #6a9955; }
        .tipo { color: #ce9178; font-weight: bold; }
        .separator { border-top: 2px solid #569cd6; margin: 30px 0; }
        button { background: #0e639c; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; margin: 5px; }
        button:hover { background: #1177bb; }
        .actions { margin: 20px 0; }
    </style>
</head>
<body>';

echo '<h1>🔍 SRI Debug Log Viewer</h1>';

// Información del sistema
echo '<div class="info">';
echo '<strong>Directorio de logs:</strong> ' . htmlspecialchars($logDir) . '<br>';
echo '<strong>Archivo de hoy:</strong> ' . htmlspecialchars($logFile) . '<br>';
echo '<strong>Fecha actual:</strong> ' . date('Y-m-d H:i:s') . '<br>';
echo '<strong>PHP Version:</strong> ' . phpversion() . '<br>';
echo '</div>';

// Acciones
echo '<div class="actions">';
echo '<form method="post" style="display: inline;">';
echo '<button type="submit" name="action" value="refresh">🔄 Refrescar</button>';
echo '<button type="submit" name="action" value="clear">🗑️ Limpiar Log</button>';
echo '<button type="submit" name="action" value="download">📥 Descargar Log</button>';
echo '</form>';
echo '</div>';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear' && file_exists($logFile)) {
        file_put_contents($logFile, '');
        echo '<div class="success">✓ Log limpiado correctamente</div>';
    }
    
    if ($action === 'download' && file_exists($logFile)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="sri_debug_' . $today . '.log"');
        readfile($logFile);
        exit;
    }
}

// Buscar archivos de log
echo '<h2>📁 Archivos de Log Disponibles</h2>';
$logFiles = glob($logDir . '/sri_debug_*.log');

if (empty($logFiles)) {
    echo '<div class="warning">No se encontraron archivos de log del SRI.</div>';
    echo '<p>Los logs se generarán cuando emitas una factura al SRI.</p>';
} else {
    echo '<ul>';
    foreach ($logFiles as $file) {
        $size = filesize($file);
        $sizeFormatted = $size > 1024 ? round($size / 1024, 2) . ' KB' : $size . ' bytes';
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo '<li>' . basename($file) . ' (' . $sizeFormatted . ') - Modificado: ' . $modified . '</li>';
    }
    echo '</ul>';
}

// Mostrar contenido del log de hoy
echo '<h2>📋 Contenido del Log de Hoy</h2>';

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    
    if (empty(trim($content))) {
        echo '<div class="warning">El archivo de log está vacío. Emite una factura para generar registros.</div>';
    } else {
        // Dividir en secciones
        $sections = preg_split('/={80}/', $content);
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            
            echo '<div class="section">';
            
            // Resaltar tipos
            $section = preg_replace('/\[([\d-]+ [\d:]+)\]/', '<span class="timestamp">[$1]</span>', $section);
            $section = preg_replace('/(RECEPCION_[A-Z_]+|AUTORIZACION_[A-Z_]+)/', '<span class="tipo">$1</span>', $section);
            
            // Formatear JSON si existe
            if (preg_match('/JSON:\s*(\{[\s\S]*?\})/m', $section, $matches)) {
                $json = $matches[1];
                $formatted = json_encode(json_decode($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($formatted) {
                    $section = str_replace($json, $formatted, $section);
                }
            }
            
            echo '<pre>' . htmlspecialchars($section) . '</pre>';
            echo '</div>';
        }
    }
} else {
    echo '<div class="warning">El archivo de log de hoy no existe aún.</div>';
    echo '<p>Emite una factura al SRI para generar el log de debugging.</p>';
}

// Instrucciones
echo '<h2>📖 Instrucciones</h2>';
echo '<div class="info">';
echo '<ol>';
echo '<li>Reemplaza <code>modules/sri/SriService.php</code> con la versión del ZIP</li>';
echo '<li>Intenta emitir una factura al SRI</li>';
echo '<li>Vuelve a esta página y haz clic en "Refrescar"</li>';
echo '<li>Revisa los logs para identificar el problema</li>';
echo '<li><strong>¡IMPORTANTE!</strong> Elimina este archivo después de debugear</li>';
echo '</ol>';
echo '</div>';

// Qué buscar
echo '<h2>🔎 Qué Buscar en el Log</h2>';
echo '<div class="info">';
echo '<ul>';
echo '<li><strong>RECEPCION_RESPONSE_RAW:</strong> Respuesta cruda del servicio de recepción</li>';
echo '<li><strong>RECEPCION_ANALISIS:</strong> Estructura del objeto de respuesta</li>';
echo '<li><strong>AUTORIZACION_RESPONSE_RAW:</strong> Respuesta cruda del servicio de autorización</li>';
echo '<li><strong>AUTORIZACION_ANALISIS:</strong> Propiedades disponibles en la respuesta</li>';
echo '<li><strong>AUTORIZACION_INDIVIDUAL:</strong> Datos de cada autorización</li>';
echo '<li><strong>has_estado / estado_value:</strong> Si el estado está presente y su valor</li>';
echo '</ul>';
echo '</div>';

echo '</body></html>';
