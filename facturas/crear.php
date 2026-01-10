<?php
/**
 * SHALOM FACTURA - Crear Factura con Handsontable
 *
 * FIXES incluidos:
 *  - Dropdown de clientes “portalizado” (no se corta por overflow del card)
 *  - Modal de servicios SIEMPRE arriba (z-index alto)
 *  - Default Forma de Pago: "Otros con utilización del sistema financiero"
 *  - Default Cantidad = 1 (editable)
 *  - Re-cálculo IVA/Totales SIEMPRE al agregar/editar servicios (incluye el bug: “segunda fila no calcula hasta otra acción”)
 *  - Si agregas el MISMO servicio nuevamente: incrementa cantidad (en vez de crear fila duplicada)
 *  - Handsontable defensivo: espera a que la librería esté cargada para evitar “Handsontable is not defined”
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if (!auth()->check()) {
    redirect(url('login.php'));
}

if (!auth()->can('facturas.crear')) {
    flash('error', 'No tiene permisos para crear facturas');
    redirect(url('facturas/'));
}

$db = db();
$empresaId = auth()->empresaId();
$empresa = auth()->empresa();

// Establecimientos
$establecimientos = $db->select(
    'establecimientos',
    ['*'],
    'empresa_id = :empresa_id AND activo = 1',
    [':empresa_id' => $empresaId]
);

// Puntos de emisión
$puntosEmision = $db->query("
    SELECT pe.*, e.codigo as establecimiento_codigo, e.nombre as establecimiento_nombre
    FROM puntos_emision pe
    JOIN establecimientos e ON pe.establecimiento_id = e.id
    WHERE e.empresa_id = :empresa_id AND pe.activo = 1
")->fetchAll([':empresa_id' => $empresaId]);

// Impuestos IVA activos
$impuestos = $db->select('cat_impuestos', ['*'], 'activo = 1 AND tipo = "IVA"', [], 'porcentaje DESC');

// Formas de pago
$formasPago = $db->select('cat_formas_pago', ['*'], 'activo = 1');

// Monedas
$monedas = $db->select('cat_monedas', ['*'], 'activo = 1');

// Cotización precargada (si aplica)
$cotizacionId = $_GET['cotizacion_id'] ?? null;
$cotizacionData = null;
if ($cotizacionId) {
    $cotizacionData = $db->query("
        SELECT c.*, cl.razon_social as cliente_nombre, cl.identificacion as cliente_identificacion
        FROM cotizaciones c
        JOIN clientes cl ON c.cliente_id = cl.id
        WHERE c.id = :id AND c.empresa_id = :empresa_id AND c.estado = 'aceptada'
    ")->fetch([':id' => $cotizacionId, ':empresa_id' => $empresaId]);
}

// ---------- Default forma de pago por nombre (robusto) ----------
function shalom_norm_text($s) {
    $s = (string)$s;
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
    ];
    $s = strtr($s, $map);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

$defaultFormaPagoId = null;
$targetFp = shalom_norm_text('Otros con utilización del sistema financiero');

foreach ($formasPago as $fp) {
    $nombre = shalom_norm_text($fp['nombre'] ?? '');
    if ($nombre === $targetFp) {
        $defaultFormaPagoId = $fp['id'];
        break;
    }
}
if (!$defaultFormaPagoId) {
    foreach ($formasPago as $fp) {
        $nombre = shalom_norm_text($fp['nombre'] ?? '');
        if (str_contains($nombre, 'otros') && str_contains($nombre, 'sistema financiero')) {
            $defaultFormaPagoId = $fp['id'];
            break;
        }
    }
}
if (!$defaultFormaPagoId && !empty($formasPago)) {
    $defaultFormaPagoId = $formasPago[0]['id'];
}

// Layout
$pageTitle = 'Nueva Factura';
$currentPage = 'facturas';
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => url('facturas/')],
    ['title' => 'Nueva Factura']
];

ob_start();
?>

<style>
/* Modal por encima de clones/headers/dropdowns de Handsontable */
#modalBuscarServicio { z-index: 200000 !important; }
#modalBuscarServicio * { z-index: inherit; }

/* Dropdown de clientes (portal en body) por encima de todo */
#clienteResultados { z-index: 199999 !important; }
</style>

<form id="facturaForm" data-validate>
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Principal -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Datos del Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="user" class="w-5 h-5 inline mr-2"></i>
                        Datos del Cliente
                    </h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="form-label required">Cliente</label>

                            <div class="relative" id="clienteBuscarWrap">
                                <input type="hidden" name="cliente_id" id="clienteId" required>
                                <input type="text" id="clienteBuscar" class="form-control"
                                       placeholder="Buscar por nombre, RUC o cédula..." autocomplete="off">
                            </div>

                            <!-- Dropdown portalizado (se mueve a <body> con JS) -->
                            <div id="clienteResultados"
                                 class="fixed bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-64 overflow-y-auto">
                            </div>
                        </div>

                        <div id="clienteInfo" class="md:col-span-2 hidden">
                            <div class="bg-shalom-secondary rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <span class="text-shalom-muted">Identificación:</span>
                                        <div class="font-medium" id="clienteIdentificacion">-</div>
                                    </div>
                                    <div>
                                        <span class="text-shalom-muted">Email:</span>
                                        <div class="font-medium" id="clienteEmail">-</div>
                                    </div>
                                    <div>
                                        <span class="text-shalom-muted">Teléfono:</span>
                                        <div class="font-medium" id="clienteTelefono">-</div>
                                    </div>
                                    <div class="md:col-span-3">
                                        <span class="text-shalom-muted">Dirección:</span>
                                        <div class="font-medium" id="clienteDireccion">-</div>
                                    </div>
                                </div>
                                <button type="button" class="mt-3 text-sm text-shalom-primary hover:underline" onclick="limpiarCliente()">
                                    <i data-lucide="x" class="w-3 h-3 inline"></i> Cambiar cliente
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Detalle -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="list" class="w-5 h-5 inline mr-2"></i>
                        Detalle de la Factura
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
                    <div id="detalleFactura" class="w-full"></div>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="info" class="w-5 h-5 inline mr-2"></i>
                        Información Adicional
                    </h3>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="agregarInfoAdicional()">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="infoAdicionalContainer" class="space-y-2"></div>
                    <p class="text-sm text-shalom-muted mt-2">Agregue información adicional que aparecerá en el comprobante (máximo 15 campos)</p>
                </div>
            </div>

        </div>

        <!-- Columna lateral -->
        <div class="space-y-6">

            <!-- Datos del comprobante -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Datos del Comprobante</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label required">Establecimiento</label>
                        <select name="establecimiento_id" id="establecimientoId" class="form-control" required onchange="cargarPuntosEmision()">
                            <option value="">Seleccione...</option>
                            <?php foreach ($establecimientos as $est): ?>
                                <option value="<?= $est['id'] ?>" data-codigo="<?= $est['codigo'] ?>">
                                    <?= e($est['codigo'] . ' - ' . $est['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label required">Punto de Emisión</label>
                        <select name="punto_emision_id" id="puntoEmisionId" class="form-control" required>
                            <option value="">Seleccione establecimiento primero</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label required">Fecha de Emisión</label>
                        <input type="date" name="fecha_emision" id="fechaEmision" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="date" name="fecha_vencimiento" id="fechaVencimiento" class="form-control">
                    </div>

                    <div>
                        <label class="form-label">Guía de Remisión</label>
                        <input type="text" name="guia_remision" class="form-control" placeholder="000-000-000000000">
                    </div>
                </div>
            </div>

            <!-- Forma de pago -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Forma de Pago</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label required">Forma de Pago</label>
                        <select name="forma_pago_id" id="formaPagoId" class="form-control" required>
                            <?php foreach ($formasPago as $fp): ?>
                                <option value="<?= $fp['id'] ?>" <?= ($fp['id'] == $defaultFormaPagoId ? 'selected' : '') ?>>
                                    <?= e($fp['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Plazo (días)</label>
                        <input type="number" name="plazo" id="plazo" class="form-control" value="0" min="0">
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
                        <span class="text-white/80">Subtotal Sin Impuestos:</span>
                        <span class="font-semibold" id="subtotalSinImpuestos">$ 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-white/80">Total Descuento:</span>
                        <span class="font-semibold" id="totalDescuento">$ 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-white/80">Subtotal IVA 15%:</span>
                        <span class="font-semibold" id="subtotalIva15">$ 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-white/80">Subtotal IVA 0%:</span>
                        <span class="font-semibold" id="subtotalIva0">$ 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-white/80">IVA 15%:</span>
                        <span class="font-semibold" id="totalIva">$ 0.00</span>
                    </div>
                    <hr class="border-white/20">
                    <div class="flex justify-between text-lg">
                        <span>TOTAL:</span>
                        <span class="font-bold" id="totalFactura">$ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="space-y-3">
                <button type="submit" name="accion" value="guardar" class="btn btn-primary w-full">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Guardar Borrador
                </button>
                <button type="submit" name="accion" value="emitir" class="btn btn-success w-full">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Guardar y Emitir
                </button>
                <a href="<?= url('facturas/') ?>" class="btn btn-secondary w-full">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Cancelar
                </a>
            </div>

        </div>
    </div>
</form>

<!-- Modal Buscar Servicio -->
<div id="modalBuscarServicio" class="fixed inset-0 hidden">
    <div class="fixed inset-0 bg-black/50" onclick="cerrarModalServicio()"></div>
    <div class="fixed inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-full md:max-w-3xl bg-white rounded-lg shadow-xl flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-shalom-primary">Buscar Servicio</h3>
            <button type="button" onclick="cerrarModalServicio()" class="btn btn-icon btn-sm btn-secondary">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="text" id="buscarServicioInput" class="form-control mb-4" placeholder="Buscar por código o nombre..." oninput="filtrarServicios()">
            <div id="serviciosLista" class="max-h-96 overflow-y-auto"></div>
        </div>
    </div>
</div>

<script>
// =========================
// Globals
// =========================
let hotInstance = null;
const impuestos = <?= json_encode($impuestos) ?>;
const puntosEmision = <?= json_encode($puntosEmision) ?>;
let serviciosCache = [];

// =========================
// Utils
// =========================
function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
function norm(v) { return String(v ?? '').trim(); }
function formatCurrency(value) {
  return '$ ' + (parseFloat(value || 0)).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}
function findImpuestoByCodigoPorcentaje(codigoPorcentaje) {
  const c = norm(codigoPorcentaje || '4') || '4';
  return impuestos.find(i => norm(i.codigo_porcentaje) === c) || null;
}

// ✅ Scheduler: evita el bug “no calcula hasta otra acción” y evita recalcular 20 veces seguidas
let _recalcScheduled = false;
function scheduleRecalc() {
  if (_recalcScheduled) return;
  _recalcScheduled = true;

  // requestAnimationFrame asegura que Handsontable ya aplicó cambios internos antes de calcular
  requestAnimationFrame(() => {
    _recalcScheduled = false;
    if (hotInstance) calcularTotales();
  });
}

// Helper multi-set (compatible con varias versiones)
function setCells(changes, source) {
  if (!hotInstance || !Array.isArray(changes) || changes.length === 0) return;
  // Handsontable soporta setDataAtCell(array, source)
  hotInstance.setDataAtCell(changes, source);
}

// =========================
// Default forma de pago (JS fallback)
// =========================
function setDefaultFormaPagoByText() {
  const sel = document.getElementById('formaPagoId');
  if (!sel) return;
  const target = 'otros con utilización del sistema financiero'.toLowerCase();
  const opts = Array.from(sel.options);
  const found = opts.find(o => (o.textContent || '').trim().toLowerCase() === target);
  if (found) sel.value = found.value;
}

// =========================
// CLIENTES: Dropdown portal
// =========================
const clienteInput = document.getElementById('clienteBuscar');
const clienteResultados = document.getElementById('clienteResultados');
const clienteWrap = document.getElementById('clienteBuscarWrap');

let timeoutBusqueda = null;
let clientesResultadosCache = [];

function ensureClienteDropdownPortal() {
  if (clienteResultados && clienteResultados.parentElement !== document.body) {
    document.body.appendChild(clienteResultados);
  }
}
function posicionarResultadosCliente() {
  ensureClienteDropdownPortal();
  const rect = clienteInput.getBoundingClientRect();
  const gap = 6;
  clienteResultados.style.left = rect.left + 'px';
  clienteResultados.style.top = (rect.bottom + gap) + 'px';
  clienteResultados.style.width = rect.width + 'px';
}
function abrirResultadosCliente() {
  posicionarResultadosCliente();
  clienteResultados.classList.remove('hidden');
}
function cerrarResultadosCliente() {
  clienteResultados.classList.add('hidden');
}

clienteInput.addEventListener('input', function() {
  clearTimeout(timeoutBusqueda);
  const query = this.value.trim();
  if (query.length < 2) { cerrarResultadosCliente(); return; }

  timeoutBusqueda = setTimeout(async () => {
    try {
      const response = await ShalomApp.get(`<?= url('api/clientes/buscar.php') ?>?q=${encodeURIComponent(query)}`);
      mostrarResultadosCliente(response.data);
    } catch (e) {
      console.error('Error buscando cliente:', e);
    }
  }, 250);
});

function mostrarResultadosCliente(clientes) {
  clientesResultadosCache = Array.isArray(clientes) ? clientes : [];

  if (clientesResultadosCache.length === 0) {
    clienteResultados.innerHTML = `
      <div class="p-4 text-center text-sm text-shalom-muted">
        No se encontraron clientes
        <button type="button" class="block w-full mt-2 btn btn-sm btn-primary" onclick="abrirNuevoCliente()">
          <i data-lucide="plus" class="w-4 h-4"></i> Crear nuevo cliente
        </button>
      </div>
    `;
    abrirResultadosCliente();
    lucide?.createIcons?.();
    return;
  }

  clienteResultados.innerHTML = clientesResultadosCache.map((c, idx) => `
    <div class="p-3 hover:bg-gray-50 cursor-pointer border-b" data-cliente-index="${idx}">
      <div class="font-medium text-shalom-primary">${escapeHtml(c.razon_social || '')}</div>
      <div class="text-sm text-shalom-muted">${escapeHtml(c.identificacion || '')} - ${escapeHtml(c.email || 'Sin email')}</div>
    </div>
  `).join('');

  abrirResultadosCliente();
}

clienteResultados.addEventListener('click', (e) => {
  const item = e.target.closest('[data-cliente-index]');
  if (!item) return;
  const idx = parseInt(item.getAttribute('data-cliente-index'), 10);
  const cliente = clientesResultadosCache[idx];
  if (cliente) seleccionarCliente(cliente);
});

function seleccionarCliente(cliente) {
  document.getElementById('clienteId').value = cliente.id;
  clienteInput.value = cliente.razon_social || '';
  cerrarResultadosCliente();

  document.getElementById('clienteInfo').classList.remove('hidden');
  clienteWrap.classList.add('hidden');

  document.getElementById('clienteIdentificacion').textContent = cliente.identificacion || '-';
  document.getElementById('clienteEmail').textContent = cliente.email || '-';
  document.getElementById('clienteTelefono').textContent = cliente.telefono || '-';
  document.getElementById('clienteDireccion').textContent = cliente.direccion || '-';
}

function limpiarCliente() {
  document.getElementById('clienteId').value = '';
  clienteInput.value = '';
  document.getElementById('clienteInfo').classList.add('hidden');
  clienteWrap.classList.remove('hidden');
  cerrarResultadosCliente();
  clienteInput.focus();
}
function abrirNuevoCliente() {
  window.open('<?= url('clientes/crear.php') ?>', '_blank');
}

window.addEventListener('resize', () => {
  if (!clienteResultados.classList.contains('hidden')) posicionarResultadosCliente();
});
document.addEventListener('scroll', () => {
  if (!clienteResultados.classList.contains('hidden')) posicionarResultadosCliente();
}, true);
document.addEventListener('click', (e) => {
  if (!e.target.closest('#clienteBuscar') && !e.target.closest('#clienteResultados')) cerrarResultadosCliente();
});

// =========================
// Handsontable init (defensivo)
// =========================
document.addEventListener('DOMContentLoaded', () => {
  ensureClienteDropdownPortal();
  setDefaultFormaPagoByText();

  // Espera por si Handsontable carga tarde desde el layout/vendor
  (function waitHandsontable(tries = 80) {
    if (window.Handsontable) return initHandsontable();
    if (tries <= 0) {
      console.error('Handsontable no está disponible. Revise que el layout cargue el vendor JS/CSS.');
      ShalomApp?.toast?.('Handsontable no está cargado (vendor JS). Revise el layout.', 'error');
      return;
    }
    setTimeout(() => waitHandsontable(tries - 1), 50);
  })();
});

function initHandsontable() {
  const container = document.getElementById('detalleFactura');

  hotInstance = new Handsontable(container, {
    data: [
      ['', '', 1, 0, 0, '4', 0, 0, 0, null]
    ],
    colHeaders: ['Código', 'Descripción', 'Cantidad', 'P. Unitario', 'Desc.', '% IVA', 'Subtotal', 'IVA', 'Total', 'Servicio ID'],
    columns: [
      { data: 0, type: 'text', width: 110 },
      { data: 1, type: 'text', width: 300 },
      // ✅ editable + default 1
      { data: 2, type: 'numeric', width: 90, numericFormat: { pattern: '0.0000' } },
      { data: 3, type: 'numeric', width: 120, numericFormat: { pattern: '0.0000' } },
      { data: 4, type: 'numeric', width: 90, numericFormat: { pattern: '0.00' } },
      {
        data: 5,
        type: 'dropdown',
        width: 90,
        source: impuestos.map(i => norm(i.codigo_porcentaje)),
        allowInvalid: false
      },
      { data: 6, type: 'numeric', width: 120, numericFormat: { pattern: '0.00' }, readOnly: true },
      { data: 7, type: 'numeric', width: 90, numericFormat: { pattern: '0.00' }, readOnly: true },
      { data: 8, type: 'numeric', width: 120, numericFormat: { pattern: '0.00' }, readOnly: true },
      { data: 9, type: 'text', width: 1 }
    ],
    hiddenColumns: { columns: [9], indicators: false },
    rowHeaders: true,
    stretchH: 'all',
    height: 320,
    minRows: 1,
    minSpareRows: 0,
    contextMenu: {
      items: {
        'row_above': { name: 'Insertar fila arriba' },
        'row_below': { name: 'Insertar fila abajo' },
        'remove_row': { name: 'Eliminar fila' },
        'separator': Handsontable.plugins.ContextMenu.SEPARATOR,
        'copy': { name: 'Copiar' },
        'cut': { name: 'Cortar' }
      }
    },
    licenseKey: 'non-commercial-and-evaluation',

    // ✅ Recalcular SIEMPRE que cambie algo (incluye cambios programáticos)
    //    PERO ignorar cuando nosotros escribimos celdas calculadas.
    afterChange: function(changes, source) {
      if (!changes) return;
      if (source === 'calcular') return;
      scheduleRecalc();
    },
    afterRemoveRow: function() {
      scheduleRecalc();
    },
    afterCreateRow: function(index, amount) {
      for (let i = 0; i < amount; i++) setRowDefaults(index + i);
      scheduleRecalc();
    },
    cells: function(row, col) {
      const props = {};
      if (col === 6 || col === 7 || col === 8) {
        props.renderer = function(instance, td) {
          Handsontable.renderers.NumericRenderer.apply(this, arguments);
          td.style.backgroundColor = '#f9fafb';
          td.style.fontWeight = '500';
        };
      }
      return props;
    }
  });

  // servicios cache
  cargarServicios();

  // auto-select establecimiento si solo hay uno
  if (<?= count($establecimientos) ?> === 1) {
    document.getElementById('establecimientoId').selectedIndex = 1;
    cargarPuntosEmision();
  }

  // defaults primera fila (por seguridad)
  setRowDefaults(0);
  scheduleRecalc();
}

// Defaults para filas nuevas (sin pisar si ya hay datos)
function setRowDefaults(rowIndex) {
  if (!hotInstance) return;

  const row = hotInstance.getDataAtRow(rowIndex) || [];
  const hasDesc = norm(row[1]) !== '';
  if (hasDesc) return;

  // ✅ cantidad default 1
  const changes = [
    [rowIndex, 2, 1],
    [rowIndex, 3, 0],
    [rowIndex, 4, 0],
    [rowIndex, 5, '4'],
    [rowIndex, 9, null]
  ];
  setCells(changes, 'program');

  // celdas calculadas a 0
  setCells([
    [rowIndex, 6, 0],
    [rowIndex, 7, 0],
    [rowIndex, 8, 0],
  ], 'calcular');
}

// =========================
// Totales (en un solo setDataAtCell)
// =========================
function calcularTotales() {
  if (!hotInstance) return;

  const data = hotInstance.getData();
  let subtotalSinImpuestos = 0;
  let totalDescuento = 0;
  let subtotalIva15 = 0;
  let subtotalIva0 = 0;
  let totalIva = 0;

  const calcChanges = [];

  for (let i = 0; i < data.length; i++) {
    const row = data[i];

    const desc = norm(row[1]);
    const cantidad = parseFloat(row[2]) || 0;
    const precioUnitario = parseFloat(row[3]) || 0;
    const descuento = parseFloat(row[4]) || 0;
    const codigoIva = norm(row[5] || '4') || '4';

    if (!desc || cantidad <= 0) {
      calcChanges.push([i, 6, 0], [i, 7, 0], [i, 8, 0]);
      continue;
    }

    const impuesto = findImpuestoByCodigoPorcentaje(codigoIva);
    const porcentajeIva = impuesto ? (parseFloat(impuesto.porcentaje) || 0) : 15;

    let subtotalLinea = (cantidad * precioUnitario) - descuento;
    if (subtotalLinea < 0) subtotalLinea = 0;

    const ivaLinea = subtotalLinea * (porcentajeIva / 100);
    const totalLinea = subtotalLinea + ivaLinea;

    calcChanges.push([i, 6, subtotalLinea], [i, 7, ivaLinea], [i, 8, totalLinea]);

    subtotalSinImpuestos += subtotalLinea;
    totalDescuento += descuento;

    if (porcentajeIva > 0) {
      subtotalIva15 += subtotalLinea;
      totalIva += ivaLinea;
    } else {
      subtotalIva0 += subtotalLinea;
    }
  }

  // ✅ aplicar cambios calculados en bloque
  setCells(calcChanges, 'calcular');

  const total = subtotalSinImpuestos + totalIva;

  document.getElementById('subtotalSinImpuestos').textContent = formatCurrency(subtotalSinImpuestos);
  document.getElementById('totalDescuento').textContent = formatCurrency(totalDescuento);
  document.getElementById('subtotalIva15').textContent = formatCurrency(subtotalIva15);
  document.getElementById('subtotalIva0').textContent = formatCurrency(subtotalIva0);
  document.getElementById('totalIva').textContent = formatCurrency(totalIva);
  document.getElementById('totalFactura').textContent = formatCurrency(total);
}

// =========================
// Agregar línea
// =========================
function agregarLinea() {
  if (!hotInstance) return;
  const last = hotInstance.countRows() - 1;
  hotInstance.alter('insert_row_below', last);
  const newRow = last + 1;

  // defaults + recalcular
  setRowDefaults(newRow);
  scheduleRecalc();

  setTimeout(() => {
    hotInstance.selectCell(newRow, 2);
    hotInstance.getActiveEditor()?.beginEditing?.();
  }, 0);
}

// =========================
// Puntos de emisión
// =========================
function cargarPuntosEmision() {
  const establecimientoId = document.getElementById('establecimientoId').value;
  const select = document.getElementById('puntoEmisionId');

  select.innerHTML = '<option value="">Seleccione...</option>';
  if (!establecimientoId) return;

  const puntosFiltrados = puntosEmision.filter(pe => pe.establecimiento_id == establecimientoId);

  puntosFiltrados.forEach(pe => {
    select.innerHTML += `<option value="${pe.id}">${pe.establecimiento_codigo}-${pe.codigo} - ${pe.descripcion || 'Principal'}</option>`;
  });

  if (puntosFiltrados.length === 1) select.selectedIndex = 1;
}

// =========================
// Servicios (modal)
// =========================
async function cargarServicios() {
  try {
    const response = await ShalomApp.get('<?= url('api/servicios/listar.php') ?>');
    serviciosCache = response.data || [];
  } catch (error) {
    console.error('Error cargando servicios:', error);
  }
}

function buscarServicio() {
  cerrarResultadosCliente();
  document.getElementById('modalBuscarServicio').classList.remove('hidden');
  filtrarServicios();
  document.getElementById('buscarServicioInput').focus();
}
function cerrarModalServicio() {
  document.getElementById('modalBuscarServicio').classList.add('hidden');
}
function filtrarServicios() {
  const query = (document.getElementById('buscarServicioInput').value || '').toLowerCase();
  const container = document.getElementById('serviciosLista');

  const list = serviciosCache.filter(s =>
    (s.codigo || '').toLowerCase().includes(query) ||
    (s.nombre || '').toLowerCase().includes(query)
  );

  if (list.length === 0) {
    container.innerHTML = '<div class="p-4 text-center text-shalom-muted">No se encontraron servicios</div>';
    return;
  }

  container.innerHTML = list.map(s => `
    <div class="p-3 hover:bg-gray-50 cursor-pointer border-b flex justify-between items-center" data-servicio-id="${s.id}">
      <div>
        <div class="font-medium">${escapeHtml(s.codigo)} - ${escapeHtml(s.nombre)}</div>
        <div class="text-sm text-shalom-muted">${escapeHtml(s.categoria_nombre || 'Sin categoría')}</div>
      </div>
      <div class="text-right">
        <div class="font-semibold text-shalom-primary">${formatCurrency(s.precio_unitario)}</div>
        <div class="text-xs text-shalom-muted">${escapeHtml(s.impuesto_nombre || '')}</div>
      </div>
    </div>
  `).join('');
}

document.getElementById('serviciosLista').addEventListener('click', (e) => {
  const item = e.target.closest('[data-servicio-id]');
  if (!item) return;

  const id = item.getAttribute('data-servicio-id');
  const servicio = serviciosCache.find(x => String(x.id) === String(id));
  if (servicio) agregarServicio(servicio);
});

function findExistingRowByServicioId(servicioId) {
  const rows = hotInstance.countRows();
  for (let r = 0; r < rows; r++) {
    const row = hotInstance.getDataAtRow(r);
    const sid = row?.[9];
    const desc = norm(row?.[1]);
    if (desc && String(sid) === String(servicioId)) return r;
  }
  return null;
}
function findEmptyRow() {
  const rows = hotInstance.countRows();
  for (let r = 0; r < rows; r++) {
    const row = hotInstance.getDataAtRow(r);
    const codigo = norm(row?.[0]);
    const desc = norm(row?.[1]);
    if (!codigo && !desc) return r;
  }
  return null;
}

function agregarServicio(servicio) {
  if (!hotInstance) return;

  const servicioId = servicio.id || null;

  // ✅ si ya existe: incrementa cantidad y recalcula
  const existingRow = servicioId ? findExistingRowByServicioId(servicioId) : null;
  if (existingRow !== null) {
    const currentQty = parseFloat(hotInstance.getDataAtCell(existingRow, 2)) || 0;
    setCells([[existingRow, 2, currentQty + 1]], 'program');
    cerrarModalServicio();
    scheduleRecalc();

    setTimeout(() => {
      hotInstance.selectCell(existingRow, 2);
      hotInstance.getActiveEditor()?.beginEditing?.();
    }, 0);
    return;
  }

  // target row
  let targetRow = findEmptyRow();
  if (targetRow === null) {
    const last = hotInstance.countRows() - 1;
    hotInstance.alter('insert_row_below', last);
    targetRow = last + 1;
  }

  const ivaCode = norm(servicio.codigo_porcentaje_iva || '4') || '4';

  // ✅ IMPORTANTÍSIMO: set en bloque + scheduleRecalc (soluciona “segunda fila no calcula hasta otra acción”)
  setCells([
    [targetRow, 0, servicio.codigo || ''],
    [targetRow, 1, servicio.nombre || ''],
    [targetRow, 2, 1], // default 1
    [targetRow, 3, parseFloat(servicio.precio_unitario || 0)],
    [targetRow, 4, 0],
    [targetRow, 5, ivaCode],
    [targetRow, 9, servicioId],
  ], 'program');

  cerrarModalServicio();
  scheduleRecalc();

  setTimeout(() => {
    hotInstance.selectCell(targetRow, 2);
    hotInstance.getActiveEditor()?.beginEditing?.();
  }, 0);
}

// =========================
// Información adicional
// =========================
function agregarInfoAdicional() {
  const container = document.getElementById('infoAdicionalContainer');
  const count = container.children.length;

  if (count >= 15) {
    ShalomApp.toast('Máximo 15 campos de información adicional', 'warning');
    return;
  }

  const div = document.createElement('div');
  div.className = 'flex gap-2';
  div.innerHTML = `
    <input type="text" name="info_adicional[${count}][nombre]" class="form-control" placeholder="Nombre" required>
    <input type="text" name="info_adicional[${count}][valor]" class="form-control" placeholder="Valor" required>
    <button type="button" class="btn btn-icon btn-danger btn-sm" onclick="this.parentElement.remove()">
      <i data-lucide="trash-2" class="w-4 h-4"></i>
    </button>
  `;
  container.appendChild(div);
  lucide?.createIcons?.();
}

// =========================
// Submit
// =========================
document.getElementById('facturaForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  if (!document.getElementById('establecimientoId').value) {
    ShalomApp.toast('Debe seleccionar un establecimiento', 'error'); return;
  }
  if (!document.getElementById('puntoEmisionId').value) {
    ShalomApp.toast('Debe seleccionar un punto de emisión', 'error'); return;
  }
  if (!document.getElementById('clienteId').value) {
    ShalomApp.toast('Debe seleccionar un cliente', 'error'); return;
  }

  const detalles = hotInstance.getData().filter(row => norm(row[1]) && (parseFloat(row[2]) > 0));
  if (detalles.length === 0) {
    ShalomApp.toast('Debe agregar al menos un detalle', 'error'); return;
  }

  const accion = e.submitter?.value || 'guardar';

  const formData = {
    cliente_id: document.getElementById('clienteId').value,
    establecimiento_id: document.getElementById('establecimientoId').value,
    punto_emision_id: document.getElementById('puntoEmisionId').value,
    tipo_comprobante_id: 1,
    fecha_emision: document.getElementById('fechaEmision').value,
    fecha_vencimiento: document.getElementById('fechaVencimiento').value || null,
    detalles: detalles.map(row => {
      const codigoPorcentaje = norm(row[5] || '4') || '4';
      const impuesto = findImpuestoByCodigoPorcentaje(codigoPorcentaje);
      return {
        servicio_id: row[9] || null,
        codigo: row[0] || '',
        descripcion: row[1],
        cantidad: parseFloat(row[2]) || 1,
        precio_unitario: parseFloat(row[3]) || 0,
        descuento: parseFloat(row[4]) || 0,
        subtotal: parseFloat(row[6]) || 0,
        impuestos: [{
          impuesto_id: impuesto?.id || 4,
          codigo: '2',
          codigo_porcentaje: norm(impuesto?.codigo_porcentaje || codigoPorcentaje),
          tarifa: parseFloat(impuesto?.porcentaje) || 15,
          base_imponible: parseFloat(row[6]) || 0,
          valor: parseFloat(row[7]) || 0
        }]
      };
    }),
    formas_pago: [{
      forma_pago_id: document.getElementById('formaPagoId').value,
      total: parseFloat(document.getElementById('totalFactura').textContent.replace(/[^0-9.-]/g, '')) || 0,
      plazo: parseInt(document.getElementById('plazo').value) || 0
    }],
    info_adicional: [],
    accion: accion
  };

  ShalomApp.showLoading();

  try {
    const response = await window.fetch('<?= url('api/facturas/crear.php') ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': ShalomApp.config.csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(formData)
    });

    const data = await response.json();
    ShalomApp.hideLoading();

    if (data.success) {
      ShalomApp.toast(data.message, 'success');
      setTimeout(() => {
        window.location.href = '<?= url('facturas/ver.php') ?>?id=' + data.id;
      }, 500);
    } else {
      ShalomApp.toast(data.message || 'Error al crear la factura', 'error');
    }
  } catch (error) {
    ShalomApp.hideLoading();
    console.error('Error:', error);
    ShalomApp.toast('Error al guardar la factura: ' + error.message, 'error');
  }
});
</script>

<?php
$content = ob_get_clean();
require_once TEMPLATES_PATH . '/layouts/main.php';
