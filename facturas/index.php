<?php
/**
 * SHALOM FACTURA - Listado de Facturas
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Facturas\Factura;

// Verificar autenticación y permisos
if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('facturas.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$facturaModel = new Factura();

// Obtener filtros
$filters = [
    'estado' => $_GET['estado'] ?? '',
    'estado_sri' => $_GET['estado_sri'] ?? '',
    'estado_pago' => $_GET['estado_pago'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $facturaModel->getAll($filters, $limit, $offset);
$facturas = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

// Estadísticas
$stats = $facturaModel->getEstadisticas('mes');

// Variables para el layout
$pageTitle = 'Facturas';
$currentPage = 'facturas';
$breadcrumbs = [
    ['title' => 'Facturas']
];

ob_start();
?>

<!-- Header de página -->
<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Facturas</h1>
        <p class="text-shalom-muted">Gestión de facturas electrónicas</p>
    </div>
    
    <?php if (auth()->can('facturas.crear')): ?>
    <a href="<?= url('facturas/crear.php') ?>" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nueva Factura
    </a>
    <?php endif; ?>
</div>

<!-- Estadísticas -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="receipt" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Facturas del Mes</div>
            <div class="stat-value"><?= number_format($stats['cantidad_facturas']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="trending-up" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Facturado</div>
            <div class="stat-value"><?= currency($stats['total_facturado']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pendiente de Cobro</div>
            <div class="stat-value"><?= currency($stats['total_pendiente']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gold">
            <i data-lucide="check-circle" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Autorizadas SRI</div>
            <div class="stat-value">
                <?php 
                $autorizadas = 0;
                foreach ($stats['por_estado_sri'] as $estado) {
                    if ($estado['estado_sri'] === 'autorizada') {
                        $autorizadas = $estado['cantidad'];
                        break;
                    }
                }
                echo number_format($autorizadas);
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
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
                <select name="estado_pago" class="form-control">
                    <option value="">Estado Pago</option>
                    <option value="pendiente" <?= $filters['estado_pago'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="parcial" <?= $filters['estado_pago'] === 'parcial' ? 'selected' : '' ?>>Parcial</option>
                    <option value="pagado" <?= $filters['estado_pago'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                    <option value="vencido" <?= $filters['estado_pago'] === 'vencido' ? 'selected' : '' ?>>Vencido</option>
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
                <a href="<?= url('facturas/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de facturas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Facturas</h3>
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
                    <th>Pago</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($facturas)): ?>
                <tr>
                    <td colspan="8" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="receipt" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay facturas</div>
                            <div class="empty-state-text">Comience creando su primera factura</div>
                            <?php if (auth()->can('facturas.crear')): ?>
                            <a href="<?= url('facturas/crear.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Nueva Factura
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($facturas as $factura): ?>
                    <tr>
                        <td>
                            <div class="font-medium text-shalom-primary"><?= e($factura['numero_documento']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($factura['tipo_comprobante_nombre']) ?></div>
                        </td>
                        <td><?= format_date($factura['fecha_emision']) ?></td>
                        <td>
                            <div class="font-medium"><?= e($factura['cliente_nombre']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($factura['cliente_identificacion']) ?></div>
                        </td>
                        <td class="font-semibold"><?= currency($factura['total']) ?></td>
                        <td>
                            <?php
                            $estadoClass = match($factura['estado']) {
                                'borrador' => 'badge-muted',
                                'emitida' => 'badge-success',
                                'anulada' => 'badge-danger',
                                default => 'badge-muted'
                            };
                            ?>
                            <span class="badge <?= $estadoClass ?>"><?= ucfirst($factura['estado']) ?></span>
                        </td>
                        <td>
                            <?php
                            $estadoSriClass = match($factura['estado_sri']) {
                                'autorizada' => 'badge-success',
                                'rechazado' => 'badge-danger',
                                'no_autorizado' => 'badge-danger',
                                'enviado' => 'badge-info',
                                'recibido' => 'badge-info',
                                default => 'badge-warning'
                            };
                            $estadoSriLabel = match($factura['estado_sri']) {
                                'autorizada' => 'Autorizada',
                                'rechazada' => 'Rechazada',
                                'no_autorizado' => 'No Autorizado',
                                'enviado' => 'Enviado',
                                'recibido' => 'Recibido',
                                default => 'Pendiente'
                            };
                            ?>
                            <span class="badge <?= $estadoSriClass ?>"><?= $estadoSriLabel ?></span>
                        </td>
                        <td>
                            <?php
                            $pagoClass = match($factura['estado_pago']) {
                                'pagado' => 'badge-success',
                                'parcial' => 'badge-info',
                                'vencido' => 'badge-danger',
                                default => 'badge-warning'
                            };
                            ?>
                            <span class="badge <?= $pagoClass ?>"><?= ucfirst($factura['estado_pago']) ?></span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= url('facturas/ver.php?id=' . $factura['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Ver">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                
                                <?php if ($factura['estado'] === 'borrador' && auth()->can('facturas.crear')): ?>
                                <a href="<?= url('facturas/editar.php?id=' . $factura['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Editar">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($factura['estado'] === 'emitida'): ?>
                                <a href="<?= url('facturas/pdf.php?id=' . $factura['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Descargar PDF" target="_blank">
                                    <i data-lucide="file-down" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                                
                                <div class="dropdown">
                                    <button class="btn btn-icon btn-sm btn-secondary">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($factura['estado'] === 'borrador' && auth()->can('facturas.crear')): ?>
                                        <a href="#" class="dropdown-item" onclick="emitirFactura(<?= $factura['id'] ?>)">
                                            <i data-lucide="send" class="w-4 h-4"></i>
                                            Emitir Factura
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($factura['estado'] === 'emitida' && in_array($factura['estado_sri'], ['rechazado', 'no_autorizado']) && auth()->can('facturas.reenviar')): ?>
                                        <a href="#" class="dropdown-item" onclick="reenviarSri(<?= $factura['id'] ?>)">
                                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                            Reenviar al SRI
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($factura['estado'] === 'emitida' && auth()->can('pagos.crear')): ?>
                                        <a href="<?= url('pagos/registrar.php?factura_id=' . $factura['id']) ?>" class="dropdown-item">
                                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                                            Registrar Pago
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($factura['estado'] === 'emitida' && auth()->can('notas_credito.crear')): ?>
                                        <a href="<?= url('notas-credito/crear.php?factura_id=' . $factura['id']) ?>" class="dropdown-item">
                                            <i data-lucide="file-minus" class="w-4 h-4"></i>
                                            Crear Nota de Crédito
                                        </a>
                                        <?php endif; ?>
                                        
                                        <div class="dropdown-divider"></div>
                                        
                                        <?php if ($factura['estado'] !== 'anulada' && auth()->can('facturas.anular')): ?>
                                        <a href="#" class="dropdown-item text-red-600" onclick="anularFactura(<?= $factura['id'] ?>)">
                                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                                            Anular Factura
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
async function emitirFactura(id) {
    if (!await ShalomApp.confirm('¿Está seguro de emitir esta factura? Una vez emitida no podrá ser modificada.')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/emitir.php') ?>', { id });
        
        if (response.success) {
            ShalomApp.toast('Factura emitida correctamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al emitir la factura', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function anularFactura(id) {
    const motivo = prompt('Ingrese el motivo de anulación:');
    
    if (!motivo) {
        return;
    }
    
    if (!await ShalomApp.confirm('¿Está seguro de anular esta factura? Esta acción no se puede deshacer.')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/anular.php') ?>', { id, motivo });
        
        if (response.success) {
            ShalomApp.toast('Factura anulada correctamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al anular la factura', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function reenviarSri(id) {
    if (!await ShalomApp.confirm('¿Desea reenviar esta factura al SRI?')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/reenviar.php') ?>', { id });
        
        if (response.success) {
            ShalomApp.toast('Factura reenviada al SRI', 'success');
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
