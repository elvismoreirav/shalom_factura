<?php
/**
 * SHALOM FACTURA - Layout Principal
 */

// Verificar autenticación
if (!defined('SHALOM_FACTURA')) {
    die('Acceso no permitido');
}

$auth = auth();
$user = $auth->user();
$empresa = $auth->empresa();

// Variables por defecto
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? '';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription ?: 'Sistema de Facturación Electrónica') ?>">
    <title><?= e($pageTitle) ?> - Shalom Factura</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= asset('img/logo-leaf.png') ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        shalom: {
                            primary: '#1e4d39',
                            'primary-dark': '#163a2b',
                            'primary-light': '#2a6b4f',
                            secondary: '#f9f8f4',
                            'secondary-dark': '#f0efe8',
                            accent: '#A3B7A5',
                            'accent-light': '#c4d4c6',
                            muted: '#73796F',
                            gold: '#D6C29A',
                            'gold-dark': '#c4ac7a'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    
    <!-- Handsontable -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="bg-shalom-secondary min-h-screen">
    <div class="app-layout">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Logo -->
            <div class="sidebar-header">
                <a href="<?= url('dashboard.php') ?>" class="sidebar-logo">
                    <img src="<?= asset('img/logo-leaf.png') ?>" alt="Shalom" class="h-10 w-auto brightness-0 invert">
                    <span class="text-xl font-semibold">Shalom</span>
                </a>
            </div>
            
            <!-- Navigation -->
            <nav class="sidebar-nav">
                <!-- Principal -->
                <div class="nav-section">
                    <div class="nav-section-title">Principal</div>
                    <a href="<?= url('dashboard.php') ?>" class="nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <!-- Ventas -->
                <div class="nav-section">
                    <div class="nav-section-title">Ventas</div>
                    
                    <?php if ($auth->canAny(['cotizaciones.ver', 'cotizaciones.crear'])): ?>
                    <a href="<?= url('cotizaciones/') ?>" class="nav-item <?= ($currentPage ?? '') === 'cotizaciones' ? 'active' : '' ?>">
                        <i data-lucide="file-text"></i>
                        <span>Cotizaciones</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAny(['facturas.ver', 'facturas.crear'])): ?>
                    <a href="<?= url('facturas/') ?>" class="nav-item <?= ($currentPage ?? '') === 'facturas' ? 'active' : '' ?>">
                        <i data-lucide="receipt"></i>
                        <span>Facturas</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAny(['notas_credito.ver', 'notas_credito.crear'])): ?>
                    <a href="<?= url('notas-credito/') ?>" class="nav-item <?= ($currentPage ?? '') === 'notas_credito' ? 'active' : '' ?>">
                        <i data-lucide="file-minus"></i>
                        <span>Notas de Crédito</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAny(['pagos.ver', 'pagos.crear'])): ?>
                    <a href="<?= url('pagos/') ?>" class="nav-item <?= ($currentPage ?? '') === 'pagos' ? 'active' : '' ?>">
                        <i data-lucide="credit-card"></i>
                        <span>Pagos</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Catálogos -->
                <div class="nav-section">
                    <div class="nav-section-title">Catálogos</div>
                    
                    <?php if ($auth->canAny(['clientes.ver', 'clientes.crear'])): ?>
                    <a href="<?= url('clientes/') ?>" class="nav-item <?= ($currentPage ?? '') === 'clientes' ? 'active' : '' ?>">
                        <i data-lucide="users"></i>
                        <span>Clientes</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->canAny(['servicios.ver', 'servicios.crear'])): ?>
                    <a href="<?= url('servicios/') ?>" class="nav-item <?= ($currentPage ?? '') === 'servicios' ? 'active' : '' ?>">
                        <i data-lucide="briefcase"></i>
                        <span>Servicios</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Reportes -->
                <?php if ($auth->can('reportes.ver')): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Reportes</div>
                    <a href="<?= url('reportes/') ?>" class="nav-item <?= ($currentPage ?? '') === 'reportes' ? 'active' : '' ?>">
                        <i data-lucide="bar-chart-3"></i>
                        <span>Reportes</span>
                    </a>
                    <a href="<?= url('reportes/ventas.php') ?>" class="nav-item <?= ($currentPage ?? '') === 'reportes_ventas' ? 'active' : '' ?>">
                        <i data-lucide="trending-up"></i>
                        <span>Ventas</span>
                    </a>
                    <a href="<?= url('reportes/impuestos.php') ?>" class="nav-item <?= ($currentPage ?? '') === 'reportes_impuestos' ? 'active' : '' ?>">
                        <i data-lucide="percent"></i>
                        <span>Impuestos</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Configuración -->
                <?php if ($auth->canAny(['empresa.ver', 'configuracion.ver', 'usuarios.ver'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Configuración</div>
                    
                    <?php if ($auth->can('empresa.ver')): ?>
                    <a href="<?= url('empresa/') ?>" class="nav-item <?= ($currentPage ?? '') === 'empresa' ? 'active' : '' ?>">
                        <i data-lucide="building-2"></i>
                        <span>Mi Empresa</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->can('usuarios.ver')): ?>
                    <a href="<?= url('usuarios/') ?>" class="nav-item <?= ($currentPage ?? '') === 'usuarios' ? 'active' : '' ?>">
                        <i data-lucide="user-cog"></i>
                        <span>Usuarios</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($auth->can('configuracion.ver')): ?>
                    <a href="<?= url('configuracion/') ?>" class="nav-item <?= ($currentPage ?? '') === 'configuracion' ? 'active' : '' ?>">
                        <i data-lucide="settings"></i>
                        <span>Configuración</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </nav>
            
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="flex items-center gap-3 text-sm text-white/70">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>Ambiente: <?= ($empresa['ambiente_sri'] ?? '1') === '2' ? 'Producción' : 'Pruebas' ?></span>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <!-- Mobile Menu Toggle -->
                    <button class="btn btn-icon btn-secondary lg:hidden" id="menuToggle">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    
                    <!-- Breadcrumbs -->
                    <nav class="breadcrumb hidden sm:flex">
                        <a href="<?= url('dashboard.php') ?>">
                            <i data-lucide="home" class="w-4 h-4"></i>
                        </a>
                        <?php if (!empty($breadcrumbs)): ?>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <span class="breadcrumb-separator">/</span>
                                <?php if (isset($crumb['url'])): ?>
                                    <a href="<?= $crumb['url'] ?>"><?= e($crumb['title']) ?></a>
                                <?php else: ?>
                                    <span class="text-shalom-primary font-medium"><?= e($crumb['title']) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="topbar-right">
                    <!-- Notificaciones -->
                    <div class="dropdown" id="notificationsDropdown">
                        <button class="btn btn-icon btn-secondary relative">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center" id="notificationBadge" style="display: none;">0</span>
                        </button>
                        <div class="dropdown-menu w-80">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <h4 class="font-semibold text-sm">Notificaciones</h4>
                            </div>
                            <div class="max-h-64 overflow-y-auto" id="notificationsList">
                                <div class="p-4 text-center text-sm text-shalom-muted">
                                    No hay notificaciones
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown" id="userDropdown">
                        <button class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
                            <div class="avatar bg-shalom-accent text-shalom-primary">
                                <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1) . substr($user['apellido'] ?? '', 0, 1)) ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium text-shalom-primary">
                                    <?= e($user['nombre'] . ' ' . $user['apellido']) ?>
                                </div>
                                <div class="text-xs text-shalom-muted">
                                    <?= e($user['rol_nombre'] ?? 'Usuario') ?>
                                </div>
                            </div>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-shalom-muted hidden md:block"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?= url('perfil/') ?>" class="dropdown-item">
                                <i data-lucide="user" class="w-4 h-4"></i>
                                Mi Perfil
                            </a>
                            <a href="<?= url('perfil/password.php') ?>" class="dropdown-item">
                                <i data-lucide="key" class="w-4 h-4"></i>
                                Cambiar Contraseña
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?= url('logout.php') ?>" class="dropdown-item text-red-600">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Flash Messages -->
            <?php if ($successMsg = flash('success')): ?>
            <div class="mx-6 mt-4">
                <div class="alert alert-success">
                    <i data-lucide="check-circle" class="alert-icon"></i>
                    <div class="alert-content"><?= e($successMsg) ?></div>
                    <button class="ml-auto" onclick="this.parentElement.remove()">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($errorMsg = flash('error')): ?>
            <div class="mx-6 mt-4">
                <div class="alert alert-error">
                    <i data-lucide="alert-circle" class="alert-icon"></i>
                    <div class="alert-content"><?= e($errorMsg) ?></div>
                    <button class="ml-auto" onclick="this.parentElement.remove()">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Page Content -->
            <div class="page-content">
                <?= $content ?? '' ?>
            </div>
            
            <!-- Footer -->
            <footer class="px-6 py-4 border-t border-gray-200 bg-white mt-auto">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-sm text-shalom-muted">
                    <div>
                        &copy; <?= date('Y') ?> <strong class="text-shalom-primary">Shalom</strong> - Soluciones Digitales con Propósito
                    </div>
                    <div>
                        Versión <?= APP_VERSION ?>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="text-center">
            <div class="loading-spinner w-12 h-12 border-4 mb-4"></div>
            <p class="text-shalom-primary font-medium">Procesando...</p>
        </div>
    </div>
    
    <!-- Modal Container -->
    <div id="modalContainer"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    
    <script>
        // Inicializar iconos Lucide
        lucide.createIcons();
        
        // CSRF Token para AJAX
        window.csrfToken = '<?= csrf_token() ?>';
        window.baseUrl = '<?= BASE_URL ?>';
    </script>
    
    <?php if (isset($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>
