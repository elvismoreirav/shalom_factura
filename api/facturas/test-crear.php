<?php
/**
 * DIAGNÓSTICO - Test crear factura
 * Acceder directamente: /api/facturas/test-crear.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Capturar cualquier output o error
ob_start();

$diagnostico = [
    'paso' => 'inicio',
    'errores' => [],
    'output_previo' => '',
    'memoria_inicio' => memory_get_usage(true),
];

try {
    $diagnostico['paso'] = 'cargando_bootstrap';
    require_once dirname(__DIR__, 2) . '/bootstrap.php';
    
    $diagnostico['paso'] = 'bootstrap_cargado';
    $diagnostico['output_previo'] = ob_get_clean();
    ob_start();
    
    // Verificar autenticación
    $diagnostico['paso'] = 'verificando_auth';
    if (!auth()->check()) {
        throw new Exception('No autenticado');
    }
    
    $diagnostico['auth'] = [
        'usuario_id' => auth()->id(),
        'empresa_id' => auth()->empresaId(),
    ];
    
    // Si es GET, mostrar formulario de prueba
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html>
<head><title>Test API Facturas</title></head>
<body style="font-family: monospace; padding: 20px;">
<h2>Diagnóstico API Facturas</h2>
<pre>' . json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>
<hr>
<h3>Test con datos mínimos</h3>
<form method="POST" id="testForm">
<p>Cliente ID: <input type="text" name="cliente_id" value="1" size="5"></p>
<p>Establecimiento ID: <input type="text" name="establecimiento_id" value="1" size="5"></p>
<p>Punto Emisión ID: <input type="text" name="punto_emision_id" value="1" size="5"></p>
<button type="submit">Probar Crear Factura</button>
</form>
<div id="resultado" style="margin-top:20px; padding:10px; background:#f0f0f0;"></div>
<script>
document.getElementById("testForm").addEventListener("submit", async function(e) {
    e.preventDefault();
    const resultado = document.getElementById("resultado");
    resultado.innerHTML = "Enviando...";
    
    const data = {
        cliente_id: document.querySelector("[name=cliente_id]").value,
        establecimiento_id: document.querySelector("[name=establecimiento_id]").value,
        punto_emision_id: document.querySelector("[name=punto_emision_id]").value,
        tipo_comprobante_id: 1,
        fecha_emision: new Date().toISOString().split("T")[0],
        detalles: [{
            codigo: "TEST001",
            descripcion: "Servicio de prueba",
            cantidad: 1,
            precio_unitario: 100,
            descuento: 0,
            subtotal: 100,
            impuestos: [{
                impuesto_id: 4,
                codigo: "2",
                codigo_porcentaje: "4",
                tarifa: 15,
                base_imponible: 100,
                valor: 15
            }]
        }],
        formas_pago: [{
            forma_pago_id: 1,
            total: 115,
            plazo: 0
        }],
        accion: "guardar"
    };
    
    try {
        const startTime = Date.now();
        const response = await fetch("crear.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(data)
        });
        const endTime = Date.now();
        
        const responseText = await response.text();
        
        resultado.innerHTML = "<h4>Respuesta (" + (endTime-startTime) + "ms):</h4>" +
            "<p>Status: " + response.status + "</p>" +
            "<p>Content-Type: " + response.headers.get("content-type") + "</p>" +
            "<p>Longitud: " + responseText.length + " bytes</p>" +
            "<pre style=\"white-space:pre-wrap;word-break:break-all;max-height:300px;overflow:auto;background:#fff;padding:10px;\">" + 
            responseText.substring(0, 5000) + "</pre>";
            
        // Intentar parsear como JSON
        try {
            const json = JSON.parse(responseText);
            resultado.innerHTML += "<h4>JSON Parseado:</h4><pre>" + JSON.stringify(json, null, 2) + "</pre>";
        } catch(e) {
            resultado.innerHTML += "<p style=\"color:red;\">Error parseando JSON: " + e.message + "</p>";
        }
    } catch(e) {
        resultado.innerHTML = "<p style=\"color:red;\">Error: " + e.message + "</p>";
    }
});
</script>
</body></html>';
        exit;
    }
    
    // Si es POST, procesar
    $diagnostico['paso'] = 'procesando_post';
    
    $inputRaw = file_get_contents('php://input');
    $diagnostico['input_length'] = strlen($inputRaw);
    
    $input = json_decode($inputRaw, true);
    if (!$input) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    $diagnostico['paso'] = 'creando_factura';
    $diagnostico['memoria_antes_crear'] = memory_get_usage(true);
    
    $facturaModel = new \Shalom\Modules\Facturas\Factura();
    $result = $facturaModel->create($input);
    
    $diagnostico['paso'] = 'factura_creada';
    $diagnostico['resultado'] = $result;
    $diagnostico['memoria_despues'] = memory_get_usage(true);
    
    // Limpiar buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar respuesta
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $diagnostico['error'] = $e->getMessage();
    $diagnostico['trace'] = $e->getTraceAsString();
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'diagnostico' => $diagnostico
    ], JSON_UNESCAPED_UNICODE);
}
