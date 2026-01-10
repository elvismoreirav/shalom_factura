<?php
/**
 * SHALOM FACTURA - Login
 */

require_once __DIR__ . '/bootstrap.php';

// Si ya está autenticado, redirigir al dashboard
if (auth()->check()) {
    redirect(url('dashboard.php'));
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese su email y contraseña';
    } else {
        $result = auth()->attempt($email, $password, $remember);
        
        if ($result['success']) {
            $redirectTo = $_GET['redirect'] ?? url('dashboard.php');
            redirect($redirectTo);
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Shalom Factura</title>
    
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
                            muted: '#73796F',
                            gold: '#D6C29A'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <style>
        .login-bg {
            background: linear-gradient(135deg, #1e4d39 0%, #163a2b 50%, #0f2a1f 100%);
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }
        .leaf-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30 5 Q45 15 40 35 Q35 50 30 55 Q25 50 20 35 Q15 15 30 5' fill='rgba(255,255,255,0.03)'/%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="min-h-screen login-bg leaf-pattern flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="<?= asset('img/logo-leaf.png') ?>" alt="Shalom" class="h-16 mx-auto mb-4 brightness-0 invert">
            <h1 class="text-3xl font-bold text-white">Shalom Factura</h1>
            <p class="text-white/70 mt-2">Sistema de Facturación Electrónica</p>
        </div>
        
        <!-- Card de Login -->
        <div class="login-card rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <h2 class="text-xl font-semibold text-shalom-primary">Iniciar Sesión</h2>
                <p class="text-shalom-muted text-sm mt-1">Ingrese sus credenciales para continuar</p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= e($error) ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-5">
                <?= csrf_field() ?>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-shalom-primary mb-1.5">
                        Correo Electrónico
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-shalom-muted">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </span>
                        <input type="email" name="email" id="email" required autofocus
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-shalom-primary/20 focus:border-shalom-primary transition-colors"
                               placeholder="ejemplo@correo.com">
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-shalom-primary mb-1.5">
                        Contraseña
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-shalom-muted">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </span>
                        <input type="password" name="password" id="password" required
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-shalom-primary/20 focus:border-shalom-primary transition-colors"
                               placeholder="••••••••">
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-shalom-primary focus:ring-shalom-primary">
                        <span class="text-sm text-shalom-muted">Recordarme</span>
                    </label>
                    <a href="<?= url('recuperar-password.php') ?>" class="text-sm text-shalom-primary hover:underline">
                        ¿Olvidó su contraseña?
                    </a>
                </div>
                
                <button type="submit" class="w-full bg-shalom-primary hover:bg-shalom-primary-light text-white font-medium py-3 px-4 rounded-lg transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-shalom-primary">
                    Iniciar Sesión
                </button>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-white/60 text-sm">
            <p>&copy; <?= date('Y') ?> Shalom - Soluciones Digitales con Propósito</p>
            <p class="mt-1">Versión <?= APP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
