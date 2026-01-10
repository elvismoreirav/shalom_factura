<?php
/**
 * SHALOM FACTURA - Crear/Editar Cliente
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Clientes\Cliente;
use Shalom\Core\Helpers;

if (!auth()->check()) {
    redirect(url('login.php'));
}

$clienteModel = new Cliente();
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) {
    if (!auth()->can('clientes.editar')) {
        flash('error', 'No tiene permisos para editar clientes');
        redirect(url('clientes/'));
    }
    
    $cliente = $clienteModel->getById($id);
    if (!$cliente) {
        flash('error', 'Cliente no encontrado');
        redirect(url('clientes/'));
    }
    $pageTitle = 'Editar Cliente';
} else {
    if (!auth()->can('clientes.crear')) {
        flash('error', 'No tiene permisos para crear clientes');
        redirect(url('clientes/'));
    }
    $cliente = [];
    $pageTitle = 'Nuevo Cliente';
}

$tiposIdentificacion = $clienteModel->getTiposIdentificacion();
$errors = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo_identificacion_id' => $_POST['tipo_identificacion_id'] ?? '',
        'identificacion' => trim($_POST['identificacion'] ?? ''),
        'razon_social' => trim($_POST['razon_social'] ?? ''),
        'nombre_comercial' => trim($_POST['nombre_comercial'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'celular' => trim($_POST['celular'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'ciudad' => trim($_POST['ciudad'] ?? ''),
        'provincia' => trim($_POST['provincia'] ?? ''),
        'es_contribuyente_especial' => isset($_POST['es_contribuyente_especial']) ? 1 : 0,
        'aplica_retencion' => isset($_POST['aplica_retencion']) ? 1 : 0,
        'porcentaje_descuento' => (float)($_POST['porcentaje_descuento'] ?? 0),
        'dias_credito' => (int)($_POST['dias_credito'] ?? 0),
        'limite_credito' => (float)($_POST['limite_credito'] ?? 0),
        'notas' => trim($_POST['notas'] ?? ''),
        'contacto_nombre' => trim($_POST['contacto_nombre'] ?? ''),
        'contacto_cargo' => trim($_POST['contacto_cargo'] ?? '')
    ];
    
    if ($isEdit) {
        $result = $clienteModel->update($id, $data);
    } else {
        $result = $clienteModel->create($data);
    }
    
    if ($result['success']) {
        flash('success', $result['message']);
        redirect(url('clientes/'));
    } else {
        $errors[] = $result['message'];
        $cliente = array_merge($cliente, $data);
    }
}

$currentPage = 'clientes';
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => url('clientes/')],
    ['title' => $isEdit ? 'Editar' : 'Nuevo']
];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-shalom-primary"><?= e($pageTitle) ?></h1>
            <p class="text-shalom-muted">Complete la información del cliente</p>
        </div>
        <a href="<?= url('clientes/') ?>" class="btn btn-secondary">
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
        
        <!-- Datos de Identificación -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="id-card" class="w-5 h-5 inline mr-2"></i>
                    Datos de Identificación
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label required">Tipo de Identificación</label>
                        <select name="tipo_identificacion_id" class="form-control" required onchange="validarIdentificacion()">
                            <option value="">Seleccione...</option>
                            <?php foreach ($tiposIdentificacion as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>" data-codigo="<?= $tipo['codigo'] ?>" <?= ($cliente['tipo_identificacion_id'] ?? '') == $tipo['id'] ? 'selected' : '' ?>>
                                <?= e($tipo['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label required">Identificación</label>
                        <input type="text" name="identificacion" class="form-control" required
                               value="<?= e($cliente['identificacion'] ?? '') ?>"
                               data-mask="identificacion"
                               placeholder="Ingrese RUC o Cédula">
                        <div class="form-text" id="identificacionHelp"></div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label required">Razón Social / Nombres</label>
                        <input type="text" name="razon_social" class="form-control" required
                               value="<?= e($cliente['razon_social'] ?? '') ?>"
                               placeholder="Nombre completo o razón social">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control"
                               value="<?= e($cliente['nombre_comercial'] ?? '') ?>"
                               placeholder="Nombre comercial (opcional)">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Datos de Contacto -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="phone" class="w-5 h-5 inline mr-2"></i>
                    Datos de Contacto
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($cliente['email'] ?? '') ?>"
                               placeholder="correo@ejemplo.com">
                    </div>
                    
                    <div>
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control"
                               value="<?= e($cliente['telefono'] ?? '') ?>"
                               data-mask="telefono"
                               placeholder="04-XXXXXXX">
                    </div>
                    
                    <div>
                        <label class="form-label">Celular</label>
                        <input type="text" name="celular" class="form-control"
                               value="<?= e($cliente['celular'] ?? '') ?>"
                               data-mask="telefono"
                               placeholder="09XXXXXXXX">
                    </div>
                    
                    <div>
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control"
                               value="<?= e($cliente['ciudad'] ?? '') ?>">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion" class="form-control" rows="2"
                                  placeholder="Dirección completa"><?= e($cliente['direccion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración Comercial -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="settings" class="w-5 h-5 inline mr-2"></i>
                    Configuración Comercial
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">% Descuento</label>
                        <div class="input-group">
                            <input type="number" name="porcentaje_descuento" class="form-control"
                                   value="<?= e($cliente['porcentaje_descuento'] ?? 0) ?>"
                                   min="0" max="100" step="0.01">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Días de Crédito</label>
                        <input type="number" name="dias_credito" class="form-control"
                               value="<?= e($cliente['dias_credito'] ?? 0) ?>"
                               min="0">
                    </div>
                    
                    <div>
                        <label class="form-label">Límite de Crédito</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="limite_credito" class="form-control"
                                   value="<?= e($cliente['limite_credito'] ?? 0) ?>"
                                   min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="md:col-span-3 flex gap-6">
                        <label class="form-check">
                            <input type="checkbox" name="es_contribuyente_especial" class="form-check-input"
                                   <?= ($cliente['es_contribuyente_especial'] ?? 0) ? 'checked' : '' ?>>
                            <span>Contribuyente Especial</span>
                        </label>
                        
                        <label class="form-check">
                            <input type="checkbox" name="aplica_retencion" class="form-check-input"
                                   <?= ($cliente['aplica_retencion'] ?? 0) ? 'checked' : '' ?>>
                            <span>Aplica Retención</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contacto Adicional -->
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="user-plus" class="w-5 h-5 inline mr-2"></i>
                    Contacto Adicional
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Nombre del Contacto</label>
                        <input type="text" name="contacto_nombre" class="form-control"
                               value="<?= e($cliente['contacto_nombre'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label class="form-label">Cargo</label>
                        <input type="text" name="contacto_cargo" class="form-control"
                               value="<?= e($cliente['contacto_cargo'] ?? '') ?>">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"
                                  placeholder="Notas adicionales sobre el cliente"><?= e($cliente['notas'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones -->
        <div class="flex justify-end gap-3">
            <a href="<?= url('clientes/') ?>" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i>
                <?= $isEdit ? 'Guardar Cambios' : 'Crear Cliente' ?>
            </button>
        </div>
    </form>
</div>

<script>
function validarIdentificacion() {
    const select = document.querySelector('[name="tipo_identificacion_id"]');
    const input = document.querySelector('[name="identificacion"]');
    const help = document.getElementById('identificacionHelp');
    const option = select.options[select.selectedIndex];
    const codigo = option?.dataset.codigo;
    
    if (codigo === '05') {
        input.maxLength = 10;
        help.textContent = 'Ingrese los 10 dígitos de la cédula';
        input.dataset.validate = 'cedula';
    } else if (codigo === '04') {
        input.maxLength = 13;
        help.textContent = 'Ingrese los 13 dígitos del RUC';
        input.dataset.validate = 'ruc';
    } else {
        input.maxLength = 20;
        help.textContent = '';
        delete input.dataset.validate;
    }
}

document.addEventListener('DOMContentLoaded', validarIdentificacion);
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
