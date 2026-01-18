<?php
/**
 * SHALOM FACTURA - Reporte de Análisis de Clientes
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
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-01-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$reporte = $reporteModel->getAnalisisClientes($fechaDesde, $fechaHasta);

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="analisis_clientes_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Análisis de Clientes</h2>";
    echo "<p>Período: " . format_date($fechaDesde) . " al " . format_date($fechaHasta) . "</p>";

    echo "<table border='1'>";
    echo "<tr><th>#</th><th>Cliente</th><th>RUC/Cédula</th><th>Facturas</th><th>Total Facturado</th><th>Ticket Promedio</th><th>Primera Compra</th><th>Última Compra</th><th>Días sin Comprar</th></tr>";

    $pos = 1;
    foreach ($reporte['ranking'] as $c) {
        echo "<tr>";
        echo "<td>" . $pos++ . "</td>";
        echo "<td>" . htmlspecialchars($c['razon_social']) . "</td>";
        echo "<td>" . $c['identificacion'] . "</td>";
        echo "<td style='text-align:center'>" . $c['total_facturas'] . "</td>";
        echo "<td style='text-align:right'>" . number_format($c['total_facturado'], 2) . "</td>";
        echo "<td style='text-align:right'>" . number_format($c['ticket_promedio'], 2) . "</td>";
        echo "<td>" . $c['primera_compra'] . "</td>";
        echo "<td>" . $c['ultima_compra'] . "</td>";
        echo "<td style='text-align:center'>" . $c['dias_sin_comprar'] . "</td>";
        echo "</tr>";
    }

    echo "<tr style='font-weight:bold'>";
    echo "<td colspan='4'>TOTALES</td>";
    echo "<td style='text-align:right'>" . number_format($reporte['resumen']['total_facturado'], 2) . "</td>";
    echo "<td colspan='4'></td>";
    echo "</tr>";

    echo "</table></body></html>";
    exit;
}

$pageTitle = 'Análisis de Clientes';
$currentPage = 'reportes_clientes';
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => url('reportes/')],
    ['title' => 'Clientes']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Análisis de Clientes</h1>
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
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="form-label">Fecha Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= e($fechaDesde) ?>">
            </div>
            <div>
                <label class="form-label">Fecha Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= e($fechaHasta) ?>">
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
            <div class="stat-label">Clientes Activos</div>
            <div class="stat-value text-xl"><?= number_format($reporte['resumen']['total_clientes']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Clientes Nuevos</div>
            <div class="stat-value text-xl text-green-600"><?= number_format($reporte['resumen']['clientes_nuevos']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Ticket Promedio</div>
            <div class="stat-value text-xl"><?= currency($reporte['resumen']['ticket_promedio']) ?></div>
        </div>
    </div>
    <div class="stat-card bg-shalom-primary text-white">
        <div class="stat-content">
            <div class="stat-label text-white/80">Total Facturado</div>
            <div class="stat-value text-xl"><?= currency($reporte['resumen']['total_facturado']) ?></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Gráfico de evolución mensual -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Clientes Activos por Mes</h3>
        </div>
        <div class="card-body">
            <canvas id="chartEvolucion" height="200"></canvas>
        </div>
    </div>

    <!-- Frecuencia de compra -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Frecuencia de Compra</h3>
        </div>
        <div class="card-body">
            <canvas id="chartFrecuencia" height="200"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Por tipo de identificación -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Por Tipo de Identificación</h3>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th class="text-right">Clientes</th>
                        <th class="text-right">Total Facturado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte['por_tipo_identificacion'] as $t): ?>
                    <tr>
                        <td><?= e($t['tipo'] ?? 'Sin especificar') ?></td>
                        <td class="text-right"><?= number_format($t['cantidad']) ?></td>
                        <td class="text-right font-semibold"><?= currency($t['total_facturado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Clientes Nuevos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Clientes Nuevos en el Período</h3>
            <span class="badge badge-success"><?= count($reporte['clientes_nuevos']) ?></span>
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
                        <?php foreach (array_slice($reporte['clientes_nuevos'], 0, 10) as $c): ?>
                        <tr>
                            <td>
                                <div class="font-medium"><?= e($c['razon_social']) ?></div>
                                <div class="text-xs text-shalom-muted"><?= e($c['identificacion']) ?></div>
                            </td>
                            <td class="text-right"><?= $c['facturas'] ?></td>
                            <td class="text-right font-semibold"><?= currency($c['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reporte['clientes_nuevos'])): ?>
                        <tr>
                            <td colspan="3" class="text-center text-shalom-muted py-4">
                                No hay clientes nuevos en este período
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Ranking de Clientes -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ranking de Clientes</h3>
        <div class="text-sm text-shalom-muted"><?= count($reporte['ranking']) ?> clientes activos</div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-12">#</th>
                    <th>Cliente</th>
                    <th>RUC/Cédula</th>
                    <th class="text-center">Facturas</th>
                    <th class="text-right">Total Facturado</th>
                    <th class="text-right">Ticket Prom.</th>
                    <th>Primera Compra</th>
                    <th>Última Compra</th>
                    <th class="text-center">Días sin Comprar</th>
                </tr>
            </thead>
            <tbody>
                <?php $pos = 1; foreach ($reporte['ranking'] as $c): ?>
                <tr>
                    <td class="font-semibold">
                        <?php if ($pos <= 3): ?>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?= $pos == 1 ? 'bg-yellow-100 text-yellow-700' : ($pos == 2 ? 'bg-gray-100 text-gray-700' : 'bg-orange-100 text-orange-700') ?>">
                            <?= $pos ?>
                        </span>
                        <?php else: ?>
                        <?= $pos ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="font-medium"><?= e($c['razon_social']) ?></div>
                        <?php if ($c['email']): ?>
                        <div class="text-xs text-shalom-muted"><?= e($c['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="font-mono text-sm"><?= e($c['identificacion']) ?></td>
                    <td class="text-center">
                        <span class="badge badge-info"><?= $c['total_facturas'] ?></span>
                    </td>
                    <td class="text-right font-semibold"><?= currency($c['total_facturado']) ?></td>
                    <td class="text-right"><?= currency($c['ticket_promedio']) ?></td>
                    <td class="text-sm"><?= format_date($c['primera_compra']) ?></td>
                    <td class="text-sm"><?= format_date($c['ultima_compra']) ?></td>
                    <td class="text-center">
                        <?php
                        $dias = (int)$c['dias_sin_comprar'];
                        $badgeClass = $dias <= 30 ? 'badge-success' : ($dias <= 60 ? 'badge-warning' : 'badge-danger');
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $dias ?> días</span>
                    </td>
                </tr>
                <?php $pos++; endforeach; ?>
                <?php if (empty($reporte['ranking'])): ?>
                <tr>
                    <td colspan="9" class="text-center text-shalom-muted py-8">
                        No hay datos para el período seleccionado
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de evolución mensual
    const datosEvolucion = <?= json_encode($reporte['evolucion_mensual']) ?>;

    if (datosEvolucion.length > 0) {
        const ctxEvolucion = document.getElementById('chartEvolucion').getContext('2d');
        new Chart(ctxEvolucion, {
            type: 'line',
            data: {
                labels: datosEvolucion.map(d => {
                    const [año, mes] = d.mes.split('-');
                    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    return meses[parseInt(mes) - 1] + ' ' + año.slice(2);
                }),
                datasets: [{
                    label: 'Clientes Activos',
                    data: datosEvolucion.map(d => parseInt(d.clientes_activos)),
                    borderColor: '#1e4d39',
                    backgroundColor: 'rgba(30, 77, 57, 0.1)',
                    fill: true,
                    tension: 0.4
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
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // Gráfico de frecuencia
    const frecuencia = <?= json_encode($reporte['frecuencia']) ?>;
    const labels = {
        'unica': 'Compra única',
        'ocasional': 'Ocasional (2-3)',
        'frecuente': 'Frecuente (4-6)',
        'muy_frecuente': 'Muy frecuente (7+)'
    };
    const colores = ['#ef4444', '#f59e0b', '#22c55e', '#1e4d39'];

    const ctxFrecuencia = document.getElementById('chartFrecuencia').getContext('2d');
    new Chart(ctxFrecuencia, {
        type: 'doughnut',
        data: {
            labels: Object.keys(frecuencia).map(k => labels[k]),
            datasets: [{
                data: Object.values(frecuencia).map(f => f.clientes),
                backgroundColor: colores,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 12 }
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
