<?php
/**
 * SHALOM FACTURA - Dashboard Principal
 */

require_once __DIR__ . '/bootstrap.php';

// Verificar autenticación
if (!auth()->check()) {
    redirect(url('login.php'));
}

$db = db();
$empresaId = auth()->empresaId();
$userId = auth()->id();

// Obtener estadísticas del mes actual
$mesActual = date('Y-m-01');
$hoy = date('Y-m-d');

// Total facturado del mes
$totalMes = $db->query("
    SELECT COALESCE(SUM(total), 0) 
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND fecha_emision >= :mes 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId, ':mes' => $mesActual]);

// Total mes anterior (para comparar)
$mesAnterior = date('Y-m-01', strtotime('-1 month'));
$totalMesAnterior = $db->query("
    SELECT COALESCE(SUM(total), 0) 
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND fecha_emision >= :mes_inicio 
    AND fecha_emision < :mes_fin 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId, ':mes_inicio' => $mesAnterior, ':mes_fin' => $mesActual]);

$variacionMes = $totalMesAnterior > 0 ? (($totalMes - $totalMesAnterior) / $totalMesAnterior) * 100 : 0;

// Cantidad de facturas del mes
$cantidadFacturas = $db->query("
    SELECT COUNT(*) 
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND fecha_emision >= :mes 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId, ':mes' => $mesActual]);

// Total pendiente de cobro
$totalPendiente = $db->query("
    SELECT COALESCE(SUM(total), 0) 
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND estado_pago IN ('pendiente', 'parcial') 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId]);

// Facturas vencidas
$facturasVencidas = $db->query("
    SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND estado_pago IN ('pendiente', 'parcial') 
    AND fecha_vencimiento < :hoy 
    AND deleted_at IS NULL
")->fetch([':empresa_id' => $empresaId, ':hoy' => $hoy]);

// Asegurar valores por defecto
$facturasVencidas = $facturasVencidas ?: ['cantidad' => 0, 'monto' => 0];

// Total clientes activos
$totalClientes = $db->query("
    SELECT COUNT(*) 
    FROM clientes 
    WHERE empresa_id = :empresa_id 
    AND estado = 'activo' 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId]);

// Últimas 5 facturas
$ultimasFacturas = $db->query("
    SELECT f.*, c.razon_social as cliente_nombre,
           CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero_documento
    FROM facturas f
    JOIN clientes c ON f.cliente_id = c.id
    JOIN establecimientos e ON f.establecimiento_id = e.id
    JOIN puntos_emision pe ON f.punto_emision_id = pe.id
    WHERE f.empresa_id = :empresa_id AND f.deleted_at IS NULL
    ORDER BY f.created_at DESC
    LIMIT 5
")->fetchAll([':empresa_id' => $empresaId]);

// Facturas pendientes de autorización SRI
$pendientesSri = $db->query("
    SELECT COUNT(*) 
    FROM facturas 
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND estado_sri NOT IN ('autorizado') 
    AND deleted_at IS NULL
")->fetchColumn([':empresa_id' => $empresaId]);

// Ventas por día (últimos 7 días)
$ventasPorDia = $db->query("
    SELECT DATE(fecha_emision) as fecha, COALESCE(SUM(total), 0) as total
    FROM facturas
    WHERE empresa_id = :empresa_id 
    AND estado = 'emitida' 
    AND fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND deleted_at IS NULL
    GROUP BY DATE(fecha_emision)
    ORDER BY fecha
")->fetchAll([':empresa_id' => $empresaId]);

// Top 5 clientes del mes
$topClientes = $db->query("
    SELECT c.razon_social, COALESCE(SUM(f.total), 0) as total, COUNT(f.id) as cantidad
    FROM facturas f
    JOIN clientes c ON f.cliente_id = c.id
    WHERE f.empresa_id = :empresa_id 
    AND f.estado = 'emitida' 
    AND f.fecha_emision >= :mes 
    AND f.deleted_at IS NULL
    GROUP BY c.id
    ORDER BY total DESC
    LIMIT 5
")->fetchAll([':empresa_id' => $empresaId, ':mes' => $mesActual]);

// Variables para el layout
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$breadcrumbs = [];

ob_start();
?>

<!-- Saludo y fecha -->
<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-shalom-primary">
            ¡Hola, <?= e(auth()->user()['nombre']) ?>!
        </h1>
        <p class="text-shalom-muted">
            <?php
            // Formato de fecha en español sin usar strftime (deprecado en PHP 8.1)
            $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $dia = $dias[(int)date('w')];
            $mes = $meses[(int)date('n') - 1];
            echo ucfirst($dia) . ', ' . date('d') . ' de ' . $mes . ' de ' . date('Y');
            ?>
        </p>
    </div>
    
    <?php if (auth()->can('facturas.crear')): ?>
    <a href="<?= url('facturas/crear.php') ?>" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nueva Factura
    </a>
    <?php endif; ?>
</div>

<!-- Alertas importantes -->
<?php if ($pendientesSri > 0): ?>
<div class="alert alert-warning mb-6">
    <i data-lucide="alert-triangle" class="alert-icon"></i>
    <div class="alert-content">
        <strong>Atención:</strong> Tiene <?= $pendientesSri ?> factura(s) pendiente(s) de autorización del SRI.
        <a href="<?= url('facturas/?estado_sri=pendiente') ?>" class="underline ml-2">Ver facturas</a>
    </div>
</div>
<?php endif; ?>

<?php if ($facturasVencidas['cantidad'] > 0): ?>
<div class="alert alert-error mb-6">
    <i data-lucide="clock" class="alert-icon"></i>
    <div class="alert-content">
        <strong>Facturas vencidas:</strong> Tiene <?= $facturasVencidas['cantidad'] ?> factura(s) vencida(s) por un total de <?= currency($facturasVencidas['monto']) ?>.
        <a href="<?= url('facturas/?estado_pago=vencido') ?>" class="underline ml-2">Ver facturas</a>
    </div>
</div>
<?php endif; ?>

<!-- Estadísticas principales -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="trending-up" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Facturado este mes</div>
            <div class="stat-value"><?= currency($totalMes) ?></div>
            <div class="stat-change <?= $variacionMes >= 0 ? 'positive' : 'negative' ?>">
                <?= $variacionMes >= 0 ? '+' : '' ?><?= number_format($variacionMes, 1) ?>% vs mes anterior
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="receipt" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Facturas emitidas</div>
            <div class="stat-value"><?= number_format($cantidadFacturas) ?></div>
            <div class="stat-change text-shalom-muted">Este mes</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Por cobrar</div>
            <div class="stat-value"><?= currency($totalPendiente) ?></div>
            <div class="stat-change text-shalom-muted">Pendiente de pago</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon gold">
            <i data-lucide="users" class="w-6 h-6"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Clientes activos</div>
            <div class="stat-value"><?= number_format($totalClientes) ?></div>
            <div class="stat-change text-shalom-muted">Total registrados</div>
        </div>
    </div>
</div>

<!-- Gráficos y tablas -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Gráfico de ventas -->
    <div class="lg:col-span-2">
        <div class="card h-full">
            <div class="card-header">
                <h3 class="card-title">Ventas de los últimos 7 días</h3>
            </div>
            <div class="card-body">
                <canvas id="chartVentas" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top clientes -->
    <div class="card h-full">
        <div class="card-header">
            <h3 class="card-title">Top Clientes del Mes</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topClientes)): ?>
            <div class="p-6 text-center text-shalom-muted">
                <i data-lucide="users" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                <p>Sin datos este mes</p>
            </div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($topClientes as $index => $cliente): ?>
                <div class="flex items-center gap-3 p-4">
                    <div class="w-8 h-8 rounded-full bg-shalom-accent/30 flex items-center justify-center text-sm font-bold text-shalom-primary">
                        <?= $index + 1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium truncate"><?= e($cliente['razon_social']) ?></div>
                        <div class="text-xs text-shalom-muted"><?= $cliente['cantidad'] ?> facturas</div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-shalom-primary"><?= currency($cliente['total']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Últimas facturas -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Últimas Facturas</h3>
        <a href="<?= url('facturas/') ?>" class="text-sm text-shalom-primary hover:underline">Ver todas</a>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimasFacturas)): ?>
                <tr>
                    <td colspan="7" class="text-center py-8">
                        <div class="text-shalom-muted">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                            <p>No hay facturas registradas</p>
                            <?php if (auth()->can('facturas.crear')): ?>
                            <a href="<?= url('facturas/crear.php') ?>" class="btn btn-primary mt-4">
                                Crear primera factura
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($ultimasFacturas as $factura): ?>
                    <tr>
                        <td>
                            <span class="font-medium"><?= e($factura['numero_documento']) ?></span>
                        </td>
                        <td><?= format_date($factura['fecha_emision']) ?></td>
                        <td><?= e($factura['cliente_nombre']) ?></td>
                        <td class="font-semibold"><?= currency($factura['total']) ?></td>
                        <td>
                            <?php
                            $estadoClass = match($factura['estado']) {
                                'borrador' => 'badge-muted',
                                'emitida' => 'badge-success',
                                'anulada' => 'badge-danger',
                                default => 'badge-muted'
                            };
                            ?>
                            <span class="badge <?= $estadoClass ?>"><?= ucfirst($factura['estado']) ?></span>
                        </td>
                        <td>
                            <?php
                            $pagoClass = match($factura['estado_pago']) {
                                'pagado' => 'badge-success',
                                'parcial' => 'badge-info',
                                'vencido' => 'badge-danger',
                                default => 'badge-warning'
                            };
                            ?>
                            <span class="badge <?= $pagoClass ?>"><?= ucfirst($factura['estado_pago']) ?></span>
                        </td>
                        <td>
                            <a href="<?= url('facturas/ver.php?id=' . $factura['id']) ?>" class="btn btn-icon btn-sm btn-secondary">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos para el gráfico
    const ventasData = <?= json_encode($ventasPorDia) ?>;
    
    // Llenar días faltantes
    const labels = [];
    const values = [];
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        
        labels.push(date.toLocaleDateString('es-EC', { weekday: 'short', day: 'numeric' }));
        
        const found = ventasData.find(v => v.fecha === dateStr);
        values.push(found ? parseFloat(found.total) : 0);
    }
    
    // Crear gráfico
    const ctx = document.getElementById('chartVentas').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ventas',
                data: values,
                backgroundColor: 'rgba(30, 77, 57, 0.8)',
                borderColor: '#1e4d39',
                borderWidth: 1,
                borderRadius: 4
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
                            return '$ ' + value;
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