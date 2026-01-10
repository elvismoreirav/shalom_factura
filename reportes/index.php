<?php
/**
 * SHALOM FACTURA - Reportes
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
$dashboard = $reporteModel->getDashboardEjecutivo();

$pageTitle = 'Reportes';
$currentPage = 'reportes';
$breadcrumbs = [['title' => 'Reportes']];

ob_start();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-shalom-primary">Reportes y Estadísticas</h1>
    <p class="text-shalom-muted">Análisis completo de su negocio</p>
</div>

<!-- Dashboard Ejecutivo -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="trending-up" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Ventas del Mes</div>
            <div class="stat-value"><?= currency($dashboard['ventas_mes']['total']) ?></div>
            <div class="stat-change <?= $dashboard['ventas_mes']['variacion'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $dashboard['ventas_mes']['variacion'] >= 0 ? '+' : '' ?><?= $dashboard['ventas_mes']['variacion'] ?>% vs mes anterior
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="credit-card" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Cobros del Mes</div>
            <div class="stat-value"><?= currency($dashboard['cobros_mes']) ?></div>
            <div class="stat-change text-shalom-muted"><?= $dashboard['ventas_mes']['cantidad'] ?> facturas emitidas</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pendiente de Cobro</div>
            <div class="stat-value"><?= currency($dashboard['por_cobrar']) ?></div>
            <div class="stat-change <?= $dashboard['vencido'] > 0 ? 'negative' : 'text-shalom-muted' ?>">
                <?= currency($dashboard['vencido']) ?> vencido
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gold">
            <i data-lucide="file-text" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Cotizaciones Pendientes</div>
            <div class="stat-value"><?= $dashboard['cotizaciones_pendientes']['cantidad'] ?></div>
            <div class="stat-change text-shalom-muted">Valor: <?= currency($dashboard['cotizaciones_pendientes']['valor']) ?></div>
        </div>
    </div>
</div>

<!-- Gráfico de ventas 12 meses -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Ventas Últimos 12 Meses</h3>
    </div>
    <div class="card-body">
        <canvas id="chartVentas12Meses" height="100"></canvas>
    </div>
</div>

<!-- Reportes Disponibles -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Reporte de Ventas -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="bar-chart-3" class="w-6 h-6 text-green-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Reporte de Ventas</h3>
                    <p class="text-sm text-shalom-muted">Análisis detallado de facturación</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                Resumen de ventas por período, cliente y producto. Incluye gráficos y exportación a Excel.
            </p>
            <a href="<?= url('reportes/ventas.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar Reporte
            </a>
        </div>
    </div>
    
    <!-- Reporte de Impuestos -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="percent" class="w-6 h-6 text-blue-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Reporte de Impuestos</h3>
                    <p class="text-sm text-shalom-muted">Para declaración mensual</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                IVA cobrado, retenciones recibidas y cálculo de impuesto a pagar. Listo para su declaración.
            </p>
            <a href="<?= url('reportes/impuestos.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar Reporte
            </a>
        </div>
    </div>
    
    <!-- Cuentas por Cobrar -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Cuentas por Cobrar</h3>
                    <p class="text-sm text-shalom-muted">Cartera de clientes</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                Saldos pendientes por cliente, antigüedad de cartera y facturas vencidas.
            </p>
            <a href="<?= url('reportes/cuentas-por-cobrar.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar Reporte
            </a>
        </div>
    </div>
    
    <!-- Productos más vendidos -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="trophy" class="w-6 h-6 text-purple-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Productos más Vendidos</h3>
                    <p class="text-sm text-shalom-muted">Top de servicios/productos</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                Ranking de productos y servicios por cantidad vendida y monto facturado.
            </p>
            <a href="<?= url('reportes/productos.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar Reporte
            </a>
        </div>
    </div>
    
    <!-- Clientes -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="users" class="w-6 h-6 text-teal-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Análisis de Clientes</h3>
                    <p class="text-sm text-shalom-muted">Comportamiento de compra</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                Ranking de clientes, frecuencia de compra y ticket promedio.
            </p>
            <a href="<?= url('reportes/clientes.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar Reporte
            </a>
        </div>
    </div>
    
    <!-- ATS -->
    <div class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="file-code" class="w-6 h-6 text-red-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-shalom-primary">Anexo ATS</h3>
                    <p class="text-sm text-shalom-muted">Para el SRI</p>
                </div>
            </div>
            <p class="text-sm text-shalom-muted mb-4">
                Generación del archivo XML del Anexo Transaccional Simplificado.
            </p>
            <a href="<?= url('reportes/ats.php') ?>" class="btn btn-primary w-full">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Generar ATS
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const datos = <?= json_encode($dashboard['ventas_12_meses']) ?>;
    
    const labels = datos.map(d => {
        const [año, mes] = d.mes.split('-');
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return meses[parseInt(mes) - 1] + ' ' + año.slice(2);
    });
    
    const values = datos.map(d => parseFloat(d.total));
    
    const ctx = document.getElementById('chartVentas12Meses').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ventas',
                data: values,
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
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$ ' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$ ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
