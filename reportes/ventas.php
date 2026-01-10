<?php
/**
 * SHALOM FACTURA - Reporte de Ventas
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

// Filtros
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$clienteId = $_GET['cliente_id'] ?? null;

$filtros = ['cliente_id' => $clienteId];

$reporte = $reporteModel->getVentas($fechaDesde, $fechaHasta, $filtros);

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="reporte_ventas_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Reporte de Ventas</h2>";
    echo "<p>Período: " . format_date($fechaDesde) . " al " . format_date($fechaHasta) . "</p>";
    
    echo "<table border='1'>";
    echo "<tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>RUC/Cédula</th><th>Subtotal</th><th>IVA</th><th>Total</th><th>Estado Pago</th></tr>";
    
    foreach ($reporte['facturas'] as $f) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($f['numero']) . "</td>";
        echo "<td>" . $f['fecha_emision'] . "</td>";
        echo "<td>" . htmlspecialchars($f['cliente']) . "</td>";
        echo "<td>" . $f['identificacion'] . "</td>";
        echo "<td style='text-align:right'>" . number_format($f['subtotal'], 2) . "</td>";
        echo "<td style='text-align:right'>" . number_format($f['iva'], 2) . "</td>";
        echo "<td style='text-align:right'>" . number_format($f['total'], 2) . "</td>";
        echo "<td>" . ucfirst($f['estado_pago']) . "</td>";
        echo "</tr>";
    }
    
    echo "<tr style='font-weight:bold'>";
    echo "<td colspan='4'>TOTALES</td>";
    echo "<td style='text-align:right'>" . number_format($reporte['resumen']['subtotal'], 2) . "</td>";
    echo "<td style='text-align:right'>" . number_format($reporte['resumen']['total_iva'], 2) . "</td>";
    echo "<td style='text-align:right'>" . number_format($reporte['resumen']['total'], 2) . "</td>";
    echo "<td></td>";
    echo "</tr>";
    
    echo "</table></body></html>";
    exit;
}

$pageTitle = 'Reporte de Ventas';
$currentPage = 'reportes_ventas';
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => url('reportes/')],
    ['title' => 'Ventas']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Reporte de Ventas</h1>
        <p class="text-shalom-muted">
            Período: <?= format_date($fechaDesde) ?> al <?= format_date($fechaHasta) ?>
        </p>
    </div>
    
    <div class="flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
            Exportar Excel
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="form-label">Fecha Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= e($fechaDesde) ?>">
            </div>
            <div>
                <label class="form-label">Fecha Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= e($fechaHasta) ?>">
            </div>
            <div>
                <label class="form-label">Cliente</label>
                <select name="cliente_id" class="form-control">
                    <option value="">Todos los clientes</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Generar Reporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Facturas Emitidas</div>
            <div class="stat-value text-xl"><?= number_format($reporte['resumen']['cantidad_facturas']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Subtotal</div>
            <div class="stat-value text-xl"><?= currency($reporte['resumen']['subtotal']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">IVA</div>
            <div class="stat-value text-xl"><?= currency($reporte['resumen']['total_iva']) ?></div>
        </div>
    </div>
    <div class="stat-card bg-shalom-primary text-white">
        <div class="stat-content">
            <div class="stat-label text-white/80">Total Ventas</div>
            <div class="stat-value text-xl"><?= currency($reporte['resumen']['total']) ?></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Gráfico por día -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ventas por Día</h3>
        </div>
        <div class="card-body">
            <canvas id="chartPorDia" height="200"></canvas>
        </div>
    </div>
    
    <!-- Top Clientes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top Clientes</h3>
        </div>
        <div class="card-body p-0">
            <div class="max-h-64 overflow-y-auto">
                <table class="table">
                    <thead class="sticky top-0 bg-white">
                        <tr>
                            <th>Cliente</th>
                            <th class="text-right">Facturas</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($reporte['por_cliente'], 0, 10) as $c): ?>
                        <tr>
                            <td>
                                <div class="font-medium"><?= e($c['razon_social']) ?></div>
                                <div class="text-xs text-shalom-muted"><?= e($c['identificacion']) ?></div>
                            </td>
                            <td class="text-right"><?= $c['cantidad'] ?></td>
                            <td class="text-right font-semibold"><?= currency($c['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Top Servicios -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Top Servicios/Productos</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte['por_servicio'] as $s): ?>
                    <tr>
                        <td><?= e($s['descripcion']) ?></td>
                        <td class="text-right"><?= number_format($s['cantidad'], 2) ?></td>
                        <td class="text-right font-semibold"><?= currency($s['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detalle de Facturas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalle de Facturas</h3>
        <div class="text-sm text-shalom-muted"><?= count($reporte['facturas']) ?> registros</div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>RUC/Cédula</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">IVA</th>
                    <th class="text-right">Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte['facturas'] as $f): ?>
                <tr>
                    <td class="font-mono"><?= e($f['numero']) ?></td>
                    <td><?= format_date($f['fecha_emision']) ?></td>
                    <td><?= e($f['cliente']) ?></td>
                    <td><?= e($f['identificacion']) ?></td>
                    <td class="text-right"><?= currency($f['subtotal']) ?></td>
                    <td class="text-right"><?= currency($f['iva']) ?></td>
                    <td class="text-right font-semibold"><?= currency($f['total']) ?></td>
                    <td>
                        <?php
                        $pagoClass = match($f['estado_pago']) {
                            'pagado' => 'badge-success',
                            'parcial' => 'badge-info',
                            'vencido' => 'badge-danger',
                            default => 'badge-warning'
                        };
                        ?>
                        <span class="badge <?= $pagoClass ?>"><?= ucfirst($f['estado_pago']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="4">TOTALES</td>
                    <td class="text-right"><?= currency($reporte['resumen']['subtotal']) ?></td>
                    <td class="text-right"><?= currency($reporte['resumen']['total_iva']) ?></td>
                    <td class="text-right"><?= currency($reporte['resumen']['total']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const datosPorDia = <?= json_encode($reporte['por_dia']) ?>;
    
    const ctx = document.getElementById('chartPorDia').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: datosPorDia.map(d => {
                const fecha = new Date(d.fecha + 'T00:00:00');
                return fecha.toLocaleDateString('es-EC', { day: '2-digit', month: 'short' });
            }),
            datasets: [{
                label: 'Ventas',
                data: datosPorDia.map(d => parseFloat(d.total)),
                backgroundColor: 'rgba(30, 77, 57, 0.8)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => '$ ' + value
                    }
                }
            }
        }
    });
});
</script>

<style>
@media print {
    .sidebar, .topbar, .btn, form, .card-header button { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
