<?php
/**
 * SHALOM FACTURA - Reporte de Impuestos
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Reportes\Reporte;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('reportes.ver')) {
    flash('error', 'No tiene permisos para acceder a reportes');
    redirect(url('dashboard.php'));
}

$reporteModel = new Reporte();

$mes = $_GET['mes'] ?? date('m');
$año = $_GET['año'] ?? date('Y');

$reporte = $reporteModel->getImpuestos($mes, $año);

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$pageTitle = 'Reporte de Impuestos';
$currentPage = 'reportes_impuestos';
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => url('reportes/')],
    ['title' => 'Impuestos']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Reporte de Impuestos</h1>
        <p class="text-shalom-muted">
            Período: <?= $meses[$mes] ?> <?= $año ?>
        </p>
    </div>
    
    <div class="flex gap-2">
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="form-label">Mes</label>
                <select name="mes" class="form-control">
                    <?php foreach ($meses as $num => $nombre): ?>
                    <option value="<?= $num ?>" <?= $mes === $num ? 'selected' : '' ?>><?= $nombre ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Año</label>
                <select name="año" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $año == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>
                Generar
            </button>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="trending-up" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">IVA Cobrado</div>
            <div class="stat-value"><?= currency($reporte['resumen']['iva_cobrado']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="minus-circle" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">IVA Retenido</div>
            <div class="stat-value"><?= currency($reporte['resumen']['iva_retenido']) ?></div>
        </div>
    </div>
    
    <div class="stat-card bg-shalom-primary text-white">
        <div class="stat-content">
            <div class="stat-label text-white/80">IVA a Pagar</div>
            <div class="stat-value">
                <?php
                $ivaPagar = $reporte['resumen']['iva_a_pagar'];
                echo $ivaPagar >= 0 ? currency($ivaPagar) : '(' . currency(abs($ivaPagar)) . ') Crédito';
                ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- IVA en Ventas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">IVA en Ventas</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th class="text-right">Base Imponible</th>
                        <th class="text-right">IVA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reporte['ventas']['detalle'])): ?>
                    <tr>
                        <td colspan="3" class="text-center text-shalom-muted">Sin datos para el período</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($reporte['ventas']['detalle'] as $v): ?>
                        <tr>
                            <td><?= e($v['impuesto']) ?></td>
                            <td class="text-right"><?= currency($v['base_imponible']) ?></td>
                            <td class="text-right font-medium"><?= currency($v['valor_iva']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-right"><?= currency($reporte['ventas']['total_gravadas'] + $reporte['ventas']['total_0']) ?></td>
                        <td class="text-right"><?= currency($reporte['ventas']['total_iva']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Resumen de Ventas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Resumen de Ventas</h3>
        </div>
        <div class="card-body">
            <div class="space-y-4">
                <div class="flex justify-between py-2 border-b">
                    <span class="text-shalom-muted">Ventas gravadas con IVA 15%:</span>
                    <span class="font-medium"><?= currency($reporte['ventas']['total_gravadas']) ?></span>
                </div>
                <div class="flex justify-between py-2 border-b">
                    <span class="text-shalom-muted">Ventas con tarifa 0%:</span>
                    <span class="font-medium"><?= currency($reporte['ventas']['total_0']) ?></span>
                </div>
                <div class="flex justify-between py-2 border-b">
                    <span class="text-shalom-muted">Total Ventas:</span>
                    <span class="font-semibold"><?= currency($reporte['ventas']['total_gravadas'] + $reporte['ventas']['total_0']) ?></span>
                </div>
                <div class="flex justify-between py-2 text-lg">
                    <span class="font-semibold text-shalom-primary">IVA Cobrado:</span>
                    <span class="font-bold text-shalom-primary"><?= currency($reporte['ventas']['total_iva']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Retenciones de IVA -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Retenciones de IVA Recibidas</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>%</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Base Imponible</th>
                    <th class="text-right">Valor Retenido</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reporte['retenciones_iva']['detalle'])): ?>
                <tr>
                    <td colspan="6" class="text-center text-shalom-muted">Sin retenciones de IVA en el período</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reporte['retenciones_iva']['detalle'] as $r): ?>
                    <tr>
                        <td class="font-mono"><?= e($r['codigo']) ?></td>
                        <td><?= e($r['descripcion']) ?></td>
                        <td><?= $r['porcentaje'] ?>%</td>
                        <td class="text-right"><?= $r['cantidad'] ?></td>
                        <td class="text-right"><?= currency($r['base_imponible']) ?></td>
                        <td class="text-right font-medium"><?= currency($r['valor_retenido']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="5">TOTAL RETENCIONES IVA</td>
                    <td class="text-right"><?= currency($reporte['retenciones_iva']['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Retenciones de Renta -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Retenciones de Renta Recibidas</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>%</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Base Imponible</th>
                    <th class="text-right">Valor Retenido</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reporte['retenciones_renta']['detalle'])): ?>
                <tr>
                    <td colspan="6" class="text-center text-shalom-muted">Sin retenciones de Renta en el período</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reporte['retenciones_renta']['detalle'] as $r): ?>
                    <tr>
                        <td class="font-mono"><?= e($r['codigo']) ?></td>
                        <td><?= e($r['descripcion']) ?></td>
                        <td><?= $r['porcentaje'] ?>%</td>
                        <td class="text-right"><?= $r['cantidad'] ?></td>
                        <td class="text-right"><?= currency($r['base_imponible']) ?></td>
                        <td class="text-right font-medium"><?= currency($r['valor_retenido']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="5">TOTAL RETENCIONES RENTA</td>
                    <td class="text-right"><?= currency($reporte['retenciones_renta']['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Cálculo final -->
<div class="card bg-shalom-secondary">
    <div class="card-header">
        <h3 class="card-title">Cálculo de IVA Mensual</h3>
    </div>
    <div class="card-body">
        <div class="max-w-md mx-auto space-y-3">
            <div class="flex justify-between py-2 border-b border-shalom-primary/20">
                <span>IVA Cobrado en Ventas:</span>
                <span class="font-medium"><?= currency($reporte['resumen']['iva_cobrado']) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-shalom-primary/20">
                <span>(-) Retenciones de IVA recibidas:</span>
                <span class="font-medium text-red-600">- <?= currency($reporte['resumen']['iva_retenido']) ?></span>
            </div>
            <div class="flex justify-between py-4 text-xl">
                <span class="font-semibold text-shalom-primary">IVA A PAGAR:</span>
                <span class="font-bold text-shalom-primary">
                    <?php
                    $ivaPagar = $reporte['resumen']['iva_a_pagar'];
                    if ($ivaPagar >= 0) {
                        echo currency($ivaPagar);
                    } else {
                        echo '<span class="text-blue-600">(' . currency(abs($ivaPagar)) . ') Crédito Tributario</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="mt-6 p-4 bg-white/50 rounded-lg text-sm text-shalom-muted">
            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
            <strong>Nota:</strong> Este reporte es informativo. Para su declaración oficial consulte con su contador 
            y verifique la información con los documentos originales.
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .btn, form { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
}
</style>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
