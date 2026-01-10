<?php
/**
 * SHALOM FACTURA - Listado de Cotizaciones
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Cotizaciones\Cotizacion;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('cotizaciones.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$cotizacionModel = new Cotizacion();

$filters = [
    'estado' => $_GET['estado'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $cotizacionModel->getAll($filters, $limit, $offset);
$cotizaciones = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

$stats = $cotizacionModel->getEstadisticas('mes');

$pageTitle = 'Cotizaciones';
$currentPage = 'cotizaciones';
$breadcrumbs = [['title' => 'Cotizaciones']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Cotizaciones</h1>
        <p class="text-shalom-muted">Gestión de proformas y propuestas comerciales</p>
    </div>
    
    <?php if (auth()->can('cotizaciones.crear')): ?>
    <a href="<?= url('cotizaciones/crear.php') ?>" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nueva Cotización
    </a>
    <?php endif; ?>
</div>

<!-- Estadísticas -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Total</div>
            <div class="stat-value text-xl"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Enviadas</div>
            <div class="stat-value text-xl text-blue-600"><?= number_format($stats['enviadas']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Aceptadas</div>
            <div class="stat-value text-xl text-green-600"><?= number_format($stats['aceptadas']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Rechazadas</div>
            <div class="stat-value text-xl text-red-600"><?= number_format($stats['rechazadas']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Valor Aceptadas</div>
            <div class="stat-value text-xl"><?= currency($stats['valor_aceptadas']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Tasa Conversión</div>
            <div class="stat-value text-xl"><?= $stats['tasa_conversion'] ?>%</div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar cliente, número o asunto..." value="<?= e($filters['buscar']) ?>">
            </div>
            
            <div>
                <select name="estado" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="borrador" <?= $filters['estado'] === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                    <option value="enviada" <?= $filters['estado'] === 'enviada' ? 'selected' : '' ?>>Enviada</option>
                    <option value="aceptada" <?= $filters['estado'] === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                    <option value="rechazada" <?= $filters['estado'] === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="facturada" <?= $filters['estado'] === 'facturada' ? 'selected' : '' ?>>Facturada</option>
                </select>
            </div>
            
            <div>
                <input type="date" name="fecha_desde" class="form-control" value="<?= e($filters['fecha_desde']) ?>" placeholder="Desde">
            </div>
            
            <div class="flex gap-2">
                <input type="date" name="fecha_hasta" class="form-control" value="<?= e($filters['fecha_hasta']) ?>" placeholder="Hasta">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('cotizaciones/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Cotizaciones</h3>
        <div class="text-sm text-shalom-muted"><?= number_format($total) ?> registros</div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Asunto</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Validez</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cotizaciones)): ?>
                <tr>
                    <td colspan="8" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="file-text" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay cotizaciones</div>
                            <div class="empty-state-text">Comience creando su primera cotización</div>
                            <?php if (auth()->can('cotizaciones.crear')): ?>
                            <a href="<?= url('cotizaciones/crear.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Nueva Cotización
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($cotizaciones as $cotizacion): ?>
                    <tr>
                        <td>
                            <span class="font-mono font-medium text-shalom-primary"><?= e($cotizacion['numero']) ?></span>
                        </td>
                        <td><?= format_date($cotizacion['fecha']) ?></td>
                        <td>
                            <div class="font-medium"><?= e($cotizacion['cliente_nombre']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($cotizacion['cliente_identificacion']) ?></div>
                        </td>
                        <td>
                            <div class="max-w-xs truncate"><?= e($cotizacion['asunto'] ?: '-') ?></div>
                        </td>
                        <td class="font-semibold"><?= currency($cotizacion['total']) ?></td>
                        <td>
                            <?php
                            $estadoClass = match($cotizacion['estado']) {
                                'borrador' => 'badge-muted',
                                'enviada' => 'badge-info',
                                'aceptada' => 'badge-success',
                                'rechazada' => 'badge-danger',
                                'vencida' => 'badge-warning',
                                'facturada' => 'badge-primary',
                                default => 'badge-muted'
                            };
                            ?>
                            <span class="badge <?= $estadoClass ?>"><?= ucfirst($cotizacion['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($cotizacion['fecha_validez']): ?>
                                <?php 
                                $vencida = strtotime($cotizacion['fecha_validez']) < time();
                                ?>
                                <span class="<?= $vencida ? 'text-red-600' : '' ?>">
                                    <?= format_date($cotizacion['fecha_validez']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-shalom-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= url('cotizaciones/ver.php?id=' . $cotizacion['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Ver">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                
                                <?php if (in_array($cotizacion['estado'], ['borrador', 'enviada']) && auth()->can('cotizaciones.editar')): ?>
                                <a href="<?= url('cotizaciones/editar.php?id=' . $cotizacion['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Editar">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                                
                                <div class="dropdown">
                                    <button class="btn btn-icon btn-sm btn-secondary">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($cotizacion['estado'] === 'borrador'): ?>
                                        <a href="#" class="dropdown-item" onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'enviada')">
                                            <i data-lucide="send" class="w-4 h-4"></i>
                                            Marcar como Enviada
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($cotizacion['estado'] === 'enviada'): ?>
                                        <a href="#" class="dropdown-item text-green-600" onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'aceptada')">
                                            <i data-lucide="check" class="w-4 h-4"></i>
                                            Marcar como Aceptada
                                        </a>
                                        <a href="#" class="dropdown-item text-red-600" onclick="rechazarCotizacion(<?= $cotizacion['id'] ?>)">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                            Marcar como Rechazada
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($cotizacion['estado'] === 'aceptada' && auth()->can('facturas.crear')): ?>
                                        <a href="<?= url('facturas/crear.php?cotizacion_id=' . $cotizacion['id']) ?>" class="dropdown-item text-shalom-primary">
                                            <i data-lucide="receipt" class="w-4 h-4"></i>
                                            Convertir a Factura
                                        </a>
                                        <?php endif; ?>
                                        
                                        <a href="#" class="dropdown-item" onclick="duplicarCotizacion(<?= $cotizacion['id'] ?>)">
                                            <i data-lucide="copy" class="w-4 h-4"></i>
                                            Duplicar
                                        </a>
                                        
                                        <a href="<?= url('cotizaciones/pdf.php?id=' . $cotizacion['id']) ?>" class="dropdown-item" target="_blank">
                                            <i data-lucide="file-down" class="w-4 h-4"></i>
                                            Descargar PDF
                                        </a>
                                        
                                        <?php if ($cotizacion['estado'] !== 'facturada' && auth()->can('cotizaciones.eliminar')): ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" class="dropdown-item text-red-600" onclick="eliminarCotizacion(<?= $cotizacion['id'] ?>)">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            Eliminar
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
async function cambiarEstado(id, estado) {
    const mensajes = {
        'enviada': '¿Marcar esta cotización como enviada?',
        'aceptada': '¿Marcar esta cotización como aceptada?'
    };
    
    if (!await ShalomApp.confirm(mensajes[estado] || '¿Cambiar estado?')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/estado.php') ?>', { id, estado });
        
        if (response.success) {
            ShalomApp.toast('Estado actualizado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al cambiar estado', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function rechazarCotizacion(id) {
    const motivo = prompt('Ingrese el motivo del rechazo:');
    if (motivo === null) return;
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/estado.php') ?>', { 
            id, 
            estado: 'rechazada',
            motivo 
        });
        
        if (response.success) {
            ShalomApp.toast('Cotización marcada como rechazada', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al rechazar cotización', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function duplicarCotizacion(id) {
    if (!await ShalomApp.confirm('¿Crear una copia de esta cotización?')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/duplicar.php') ?>', { id });
        
        if (response.success) {
            ShalomApp.toast('Cotización duplicada correctamente', 'success');
            setTimeout(() => {
                window.location.href = '<?= url('cotizaciones/editar.php') ?>?id=' + response.id;
            }, 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al duplicar cotización', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function eliminarCotizacion(id) {
    if (!await ShalomApp.confirm('¿Está seguro de eliminar esta cotización?')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/eliminar.php') ?>', { id });
        
        if (response.success) {
            ShalomApp.toast('Cotización eliminada correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al eliminar cotización', 'error');
    }
    
    ShalomApp.hideLoading();
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
