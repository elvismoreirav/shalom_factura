<?php
/**
 * SHALOM FACTURA - Reporte de Cuentas por Cobrar
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
$reporte = $reporteModel->getCuentasPorCobrar();

// Exportar a Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="cuentas_por_cobrar_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Cuentas por Cobrar</h2>";
    echo "<p>Fecha de reporte: " . date('d/m/Y H:i') . "</p>";
    
    echo "<table border='1'>";
    echo "<tr><th>Cliente</th><th>RUC/Cédula</th><th>Email</th><th>Teléfono</th><th>Facturas</th><th>Total Facturado</th><th>Saldo Pendiente</th><th>Vencidas</th></tr>";
    
    foreach ($reporte['por_cliente'] as $c) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($c['razon_social']) . "</td>";
        echo "<td>" . $c['identificacion'] . "</td>";
        echo "<td>" . $c['email'] . "</td>";
        echo "<td>" . $c['telefono'] . "</td>";
        echo "<td style='text-align:center'>" . $c['facturas_pendientes'] . "</td>";
        echo "<td style='text-align:right'>" . number_format($c['total_facturado'], 2) . "</td>";
        echo "<td style='text-align:right'>" . number_format($c['saldo_pendiente'], 2) . "</td>";
        echo "<td style='text-align:center'>" . $c['facturas_vencidas'] . "</td>";
        echo "</tr>";
    }
    
    echo "<tr style='font-weight:bold'>";
    echo "<td colspan='6'>TOTAL</td>";
    echo "<td style='text-align:right'>" . number_format($reporte['resumen']['total_pendiente'], 2) . "</td>";
    echo "<td></td>";
    echo "</tr>";
    
    echo "</table></body></html>";
    exit;
}

$pageTitle = 'Cuentas por Cobrar';
$currentPage = 'reportes_cxc';
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => url('reportes/')],
    ['title' => 'Cuentas por Cobrar']
];

ob_start();
?>

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">Cuentas por Cobrar</h1>
        <p class="text-shalom-muted">Cartera de clientes al <?= date('d/m/Y') ?></p>
    </div>
    
    <div class="flex gap-2">
        <a href="?export=excel" class="btn btn-success">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
            Exportar Excel
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Imprimir
        </button>
    </div>
</div>

<!-- Resumen -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Pendiente</div>
            <div class="stat-value"><?= currency($reporte['resumen']['total_pendiente']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i data-lucide="alert-circle" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Vencido</div>
            <div class="stat-value"><?= currency($reporte['resumen']['total_vencido']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="users" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Clientes con Saldo</div>
            <div class="stat-value"><?= $reporte['resumen']['cantidad_clientes'] ?></div>
        </div>
    </div>
</div>

<!-- Antigüedad de Cartera -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Antigüedad de Cartera</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <?php 
            $rangos = ['vigente' => 'Vigente', '1-30' => '1-30 días', '31-60' => '31-60 días', '61-90' => '61-90 días', '90+' => 'Más de 90'];
            $colores = ['vigente' => 'bg-green-100 text-green-800', '1-30' => 'bg-yellow-100 text-yellow-800', '31-60' => 'bg-orange-100 text-orange-800', '61-90' => 'bg-red-100 text-red-800', '90+' => 'bg-red-200 text-red-900'];
            
            // Crear array indexado por rango
            $antiguedadIndexada = [];
            foreach ($reporte['antiguedad'] as $a) {
                $antiguedadIndexada[$a['rango']] = $a;
            }
            
            foreach ($rangos as $key => $label):
                $data = $antiguedadIndexada[$key] ?? ['cantidad' => 0, 'saldo' => 0];
            ?>
            <div class="p-4 rounded-lg <?= $colores[$key] ?>">
                <div class="text-sm font-medium"><?= $label ?></div>
                <div class="text-2xl font-bold"><?= currency($data['saldo']) ?></div>
                <div class="text-xs"><?= $data['cantidad'] ?> facturas</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Detalle por Cliente -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalle por Cliente</h3>
        <div class="text-sm text-shalom-muted"><?= count($reporte['por_cliente']) ?> clientes</div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th class="text-center">Facturas</th>
                    <th class="text-right">Total Facturado</th>
                    <th class="text-right">Saldo Pendiente</th>
                    <th>Próximo Vto.</th>
                    <th class="text-center">Vencidas</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reporte['por_cliente'])): ?>
                <tr>
                    <td colspan="8" class="text-center py-8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i data-lucide="check-circle" class="w-8 h-8"></i>
                            </div>
                            <div class="empty-state-title">Sin cuentas por cobrar</div>
                            <div class="empty-state-text">Todas las facturas están pagadas</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reporte['por_cliente'] as $cliente): ?>
                    <tr>
                        <td>
                            <div class="font-medium text-shalom-primary"><?= e($cliente['razon_social']) ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($cliente['identificacion']) ?></div>
                        </td>
                        <td>
                            <div class="text-sm"><?= e($cliente['email'] ?: '-') ?></div>
                            <div class="text-xs text-shalom-muted"><?= e($cliente['telefono'] ?: '') ?></div>
                        </td>
                        <td class="text-center"><?= $cliente['facturas_pendientes'] ?></td>
                        <td class="text-right"><?= currency($cliente['total_facturado']) ?></td>
                        <td class="text-right font-semibold text-shalom-primary"><?= currency($cliente['saldo_pendiente']) ?></td>
                        <td>
                            <?php if ($cliente['proxima_vencimiento']): ?>
                                <?php 
                                $vencida = strtotime($cliente['proxima_vencimiento']) < time();
                                ?>
                                <span class="<?= $vencida ? 'text-red-600 font-medium' : '' ?>">
                                    <?= format_date($cliente['proxima_vencimiento']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($cliente['facturas_vencidas'] > 0): ?>
                            <span class="badge badge-danger"><?= $cliente['facturas_vencidas'] ?></span>
                            <?php else: ?>
                            <span class="text-shalom-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= url('clientes/ver.php?id=' . $cliente['cliente_id']) ?>" class="btn btn-icon btn-sm btn-secondary" title="Ver cliente">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <?php if (auth()->can('pagos.crear')): ?>
                                <a href="<?= url('pagos/registrar.php?cliente_id=' . $cliente['cliente_id']) ?>" class="btn btn-icon btn-sm btn-success" title="Registrar pago">
                                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($reporte['por_cliente'])): ?>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td colspan="4">TOTAL</td>
                    <td class="text-right"><?= currency($reporte['resumen']['total_pendiente']) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .btn, form { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
