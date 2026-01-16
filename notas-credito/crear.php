<?php
/**
 * SHALOM FACTURA - Crear Nota de Crédito
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Facturas\Factura;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('notas_credito.crear')) {
    flash('error', 'No tiene permisos para crear notas de crédito');
    redirect(url('notas-credito/'));
}

$db = db();
$empresaId = auth()->empresaId();

$facturaId = isset($_GET['factura_id']) ? (int) $_GET['factura_id'] : null;
$facturaModel = new Factura();
$factura = null;

$facturasDisponibles = $db->query("
    SELECT f.id,
        f.fecha_emision,
        f.total,
        c.razon_social as cliente_nombre,
        c.identificacion as cliente_identificacion,
        CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero_documento
    FROM facturas f
    JOIN clientes c ON f.cliente_id = c.id
    JOIN establecimientos e ON f.establecimiento_id = e.id
    JOIN puntos_emision pe ON f.punto_emision_id = pe.id
    WHERE f.empresa_id = :empresa_id
        AND f.deleted_at IS NULL
        AND f.estado_sri = 'autorizada'
    ORDER BY f.fecha_emision DESC, f.id DESC
    LIMIT 150
")->fetchAll([':empresa_id' => $empresaId]);

if ($facturaId) {
    $factura = $facturaModel->getById($facturaId);
}

$pageTitle = 'Nueva Nota de Crédito';
$currentPage = 'notas_credito';
$breadcrumbs = [
    ['title' => 'Notas de Crédito', 'url' => url('notas-credito/')],
    ['title' => 'Nueva Nota de Crédito']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Nueva Nota de Crédito</h1>
        <p class="text-shalom-muted">Seleccione una factura autorizada y registre el motivo</p>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Factura Asociada</h3>
    </div>
    <div class="card-body">
        <label class="form-label required">Factura autorizada</label>
        <select class="form-control" id="facturaSelector">
            <option value="">Seleccione una factura...</option>
            <?php foreach ($facturasDisponibles as $item): ?>
                <option value="<?= $item['id'] ?>" <?= $facturaId === (int) $item['id'] ? 'selected' : '' ?>>
                    <?= e($item['numero_documento']) ?> - <?= e($item['cliente_nombre']) ?> (<?= currency($item['total']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="text-sm text-shalom-muted mt-2">Solo se listan facturas autorizadas por el SRI.</p>
    </div>
</div>

<?php if (!$factura && $facturaId): ?>
<div class="alert alert-danger mb-6">No se encontró la factura seleccionada o no pertenece a su empresa.</div>
<?php endif; ?>

<?php if ($factura): ?>
<form id="notaCreditoForm" data-validate>
    <?= csrf_field() ?>
    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="user" class="w-5 h-5 inline mr-2"></i>
                        Datos del Cliente
                    </h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-shalom-muted">Razón Social:</span>
                            <div class="font-medium"><?= e($factura['cliente_nombre']) ?></div>
                        </div>
                        <div>
                            <span class="text-shalom-muted">Identificación:</span>
                            <div class="font-medium"><?= e($factura['cliente_identificacion']) ?></div>
                        </div>
                        <div>
                            <span class="text-shalom-muted">Email:</span>
                            <div class="font-medium"><?= e($factura['cliente_email']) ?></div>
                        </div>
                        <div>
                            <span class="text-shalom-muted">Teléfono:</span>
                            <div class="font-medium"><?= e($factura['cliente_telefono']) ?></div>
                        </div>
                        <div class="md:col-span-2">
                            <span class="text-shalom-muted">Dirección:</span>
                            <div class="font-medium"><?= e($factura['cliente_direccion']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="list" class="w-5 h-5 inline mr-2"></i>
                        Detalle de la Nota de Crédito
                    </h3>
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
                                <?php foreach ($factura['detalles'] as $detalle): ?>
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
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Datos del Comprobante</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label">Factura origen</label>
                        <input type="text" class="form-control" value="<?= e($factura['numero_documento']) ?>" disabled>
                    </div>
                    <div>
                        <label class="form-label required">Fecha de emisión</label>
                        <input type="date" name="fecha_emision" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="form-label required">Motivo</label>
                        <textarea name="motivo" class="form-control" rows="4" placeholder="Ej: Devolución de mercadería" required></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Totales</h3>
                </div>
                <div class="card-body space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span class="font-medium"><?= currency($factura['subtotal_sin_impuestos']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Descuento:</span>
                        <span class="font-medium"><?= currency($factura['total_descuento']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>IVA:</span>
                        <span class="font-medium"><?= currency($factura['total_iva']) ?></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-2">
                        <span class="font-semibold">Total:</span>
                        <span class="font-semibold"><?= currency($factura['total']) ?></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <button type="submit" class="btn btn-secondary" data-accion="guardar">Guardar Nota de Crédito</button>
                <button type="submit" class="btn btn-primary" data-accion="emitir">Guardar y Emitir</button>
                <a href="<?= url('facturas/ver.php?id=' . $factura['id']) ?>" class="btn btn-light">Volver a la factura</a>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
const selector = document.getElementById('facturaSelector');
if (selector) {
    selector.addEventListener('change', () => {
        const id = selector.value;
        if (id) {
            window.location.href = '<?= url('notas-credito/crear.php') ?>?factura_id=' + id;
        }
    });
}

const form = document.getElementById('notaCreditoForm');
if (form) {
    let accion = 'guardar';
    form.querySelectorAll('button[type="submit"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            accion = btn.dataset.accion || 'guardar';
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = {
            factura_id: form.querySelector('input[name="factura_id"]').value,
            fecha_emision: form.querySelector('input[name="fecha_emision"]').value,
            motivo: form.querySelector('textarea[name="motivo"]').value,
            accion: accion
        };

        ShalomApp.showLoading();

        try {
            const response = await window.fetch('<?= url('api/notas-credito/crear.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                ShalomApp.toast(result.message || 'Nota de crédito creada', 'success');
                setTimeout(() => {
                    window.location.href = '<?= url('notas-credito/ver.php') ?>?id=' + result.id;
                }, 1200);
            } else {
                ShalomApp.toast(result.message || 'No se pudo crear la nota de crédito', 'error');
            }
        } catch (error) {
            ShalomApp.toast('Error al crear la nota de crédito', 'error');
        }

        ShalomApp.hideLoading();
    });
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
