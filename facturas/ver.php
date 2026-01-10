<?php
/**
 * SHALOM FACTURA - Ver Factura
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Facturas\Factura;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('facturas.ver')) {
    flash('error', 'No tiene permisos para ver facturas');
    redirect(url('dashboard.php'));
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    flash('error', 'Factura no especificada');
    redirect(url('facturas/'));
}

$facturaModel = new Factura();
$factura = $facturaModel->getById($id);

if (!$factura) {
    flash('error', 'Factura no encontrada');
    redirect(url('facturas/'));
}

$pageTitle = 'Factura ' . $factura['numero_documento'];
$currentPage = 'facturas';
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => url('facturas/')],
    ['title' => $factura['numero_documento']]
];

ob_start();
?>

<div class="max-w-5xl mx-auto">
    <!-- Encabezado -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-shalom-primary"><?= e($factura['numero_documento']) ?></h1>
                
                <!-- Badges de estado -->
                <?php
                $estadoClass = match($factura['estado']) {
                    'borrador' => 'badge-muted',
                    'emitida' => 'badge-success',
                    'anulada' => 'badge-danger',
                    default => 'badge-muted'
                };
                $estadoSriClass = match($factura['estado_sri']) {
                    'pendiente' => 'badge-warning',
                    'recibida' => 'badge-info',
                    'autorizada' => 'badge-success',
                    'rechazada' => 'badge-danger',
                    default => 'badge-muted'
                };
                $estadoPagoClass = match($factura['estado_pago']) {
                    'pagado' => 'badge-success',
                    'parcial' => 'badge-info',
                    'vencido' => 'badge-danger',
                    default => 'badge-warning'
                };
                ?>
                <span class="badge <?= $estadoClass ?>"><?= ucfirst($factura['estado']) ?></span>
                <span class="badge <?= $estadoSriClass ?>">SRI: <?= ucfirst($factura['estado_sri']) ?></span>
                <span class="badge <?= $estadoPagoClass ?>"><?= ucfirst($factura['estado_pago']) ?></span>
            </div>
            <p class="text-shalom-muted mt-1">
                Emitida el <?= format_date($factura['fecha_emision']) ?>
            </p>
        </div>
        
        <div class="flex flex-wrap gap-2">
            <a href="<?= url('facturas/pdf.php?id=' . $factura['id']) ?>" class="btn btn-secondary" target="_blank">
                <i data-lucide="file-down" class="w-4 h-4"></i>
                PDF
            </a>
            
            <?php if ($factura['estado'] === 'emitida' && $factura['estado_sri'] === 'autorizada'): ?>
            <button type="button" class="btn btn-secondary" onclick="enviarEmail()">
                <i data-lucide="mail" class="w-4 h-4"></i>
                Enviar Email
            </button>
            <?php endif; ?>
            
            <?php if ($factura['estado'] === 'emitida' && in_array($factura['estado_pago'], ['pendiente', 'parcial']) && auth()->can('pagos.crear')): ?>
            <a href="<?= url('pagos/registrar.php?factura_id=' . $factura['id']) ?>" class="btn btn-success">
                <i data-lucide="credit-card" class="w-4 h-4"></i>
                Registrar Pago
            </a>
            <?php endif; ?>
            
            <a href="<?= url('facturas/') ?>" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Volver
            </a>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if ($factura['estado_sri'] === 'rechazada' && !empty($factura['mensaje_sri'])): ?>
    <div class="alert alert-error mb-6">
        <i data-lucide="alert-circle" class="alert-icon"></i>
        <div class="alert-content">
            <div class="font-semibold">Rechazada por el SRI</div>
            <div class="text-sm"><?= e($factura['mensaje_sri']) ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($factura['estado_pago'] === 'vencido'): ?>
    <div class="alert alert-warning mb-6">
        <i data-lucide="clock" class="alert-icon"></i>
        <div class="alert-content">
            <div class="font-semibold">Factura Vencida</div>
            <div class="text-sm">
                Esta factura venció el <?= format_date($factura['fecha_vencimiento']) ?>. 
                Saldo pendiente: <?= currency($factura['total'] - ($factura['total_pagado'] ?? 0)) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Datos principales -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Información de la factura -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información del Comprobante</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <div class="text-sm text-shalom-muted">Número</div>
                            <div class="font-mono font-semibold"><?= e($factura['numero_documento']) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Fecha Emisión</div>
                            <div class="font-medium"><?= format_date($factura['fecha_emision']) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Fecha Vencimiento</div>
                            <div class="font-medium"><?= format_date($factura['fecha_vencimiento']) ?></div>
                        </div>
                        <?php if ($factura['numero_autorizacion']): ?>
                        <div class="col-span-full">
                            <div class="text-sm text-shalom-muted">Número de Autorización</div>
                            <div class="font-mono text-sm break-all"><?= e($factura['numero_autorizacion']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($factura['clave_acceso']): ?>
                        <div class="col-span-full">
                            <div class="text-sm text-shalom-muted">Clave de Acceso</div>
                            <div class="font-mono text-sm break-all"><?= e($factura['clave_acceso']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cliente</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-shalom-muted">Razón Social</div>
                            <div class="font-semibold text-shalom-primary"><?= e($factura['cliente_nombre']) ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-shalom-muted">Identificación</div>
                            <div class="font-medium"><?= e($factura['cliente_identificacion']) ?></div>
                        </div>
                        <?php if ($factura['cliente_email']): ?>
                        <div>
                            <div class="text-sm text-shalom-muted">Email</div>
                            <div><?= e($factura['cliente_email']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($factura['cliente_telefono']): ?>
                        <div>
                            <div class="text-sm text-shalom-muted">Teléfono</div>
                            <div><?= e($factura['cliente_telefono']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($factura['cliente_direccion']): ?>
                        <div class="md:col-span-2">
                            <div class="text-sm text-shalom-muted">Dirección</div>
                            <div><?= e($factura['cliente_direccion']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Detalle -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Detalle</h3>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">P. Unit.</th>
                                <th class="text-right">Desc.</th>
                                <th class="text-right">IVA</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($factura['detalles'] as $detalle): ?>
                            <tr>
                                <td class="font-mono text-sm"><?= e($detalle['codigo_principal']) ?></td>
                                <td><?= e($detalle['descripcion']) ?></td>
                                <td class="text-right"><?= number_format($detalle['cantidad'], 2) ?></td>
                                <td class="text-right"><?= currency($detalle['precio_unitario']) ?></td>
                                <td class="text-right"><?= currency($detalle['descuento']) ?></td>
                                <td class="text-right"><?= currency($detalle['valor_iva'] ?? 0) ?></td>
                                <td class="text-right font-semibold"><?= currency($detalle['precio_total_sin_impuesto'] + ($detalle['valor_iva'] ?? 0)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Información adicional -->
            <?php if (!empty($factura['info_adicional'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información Adicional</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($factura['info_adicional'] as $info): ?>
                        <div class="text-sm">
                            <span class="text-shalom-muted"><?= e($info['nombre']) ?>:</span>
                            <span class="font-medium"><?= e($info['valor']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna lateral -->
        <div class="space-y-6">
            <!-- Totales -->
            <div class="card bg-shalom-secondary">
                <div class="card-header">
                    <h3 class="card-title">Resumen</h3>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex justify-between">
                        <span class="text-shalom-muted">Subtotal 15%:</span>
                        <span class="font-medium"><?= currency($factura['subtotal_iva'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-shalom-muted">Subtotal 0%:</span>
                        <span class="font-medium"><?= currency($factura['subtotal_iva_0'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-shalom-muted">Descuento:</span>
                        <span class="font-medium">-<?= currency($factura['total_descuento']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-shalom-muted">Subtotal sin IVA:</span>
                        <span class="font-medium"><?= currency($factura['subtotal_sin_impuestos']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-shalom-muted">IVA 15%:</span>
                        <span class="font-medium"><?= currency($factura['total_iva']) ?></span>
                    </div>
                    <hr class="border-shalom-primary/20">
                    <div class="flex justify-between text-lg">
                        <span class="font-semibold text-shalom-primary">TOTAL:</span>
                        <span class="font-bold text-shalom-primary"><?= currency($factura['total']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Formas de pago -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Formas de Pago</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($factura['formas_pago'])): ?>
                        <?php foreach ($factura['formas_pago'] as $fp): ?>
                        <div class="flex justify-between py-2 border-b last:border-0">
                            <span><?= e($fp['forma_pago_nombre'] ?? 'N/A') ?></span>
                            <span class="font-medium"><?= currency($fp['total'] ?? 0) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-shalom-muted text-sm">Sin formas de pago registradas</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pagos aplicados -->
            <?php if (!empty($factura['pagos'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pagos Recibidos</h3>
                </div>
                <div class="card-body p-0">
                    <div class="divide-y">
                        <?php foreach ($factura['pagos'] as $pago): ?>
                        <div class="p-3">
                            <div class="flex justify-between">
                                <div>
                                    <div class="font-medium"><?= e($pago['numero_recibo']) ?></div>
                                    <div class="text-xs text-shalom-muted"><?= format_date($pago['fecha']) ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-green-600"><?= currency($pago['monto']) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer bg-gray-50">
                    <div class="flex justify-between font-semibold">
                        <span>Saldo Pendiente:</span>
                        <span class="<?= ($factura['total'] - ($factura['total_pagado'] ?? 0)) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= currency($factura['total'] - ($factura['total_pagado'] ?? 0)) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Acciones -->
            <?php if ($factura['estado'] !== 'anulada'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Acciones</h3>
                </div>
                <div class="card-body space-y-2">
                    <?php if ($factura['estado'] === 'borrador' && auth()->can('facturas.crear')): ?>
                    <a href="<?= url('facturas/editar.php?id=' . $factura['id']) ?>" class="btn btn-secondary w-full">
                        <i data-lucide="pencil" class="w-4 h-4"></i>
                        Editar
                    </a>
                    <button type="button" class="btn btn-primary w-full" onclick="emitirFactura()">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Emitir al SRI
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($factura['estado'] === 'emitida' && in_array($factura['estado_sri'], ['pendiente', 'rechazada'])): ?>
                    <button type="button" class="btn btn-warning w-full" onclick="reenviarSri()">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Reenviar al SRI
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($factura['estado'] === 'emitida' && auth()->can('facturas.crear')): ?>
                    <a href="<?= url('notas-credito/crear.php?factura_id=' . $factura['id']) ?>" class="btn btn-secondary w-full">
                        <i data-lucide="file-minus" class="w-4 h-4"></i>
                        Crear Nota de Crédito
                    </a>
                    <?php endif; ?>
                    
                    <?php if (auth()->can('facturas.anular')): ?>
                    <button type="button" class="btn btn-danger w-full" onclick="anularFactura()">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        Anular Factura
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function emitirFactura() {
    if (!await ShalomApp.confirm('¿Está seguro de emitir esta factura al SRI?')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/emitir.php') ?>', { id: <?= $factura['id'] ?> });
        
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

async function reenviarSri() {
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/reenviar.php') ?>', { id: <?= $factura['id'] ?> });
        
        if (response.success) {
            ShalomApp.toast('Comprobante reenviado al SRI', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al reenviar al SRI', 'error');
    }
    
    ShalomApp.hideLoading();
}

async function anularFactura() {
    const motivo = prompt('Ingrese el motivo de anulación:');
    if (!motivo) return;
    
    if (!await ShalomApp.confirm('¿Está seguro de anular esta factura? Esta acción no se puede deshacer.')) {
        return;
    }
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/facturas/anular.php') ?>', { 
            id: <?= $factura['id'] ?>,
            motivo 
        });
        
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

function enviarEmail() {
    ShalomApp.toast('Función de envío de email próximamente', 'info');
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';