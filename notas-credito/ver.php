<?php
/**
 * SHALOM FACTURA - Ver Nota de Crédito
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\NotasCredito\NotaCredito;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('notas_credito.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$notaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$notaModel = new NotaCredito();
$nota = $notaModel->getById($notaId);

if (!$nota) {
    flash('error', 'Nota de crédito no encontrada');
    redirect(url('notas-credito/'));
}

$pageTitle = 'Nota de Crédito ' . $nota['numero_documento'];
$currentPage = 'notas_credito';
$breadcrumbs = [
    ['title' => 'Notas de Crédito', 'url' => url('notas-credito/')],
    ['title' => $nota['numero_documento']]
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary"><?= e($nota['numero_documento']) ?></h1>
        <p class="text-shalom-muted">Nota de crédito de la factura <?= e($nota['factura_numero']) ?></p>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php if ($nota['estado'] === 'borrador' && auth()->can('notas_credito.crear')): ?>
        <button class="btn btn-primary" onclick="emitirNota()">
            <i data-lucide="send" class="w-4 h-4"></i>
            Emitir Nota
        </button>
        <?php endif; ?>

        <?php if ($nota['estado'] === 'emitida' && auth()->can('notas_credito.crear')): ?>
        <button class="btn btn-secondary" onclick="consultarSri()">
            <i data-lucide="search" class="w-4 h-4"></i>
            Consultar SRI
        </button>
        <?php endif; ?>

        <?php if ($nota['estado'] === 'emitida' && in_array($nota['estado_sri'], ['rechazada', 'no_autorizada']) && auth()->can('notas_credito.crear')): ?>
        <button class="btn btn-secondary" onclick="reenviarSri()">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            Reenviar al SRI
        </button>
        <?php endif; ?>

        <?php if ($nota['estado'] !== 'anulada' && auth()->can('notas_credito.anular')): ?>
        <button class="btn btn-danger" onclick="anularNota()">
            <i data-lucide="x-circle" class="w-4 h-4"></i>
            Anular Nota
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h3 class="card-title">Datos del Cliente</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-shalom-muted">Razón Social:</span>
                    <div class="font-medium"><?= e($nota['cliente_nombre']) ?></div>
                </div>
                <div>
                    <span class="text-shalom-muted">Identificación:</span>
                    <div class="font-medium"><?= e($nota['cliente_identificacion']) ?></div>
                </div>
                <div>
                    <span class="text-shalom-muted">Email:</span>
                    <div class="font-medium"><?= e($nota['cliente_email']) ?></div>
                </div>
                <div>
                    <span class="text-shalom-muted">Teléfono:</span>
                    <div class="font-medium"><?= e($nota['cliente_telefono']) ?></div>
                </div>
                <div class="md:col-span-2">
                    <span class="text-shalom-muted">Dirección:</span>
                    <div class="font-medium"><?= e($nota['cliente_direccion']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Estado</h3>
        </div>
        <div class="card-body space-y-3 text-sm">
            <div class="flex justify-between">
                <span>Estado:</span>
                <span class="badge badge-<?= $nota['estado'] === 'emitida' ? 'success' : ($nota['estado'] === 'anulada' ? 'danger' : 'warning') ?>">
                    <?= ucfirst($nota['estado']) ?>
                </span>
            </div>
            <div class="flex justify-between">
                <span>Estado SRI:</span>
                <span class="badge badge-<?= $nota['estado_sri'] === 'autorizada' ? 'success' : ($nota['estado_sri'] === 'rechazada' ? 'danger' : 'warning') ?>">
                    <?= ucfirst(str_replace('_', ' ', $nota['estado_sri'])) ?>
                </span>
            </div>
            <div class="flex justify-between">
                <span>Fecha emisión:</span>
                <span class="font-medium"><?= format_date($nota['fecha_emision']) ?></span>
            </div>
            <div class="flex justify-between">
                <span>Factura origen:</span>
                <span class="font-medium"><?= e($nota['factura_numero']) ?></span>
            </div>
            <?php if (!empty($nota['numero_autorizacion'])): ?>
            <div>
                <span class="text-shalom-muted">Autorización:</span>
                <div class="font-medium break-all"><?= e($nota['numero_autorizacion']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Detalle</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Descuento</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nota['detalles'] as $detalle): ?>
                    <tr>
                        <td>
                            <div class="font-medium"><?= e($detalle['descripcion']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($detalle['codigo_principal']) ?></div>
                        </td>
                        <td><?= number_format_custom($detalle['cantidad'], 2) ?></td>
                        <td><?= currency($detalle['precio_unitario']) ?></td>
                        <td><?= currency($detalle['descuento'] ?? 0) ?></td>
                        <td><?= currency($detalle['precio_total_sin_impuesto']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h3 class="card-title">Motivo</h3>
        </div>
        <div class="card-body">
            <p class="text-sm"><?= e($nota['motivo']) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Totales</h3>
        </div>
        <div class="card-body space-y-2 text-sm">
            <div class="flex justify-between">
                <span>Subtotal:</span>
                <span class="font-medium"><?= currency($nota['subtotal_sin_impuestos']) ?></span>
            </div>
            <div class="flex justify-between">
                <span>Descuento:</span>
                <span class="font-medium"><?= currency($nota['total_descuento']) ?></span>
            </div>
            <div class="flex justify-between">
                <span>IVA:</span>
                <span class="font-medium"><?= currency($nota['total_iva']) ?></span>
            </div>
            <div class="flex justify-between border-t border-gray-200 pt-2">
                <span class="font-semibold">Total:</span>
                <span class="font-semibold"><?= currency($nota['total']) ?></span>
            </div>
        </div>
    </div>
</div>

<script>
async function emitirNota() {
    if (!await ShalomApp.confirm('¿Está seguro de emitir esta nota de crédito?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/emitir.php') ?>', { id: <?= $nota['id'] ?> });
        if (response.success) {
            ShalomApp.toast('Nota de crédito emitida correctamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al emitir la nota de crédito', 'error');
    }

    ShalomApp.hideLoading();
}

async function consultarSri() {
    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/consultar.php') ?>', { id: <?= $nota['id'] ?> });
        if (response.success) {
            ShalomApp.toast(response.mensaje || response.message || 'Consulta realizada', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message || response.mensaje || 'No se pudo consultar', 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al consultar el SRI', 'error');
    }

    ShalomApp.hideLoading();
}

async function reenviarSri() {
    if (!await ShalomApp.confirm('¿Desea reenviar esta nota de crédito al SRI?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/reenviar.php') ?>', { id: <?= $nota['id'] ?> });
        if (response.success) {
            ShalomApp.toast('Nota de crédito reenviada al SRI', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al reenviar al SRI', 'error');
    }

    ShalomApp.hideLoading();
}

async function anularNota() {
    const motivo = prompt('Ingrese el motivo de anulación:');
    if (!motivo) {
        return;
    }

    if (!await ShalomApp.confirm('¿Está seguro de anular esta nota de crédito?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/anular.php') ?>', { id: <?= $nota['id'] ?>, motivo });
        if (response.success) {
            ShalomApp.toast('Nota de crédito anulada correctamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al anular la nota de crédito', 'error');
    }

    ShalomApp.hideLoading();
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
