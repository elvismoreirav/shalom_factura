<?php
/**
 * SHALOM FACTURA - Crear/Editar Cotización
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Shalom\Modules\Cotizaciones\Cotizacion;
use Shalom\Modules\Servicios\Servicio;

if (!auth()->check()) {
    redirect(url('login.php'));
}

$cotizacionModel = new Cotizacion();
$servicioModel = new Servicio();

$impuestos = $servicioModel->getImpuestosIva();

// Detectar modo edición
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$cotizacion = null;
$detalles = [];

if ($isEdit) {
    if (!auth()->can('cotizaciones.editar')) {
        flash('error', 'No tiene permisos para editar cotizaciones');
        redirect(url('cotizaciones/'));
    }
    
    $cotizacion = $cotizacionModel->getById($id);
    if (!$cotizacion) {
        flash('error', 'Cotización no encontrada');
        redirect(url('cotizaciones/'));
    }
    
    // Solo se pueden editar borradores y enviadas
    if (!in_array($cotizacion['estado'], ['borrador', 'enviada'])) {
        flash('error', 'Esta cotización no puede ser editada');
        redirect(url('cotizaciones/ver.php?id=' . $id));
    }
    
    $detalles = $cotizacionModel->getDetalles($id);
    $pageTitle = 'Editar Cotización #' . $cotizacion['numero'];
    $breadcrumbTitle = 'Editar';
} else {
    if (!auth()->can('cotizaciones.crear')) {
        flash('error', 'No tiene permisos para crear cotizaciones');
        redirect(url('cotizaciones/'));
    }
    $pageTitle = 'Nueva Cotización';
    $breadcrumbTitle = 'Nueva';
}

$currentPage = 'cotizaciones';
$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => url('cotizaciones/')],
    ['title' => $breadcrumbTitle]
];

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-shalom-primary"><?= e($pageTitle) ?></h1>
            <p class="text-shalom-muted">Complete los datos de la propuesta comercial</p>
        </div>
        <a href="<?= url('cotizaciones/') ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Volver
        </a>
    </div>
    
    <form id="cotizacionForm">
        <?= csrf_field() ?>
        <input type="hidden" id="cotizacionId" value="<?= $id ?>">
        
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
                        <div id="clienteSeleccion">
                            <div class="relative">
                                <input type="hidden" name="cliente_id" id="clienteId">
                                <input type="text" id="clienteBuscar" class="form-control" placeholder="Buscar cliente por nombre o identificación..." autocomplete="off">
                                <div id="clienteResultados" class="absolute top-full left-0 right-0 bg-white border rounded-lg shadow-lg mt-1 z-10 hidden max-h-64 overflow-y-auto"></div>
                            </div>
                        </div>
                        
                        <div id="clienteInfo" class="hidden mt-4">
                            <div class="bg-shalom-secondary rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold text-shalom-primary" id="clienteNombre"></div>
                                        <div class="text-sm text-shalom-muted" id="clienteIdentificacion"></div>
                                        <div class="text-sm text-shalom-muted" id="clienteEmail"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="cambiarCliente()">
                                        Cambiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información de la cotización -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="file-text" class="w-5 h-5 inline mr-2"></i>
                            Información
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="form-label">Asunto</label>
                                <input type="text" name="asunto" id="asunto" class="form-control" placeholder="Ej: Propuesta de servicios de consultoría">
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Introducción</label>
                                <textarea name="introduccion" id="introduccion" class="form-control" rows="3" placeholder="Texto introductorio de la cotización..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detalle -->
                <div class="card">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="card-title">
                            <i data-lucide="list" class="w-5 h-5 inline mr-2"></i>
                            Detalle de Servicios
                        </h3>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="buscarServicio()">
                                <i data-lucide="search" class="w-4 h-4"></i>
                                Buscar Servicio
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="agregarLinea()">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Agregar Línea
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="detalleContainer" style="height: 300px; overflow: auto;"></div>
                    </div>
                </div>
                
                <!-- Condiciones y notas -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Condiciones y Notas</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Condiciones</label>
                                <textarea name="condiciones" id="condiciones" class="form-control" rows="4" placeholder="Términos y condiciones de la cotización...">• Precios incluyen IVA
• Validez de la cotización: 30 días
• Forma de pago: 50% anticipo, 50% contra entrega
• Tiempo de entrega: A convenir</textarea>
                            </div>
                            <div>
                                <label class="form-label">Notas adicionales</label>
                                <textarea name="notas" id="notas" class="form-control" rows="4" placeholder="Notas internas o para el cliente..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Columna Lateral -->
            <div class="space-y-6">
                <!-- Datos de la cotización -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Datos</h3>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label required">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= $isEdit ? date('Y-m-d', strtotime($cotizacion['fecha'])) : date('Y-m-d') ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Válida hasta</label>
                            <input type="date" name="fecha_validez" id="fechaValidez" class="form-control" value="<?= $isEdit && $cotizacion['fecha_validez'] ? date('Y-m-d', strtotime($cotizacion['fecha_validez'])) : date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Resumen -->
                <div class="card bg-shalom-primary text-white">
                    <div class="card-header border-white/20">
                        <h3 class="card-title text-white">Resumen</h3>
                    </div>
                    <div class="card-body space-y-3">
                        <div class="flex justify-between">
                            <span class="text-white/80">Subtotal:</span>
                            <span class="font-medium" id="resumenSubtotal">$ 0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-white/80">Descuento:</span>
                            <span class="font-medium" id="resumenDescuento">$ 0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-white/80">Subtotal sin IVA:</span>
                            <span class="font-medium" id="resumenSubtotalSinIva">$ 0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-white/80">IVA 15%:</span>
                            <span class="font-medium" id="resumenIva">$ 0.00</span>
                        </div>
                        <hr class="border-white/20">
                        <div class="flex justify-between text-xl">
                            <span class="font-semibold">TOTAL:</span>
                            <span class="font-bold" id="resumenTotal">$ 0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="space-y-3">
                    <button type="submit" class="btn btn-success w-full" name="accion" value="guardar">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Guardar Cotización
                    </button>
                    <button type="button" class="btn btn-primary w-full" onclick="guardarYEnviar()">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Guardar y Marcar Enviada
                    </button>
                    <a href="<?= url('cotizaciones/') ?>" class="btn btn-secondary w-full">
                        Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal buscar servicio -->
<div id="modalServicio" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="cerrarModalServicio()"></div>
    <div class="fixed inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-full md:max-w-2xl bg-white rounded-lg shadow-xl flex flex-col max-h-[80vh]">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold">Buscar Servicio</h3>
            <button type="button" onclick="cerrarModalServicio()" class="btn btn-icon btn-sm btn-secondary">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-4 border-b">
            <input type="text" id="servicioFiltro" class="form-control" placeholder="Buscar..." oninput="filtrarServicios()">
        </div>
        <div class="flex-1 overflow-y-auto p-4">
            <div id="serviciosList" class="space-y-2"></div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>

<script>
let hot;
let serviciosCache = [];

const ivaOptions = <?= json_encode(array_map(fn($i) => ['id' => $i['id'], 'nombre' => $i['nombre'], 'porcentaje' => $i['porcentaje']], $impuestos)) ?>;

// Datos de cotización para modo edición
const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
const cotizacionData = <?= $isEdit ? json_encode([
    'id' => $cotizacion['id'],
    'cliente_id' => $cotizacion['cliente_id'],
    'cliente_nombre' => $cotizacion['cliente_nombre'],
    'cliente_identificacion' => $cotizacion['cliente_identificacion'],
    'cliente_email' => $cotizacion['cliente_email'] ?? '',
    'asunto' => $cotizacion['asunto'] ?? '',
    'introduccion' => $cotizacion['introduccion'] ?? '',
    'condiciones' => $cotizacion['condiciones'] ?? '',
    'notas' => $cotizacion['notas'] ?? '',
    'detalles' => array_map(fn($d) => [
        'servicio_id' => $d['servicio_id'],
        'codigo' => $d['codigo'] ?? '',
        'descripcion' => $d['descripcion'],
        'cantidad' => (float)$d['cantidad'],
        'precio_unitario' => (float)$d['precio_unitario'],
        'descuento' => (float)($d['descuento'] ?? 0),
        'porcentaje_iva' => (float)($d['porcentaje_iva'] ?? 15)
    ], $detalles)
]) : 'null' ?>;

document.addEventListener('DOMContentLoaded', function() {
    initHandsontable();
    cargarServicios();
    initBusquedaCliente();
    
    // Cargar datos si estamos editando
    if (isEdit && cotizacionData) {
        cargarDatosCotizacion();
    }
});

function cargarDatosCotizacion() {
    // Cargar cliente
    document.getElementById('clienteId').value = cotizacionData.cliente_id;
    document.getElementById('clienteNombre').textContent = cotizacionData.cliente_nombre;
    document.getElementById('clienteIdentificacion').textContent = cotizacionData.cliente_identificacion;
    document.getElementById('clienteEmail').textContent = cotizacionData.cliente_email;
    document.getElementById('clienteSeleccion').classList.add('hidden');
    document.getElementById('clienteInfo').classList.remove('hidden');
    
    // Cargar campos de texto
    document.getElementById('asunto').value = cotizacionData.asunto || '';
    document.getElementById('introduccion').value = cotizacionData.introduccion || '';
    document.getElementById('condiciones').value = cotizacionData.condiciones || '';
    document.getElementById('notas').value = cotizacionData.notas || '';
    
    // Cargar detalles en Handsontable
    if (cotizacionData.detalles && cotizacionData.detalles.length > 0) {
        const tableData = cotizacionData.detalles.map(d => {
            const cantidad = d.cantidad;
            const precioUnit = d.precio_unitario;
            const descuento = d.descuento;
            const porcIva = d.porcentaje_iva;
            const subtotal = (cantidad * precioUnit) - descuento;
            const iva = subtotal * (porcIva / 100);
            const total = subtotal + iva;
            
            return [
                d.codigo,
                d.descripcion,
                cantidad,
                precioUnit,
                descuento,
                porcIva,
                subtotal,
                iva,
                total,
                d.servicio_id
            ];
        });
        
        // Agregar fila vacía al final
        tableData.push(['', '', 1, 0, 0, 15, 0, 0, 0, null]);
        
        hot.loadData(tableData);
        calcularTotales();
    }
}

function initHandsontable() {
    const container = document.getElementById('detalleContainer');
    
    hot = new Handsontable(container, {
        data: [['', '', 1, 0, 0, 15, 0, 0, 0, null]],
        colHeaders: ['Código', 'Descripción', 'Cantidad', 'P.Unitario', 'Descuento', '%IVA', 'Subtotal', 'IVA', 'Total', 'ID'],
        columns: [
            { type: 'text', width: 80 },
            { type: 'text', width: 200 },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 70 },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 90 },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 80 },
            { type: 'dropdown', source: [0, 15], width: 60 },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 90, readOnly: true, className: 'htDimmed' },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 70, readOnly: true, className: 'htDimmed' },
            { type: 'numeric', numericFormat: { pattern: '0.00' }, width: 90, readOnly: true, className: 'htDimmed' },
            { type: 'numeric', width: 1, className: 'htHidden' }
        ],
        hiddenColumns: { columns: [9] },
        minSpareRows: 1,
        rowHeaders: true,
        contextMenu: true,
        licenseKey: 'non-commercial-and-evaluation',
        afterChange: function(changes, source) {
            if (source === 'loadData') return;
            calcularTotales();
        }
    });
}

function calcularTotales() {
    const data = hot.getData();
    let subtotal = 0, totalDescuento = 0, totalIva = 0;
    
    data.forEach((row, index) => {
        const cantidad = parseFloat(row[2]) || 0;
        const precioUnit = parseFloat(row[3]) || 0;
        const descuento = parseFloat(row[4]) || 0;
        const porcIva = parseFloat(row[5]) || 0;
        
        if (cantidad > 0 && precioUnit > 0) {
            const subtotalLinea = (cantidad * precioUnit) - descuento;
            const ivaLinea = subtotalLinea * (porcIva / 100);
            const totalLinea = subtotalLinea + ivaLinea;
            
            hot.setDataAtCell(index, 6, subtotalLinea, 'calc');
            hot.setDataAtCell(index, 7, ivaLinea, 'calc');
            hot.setDataAtCell(index, 8, totalLinea, 'calc');
            
            subtotal += cantidad * precioUnit;
            totalDescuento += descuento;
            totalIva += ivaLinea;
        }
    });
    
    const subtotalSinIva = subtotal - totalDescuento;
    const total = subtotalSinIva + totalIva;
    
    document.getElementById('resumenSubtotal').textContent = ShalomApp.formatCurrency(subtotal);
    document.getElementById('resumenDescuento').textContent = ShalomApp.formatCurrency(totalDescuento);
    document.getElementById('resumenSubtotalSinIva').textContent = ShalomApp.formatCurrency(subtotalSinIva);
    document.getElementById('resumenIva').textContent = ShalomApp.formatCurrency(totalIva);
    document.getElementById('resumenTotal').textContent = ShalomApp.formatCurrency(total);
}

function agregarLinea() {
    hot.alter('insert_row_below', hot.countRows() - 1);
}

async function cargarServicios() {
    try {
        const response = await ShalomApp.get('<?= url('api/servicios/listar.php') ?>');
        serviciosCache = response.data || [];
    } catch (error) {
        console.error('Error cargando servicios:', error);
    }
}

function buscarServicio() {
    renderizarServicios();
    document.getElementById('modalServicio').classList.remove('hidden');
    document.getElementById('servicioFiltro').focus();
    lucide.createIcons();
}

function cerrarModalServicio() {
    document.getElementById('modalServicio').classList.add('hidden');
    document.getElementById('servicioFiltro').value = '';
}

function renderizarServicios(filtro = '') {
    const lista = document.getElementById('serviciosList');
    const serviciosFiltrados = serviciosCache.filter(s => 
        !filtro || 
        s.nombre.toLowerCase().includes(filtro.toLowerCase()) ||
        s.codigo.toLowerCase().includes(filtro.toLowerCase())
    );
    
    lista.innerHTML = serviciosFiltrados.map(s => `
        <div class="p-3 border rounded-lg hover:bg-gray-50 cursor-pointer" onclick='agregarServicio(${JSON.stringify(s)})'>
            <div class="flex justify-between">
                <div>
                    <span class="font-mono text-sm text-shalom-muted">${s.codigo}</span>
                    <span class="font-medium ml-2">${s.nombre}</span>
                </div>
                <span class="font-semibold">${ShalomApp.formatCurrency(s.precio_unitario)}</span>
            </div>
            <div class="text-xs text-shalom-muted mt-1">${s.categoria_nombre || 'Sin categoría'} | IVA ${s.porcentaje_iva}%</div>
        </div>
    `).join('');
}

function filtrarServicios() {
    const filtro = document.getElementById('servicioFiltro').value;
    renderizarServicios(filtro);
}

function agregarServicio(servicio) {
    const lastRow = hot.countRows() - 1;
    hot.setDataAtRowProp(lastRow, 0, servicio.codigo);
    hot.setDataAtRowProp(lastRow, 1, servicio.nombre);
    hot.setDataAtRowProp(lastRow, 2, 1);
    hot.setDataAtRowProp(lastRow, 3, parseFloat(servicio.precio_unitario));
    hot.setDataAtRowProp(lastRow, 4, 0);
    hot.setDataAtRowProp(lastRow, 5, parseFloat(servicio.porcentaje_iva));
    hot.setDataAtRowProp(lastRow, 9, servicio.id);
    
    cerrarModalServicio();
    calcularTotales();
}

// Búsqueda de cliente
function initBusquedaCliente() {
    let timeout = null;
    document.getElementById('clienteBuscar').addEventListener('input', function() {
        clearTimeout(timeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            document.getElementById('clienteResultados').classList.add('hidden');
            return;
        }
        
        timeout = setTimeout(async () => {
            try {
                const response = await ShalomApp.get(`<?= url('api/clientes/buscar.php') ?>?q=${encodeURIComponent(query)}`);
                mostrarResultadosCliente(response.data);
            } catch (error) {
                console.error('Error buscando cliente:', error);
            }
        }, 300);
    });
}

function mostrarResultadosCliente(clientes) {
    const container = document.getElementById('clienteResultados');
    
    if (!clientes || clientes.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-sm text-shalom-muted">No se encontraron clientes</div>';
    } else {
        container.innerHTML = clientes.map(c => `
            <div class="p-3 hover:bg-gray-50 cursor-pointer border-b" onclick='seleccionarCliente(${JSON.stringify(c)})'>
                <div class="font-medium">${c.razon_social}</div>
                <div class="text-sm text-shalom-muted">${c.identificacion} ${c.email ? '| ' + c.email : ''}</div>
            </div>
        `).join('');
    }
    
    container.classList.remove('hidden');
}

function seleccionarCliente(cliente) {
    document.getElementById('clienteId').value = cliente.id;
    document.getElementById('clienteNombre').textContent = cliente.razon_social;
    document.getElementById('clienteIdentificacion').textContent = cliente.identificacion;
    document.getElementById('clienteEmail').textContent = cliente.email || '';
    
    document.getElementById('clienteBuscar').value = '';
    document.getElementById('clienteResultados').classList.add('hidden');
    document.getElementById('clienteSeleccion').classList.add('hidden');
    document.getElementById('clienteInfo').classList.remove('hidden');
}

function cambiarCliente() {
    document.getElementById('clienteId').value = '';
    document.getElementById('clienteSeleccion').classList.remove('hidden');
    document.getElementById('clienteInfo').classList.add('hidden');
    document.getElementById('clienteBuscar').focus();
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('#clienteBuscar') && !e.target.closest('#clienteResultados')) {
        document.getElementById('clienteResultados').classList.add('hidden');
    }
});

// Enviar formulario
document.getElementById('cotizacionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    await guardarCotizacion('guardar');
});

async function guardarYEnviar() {
    await guardarCotizacion('enviar');
}

async function guardarCotizacion(accion) {
    const clienteId = document.getElementById('clienteId').value;
    
    if (!clienteId) {
        ShalomApp.toast('Debe seleccionar un cliente', 'error');
        return;
    }
    
    // Preparar detalles
    const data = hot.getData();
    const detalles = [];
    
    for (const row of data) {
        if (row[1] && parseFloat(row[2]) > 0) {
            const cantidad = parseFloat(row[2]) || 0;
            const precioUnit = parseFloat(row[3]) || 0;
            const descuento = parseFloat(row[4]) || 0;
            const porcIva = parseFloat(row[5]) || 0;
            const subtotal = (cantidad * precioUnit) - descuento;
            const valorIva = subtotal * (porcIva / 100);
            
            detalles.push({
                servicio_id: row[9] || null,
                codigo: row[0] || '',
                descripcion: row[1],
                cantidad: cantidad,
                precio_unitario: precioUnit,
                descuento: descuento,
                subtotal: subtotal,
                porcentaje_iva: porcIva,
                valor_iva: valorIva,
                total: subtotal + valorIva
            });
        }
    }
    
    if (detalles.length === 0) {
        ShalomApp.toast('Debe agregar al menos un detalle', 'error');
        return;
    }
    
    const cotizacionId = document.getElementById('cotizacionId').value;
    
    const cotizacion = {
        id: cotizacionId || null,
        cliente_id: clienteId,
        fecha: document.getElementById('fecha').value,
        fecha_validez: document.getElementById('fechaValidez').value,
        asunto: document.getElementById('asunto').value,
        introduccion: document.getElementById('introduccion').value,
        condiciones: document.getElementById('condiciones').value,
        notas: document.getElementById('notas').value,
        detalles: detalles,
        accion: accion
    };
    
    ShalomApp.showLoading();
    
    try {
        const response = await ShalomApp.post('<?= url('api/cotizaciones/crear.php') ?>', cotizacion);
        
        if (response.success) {
            ShalomApp.toast(response.message, 'success');
            setTimeout(() => {
                window.location.href = '<?= url('cotizaciones/ver.php') ?>?id=' + (response.id || cotizacionId);
            }, 1000);
        } else {
            ShalomApp.toast(response.message, 'error');
        }
    } catch (error) {
        ShalomApp.toast('Error al guardar la cotización', 'error');
    }
    
    ShalomApp.hideLoading();
}
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';