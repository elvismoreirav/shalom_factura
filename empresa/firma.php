<?php
/**
 * SHALOM FACTURA - Configuración de Firma Electrónica
 * Fix: Eliminado has_flash(), uso de variables locales y manejo de errores P12
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Sri\FirmaElectronica;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('empresa.editar')) {
    flash('error', 'No tiene permisos para configurar la firma electrónica');
    redirect(url('empresa/'));
}

$db = db();
$empresaId = auth()->empresaId();
$empresa = auth()->empresa();

$firmaInfo = null;
$error = null;
$success = null;

// ==========================================
// 1. VERIFICAR FIRMA ACTUAL
// ==========================================
if ($empresa['firma_electronica_path']) {
    $firmaPath = UPLOADS_PATH . '/' . $empresa['firma_electronica_path'];
    
    if (file_exists($firmaPath)) {
        try {
            $firma = new FirmaElectronica($firmaPath, $empresa['firma_electronica_password'] ?? '');
            
            // Compatibilidad con versiones de FirmaElectronica
            if (method_exists($firma, 'verificarCertificado')) {
                $verificacion = $firma->verificarCertificado();
                $firmaInfo = $verificacion['info'] ?? null;
            } else {
                $firmaInfo = $firma->getInfo();
                $firmaInfo['vencido'] = $firma->proximoAVencer(0);
                $firmaInfo['titular'] = $firmaInfo['cn'] ?? 'Desconocido';
                $firmaInfo['emisor'] = $firmaInfo['issuer']['CN'] ?? 'Desconocido';
                $firmaInfo['valido_desde'] = $firmaInfo['valid_from'];
                $firmaInfo['valido_hasta'] = $firmaInfo['valid_to'];
            }
        } catch (Exception $e) {
            $error = "La firma configurada existe pero no se puede leer. Error: " . $e->getMessage();
        }
    }
}

// ==========================================
// 2. PROCESAR SUBIDA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'subir') {
        $password = $_POST['password'] ?? '';
        
        if (empty($_FILES['certificado']['name'])) {
            $error = 'Debe seleccionar un archivo de certificado';
        } elseif (empty($password)) {
            $error = 'La contraseña del certificado es requerida';
        } else {
            $file = $_FILES['certificado'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['p12', 'pfx'])) {
                $error = 'El archivo debe ser un certificado .p12 o .pfx';
            } else {
                // 1. Mover a temporal seguro para evitar problemas de permisos
                $tempDir = UPLOADS_PATH . '/temp';
                if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
                
                $tempFilename = 'chk_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                $tempPath = $tempDir . '/' . $tempFilename;
                
                if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                    try {
                        // 2. Validar certificado (Intenta abrir con la password)
                        $firma = new FirmaElectronica($tempPath, $password);
                        $info = $firma->getInfo(); // Si pasa esto, la contraseña es correcta
                        
                        // 3. Mover a carpeta final
                        $firmasDir = UPLOADS_PATH . '/firmas';
                        if (!is_dir($firmasDir)) mkdir($firmasDir, 0755, true);
                        
                        $filename = 'firma_' . $empresaId . '_' . time() . '.' . $ext;
                        $destPath  = $firmasDir . '/' . $filename;
                        
                        if (rename($tempPath, $destPath)) {
                            // 4. Actualizar DB
                            $db->update('empresas', [
                                'firma_electronica_path' => 'firmas/' . $filename,
                                'firma_electronica_password' => $password, 
                            ], 'id = :id', [':id' => $empresaId]);
                            
                            auth()->logActivity('actualizar', 'empresas', $empresaId, [], ['accion' => 'subir_firma']);
                            
                            // ÉXITO: Usamos variable local, NO redirect para mostrar el mensaje
                            $success = 'Certificado cargado y validado correctamente';
                            
                            // Actualizar info para la vista actual
                            $firmaInfo = $info;
                            $firmaInfo['titular'] = $info['cn'] ?? 'Desconocido';
                            $firmaInfo['emisor'] = $info['issuer']['CN'] ?? 'Desconocido';
                            $firmaInfo['valido_desde'] = $info['valid_from'];
                            $firmaInfo['valido_hasta'] = $info['valid_to'];
                            $firmaInfo['vencido'] = $firma->proximoAVencer(0);
                            
                        } else {
                            $error = 'Error al mover el certificado a la carpeta de firmas.';
                        }
                        
                    } catch (Exception $e) {
                        // Capturar error de contraseña o formato
                        $msg = $e->getMessage();
                        if (strpos($msg, 'Mac verify error') !== false || strpos($msg, 'PKCS12') !== false) {
                            $error = "<b>Error de Formato:</b> Su archivo .p12 es antiguo. La contraseña es correcta, pero el formato de cifrado no es compatible con este servidor. <br>Solución: Convierta su firma a formato moderno (AES-256) usando KeyStore Explorer o OpenSSL.";
                        } else {
                            $error = "Error al leer certificado: " . $msg;
                        }
                        
                        // Limpiar temp
                        if (file_exists($tempPath)) unlink($tempPath);
                    }
                } else {
                    $error = "Error al subir el archivo al servidor.";
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'eliminar') {
        // Eliminar certificado actual
        if ($empresa['firma_electronica_path']) {
            $firmaPath = UPLOADS_PATH . '/' . $empresa['firma_electronica_path'];
            if (file_exists($firmaPath)) {
                unlink($firmaPath);
            }
            
            $db->update('empresas', [
                'firma_electronica_path' => null,
                'firma_electronica_password' => null,
            ], 'id = :id', [':id' => $empresaId]);
            
            auth()->logActivity('actualizar', 'empresas', $empresaId, [], ['accion' => 'eliminar_firma']);
            
            flash('success', 'Certificado eliminado correctamente');
            redirect(url('empresa/firma.php'));
        }
    }
}

$pageTitle = 'Firma Electrónica';
$currentPage = 'empresa';
$breadcrumbs = [
    ['title' => 'Mi Empresa', 'url' => url('empresa/')],
    ['title' => 'Firma Electrónica']
];

ob_start();
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-shalom-primary">Firma Electrónica</h1>
            <p class="text-shalom-muted">Configure su certificado digital para firmar comprobantes</p>
        </div>
        <a href="<?= url('empresa/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-error mb-6">
        <i data-lucide="alert-circle" class="alert-icon"></i>
        <div class="alert-content"><?= ($error) ?></div> </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success mb-6">
        <i data-lucide="check-circle" class="alert-icon"></i>
        <div class="alert-content"><?= e($success) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if (function_exists('has_flash') && has_flash('success')): ?>
    <div class="alert alert-success mb-6">
        <i data-lucide="check-circle" class="alert-icon"></i>
        <div class="alert-content"><?= flash('success') ?></div>
    </div>
    <?php endif; ?>
    
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Estado del Certificado</h3>
        </div>
        <div class="card-body">
            <?php if ($firmaInfo): ?>
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 rounded-lg flex items-center justify-center <?= $firmaInfo['vencido'] ? 'bg-red-100' : 'bg-green-100' ?>">
                    <i data-lucide="<?= $firmaInfo['vencido'] ? 'alert-triangle' : 'shield-check' ?>" 
                       class="w-8 h-8 <?= $firmaInfo['vencido'] ? 'text-red-600' : 'text-green-600' ?>"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold text-lg <?= $firmaInfo['vencido'] ? 'text-red-600' : 'text-green-600' ?>">
                            <?= $firmaInfo['vencido'] ? 'Certificado Vencido' : 'Certificado Válido' ?>
                        </h4>
                        <span class="badge <?= $firmaInfo['vencido'] ? 'badge-danger' : 'badge-success' ?>">
                            <?= $firmaInfo['vencido'] ? 'Vencido' : 'Activo' ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <div class="text-sm text-shalom-muted">Titular</div>
                            <div class="font-medium"><?= e($firmaInfo['titular']) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Emisor</div>
                            <div class="font-medium"><?= e($firmaInfo['emisor']) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Válido desde</div>
                            <div class="font-medium"><?= date('d/m/Y H:i', strtotime($firmaInfo['valido_desde'])) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Válido hasta</div>
                            <div class="font-medium <?= $firmaInfo['vencido'] ? 'text-red-600' : '' ?>">
                                <?= date('d/m/Y H:i', strtotime($firmaInfo['valido_hasta'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($firmaInfo['vencido']): ?>
                    <div class="mt-4 p-3 bg-red-50 rounded-lg text-red-700 text-sm">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline mr-1"></i>
                        Su certificado ha vencido. No podrá emitir comprobantes electrónicos hasta que cargue un certificado válido.
                    </div>
                    <?php else: ?>
                        <?php 
                        $diasRestantes = floor((strtotime($firmaInfo['valido_hasta']) - time()) / 86400);
                        if ($diasRestantes <= 30): 
                        ?>
                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg text-yellow-700 text-sm">
                            <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                            Su certificado vence en <?= $diasRestantes ?> días. Considere renovarlo pronto.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-6 flex gap-3">
                <form method="POST" class="inline" onsubmit="return confirm('¿Está seguro de eliminar el certificado actual?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="eliminar">
                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        Eliminar Certificado
                    </button>
                </form>
            </div>
            
            <?php else: ?>
            <div class="text-center py-8">
                <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="key" class="w-10 h-10 text-yellow-600"></i>
                </div>
                <h4 class="text-lg font-semibold text-shalom-primary mb-2">Sin certificado configurado</h4>
                <p class="text-shalom-muted mb-4">
                    Necesita cargar su firma electrónica para poder emitir comprobantes electrónicos al SRI.
                </p>
                <?php if($empresa['firma_electronica_path']): ?>
                    <p class="text-red-500 text-sm bg-red-50 p-2 rounded">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline"></i>
                        Hay una firma cargada pero no se pudo leer. Intente cargarla nuevamente o verifique que el formato sea correcto.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?= $firmaInfo ? 'Actualizar Certificado' : 'Cargar Certificado' ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="subir">
                
                <div class="space-y-4">
                    <div>
                        <label class="form-label required">Archivo de Certificado (.p12 o .pfx)</label>
                        <input type="file" name="certificado" class="form-control" accept=".p12,.pfx" required>
                        <p class="form-text">
                            Seleccione el archivo de su firma electrónica. 
                            <strong>Nota:</strong> Si usa Mac/Linux y su firma es antigua, asegúrese de convertirla a formato estándar.
                        </p>
                    </div>
                    
                    <div>
                        <label class="form-label required">Contraseña del Certificado</label>
                        <input type="password" name="password" class="form-control" required>
                        <p class="form-text">
                            Ingrese la contraseña de su certificado.
                        </p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                            <?= $firmaInfo ? 'Actualizar Certificado' : 'Cargar Certificado' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';