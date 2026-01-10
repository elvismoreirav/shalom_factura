<?php
/**
 * SHALOM FACTURA - Script de Instalación
 * Ejecutar este archivo para configurar el sistema inicialmente
 */

// Iniciar sesión ANTES de cualquier operación
session_start();

// Verificar si ya está instalado
if (file_exists(__DIR__ . '/config/.installed')) {
    die('El sistema ya está instalado. Elimine el archivo config/.installed para reinstalar.');
}

// Crear carpetas necesarias si no existen
$folders = ['storage', 'storage/firmas', 'storage/xml', 'storage/pdf', 'storage/temp', 'logs', 'uploads'];
foreach ($folders as $folder) {
    $path = __DIR__ . '/' . $folder;
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = false;

// Procesar instalación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)$_POST['step'];
    
    switch ($step) {
        case 1: // Verificar requisitos
            $step = 2;
            break;
            
        case 2: // Configuración de base de datos
            $dbHost = trim($_POST['db_host'] ?? 'localhost');
            $dbPort = trim($_POST['db_port'] ?? '3306');
            $dbName = trim($_POST['db_name'] ?? 'shalom_factura');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';
            
            // Probar conexión
            try {
                $pdo = new PDO(
                    "mysql:host=$dbHost;port=$dbPort",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Crear base de datos si no existe
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$dbName`");
                
                // Importar esquema
                $schema = file_get_contents(__DIR__ . '/database/schema.sql');
                $pdo->exec($schema);
                
                // Guardar configuración temporal
                $_SESSION['install'] = [
                    'db_host' => $dbHost,
                    'db_port' => $dbPort,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_pass' => $dbPass
                ];
                
                $step = 3;
            } catch (PDOException $e) {
                $errors[] = 'Error de conexión: ' . $e->getMessage();
            }
            break;
            
        case 3: // Datos de empresa
            $installData = $_SESSION['install'] ?? [];
            
            if (empty($installData)) {
                $errors[] = 'Sesión expirada. Reinicie la instalación.';
                $step = 1;
                break;
            }
            
            $ruc = trim($_POST['ruc'] ?? '');
            $razonSocial = trim($_POST['razon_social'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            
            if (strlen($ruc) !== 13) {
                $errors[] = 'El RUC debe tener 13 dígitos';
            }
            if (empty($razonSocial)) {
                $errors[] = 'La razón social es requerida';
            }
            
            if (empty($errors)) {
                $installData['empresa'] = [
                    'ruc' => $ruc,
                    'razon_social' => $razonSocial,
                    'direccion' => $direccion,
                    'email' => $email,
                    'telefono' => $telefono
                ];
                $_SESSION['install'] = $installData;
                $step = 4;
            }
            break;
            
        case 4: // Usuario administrador
            $installData = $_SESSION['install'] ?? [];
            
            if (empty($installData)) {
                $errors[] = 'Sesión expirada. Reinicie la instalación.';
                $step = 1;
                break;
            }
            
            $adminNombre = trim($_POST['admin_nombre'] ?? '');
            $adminApellido = trim($_POST['admin_apellido'] ?? '');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
            
            if (empty($adminNombre) || empty($adminApellido)) {
                $errors[] = 'El nombre y apellido son requeridos';
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El email no es válido';
            }
            if (strlen($adminPassword) < 8) {
                $errors[] = 'La contraseña debe tener al menos 8 caracteres';
            }
            if ($adminPassword !== $adminPasswordConfirm) {
                $errors[] = 'Las contraseñas no coinciden';
            }
            
            if (empty($errors)) {
                try {
                    // Conectar a la base de datos
                    $pdo = new PDO(
                        "mysql:host={$installData['db_host']};port={$installData['db_port']};dbname={$installData['db_name']};charset=utf8mb4",
                        $installData['db_user'],
                        $installData['db_pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    // Generar UUID
                    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                    
                    // Crear empresa
                    $stmt = $pdo->prepare("
                        INSERT INTO empresas (uuid, ruc, razon_social, direccion_matriz, email, telefono, ambiente_sri, estado)
                        VALUES (:uuid, :ruc, :razon_social, :direccion, :email, :telefono, '1', 'activo')
                    ");
                    $stmt->execute([
                        ':uuid' => $uuid,
                        ':ruc' => $installData['empresa']['ruc'],
                        ':razon_social' => $installData['empresa']['razon_social'],
                        ':direccion' => $installData['empresa']['direccion'],
                        ':email' => $installData['empresa']['email'],
                        ':telefono' => $installData['empresa']['telefono']
                    ]);
                    $empresaId = $pdo->lastInsertId();
                    
                    // Crear establecimiento matriz
                    $stmt = $pdo->prepare("
                        INSERT INTO establecimientos (empresa_id, codigo, nombre, direccion, es_matriz, activo)
                        VALUES (:empresa_id, '001', 'Matriz', :direccion, 1, 1)
                    ");
                    $stmt->execute([
                        ':empresa_id' => $empresaId,
                        ':direccion' => $installData['empresa']['direccion']
                    ]);
                    $establecimientoId = $pdo->lastInsertId();
                    
                    // Crear punto de emisión
                    $stmt = $pdo->prepare("
                        INSERT INTO puntos_emision (establecimiento_id, codigo, descripcion, activo)
                        VALUES (:establecimiento_id, '001', 'Principal', 1)
                    ");
                    $stmt->execute([':establecimiento_id' => $establecimientoId]);
                    
                    // Crear usuario administrador
                    $uuid2 = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (uuid, empresa_id, rol_id, email, password, nombre, apellido, estado, email_verificado_at)
                        VALUES (:uuid, :empresa_id, 1, :email, :password, :nombre, :apellido, 'activo', NOW())
                    ");
                    $stmt->execute([
                        ':uuid' => $uuid2,
                        ':empresa_id' => $empresaId,
                        ':email' => $adminEmail,
                        ':password' => password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                        ':nombre' => $adminNombre,
                        ':apellido' => $adminApellido
                    ]);
                    
                    // Asignar todos los permisos al rol superadmin
                    $pdo->exec("
                        INSERT INTO rol_permisos (rol_id, permiso_id)
                        SELECT 1, id FROM permisos
                    ");
                    
                    // Actualizar archivo de configuración
                    $configContent = file_get_contents(__DIR__ . '/config/config.php');
                    $configContent = preg_replace(
                        "/define\('DB_HOST',[^;]+;/",
                        "define('DB_HOST', getenv('DB_HOST') ?: '{$installData['db_host']}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('DB_PORT',[^;]+;/",
                        "define('DB_PORT', getenv('DB_PORT') ?: '{$installData['db_port']}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('DB_NAME',[^;]+;/",
                        "define('DB_NAME', getenv('DB_NAME') ?: '{$installData['db_name']}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('DB_USER',[^;]+;/",
                        "define('DB_USER', getenv('DB_USER') ?: '{$installData['db_user']}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('DB_PASS',[^;]+;/",
                        "define('DB_PASS', getenv('DB_PASS') ?: '{$installData['db_pass']}');",
                        $configContent
                    );
                    file_put_contents(__DIR__ . '/config/config.php', $configContent);
                    
                    // Marcar como instalado
                    file_put_contents(__DIR__ . '/config/.installed', date('Y-m-d H:i:s'));
                    
                    // Limpiar sesión
                    unset($_SESSION['install']);
                    
                    $success = true;
                    $step = 5;
                    
                } catch (PDOException $e) {
                    $errors[] = 'Error al crear datos: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Verificar requisitos
$requirements = [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'cURL' => extension_loaded('curl'),
    'SOAP' => extension_loaded('soap'),
    'mbstring' => extension_loaded('mbstring'),
    'JSON' => extension_loaded('json'),
    'Directorio config escribible' => is_writable(__DIR__ . '/config'),
    'Directorio storage escribible' => is_writable(__DIR__ . '/storage'),
    'Directorio uploads escribible' => is_writable(__DIR__ . '/uploads'),
    'Directorio logs escribible' => is_writable(__DIR__ . '/logs'),
];

$allRequirementsMet = !in_array(false, $requirements, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Shalom Factura</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #1e4d39 0%, #163a2b 100%); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white">🌿 Shalom Factura</h1>
            <p class="text-white/70 mt-2">Asistente de Instalación</p>
        </div>
        
        <!-- Progress -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $i <= $step ? 'bg-white text-[#1e4d39]' : 'bg-white/20 text-white' ?> font-bold">
                    <?= $i ?>
                </div>
                <?php if ($i < 5): ?>
                <div class="w-8 h-1 <?= $i < $step ? 'bg-white' : 'bg-white/20' ?>"></div>
                <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <!-- Paso 1: Requisitos -->
            <h2 class="text-xl font-bold text-[#1e4d39] mb-6">Verificación de Requisitos</h2>
            
            <div class="space-y-3 mb-6">
                <?php foreach ($requirements as $req => $met): ?>
                <div class="flex items-center justify-between p-3 rounded-lg <?= $met ? 'bg-green-50' : 'bg-red-50' ?>">
                    <span><?= $req ?></span>
                    <span class="<?= $met ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $met ? '✓' : '✗' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($allRequirementsMet): ?>
            <form method="POST">
                <input type="hidden" name="step" value="1">
                <button type="submit" class="w-full bg-[#1e4d39] text-white py-3 rounded-lg font-medium hover:bg-[#2a6b4f]">
                    Continuar →
                </button>
            </form>
            <?php else: ?>
            <p class="text-red-600 text-center">Corrija los requisitos faltantes antes de continuar.</p>
            <?php endif; ?>
            
            <?php elseif ($step === 2): ?>
            <!-- Paso 2: Base de datos -->
            <h2 class="text-xl font-bold text-[#1e4d39] mb-6">Configuración de Base de Datos</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="step" value="2">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Host</label>
                        <input type="text" name="db_host" value="localhost" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Puerto</label>
                        <input type="text" name="db_port" value="3306" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Nombre de la Base de Datos</label>
                    <input type="text" name="db_name" value="shalom_factura" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Usuario</label>
                    <input type="text" name="db_user" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Contraseña</label>
                    <input type="password" name="db_pass" class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <button type="submit" class="w-full bg-[#1e4d39] text-white py-3 rounded-lg font-medium hover:bg-[#2a6b4f]">
                    Continuar →
                </button>
            </form>
            
            <?php elseif ($step === 3): ?>
            <!-- Paso 3: Empresa -->
            <h2 class="text-xl font-bold text-[#1e4d39] mb-6">Datos de la Empresa</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="step" value="3">
                
                <div>
                    <label class="block text-sm font-medium mb-1">RUC *</label>
                    <input type="text" name="ruc" maxlength="13" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Razón Social *</label>
                    <input type="text" name="razon_social" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Dirección</label>
                    <textarea name="direccion" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input type="email" name="email" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Teléfono</label>
                        <input type="text" name="telefono" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-[#1e4d39] text-white py-3 rounded-lg font-medium hover:bg-[#2a6b4f]">
                    Continuar →
                </button>
            </form>
            
            <?php elseif ($step === 4): ?>
            <!-- Paso 4: Usuario Admin -->
            <h2 class="text-xl font-bold text-[#1e4d39] mb-6">Usuario Administrador</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="step" value="4">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nombre *</label>
                        <input type="text" name="admin_nombre" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Apellido *</label>
                        <input type="text" name="admin_apellido" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Email *</label>
                    <input type="email" name="admin_email" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Contraseña * (mínimo 8 caracteres)</label>
                    <input type="password" name="admin_password" minlength="8" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Confirmar Contraseña *</label>
                    <input type="password" name="admin_password_confirm" class="w-full px-4 py-2 border rounded-lg" required>
                </div>
                
                <button type="submit" class="w-full bg-[#1e4d39] text-white py-3 rounded-lg font-medium hover:bg-[#2a6b4f]">
                    Instalar Sistema →
                </button>
            </form>
            
            <?php elseif ($step === 5 && $success): ?>
            <!-- Paso 5: Completado -->
            <div class="text-center">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="text-4xl">✓</span>
                </div>
                <h2 class="text-xl font-bold text-[#1e4d39] mb-4">¡Instalación Completada!</h2>
                <p class="text-gray-600 mb-6">El sistema ha sido instalado correctamente. Ya puede comenzar a usar Shalom Factura.</p>
                <a href="login.php" class="inline-block bg-[#1e4d39] text-white px-8 py-3 rounded-lg font-medium hover:bg-[#2a6b4f]">
                    Ir al Login →
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <p class="text-center text-white/60 text-sm mt-8">
            © <?= date('Y') ?> Shalom - Soluciones Digitales con Propósito
        </p>
    </div>
</body>
</html>
