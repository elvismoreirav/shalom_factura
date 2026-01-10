<?php
/**
 * SHALOM FACTURA - Crear/Editar Servicio
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Servicios\Servicio;

if (!auth()->check()) {
    redirect(url('login.php'));
}

$servicioModel = new Servicio();
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    if (!auth()->can('servicios.editar')) {
        flash('error', 'No tiene permisos para editar servicios');
        redirect(url('servicios/'));
    }
    
    $servicio = $servicioModel->getById($id);
    if (!$servicio) {
        flash('error', 'Servicio no encontrado');
        redirect(url('servicios/'));
    }
    $pageTitle = 'Editar Servicio';
} else {
    if (!auth()->can('servicios.crear')) {
        flash('error', 'No tiene permisos para crear servicios');
        redirect(url('servicios/'));
    }
    $servicio = [];
    $pageTitle = 'Nuevo Servicio';
}

$categorias = $servicioModel->getCategorias();
$impuestos = $servicioModel->getImpuestosIva();
$errors = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'codigo' => trim($_POST['codigo'] ?? ''),
        'codigo_auxiliar' => trim($_POST['codigo_auxiliar'] ?? ''),
        'nombre' => trim($_POST['nombre'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'categoria_id' => $_POST['categoria_id'] ?? null,
        'precio_unitario' => (float)($_POST['precio_unitario'] ?? 0),
        'costo' => (float)($_POST['costo'] ?? 0),
        'impuesto_id' => $_POST['impuesto_id'] ?? '',
        'tipo' => $_POST['tipo'] ?? 'servicio',
        'unidad_medida' => trim($_POST['unidad_medida'] ?? 'UNIDAD'),
        'es_recurrente' => isset($_POST['es_recurrente']) ? 1 : 0,
        'periodo_recurrencia' => $_POST['periodo_recurrencia'] ?? null
    ];
    
    if ($isEdit) {
        $result = $servicioModel->update($id, $data);
    } else {
        $result = $servicioModel->create($data);
    }
    
    if ($result['success']) {
        flash('success', $result['message']);
        redirect(url('servicios/'));
    } else {
        $errors[] = $result['message'];
        $servicio = array_merge($servicio, $data);
    }
}

$currentPage = 'servicios';
$breadcrumbs = [
    ['title' => 'Servicios', 'url' => url('servicios/')],
    ['title' => $isEdit ? 'Editar' : 'Nuevo']
];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-shalom-primary"><?= e($pageTitle) ?></h1>
            <p class="text-shalom-muted">Complete la información del servicio</p>
        </div>
        <a href="<?= url('servicios/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
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
    
    <form method="POST" data-validate>
        <?= csrf_field() ?>
        
        <!-- Información Básica -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="info" class="w-5 h-5 inline mr-2"></i>
                    Información Básica
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label required">Código</label>
                        <input type="text" name="codigo" class="form-control" required
                               value="<?= e($servicio['codigo'] ?? '') ?>"
                               placeholder="Ej: SERV001">
                    </div>
                    
                    <div>
                        <label class="form-label">Código Auxiliar</label>
                        <input type="text" name="codigo_auxiliar" class="form-control"
                               value="<?= e($servicio['codigo_auxiliar'] ?? '') ?>">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label required">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= e($servicio['nombre'] ?? '') ?>"
                               placeholder="Nombre del servicio o producto">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"
                                  placeholder="Descripción detallada"><?= e($servicio['descripcion'] ?? '') ?></textarea>
                    </div>
                    
                    <div>
                        <label class="form-label">Categoría</label>
                        <select name="categoria_id" class="form-control">
                            <option value="">Sin categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($servicio['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-control">
                            <option value="servicio" <?= ($servicio['tipo'] ?? 'servicio') === 'servicio' ? 'selected' : '' ?>>Servicio</option>
                            <option value="producto" <?= ($servicio['tipo'] ?? '') === 'producto' ? 'selected' : '' ?>>Producto</option>
                            <option value="paquete" <?= ($servicio['tipo'] ?? '') === 'paquete' ? 'selected' : '' ?>>Paquete</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Precios e Impuestos -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="dollar-sign" class="w-5 h-5 inline mr-2"></i>
                    Precios e Impuestos
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label required">Precio Unitario</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="precio_unitario" class="form-control" required
                                   value="<?= e($servicio['precio_unitario'] ?? 0) ?>"
                                   min="0" step="0.0001">
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Costo</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="costo" class="form-control"
                                   value="<?= e($servicio['costo'] ?? 0) ?>"
                                   min="0" step="0.0001">
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label required">Tipo de IVA</label>
                        <select name="impuesto_id" class="form-control" required>
                            <?php foreach ($impuestos as $imp): ?>
                            <option value="<?= $imp['id'] ?>" <?= ($servicio['impuesto_id'] ?? 4) == $imp['id'] ? 'selected' : '' ?>>
                                <?= e($imp['nombre']) ?> (<?= $imp['porcentaje'] ?>%)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Unidad de Medida</label>
                        <input type="text" name="unidad_medida" class="form-control"
                               value="<?= e($servicio['unidad_medida'] ?? 'UNIDAD') ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración de Recurrencia -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="repeat" class="w-5 h-5 inline mr-2"></i>
                    Configuración de Recurrencia
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-check">
                            <input type="checkbox" name="es_recurrente" class="form-check-input" 
                                   <?= ($servicio['es_recurrente'] ?? 0) ? 'checked' : '' ?>
                                   onchange="toggleRecurrencia()">
                            <span>Es un servicio recurrente</span>
                        </label>
                        <p class="text-sm text-shalom-muted mt-1">Marque si este servicio se factura periódicamente</p>
                    </div>
                    
                    <div id="periodoRecurrencia" class="<?= ($servicio['es_recurrente'] ?? 0) ? '' : 'hidden' ?>">
                        <label class="form-label">Período de Recurrencia</label>
                        <select name="periodo_recurrencia" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="diario" <?= ($servicio['periodo_recurrencia'] ?? '') === 'diario' ? 'selected' : '' ?>>Diario</option>
                            <option value="semanal" <?= ($servicio['periodo_recurrencia'] ?? '') === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                            <option value="quincenal" <?= ($servicio['periodo_recurrencia'] ?? '') === 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
                            <option value="mensual" <?= ($servicio['periodo_recurrencia'] ?? '') === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                            <option value="trimestral" <?= ($servicio['periodo_recurrencia'] ?? '') === 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                            <option value="semestral" <?= ($servicio['periodo_recurrencia'] ?? '') === 'semestral' ? 'selected' : '' ?>>Semestral</option>
                            <option value="anual" <?= ($servicio['periodo_recurrencia'] ?? '') === 'anual' ? 'selected' : '' ?>>Anual</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones -->
        <div class="flex justify-end gap-3">
            <a href="<?= url('servicios/') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i>
                <?= $isEdit ? 'Guardar Cambios' : 'Crear Servicio' ?>
            </button>
        </div>
    </form>
</div>

<script>
function toggleRecurrencia() {
    const checkbox = document.querySelector('[name="es_recurrente"]');
    const periodoDiv = document.getElementById('periodoRecurrencia');
    
    if (checkbox.checked) {
        periodoDiv.classList.remove('hidden');
    } else {
        periodoDiv.classList.add('hidden');
        document.querySelector('[name="periodo_recurrencia"]').value = '';
    }
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
