<?php
/**
 * SHALOM FACTURA - Configuración de Empresa
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('empresa.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$db = db();
$empresaId = auth()->empresaId();
$empresa = auth()->empresa();

// Obtener establecimientos
$establecimientos = $db->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM puntos_emision pe WHERE pe.establecimiento_id = e.id) as puntos_count
    FROM establecimientos e
    WHERE e.empresa_id = :empresa_id
    ORDER BY e.codigo
")->fetchAll([':empresa_id' => $empresaId]);

// Procesar formulario
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth()->can('empresa.editar')) {
    $action = $_POST['action'] ?? 'empresa';
    
    if ($action === 'empresa') {
        $data = [
            'razon_social' => trim($_POST['razon_social'] ?? ''),
            'nombre_comercial' => trim($_POST['nombre_comercial'] ?? ''),
            'direccion_matriz' => trim($_POST['direccion_matriz'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'obligado_contabilidad' => $_POST['obligado_contabilidad'] ?? 'NO',
            'contribuyente_especial' => trim($_POST['contribuyente_especial'] ?? ''),
            'tipo_contribuyente' => $_POST['tipo_contribuyente'] ?? 'PERSONA_NATURAL',
            'ambiente_sri' => $_POST['ambiente_sri'] ?? '1'
        ];
        
        if (empty($data['razon_social'])) {
            $errors[] = 'La razón social es requerida';
        }
        
        if (empty($errors)) {
            $db->update('empresas', $data, 'id = :id', [':id' => $empresaId]);
            
            // Manejar logo
            if (!empty($_FILES['logo']['name'])) {
                $file = $_FILES['logo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $filename = 'logo_' . $empresaId . '_' . time() . '.' . $ext;
                    $path = LOGOS_PATH . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $db->update('empresas', ['logo_path' => 'uploads/logos/' . $filename], 'id = :id', [':id' => $empresaId]);
                    }
                }
            }
            
            auth()->logActivity('actualizar', 'empresas', $empresaId);
            flash('success', 'Configuración actualizada correctamente');
            redirect(url('empresa/'));
        }
    }
}

$pageTitle = 'Mi Empresa';
$currentPage = 'empresa';
$breadcrumbs = [['title' => 'Mi Empresa']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Configuración de Empresa</h1>
        <p class="text-shalom-muted">Administre los datos de su empresa y establecimientos</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error mb-6">
    <i data-lucide="alert-circle" class="alert-icon"></i>
    <div class="alert-content">
        <?php foreach ($errors as $error): ?>
        <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Información de la Empresa -->
    <div class="lg:col-span-2">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="empresa">
            
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="building-2" class="w-5 h-5 inline mr-2"></i>
                        Datos de la Empresa
                    </h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">RUC</label>
                            <input type="text" class="form-control bg-gray-50" value="<?= e($empresa['ruc']) ?>" disabled>
                            <p class="form-text">El RUC no puede ser modificado</p>
                        </div>
                        
                        <div>
                            <label class="form-label required">Razón Social</label>
                            <input type="text" name="razon_social" class="form-control" required
                                   value="<?= e($empresa['razon_social']) ?>">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label">Nombre Comercial</label>
                            <input type="text" name="nombre_comercial" class="form-control"
                                   value="<?= e($empresa['nombre_comercial']) ?>">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label required">Dirección Matriz</label>
                            <textarea name="direccion_matriz" class="form-control" rows="2" required><?= e($empresa['direccion_matriz']) ?></textarea>
                        </div>
                        
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($empresa['email']) ?>">
                        </div>
                        
                        <div>
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control"
                                   value="<?= e($empresa['telefono']) ?>">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label">Sitio Web</label>
                            <input type="url" name="website" class="form-control"
                                   value="<?= e($empresa['website']) ?>"
                                   placeholder="https://www.ejemplo.com">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="file-text" class="w-5 h-5 inline mr-2"></i>
                        Configuración Tributaria
                    </h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Tipo de Contribuyente</label>
                            <select name="tipo_contribuyente" class="form-control">
                                <option value="PERSONA_NATURAL" <?= $empresa['tipo_contribuyente'] === 'PERSONA_NATURAL' ? 'selected' : '' ?>>Persona Natural</option>
                                <option value="SOCIEDAD" <?= $empresa['tipo_contribuyente'] === 'SOCIEDAD' ? 'selected' : '' ?>>Sociedad</option>
                                <option value="RIMPE_EMPRENDEDOR" <?= $empresa['tipo_contribuyente'] === 'RIMPE_EMPRENDEDOR' ? 'selected' : '' ?>>RIMPE Emprendedor</option>
                                <option value="RIMPE_NEGOCIO_POPULAR" <?= $empresa['tipo_contribuyente'] === 'RIMPE_NEGOCIO_POPULAR' ? 'selected' : '' ?>>RIMPE Negocio Popular</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Obligado a Llevar Contabilidad</label>
                            <select name="obligado_contabilidad" class="form-control">
                                <option value="NO" <?= $empresa['obligado_contabilidad'] === 'NO' ? 'selected' : '' ?>>No</option>
                                <option value="SI" <?= $empresa['obligado_contabilidad'] === 'SI' ? 'selected' : '' ?>>Sí</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Contribuyente Especial (Resolución)</label>
                            <input type="text" name="contribuyente_especial" class="form-control"
                                   value="<?= e($empresa['contribuyente_especial']) ?>"
                                   placeholder="Número de resolución (si aplica)">
                        </div>
                        
                        <div>
                            <label class="form-label">Ambiente SRI</label>
                            <select name="ambiente_sri" class="form-control">
                                <option value="1" <?= $empresa['ambiente_sri'] === '1' ? 'selected' : '' ?>>Pruebas</option>
                                <option value="2" <?= $empresa['ambiente_sri'] === '2' ? 'selected' : '' ?>>Producción</option>
                            </select>
                            <p class="form-text text-warning">⚠️ Cambie a producción solo cuando esté listo</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="image" class="w-5 h-5 inline mr-2"></i>
                        Logo de la Empresa
                    </h3>
                </div>
                <div class="card-body">
                    <div class="flex items-start gap-6">
                        <div class="w-32 h-32 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden">
                            <?php if ($empresa['logo_path']): ?>
                            <img src="<?= url($empresa['logo_path']) ?>" alt="Logo" class="max-w-full max-h-full object-contain">
                            <?php else: ?>
                            <i data-lucide="building-2" class="w-12 h-12 text-gray-400"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <p class="form-text mt-2">Formatos: JPG, PNG, GIF. Máximo 2MB. Recomendado: 200x200px</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (auth()->can('empresa.editar')): ?>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Guardar Cambios
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Establecimientos -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Establecimientos</h3>
                <?php if (auth()->can('empresa.establecimientos')): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="mostrarModalEstablecimiento()">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($establecimientos)): ?>
                <div class="p-6 text-center text-shalom-muted">
                    <i data-lucide="building" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                    <p>No hay establecimientos</p>
                </div>
                <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($establecimientos as $est): ?>
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-shalom-primary"><?= e($est['codigo']) ?></span>
                                    <?php if ($est['es_matriz']): ?>
                                    <span class="badge badge-primary">Matriz</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm font-medium"><?= e($est['nombre']) ?></div>
                                <div class="text-xs text-shalom-muted"><?= $est['puntos_count'] ?> punto(s) de emisión</div>
                            </div>
                            <span class="badge <?= $est['activo'] ? 'badge-success' : 'badge-muted' ?>">
                                <?= $est['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Firma Electrónica -->
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title">Firma Electrónica</h3>
            </div>
            <div class="card-body">
                <?php if ($empresa['firma_electronica_path']): ?>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <div>
                        <div class="font-medium text-green-600">Firma configurada</div>
                        <div class="text-xs text-shalom-muted">
                            Vence: <?= format_date($empresa['firma_electronica_vencimiento']) ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-6 h-6 text-yellow-600"></i>
                    </div>
                    <div>
                        <div class="font-medium text-yellow-600">Sin firma electrónica</div>
                        <div class="text-xs text-shalom-muted">Requerida para emitir comprobantes</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (auth()->can('empresa.editar')): ?>
                <a href="<?= url('empresa/firma.php') ?>" class="btn btn-secondary w-full">
                    <i data-lucide="key" class="w-4 h-4"></i>
                    <?= $empresa['firma_electronica_path'] ? 'Actualizar Firma' : 'Configurar Firma' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
