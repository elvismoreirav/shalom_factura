<?php
/**
 * SHALOM FACTURA - Listado de Servicios
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Servicios\Servicio;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('servicios.ver')) {
    flash('error', 'No tiene permisos para acceder a esta sección');
    redirect(url('dashboard.php'));
}

$servicioModel = new Servicio();

$filters = [
    'categoria_id' => $_GET['categoria_id'] ?? '',
    'tipo' => $_GET['tipo'] ?? '',
    'activo' => $_GET['activo'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$result = $servicioModel->getAll($filters, $limit, $offset);
$servicios = $result['data'];
$totalPages = $result['pages'];
$total = $result['total'];

$categorias = $servicioModel->getCategorias();

$pageTitle = 'Servicios';
$currentPage = 'servicios';
$breadcrumbs = [['title' => 'Servicios']];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Servicios</h1>
        <p class="text-shalom-muted">Catálogo de servicios y productos</p>
    </div>
    
    <div class="flex gap-2">
        <?php if (auth()->can('servicios.crear')): ?>
        <button type="button" class="btn btn-secondary" onclick="mostrarModalCategoria()">
            <i data-lucide="folder-plus" class="w-4 h-4"></i>
            Nueva Categoría
        </button>
        <a href="<?= url('servicios/crear.php') ?>" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nuevo Servicio
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar por código o nombre..." value="<?= e($filters['buscar']) ?>">
            </div>
            
            <div>
                <select name="categoria_id" class="form-control">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filters['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <select name="tipo" class="form-control">
                    <option value="">Todos los tipos</option>
                    <option value="servicio" <?= $filters['tipo'] === 'servicio' ? 'selected' : '' ?>>Servicio</option>
                    <option value="producto" <?= $filters['tipo'] === 'producto' ? 'selected' : '' ?>>Producto</option>
                    <option value="paquete" <?= $filters['tipo'] === 'paquete' ? 'selected' : '' ?>>Paquete</option>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('servicios/') ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Servicios</h3>
        <div class="text-sm text-shalom-muted"><?= number_format($total) ?> registros</div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>IVA</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($servicios)): ?>
                <tr>
                    <td colspan="8" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="briefcase" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">No hay servicios</div>
                            <div class="empty-state-text">Comience agregando su primer servicio</div>
                            <?php if (auth()->can('servicios.crear')): ?>
                            <a href="<?= url('servicios/crear.php') ?>" class="btn btn-primary">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Nuevo Servicio
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($servicios as $servicio): ?>
                    <tr>
                        <td>
                            <span class="font-mono font-medium"><?= e($servicio['codigo']) ?></span>
                        </td>
                        <td>
                            <div class="font-medium text-shalom-primary"><?= e($servicio['nombre']) ?></div>
                            <?php if ($servicio['descripcion']): ?>
                            <div class="text-xs text-shalom-muted truncate max-w-xs"><?= e($servicio['descripcion']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($servicio['categoria_nombre']): ?>
                            <span class="badge badge-primary"><?= e($servicio['categoria_nombre']) ?></span>
                            <?php else: ?>
                            <span class="text-shalom-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-semibold"><?= currency($servicio['precio_unitario']) ?></td>
                        <td>
                            <span class="text-sm"><?= $servicio['impuesto_nombre'] ?></span>
                        </td>
                        <td>
                            <?php
                            $tipoClass = match($servicio['tipo']) {
                                'servicio' => 'badge-info',
                                'producto' => 'badge-success',
                                'paquete' => 'badge-warning',
                                default => 'badge-muted'
                            };
                            ?>
                            <span class="badge <?= $tipoClass ?>"><?= ucfirst($servicio['tipo']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $servicio['activo'] ? 'badge-success' : 'badge-muted' ?>">
                                <?= $servicio['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <?php if (auth()->can('servicios.editar')): ?>
                                <a href="<?= url('servicios/editar.php?id=' . $servicio['id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Editar">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (auth()->can('servicios.eliminar')): ?>
                                <button type="button" class="btn btn-icon btn-sm btn-danger" title="Eliminar" onclick="eliminarServicio(<?= $servicio['id'] ?>)">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
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

<!-- Modal Nueva Categoría -->
<div id="modalCategoria" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="cerrarModalCategoria()"></div>
    <div class="fixed inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-full md:max-w-md bg-white rounded-lg shadow-xl">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-shalom-primary">Nueva Categoría</h3>
            <button type="button" onclick="cerrarModalCategoria()" class="btn btn-icon btn-sm btn-secondary">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="formCategoria" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="form-label required">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2"></textarea>
                </div>
                <div>
                    <label class="form-label">Color</label>
                    <input type="color" name="color" class="form-control h-10" value="#1e4d39">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCategoria()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Categoría</button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarModalCategoria() {
    document.getElementById('modalCategoria').classList.remove('hidden');
}

function cerrarModalCategoria() {
    document.getElementById('modalCategoria').classList.add('hidden');
    document.getElementById('formCategoria').reset();
}

document.getElementById('formCategoria').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await ShalomApp.post('<?= url('api/servicios/categoria.php') ?>', data);
        
        if (response.success) {
            ShalomApp.toast('Categoría creada correctamente', 'success');
            cerrarModalCategoria();
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al crear la categoría', 'error');
    }
});

async function eliminarServicio(id) {
    if (!await ShalomApp.confirm('¿Está seguro de eliminar este servicio?')) {
        return;
    }
    
    try {
        const response = await ShalomApp.post('<?= url('api/servicios/eliminar.php') ?>', { id });
        
        if (response.success) {
            ShalomApp.toast('Servicio eliminado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al eliminar el servicio', 'error');
    }
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
