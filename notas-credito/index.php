<?php
/**
 * SHALOM FACTURA - Listado de Notas de Crédito
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

$notaModel = new NotaCredito();

$filters = [
    'estado' => $_GET['estado'] ?? '',
    'estado_sri' => $_GET['estado_sri'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $notaModel->getAll($filters, $limit, $offset);
$notas = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

$pageTitle = 'Notas de Crédito';
$currentPage = 'notas_credito';
$breadcrumbs = [['title' => 'Notas de Crédito']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Notas de Crédito</h1>
        <p class="text-shalom-muted">Gestión de notas de crédito electrónicas</p>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="lg:col-span-2">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar cliente, RUC, autorización..." value="<?= e($filters['buscar']) ?>">
            </div>

            <div>
                <select name="estado" class="form-control">
                    <option value="">Estado</option>
                    <option value="borrador" <?= $filters['estado'] === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                    <option value="emitida" <?= $filters['estado'] === 'emitida' ? 'selected' : '' ?>>Emitida</option>
                    <option value="anulada" <?= $filters['estado'] === 'anulada' ? 'selected' : '' ?>>Anulada</option>
                </select>
            </div>

            <div>
                <select name="estado_sri" class="form-control">
                    <option value="">Estado SRI</option>
                    <option value="pendiente" <?= $filters['estado_sri'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="enviado" <?= $filters['estado_sri'] === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                    <option value="autorizada" <?= $filters['estado_sri'] === 'autorizada' ? 'selected' : '' ?>>Autorizada</option>
                    <option value="rechazada" <?= $filters['estado_sri'] === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                </select>
            </div>

            <div>
                <input type="date" name="fecha_desde" class="form-control" placeholder="Desde" value="<?= e($filters['fecha_desde']) ?>">
            </div>

            <div class="flex gap-2">
                <input type="date" name="fecha_hasta" class="form-control" placeholder="Hasta" value="<?= e($filters['fecha_hasta']) ?>">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('notas-credito/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Notas de Crédito</h3>
        <div class="text-sm text-shalom-muted"><?= number_format($total) ?> registros encontrados</div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Estado SRI</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notas)): ?>
                <tr>
                    <td colspan="7" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="file-minus" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay notas de crédito</div>
                            <div class="empty-state-text">Cree una nota de crédito desde una factura autorizada</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($notas as $nota): ?>
                    <tr>
                        <td>
                            <div class="font-medium text-shalom-primary"><?= e($nota['numero_documento']) ?></div>
                            <div class="text-xs text-shalom-muted">Factura asociada</div>
                        </td>
                        <td><?= format_date($nota['fecha_emision']) ?></td>
                        <td>
                            <div class="font-medium"><?= e($nota['cliente_nombre']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($nota['cliente_identificacion']) ?></div>
                        </td>
                        <td class="font-semibold"><?= currency($nota['total']) ?></td>
                        <td>
                            <span class="badge badge-<?= $nota['estado'] === 'emitida' ? 'success' : ($nota['estado'] === 'anulada' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($nota['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $nota['estado_sri'] === 'autorizada' ? 'success' : ($nota['estado_sri'] === 'rechazada' ? 'danger' : 'warning') ?>">
                                <?= ucfirst(str_replace('_', ' ', $nota['estado_sri'])) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="<?= url('notas-credito/ver.php?id=' . $nota['id']) ?>" class="btn btn-icon btn-sm btn-secondary">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>

                                <div class="dropdown">
                                    <button class="btn btn-icon btn-sm btn-secondary">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($nota['estado'] === 'borrador' && auth()->can('notas_credito.crear')): ?>
                                        <a href="#" class="dropdown-item" onclick="emitirNota(<?= $nota['id'] ?>)">
                                            <i data-lucide="send" class="w-4 h-4"></i>
                                            Emitir Nota de Crédito
                                        </a>
                                        <?php endif; ?>

                                        <?php if ($nota['estado'] === 'emitida' && in_array($nota['estado_sri'], ['rechazada', 'no_autorizada']) && auth()->can('notas_credito.crear')): ?>
                                        <a href="#" class="dropdown-item" onclick="reenviarSri(<?= $nota['id'] ?>)">
                                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                            Reenviar al SRI
                                        </a>
                                        <?php endif; ?>

                                        <?php if ($nota['estado'] === 'emitida' && auth()->can('notas_credito.ver')): ?>
                                        <a href="#" class="dropdown-item" onclick="consultarSri(<?= $nota['id'] ?>)">
                                            <i data-lucide="search" class="w-4 h-4"></i>
                                            Consultar SRI
                                        </a>
                                        <?php endif; ?>

                                        <div class="dropdown-divider"></div>

                                        <?php if ($nota['estado'] !== 'anulada' && auth()->can('notas_credito.anular')): ?>
                                        <a href="#" class="dropdown-item text-red-600" onclick="anularNota(<?= $nota['id'] ?>)">
                                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                                            Anular Nota de Crédito
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <div class="flex items-center justify-between">
            <div class="text-sm text-shalom-muted">
                Mostrando <?= ($offset + 1) ?> - <?= min($offset + $limit, $total) ?> de <?= $total ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="pagination-item">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
                   class="pagination-item <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="pagination-item">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
async function emitirNota(id) {
    if (!await ShalomApp.confirm('¿Está seguro de emitir esta nota de crédito?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/autorizar.php') ?>', { id });
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

async function consultarSri(id) {
    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/consultar.php') ?>', { id });
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

async function anularNota(id) {
    const motivo = prompt('Ingrese el motivo de anulación:');
    if (!motivo) {
        return;
    }

    if (!await ShalomApp.confirm('¿Está seguro de anular esta nota de crédito?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/anular.php') ?>', { id, motivo });
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

async function reenviarSri(id) {
    if (!await ShalomApp.confirm('¿Desea reenviar esta nota de crédito al SRI?')) {
        return;
    }

    ShalomApp.showLoading();

    try {
        const response = await ShalomApp.post('<?= url('api/notas-credito/reenviar.php') ?>', { id });
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
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
