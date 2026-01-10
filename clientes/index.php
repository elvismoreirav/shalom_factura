<?php
/**
 * SHALOM FACTURA - Listado de Clientes
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Clientes\Cliente;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('clientes.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$clienteModel = new Cliente();

$filters = [
    'estado' => $_GET['estado'] ?? '',
    'tipo_identificacion' => $_GET['tipo_identificacion'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $clienteModel->getAll($filters, $limit, $offset);
$clientes = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

$tiposIdentificacion = $clienteModel->getTiposIdentificacion();

$pageTitle = 'Clientes';
$currentPage = 'clientes';
$breadcrumbs = [['title' => 'Clientes']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Clientes</h1>
        <p class="text-shalom-muted">Gestión de clientes y contribuyentes</p>
    </div>
    
    <?php if (auth()->can('clientes.crear')): ?>
    <a href="<?= url('clientes/crear.php') ?>" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nuevo Cliente
    </a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre, RUC, cédula o email..." value="<?= e($filters['buscar']) ?>">
            </div>
            
            <div>
                <select name="tipo_identificacion" class="form-control">
                    <option value="">Tipo Identificación</option>
                    <?php foreach ($tiposIdentificacion as $tipo): ?>
                    <option value="<?= $tipo['id'] ?>" <?= $filters['tipo_identificacion'] == $tipo['id'] ? 'selected' : '' ?>>
                        <?= e($tipo['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex gap-2">
                <select name="estado" class="form-control">
                    <option value="">Estado</option>
                    <option value="activo" <?= $filters['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $filters['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('clientes/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Clientes</h3>
        <div class="text-sm text-shalom-muted"><?= number_format($total) ?> registros</div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Identificación</th>
                    <th>Razón Social</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Total Facturado</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="7" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="users" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay clientes</div>
                            <div class="empty-state-text">Comience agregando su primer cliente</div>
                            <?php if (auth()->can('clientes.crear')): ?>
                            <a href="<?= url('clientes/crear.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Nuevo Cliente
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <div class="font-medium"><?= e($cliente['identificacion']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($cliente['tipo_identificacion_nombre']) ?></div>
                        </td>
                        <td>
                            <div class="font-medium text-shalom-primary"><?= e($cliente['razon_social']) ?></div>
                            <?php if ($cliente['nombre_comercial']): ?>
                            <div class="text-xs text-shalom-muted"><?= e($cliente['nombre_comercial']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($cliente['email'] ?: '-') ?></td>
                        <td><?= e($cliente['telefono'] ?: $cliente['celular'] ?: '-') ?></td>
                        <td>
                            <div class="font-semibold"><?= currency($cliente['total_facturado']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= $cliente['total_facturas'] ?> facturas</div>
                        </td>
                        <td>
                            <span class="badge <?= $cliente['estado'] === 'activo' ? 'badge-success' : 'badge-muted' ?>">
                                <?= ucfirst($cliente['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= url('clientes/ver.php?id=' . $cliente['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Ver">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                
                                <?php if (auth()->can('clientes.editar')): ?>
                                <a href="<?= url('clientes/editar.php?id=' . $cliente['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Editar">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (auth()->can('facturas.crear')): ?>
                                <a href="<?= url('facturas/crear.php?cliente_id=' . $cliente['id']) ?>" class="btn btn-icon btn-sm btn-primary" title="Nueva Factura">
                                    <i data-lucide="receipt" class="w-4 h-4"></i>
                                </a>
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

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
