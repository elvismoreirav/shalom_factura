<?php
/**
 * SHALOM FACTURA - Registrar Pago
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Pagos\Pago;

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('pagos.crear')) {
    flash('error', 'No tiene permisos para registrar pagos');
    redirect(url('pagos/'));
}

$pagoModel = new Pago();
$formasPago = $pagoModel->getFormasPago();

// Pre-cargar factura si viene de una
$facturaId = $_GET['factura_id'] ?? null;
$facturaPreseleccionada = null;
$clientePreseleccionado = null;

if ($facturaId) {
    $db = db();
    $facturaPreseleccionada = $db->query("
        SELECT 
            f.id,
            f.cliente_id,
            CONCAT(e.codigo, '-', pe.codigo, '-', LPAD(f.secuencial, 9, '0')) as numero,
            f.fecha_emision,
            f.total,
            f.total - COALESCE((
                SELECT SUM(pf.monto) 
                FROM pago_facturas pf 
                JOIN pagos p ON pf.pago_id = p.id 
                WHERE pf.factura_id = f.id AND p.estado = 'confirmado'
            ), 0) as saldo_pendiente,
            cl.razon_social as cliente_nombre,
            cl.identificacion as cliente_identificacion
        FROM facturas f
        JOIN establecimientos e ON f.establecimiento_id = e.id
        JOIN puntos_emision pe ON f.punto_emision_id = pe.id
        JOIN clientes cl ON f.cliente_id = cl.id
        WHERE f.id = :id AND f.empresa_id = :empresa_id
    ")->fetch([':id' => $facturaId, ':empresa_id' => auth()->empresaId()]);
    
    if ($facturaPreseleccionada) {
        $clientePreseleccionado = [
            'id' => $facturaPreseleccionada['cliente_id'],
            'razon_social' => $facturaPreseleccionada['cliente_nombre'],
            'identificacion' => $facturaPreseleccionada['cliente_identificacion']
        ];
    }
}

$pageTitle = 'Registrar Pago';
$currentPage = 'pagos';
$breadcrumbs = [
    ['title' => 'Pagos', 'url' => url('pagos/')],
    ['title' => 'Registrar']
];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-shalom-primary">Registrar Pago</h1>
            <p class="text-shalom-muted">Registre un cobro y aplíquelo a facturas pendientes</p>
        </div>
        <a href="<?= url('pagos/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
    </div>
    
    <form id="pagoForm">
        <?= csrf_field() ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna Principal -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Cliente -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="user" class="w-5 h-5 inline mr-2"></i>
                            Cliente
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="clienteSeleccion" class="<?= $clientePreseleccionado ? 'hidden' : '' ?>">
                            <label class="form-label required">Seleccionar Cliente</label>
                            <div class="relative">
                                <input type="hidden" name="cliente_id" id="clienteId" value="<?= $clientePreseleccionado['id'] ?? '' ?>">
                                <input type="text" id="clienteBuscar" class="form-control" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                                <div id="clienteResultados" class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg mt-1 z-10 hidden max-h-64 overflow-y-auto"></div>
                            </div>
                        </div>
                        
                        <div id="clienteInfo" class="<?= $clientePreseleccionado ? '' : 'hidden' ?>">
                            <div class="bg-shalom-secondary rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-shalom-primary" id="clienteNombre"><?= e($clientePreseleccionado['razon_social'] ?? '') ?></div>
                                        <div class="text-sm text-shalom-muted" id="clienteIdentificacion"><?= e($clientePreseleccionado['identificacion'] ?? '') ?></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="cambiarCliente()">
                                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                        Cambiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Facturas Pendientes -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="file-text" class="w-5 h-5 inline mr-2"></i>
                            Facturas Pendientes
                        </h3>
                        <div id="totalPendiente" class="text-sm font-semibold text-shalom-primary">
                            Total pendiente: $ 0.00
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="facturasContainer" class="divide-y">
                            <?php if (!$clientePreseleccionado): ?>
                            <div class="p-8 text-center text-shalom-muted">
                                <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                                <p>Seleccione un cliente para ver sus facturas pendientes</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Columna Lateral -->
            <div class="space-y-6">
                <!-- Datos del Pago -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Datos del Pago</h3>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label required">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div>
                            <label class="form-label required">Forma de Pago</label>
                            <select name="forma_pago_id" id="formaPagoId" class="form-control" required onchange="toggleCamposExtra()">
                                <?php foreach ($formasPago as $fp): ?>
                                <option value="<?= $fp['id'] ?>" data-codigo="<?= $fp['codigo'] ?>">
                                    <?= e($fp['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="campoReferencia">
                            <label class="form-label">Referencia / Nº Transacción</label>
                            <input type="text" name="referencia" id="referencia" class="form-control" placeholder="Número de referencia">
                        </div>
                        
                        <div id="campoBanco" class="hidden">
                            <label class="form-label">Banco</label>
                            <input type="text" name="banco" id="banco" class="form-control">
                        </div>
                        
                        <div id="campoCheque" class="hidden">
                            <label class="form-label">Número de Cheque</label>
                            <input type="text" name="numero_cheque" id="numeroCheque" class="form-control">
                        </div>
                        
                        <div>
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" id="observaciones" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen -->
                <div class="card bg-shalom-primary text-white">
                    <div class="card-header border-white/20">
                        <h3 class="card-title text-white">Resumen del Pago</h3>
                    </div>
                    <div class="card-body space-y-3">
                        <div class="flex justify-between">
                            <span class="text-white/80">Facturas seleccionadas:</span>
                            <span class="font-semibold" id="cantidadFacturas">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-white/80">Total a aplicar:</span>
                            <span class="font-semibold" id="totalAplicar">$ 0.00</span>
                        </div>
                        <hr class="border-white/20">
                        <div class="flex justify-between text-lg">
                            <span>MONTO DEL PAGO:</span>
                            <span class="font-bold" id="montoPago">$ 0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="space-y-3">
                    <button type="submit" class="btn btn-success w-full" id="btnGuardar" disabled>
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Registrar Pago
                    </button>
                    <a href="<?= url('pagos/') ?>" class="btn btn-secondary w-full">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let facturasPendientes = [];
let facturasSeleccionadas = [];

// Búsqueda de cliente
let timeoutBusqueda = null;
document.getElementById('clienteBuscar').addEventListener('input', function() {
    clearTimeout(timeoutBusqueda);
    const query = this.value.trim();
    
    if (query.length < 2) {
        document.getElementById('clienteResultados').classList.add('hidden');
        return;
    }
    
    timeoutBusqueda = setTimeout(async () => {
        try {
            const response = await ShalomApp.get(`<?= url('api/clientes/buscar.php') ?>?q=${encodeURIComponent(query)}`);
            mostrarResultadosCliente(response.data);
        } catch (error) {
            console.error('Error buscando cliente:', error);
        }
    }, 300);
});

function mostrarResultadosCliente(clientes) {
    const container = document.getElementById('clienteResultados');
    
    if (!clientes || clientes.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-sm text-shalom-muted">No se encontraron clientes</div>';
        container.classList.remove('hidden');
        return;
    }
    
    container.innerHTML = clientes.map(cliente => `
        <div class="p-3 hover:bg-gray-50 cursor-pointer border-b" onclick='seleccionarCliente(${JSON.stringify(cliente)})'>
            <div class="font-medium text-shalom-primary">${cliente.razon_social}</div>
            <div class="text-sm text-shalom-muted">${cliente.identificacion}</div>
        </div>
    `).join('');
    
    container.classList.remove('hidden');
}

function seleccionarCliente(cliente) {
    document.getElementById('clienteId').value = cliente.id;
    document.getElementById('clienteBuscar').value = '';
    document.getElementById('clienteResultados').classList.add('hidden');
    document.getElementById('clienteSeleccion').classList.add('hidden');
    document.getElementById('clienteInfo').classList.remove('hidden');
    document.getElementById('clienteNombre').textContent = cliente.razon_social;
    document.getElementById('clienteIdentificacion').textContent = cliente.identificacion;
    
    cargarFacturasPendientes(cliente.id);
}

function cambiarCliente() {
    document.getElementById('clienteId').value = '';
    document.getElementById('clienteSeleccion').classList.remove('hidden');
    document.getElementById('clienteInfo').classList.add('hidden');
    document.getElementById('clienteBuscar').focus();
    
    facturasPendientes = [];
    facturasSeleccionadas = [];
    renderizarFacturas();
    actualizarResumen();
}

async function cargarFacturasPendientes(clienteId) {
    try {
        const response = await ShalomApp.get(`<?= url('api/pagos/facturas-pendientes.php') ?>?cliente_id=${clienteId}`);
        facturasPendientes = response.data || [];
        renderizarFacturas();
    } catch (error) {
        console.error('Error cargando facturas:', error);
        ShalomApp.toast('Error al cargar facturas pendientes', 'error');
    }
}

function renderizarFacturas() {
    const container = document.getElementById('facturasContainer');
    
    if (facturasPendientes.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-shalom-muted">
                <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-2 opacity-30"></i>
                <p>Este cliente no tiene facturas pendientes</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    let totalPendiente = 0;
    
    container.innerHTML = facturasPendientes.map(f => {
        totalPendiente += parseFloat(f.saldo_pendiente);
        const vencida = f.fecha_vencimiento && new Date(f.fecha_vencimiento) < new Date();
        
        return `
            <div class="p-4 hover:bg-gray-50">
                <div class="flex items-start gap-4">
                    <div class="flex items-center h-6">
                        <input type="checkbox" 
                               id="factura_${f.id}" 
                               class="factura-check w-5 h-5 rounded border-gray-300 text-shalom-primary focus:ring-shalom-primary"
                               data-id="${f.id}"
                               data-saldo="${f.saldo_pendiente}"
                               onchange="toggleFactura(${f.id}, ${f.saldo_pendiente})">
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-medium">${f.numero}</span>
                            ${vencida ? '<span class="badge badge-danger text-xs">Vencida</span>' : ''}
                        </div>
                        <div class="text-sm text-shalom-muted">
                            Emitida: ${ShalomApp.formatDate(f.fecha_emision)}
                            ${f.fecha_vencimiento ? ` | Vence: ${ShalomApp.formatDate(f.fecha_vencimiento)}` : ''}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-shalom-muted">Total: ${ShalomApp.formatCurrency(f.total)}</div>
                        <div class="font-semibold text-shalom-primary">Pendiente: ${ShalomApp.formatCurrency(f.saldo_pendiente)}</div>
                    </div>
                    <div class="w-32">
                        <input type="number" 
                               id="monto_${f.id}" 
                               class="form-control text-right monto-input" 
                               step="0.01" 
                               min="0" 
                               max="${f.saldo_pendiente}"
                               disabled
                               placeholder="0.00"
                               onchange="actualizarMonto(${f.id})">
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('totalPendiente').innerHTML = `Total pendiente: ${ShalomApp.formatCurrency(totalPendiente)}`;
    
    lucide.createIcons();
}

function toggleFactura(id, saldo) {
    const checkbox = document.getElementById(`factura_${id}`);
    const montoInput = document.getElementById(`monto_${id}`);
    
    if (checkbox.checked) {
        montoInput.disabled = false;
        montoInput.value = saldo.toFixed(2);
        montoInput.focus();
    } else {
        montoInput.disabled = true;
        montoInput.value = '';
    }
    
    actualizarResumen();
}

function actualizarMonto(id) {
    const saldo = parseFloat(document.getElementById(`factura_${id}`).dataset.saldo);
    const montoInput = document.getElementById(`monto_${id}`);
    let monto = parseFloat(montoInput.value) || 0;
    
    if (monto > saldo) {
        monto = saldo;
        montoInput.value = monto.toFixed(2);
    }
    
    if (monto < 0) {
        monto = 0;
        montoInput.value = '';
    }
    
    actualizarResumen();
}

function actualizarResumen() {
    let totalAplicar = 0;
    let cantidadFacturas = 0;
    
    facturasSeleccionadas = [];
    
    document.querySelectorAll('.factura-check:checked').forEach(checkbox => {
        const id = parseInt(checkbox.dataset.id);
        const montoInput = document.getElementById(`monto_${id}`);
        const monto = parseFloat(montoInput.value) || 0;
        
        if (monto > 0) {
            totalAplicar += monto;
            cantidadFacturas++;
            facturasSeleccionadas.push({ factura_id: id, monto: monto });
        }
    });
    
    document.getElementById('cantidadFacturas').textContent = cantidadFacturas;
    document.getElementById('totalAplicar').textContent = ShalomApp.formatCurrency(totalAplicar);
    document.getElementById('montoPago').textContent = ShalomApp.formatCurrency(totalAplicar);
    
    document.getElementById('btnGuardar').disabled = cantidadFacturas === 0 || totalAplicar <= 0;
}

function toggleCamposExtra() {
    const select = document.getElementById('formaPagoId');
    const codigo = select.options[select.selectedIndex].dataset.codigo;
    
    // Mostrar/ocultar campos según forma de pago
    document.getElementById('campoBanco').classList.toggle('hidden', !['02', '19'].includes(codigo));
    document.getElementById('campoCheque').classList.toggle('hidden', codigo !== '02');
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('#clienteBuscar') && !e.target.closest('#clienteResultados')) {
        document.getElementById('clienteResultados').classList.add('hidden');
    }
});

// Enviar formulario
document.getElementById('pagoForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (facturasSeleccionadas.length === 0) {
        ShalomApp.toast('Debe seleccionar al menos una factura', 'error');
        return;
    }
    
    let totalMonto = 0;
    facturasSeleccionadas.forEach(f => totalMonto += f.monto);
    
    const data = {
        cliente_id: document.getElementById('clienteId').value,
        fecha: document.getElementById('fecha').value,
        forma_pago_id: document.getElementById('formaPagoId').value,
        monto: totalMonto,
        referencia: document.getElementById('referencia').value,
        banco: document.getElementById('banco').value,
        numero_cheque: document.getElementById('numeroCheque').value,
        observaciones: document.getElementById('observaciones').value,
        facturas: facturasSeleccionadas
    };
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/pagos/crear.php') ?>', data);
        
        if (response.success) {
            ShalomApp.toast(response.message, 'success');
            setTimeout(() => {
                window.location.href = '<?= url('pagos/ver.php') ?>?id=' + response.id;
            }, 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al registrar el pago', 'error');
    }
    
    ShalomApp.hideLoading();
});

// Cargar facturas si hay cliente preseleccionado
<?php if ($clientePreseleccionado): ?>
document.addEventListener('DOMContentLoaded', function() {
    cargarFacturasPendientes(<?= $clientePreseleccionado['id'] ?>);
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
