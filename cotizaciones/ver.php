<?php
/**
 * SHALOM FACTURA - Ver Cotización
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Cotizaciones\Cotizacion;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('cotizaciones.ver')) {
    flash('error', 'No tiene permisos para ver cotizaciones');
    redirect(url('cotizaciones/'));
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Cotización no especificada');
    redirect(url('cotizaciones/'));
}

$cotizacionModel = new Cotizacion();
$cotizacion = $cotizacionModel->getById($id);

if (!$cotizacion) {
    flash('error', 'Cotización no encontrada');
    redirect(url('cotizaciones/'));
}

// Obtener detalles
$detalles = $cotizacionModel->getDetalles($id);

$pageTitle = 'Cotización #' . $cotizacion['numero'];
$currentPage = 'cotizaciones';
$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => url('cotizaciones/')],
    ['title' => 'Ver Cotización']
];

// Estados con colores
$estadoClasses = [
    'borrador' => 'bg-gray-100 text-gray-800',
    'enviada' => 'bg-blue-100 text-blue-800',
    'aceptada' => 'bg-green-100 text-green-800',
    'rechazada' => 'bg-red-100 text-red-800',
    'vencida' => 'bg-orange-100 text-orange-800',
    'facturada' => 'bg-purple-100 text-purple-800'
];

$estadoNombres = [
    'borrador' => 'Borrador',
    'enviada' => 'Enviada',
    'aceptada' => 'Aceptada',
    'rechazada' => 'Rechazada',
    'vencida' => 'Vencida',
    'facturada' => 'Facturada'
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-shalom-primary">Cotización #<?= e($cotizacion['numero']) ?></h1>
            <span class="px-3 py-1 rounded-full text-sm font-medium <?= $estadoClasses[$cotizacion['estado']] ?? 'bg-gray-100' ?>">
                <?= $estadoNombres[$cotizacion['estado']] ?? ucfirst($cotizacion['estado']) ?>
            </span>
        </div>
        <p class="text-shalom-muted"><?= e($cotizacion['cliente_nombre']) ?></p>
    </div>
    
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('cotizaciones/pdf.php?id=' . $id) ?>" class="btn btn-secondary" target="_blank">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            Ver PDF
        </a>
        
        <?php if ($cotizacion['estado'] === 'borrador'): ?>
        <a href="<?= url('cotizaciones/crear.php?id=' . $id) ?>" class="btn btn-secondary">
            <i data-lucide="edit" class="w-4 h-4"></i>
            Editar
        </a>
        <?php endif; ?>
        
        <?php if ($cotizacion['estado'] === 'aceptada' && auth()->can('facturas.crear')): ?>
        <a href="<?= url('facturas/crear.php?cotizacion_id=' . $id) ?>" class="btn btn-primary">
            <i data-lucide="receipt" class="w-4 h-4"></i>
            Convertir a Factura
        </a>
        <?php endif; ?>
        
        <a href="<?= url('cotizaciones/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Columna Principal -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Detalles de la Cotización -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="list" class="w-5 h-5 inline mr-2"></i>
                    Detalle de Ítems
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-16">#</th>
                            <th>Descripción</th>
                            <th class="text-right w-24">Cant.</th>
                            <th class="text-right w-32">P. Unit.</th>
                            <th class="text-right w-24">Desc.</th>
                            <th class="text-right w-32">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $i => $detalle): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td>
                                <div class="font-medium"><?= e($detalle['descripcion']) ?></div>
                                <?php if (!empty($detalle['codigo'])): ?>
                                <div class="text-xs text-shalom-muted"><?= e($detalle['codigo']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= number_format($detalle['cantidad'], 2) ?></td>
                            <td class="text-right"><?= currency($detalle['precio_unitario']) ?></td>
                            <td class="text-right"><?= number_format($detalle['descuento'] ?? 0, 2) ?>%</td>
                            <td class="text-right font-medium"><?= currency($detalle['subtotal']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Términos y Condiciones -->
        <?php if (!empty($cotizacion['condiciones'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="file-text" class="w-5 h-5 inline mr-2"></i>
                    Términos y Condiciones
                </h3>
            </div>
            <div class="card-body">
                <div class="prose max-w-none text-sm">
                    <?= nl2br(e($cotizacion['condiciones'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Observaciones -->
        <?php if (!empty($cotizacion['observaciones'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="message-square" class="w-5 h-5 inline mr-2"></i>
                    Observaciones
                </h3>
            </div>
            <div class="card-body">
                <p class="text-sm"><?= nl2br(e($cotizacion['observaciones'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Columna Lateral -->
    <div class="space-y-6">
        <!-- Información del Cliente -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="user" class="w-5 h-5 inline mr-2"></i>
                    Cliente
                </h3>
            </div>
            <div class="card-body space-y-3">
                <div>
                    <span class="text-xs text-shalom-muted uppercase">Razón Social</span>
                    <p class="font-medium"><?= e($cotizacion['cliente_nombre']) ?></p>
                </div>
                <div>
                    <span class="text-xs text-shalom-muted uppercase">Identificación</span>
                    <p><?= e($cotizacion['cliente_identificacion']) ?></p>
                </div>
                <?php if (!empty($cotizacion['cliente_email'])): ?>
                <div>
                    <span class="text-xs text-shalom-muted uppercase">Email</span>
                    <p><?= e($cotizacion['cliente_email']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Información de la Cotización -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="calendar" class="w-5 h-5 inline mr-2"></i>
                    Información
                </h3>
            </div>
            <div class="card-body space-y-3">
                <div class="flex justify-between">
                    <span class="text-shalom-muted">Fecha:</span>
                    <span class="font-medium"><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-shalom-muted">Válida hasta:</span>
                    <span class="font-medium"><?= date('d/m/Y', strtotime($cotizacion['fecha_validez'])) ?></span>
                </div>
                <?php if (!empty($cotizacion['asunto'])): ?>
                <div>
                    <span class="text-xs text-shalom-muted uppercase">Asunto</span>
                    <p class="text-sm"><?= e($cotizacion['asunto']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Totales -->
        <div class="card bg-shalom-primary text-white">
            <div class="card-body space-y-3">
                <div class="flex justify-between text-white/80">
                    <span>Subtotal:</span>
                    <span><?= currency($cotizacion['subtotal']) ?></span>
                </div>
                
                <?php if (($cotizacion['total_descuento'] ?? 0) > 0): ?>
                <div class="flex justify-between text-white/80">
                    <span>Descuento:</span>
                    <span>-<?= currency($cotizacion['total_descuento']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between text-white/80">
                    <span>Subtotal IVA 0%:</span>
                    <span><?= currency($cotizacion['subtotal_iva_0'] ?? 0) ?></span>
                </div>
                
                <div class="flex justify-between text-white/80">
                    <span>Subtotal IVA <?= $cotizacion['porcentaje_iva'] ?? 15 ?>%:</span>
                    <span><?= currency($cotizacion['subtotal_iva'] ?? 0) ?></span>
                </div>
                
                <div class="flex justify-between text-white/80">
                    <span>IVA <?= $cotizacion['porcentaje_iva'] ?? 15 ?>%:</span>
                    <span><?= currency($cotizacion['total_iva']) ?></span>
                </div>
                
                <div class="border-t border-white/20 pt-3 flex justify-between text-lg font-bold">
                    <span>TOTAL:</span>
                    <span><?= currency($cotizacion['total']) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Acciones de Estado -->
        <?php if (in_array($cotizacion['estado'], ['borrador', 'enviada'])): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="settings" class="w-5 h-5 inline mr-2"></i>
                    Acciones
                </h3>
            </div>
            <div class="card-body space-y-2">
                <?php if ($cotizacion['estado'] === 'borrador'): ?>
                <button onclick="cambiarEstado(<?= $id ?>, 'enviada')" class="btn btn-secondary w-full">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Marcar como Enviada
                </button>
                <?php endif; ?>
                
                <?php if ($cotizacion['estado'] === 'enviada'): ?>
                <button onclick="cambiarEstado(<?= $id ?>, 'aceptada')" class="btn btn-success w-full">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Marcar como Aceptada
                </button>
                <button onclick="mostrarRechazo()" class="btn btn-danger w-full">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Marcar como Rechazada
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Historial -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="clock" class="w-5 h-5 inline mr-2"></i>
                    Historial
                </h3>
            </div>
            <div class="card-body">
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 rounded-full bg-gray-400 mt-1.5"></div>
                        <div>
                            <p class="font-medium">Creada</p>
                            <p class="text-shalom-muted"><?= date('d/m/Y H:i', strtotime($cotizacion['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($cotizacion['enviado_at'])): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5"></div>
                        <div>
                            <p class="font-medium">Enviada</p>
                            <p class="text-shalom-muted"><?= date('d/m/Y H:i', strtotime($cotizacion['enviado_at'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cotizacion['aceptado_at'])): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 rounded-full bg-green-400 mt-1.5"></div>
                        <div>
                            <p class="font-medium">Aceptada</p>
                            <p class="text-shalom-muted"><?= date('d/m/Y H:i', strtotime($cotizacion['aceptado_at'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cotizacion['rechazado_at'])): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-2 h-2 rounded-full bg-red-400 mt-1.5"></div>
                        <div>
                            <p class="font-medium">Rechazada</p>
                            <p class="text-shalom-muted"><?= date('d/m/Y H:i', strtotime($cotizacion['rechazado_at'])) ?></p>
                            <?php if (!empty($cotizacion['motivo_rechazo'])): ?>
                            <p class="text-xs text-red-600">Motivo: <?= e($cotizacion['motivo_rechazo']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rechazo -->
<div id="modalRechazo" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-shalom-primary mb-4">Motivo del Rechazo</h3>
        <textarea id="motivoRechazo" class="form-control mb-4" rows="3" placeholder="Ingrese el motivo del rechazo (opcional)"></textarea>
        <div class="flex gap-3 justify-end">
            <button onclick="cerrarModalRechazo()" class="btn btn-secondary">Cancelar</button>
            <button onclick="confirmarRechazo()" class="btn btn-danger">Confirmar Rechazo</button>
        </div>
    </div>
</div>

<script>
async function cambiarEstado(id, estado) {
    if (!confirm('¿Está seguro de cambiar el estado de esta cotización?')) return;
    
    ShalomApp.showLoading();
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/estado.php') ?>', {
            id: id,
            estado: estado
        });
        
        if (response.success) {
            ShalomApp.toast(response.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al cambiar el estado', 'error');
    }
    ShalomApp.hideLoading();
}

function mostrarRechazo() {
    document.getElementById('modalRechazo').classList.remove('hidden');
    document.getElementById('modalRechazo').classList.add('flex');
}

function cerrarModalRechazo() {
    document.getElementById('modalRechazo').classList.add('hidden');
    document.getElementById('modalRechazo').classList.remove('flex');
}

async function confirmarRechazo() {
    const motivo = document.getElementById('motivoRechazo').value;
    
    ShalomApp.showLoading();
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/estado.php') ?>', {
            id: <?= $id ?>,
            estado: 'rechazada',
            motivo_rechazo: motivo
        });
        
        if (response.success) {
            ShalomApp.toast(response.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al rechazar la cotización', 'error');
    }
    ShalomApp.hideLoading();
    cerrarModalRechazo();
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';