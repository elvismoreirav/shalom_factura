<?php
/**
 * SHALOM FACTURA - Ver Cliente
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Clientes\Cliente;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('clientes.ver')) {
    flash('error', 'No tiene permisos para ver clientes');
    redirect(url('dashboard.php'));
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Cliente no válido');
    redirect(url('clientes/'));
}

$clienteModel = new Cliente();
$cliente = $clienteModel->getById($id);

if (!$cliente) {
    flash('error', 'Cliente no encontrado');
    redirect(url('clientes/'));
}

/**
 * Nombre del tipo de identificación (fallback)
 */
$tipoIdentificacionNombre = $cliente['tipo_identificacion_nombre'] ?? '';
if ($tipoIdentificacionNombre === '') {
    if (method_exists($clienteModel, 'getTiposIdentificacion')) {
        $tipos = $clienteModel->getTiposIdentificacion();
        foreach ($tipos as $t) {
            if ((string)($t['id'] ?? '') === (string)($cliente['tipo_identificacion_id'] ?? '')) {
                $tipoIdentificacionNombre = (string)($t['nombre'] ?? '');
                break;
            }
        }
    }
}

$pageTitle = 'Ver Cliente';
$currentPage = 'clientes';
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => url('clientes/')],
    ['title' => 'Ver']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Cliente</h1>
        <p class="text-shalom-muted">
            <?= e((string)($cliente['razon_social'] ?? '')) ?>
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php if (auth()->can('clientes.editar')): ?>
        <a href="<?= url('clientes/editar.php?id=' . (int)$id) ?>" class="btn btn-secondary">
            <i data-lucide="pencil" class="w-4 h-4"></i>
            Editar
        </a>
        <?php endif; ?>

        <?php if (auth()->can('facturas.crear')): ?>
        <a href="<?= url('facturas/crear.php?cliente_id=' . (int)$id) ?>" class="btn btn-primary">
            <i data-lucide="receipt" class="w-4 h-4"></i>
            Nueva Factura
        </a>
        <?php endif; ?>

        <a href="<?= url('clientes/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
    </div>
</div>

<div class="max-w-4xl mx-auto">

    <!-- Estado -->
    <div class="card mb-6">
        <div class="card-body flex items-center justify-between">
            <div>
                <div class="text-sm text-shalom-muted">Estado</div>
                <div class="font-semibold"><?= ucfirst((string)($cliente['estado'] ?? 'activo')) ?></div>
            </div>

            <span class="badge <?= (($cliente['estado'] ?? 'activo') === 'activo') ? 'badge-success' : 'badge-muted' ?>">
                <?= ucfirst((string)($cliente['estado'] ?? 'activo')) ?>
            </span>
        </div>
    </div>

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
                    <div class="text-sm text-shalom-muted">Tipo de Identificación</div>
                    <div class="font-medium"><?= e($tipoIdentificacionNombre ?: '-') ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Identificación</div>
                    <div class="font-medium"><?= e((string)($cliente['identificacion'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Razón Social</div>
                    <div class="font-medium"><?= e((string)($cliente['razon_social'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Nombre Comercial</div>
                    <div class="font-medium"><?= e((string)($cliente['nombre_comercial'] ?? '-')) ?></div>
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
                    <div class="text-sm text-shalom-muted">Email</div>
                    <div class="font-medium"><?= e((string)($cliente['email'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Teléfono</div>
                    <div class="font-medium"><?= e((string)($cliente['telefono'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Celular</div>
                    <div class="font-medium"><?= e((string)($cliente['celular'] ?? '-')) ?></div>
                </div>

                <div class="md:col-span-2">
                    <div class="text-sm text-shalom-muted">Dirección</div>
                    <div class="font-medium"><?= e((string)($cliente['direccion'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Ciudad</div>
                    <div class="font-medium"><?= e((string)($cliente['ciudad'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Provincia</div>
                    <div class="font-medium"><?= e((string)($cliente['provincia'] ?? '-')) ?></div>
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
                    <div class="text-sm text-shalom-muted">% Descuento</div>
                    <div class="font-medium"><?= number_format((float)($cliente['porcentaje_descuento'] ?? 0), 2) ?>%</div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Días de Crédito</div>
                    <div class="font-medium"><?= (int)($cliente['dias_credito'] ?? 0) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Límite de Crédito</div>
                    <div class="font-medium">
                        <?= function_exists('currency') ? currency((float)($cliente['limite_credito'] ?? 0)) : number_format((float)($cliente['limite_credito'] ?? 0), 2) ?>
                    </div>
                </div>

                <div class="md:col-span-3">
                    <div class="flex flex-wrap gap-3">
                        <span class="badge <?= !empty($cliente['es_contribuyente_especial']) ? 'badge-info' : 'badge-muted' ?>">
                            Contribuyente Especial: <?= !empty($cliente['es_contribuyente_especial']) ? 'Sí' : 'No' ?>
                        </span>

                        <span class="badge <?= !empty($cliente['aplica_retencion']) ? 'badge-info' : 'badge-muted' ?>">
                            Aplica Retención: <?= !empty($cliente['aplica_retencion']) ? 'Sí' : 'No' ?>
                        </span>
                    </div>
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
                    <div class="text-sm text-shalom-muted">Nombre del Contacto</div>
                    <div class="font-medium"><?= e((string)($cliente['contacto_nombre'] ?? '-')) ?></div>
                </div>

                <div>
                    <div class="text-sm text-shalom-muted">Cargo</div>
                    <div class="font-medium"><?= e((string)($cliente['contacto_cargo'] ?? '-')) ?></div>
                </div>

                <div class="md:col-span-2">
                    <div class="text-sm text-shalom-muted">Notas</div>
                    <div class="font-medium whitespace-pre-line"><?= e((string)($cliente['notas'] ?? '-')) ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
