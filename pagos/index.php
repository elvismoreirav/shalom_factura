<?php
/**
 * SHALOM FACTURA - Listado de Pagos
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Pagos\Pago;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('pagos.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$pagoModel = new Pago();

$filters = [
    'estado' => $_GET['estado'] ?? '',
    'forma_pago_id' => $_GET['forma_pago_id'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $pagoModel->getAll($filters, $limit, $offset);
$pagos = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

$stats = $pagoModel->getEstadisticas('mes');
$formasPago = $pagoModel->getFormasPago();

$pageTitle = 'Pagos';
$currentPage = 'pagos';
$breadcrumbs = [['title' => 'Pagos']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Pagos</h1>
        <p class="text-shalom-muted">Registro y gestión de cobros</p>
    </div>
    
    <div class="flex gap-2">
        <a href="<?= url('pagos/cuentas-por-cobrar.php') ?>" class="btn btn-secondary">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            Cuentas por Cobrar
        </a>
        <?php if (auth()->can('pagos.crear')): ?>
        <a href="<?= url('pagos/registrar.php') ?>" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Registrar Pago
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Estadísticas -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Cobrado (mes)</div>
            <div class="stat-value"><?= currency($stats['total_cobrado']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="receipt" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pagos Registrados</div>
            <div class="stat-value"><?= number_format($stats['total_pagos']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="x-circle" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Anulado</div>
            <div class="stat-value"><?= currency($stats['total_anulado']) ?></div>
        </div>
    </div>
</div>

<!-- Resumen por forma de pago -->
<?php if (!empty($stats['por_forma_pago'])): ?>
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Cobros por Forma de Pago</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($stats['por_forma_pago'] as $fp): ?>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold text-shalom-primary"><?= currency($fp['total']) ?></div>
                <div class="text-sm text-shalom-muted"><?= e($fp['nombre']) ?></div>
                <div class="text-xs text-shalom-muted"><?= $fp['cantidad'] ?> pagos</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar cliente, recibo o referencia..." value="<?= e($filters['buscar']) ?>">
            </div>
            
            <div>
                <select name="forma_pago_id" class="form-control">
                    <option value="">Forma de Pago</option>
                    <?php foreach ($formasPago as $fp): ?>
                    <option value="<?= $fp['id'] ?>" <?= $filters['forma_pago_id'] == $fp['id'] ? 'selected' : '' ?>>
                        <?= e($fp['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <select name="estado" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="confirmado" <?= $filters['estado'] === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                    <option value="anulado" <?= $filters['estado'] === 'anulado' ? 'selected' : '' ?>>Anulado</option>
                </select>
            </div>
            
            <div>
                <input type="date" name="fecha_desde" class="form-control" value="<?= e($filters['fecha_desde']) ?>">
            </div>
            
            <div class="flex gap-2">
                <input type="date" name="fecha_hasta" class="form-control" value="<?= e($filters['fecha_hasta']) ?>">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('pagos/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Pagos</h3>
        <div class="text-sm text-shalom-muted"><?= number_format($total) ?> registros</div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Recibo</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Forma de Pago</th>
                    <th>Referencia</th>
                    <th>Monto</th>
                    <th>Facturas</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagos)): ?>
                <tr>
                    <td colspan="9" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="credit-card" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay pagos registrados</div>
                            <div class="empty-state-text">Comience registrando un pago</div>
                            <?php if (auth()->can('pagos.crear')): ?>
                            <a href="<?= url('pagos/registrar.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Registrar Pago
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($pagos as $pago): ?>
                    <tr class="<?= $pago['estado'] === 'anulado' ? 'opacity-50' : '' ?>">
                        <td>
                            <span class="font-mono font-medium text-shalom-primary"><?= e($pago['numero_recibo']) ?></span>
                        </td>
                        <td><?= format_date($pago['fecha']) ?></td>
                        <td>
                            <div class="font-medium"><?= e($pago['cliente_nombre']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($pago['cliente_identificacion']) ?></div>
                        </td>
                        <td><?= e($pago['forma_pago_nombre']) ?></td>
                        <td>
                            <?php if ($pago['referencia']): ?>
                            <span class="font-mono text-sm"><?= e($pago['referencia']) ?></span>
                            <?php else: ?>
                            <span class="text-shalom-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-semibold"><?= currency($pago['monto']) ?></td>
                        <td>
                            <span class="badge badge-info"><?= $pago['facturas_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $pago['estado'] === 'confirmado' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($pago['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= url('pagos/ver.php?id=' . $pago['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Ver">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                
                                <a href="<?= url('pagos/recibo.php?id=' . $pago['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Imprimir Recibo" target="_blank">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                                
                                <?php if ($pago['estado'] === 'confirmado' && auth()->can('pagos.anular')): ?>
                                <button type="button" class="btn btn-icon btn-sm btn-danger" title="Anular" onclick="anularPago(<?= $pago['id'] ?>)">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                                </button>
                                <?php endif; ?>
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
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
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
async function anularPago(id) {
    const motivo = prompt('Ingrese el motivo de anulación:');
    if (!motivo) return;
    
    if (!await ShalomApp.confirm('¿Está seguro de anular este pago? Las facturas asociadas volverán a estado pendiente.')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/pagos/anular.php') ?>', { id, motivo });
        
        if (response.success) {
            ShalomApp.toast('Pago anulado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al anular el pago', 'error');
    }
    
    ShalomApp.hideLoading();
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
