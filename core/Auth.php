<?php
/**
 * SHALOM FACTURA - Clase Auth
 * Gestión de autenticación y sesiones
 */

namespace Shalom\Core;

class Auth
{
    private static ?Auth $instance = null;
    private Database $db;
    private ?array $user = null;
    private ?array $empresa = null;
    private array $permisos = [];
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->initSession();
        $this->loadUser();
    }
    
    public static function getInstance(): Auth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar sesión
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => SESSION_PATH,
                'secure' => SESSION_SECURE,
                'httponly' => SESSION_HTTPONLY,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
        
        // Regenerar ID de sesión periódicamente
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
    
    /**
     * Cargar usuario de la sesión
     */
    private function loadUser(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->db->query("
                SELECT u.*, r.nombre as rol_nombre, r.slug as rol_slug
                FROM usuarios u
                JOIN roles r ON u.rol_id = r.id
                WHERE u.id = :id AND u.estado = 'activo' AND u.deleted_at IS NULL
            ")->fetch([':id' => $_SESSION['user_id']]);
            
            if ($this->user) {
                $this->loadEmpresa();
                $this->loadPermisos();
            } else {
                $this->logout();
            }
        }
    }
    
    /**
     * Cargar empresa del usuario
     */
    private function loadEmpresa(): void
    {
        if ($this->user && $this->user['empresa_id']) {
            $this->empresa = $this->db->query("
                SELECT * FROM empresas 
                WHERE id = :id AND estado = 'activo' AND deleted_at IS NULL
            ")->fetch([':id' => $this->user['empresa_id']]);
        }
    }
    
    /**
     * Cargar permisos del usuario
     */
    private function loadPermisos(): void
    {
        if ($this->user) {
            $permisos = $this->db->query("
                SELECT p.slug
                FROM permisos p
                JOIN rol_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = :rol_id
            ")->fetchAll([':rol_id' => $this->user['rol_id']]);
            
            $this->permisos = array_column($permisos, 'slug');
        }
    }
    
    /**
     * Intentar login
     */
    public function attempt(string $email, string $password, bool $remember = false): array
    {
        $result = ['success' => false, 'message' => ''];
        
        // Buscar usuario
        $user = $this->db->query("
            SELECT u.*, r.slug as rol_slug
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.email = :email AND u.deleted_at IS NULL
        ")->fetch([':email' => strtolower(trim($email))]);
        
        if (!$user) {
            $result['message'] = 'Credenciales incorrectas';
            return $result;
        }
        
        // Verificar si está bloqueado
        if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
            $minutos = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            $result['message'] = "Cuenta bloqueada. Intente en $minutos minutos.";
            return $result;
        }
        
        // Verificar estado
        if ($user['estado'] !== 'activo') {
            $result['message'] = 'Su cuenta está ' . $user['estado'];
            return $result;
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            $this->registerFailedAttempt($user['id']);
            $result['message'] = 'Credenciales incorrectas';
            return $result;
        }
        
        // Login exitoso
        $this->loginUser($user, $remember);
        $result['success'] = true;
        $result['message'] = 'Inicio de sesión exitoso';
        
        return $result;
    }
    
    /**
     * Registrar intento fallido
     */
    private function registerFailedAttempt(int $userId): void
    {
        $this->db->query("
            UPDATE usuarios 
            SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = IF(intentos_fallidos + 1 >= :max, DATE_ADD(NOW(), INTERVAL :lockout SECOND), NULL)
            WHERE id = :id
        ")->execute([
            ':max' => MAX_LOGIN_ATTEMPTS,
            ':lockout' => LOCKOUT_TIME,
            ':id' => $userId
        ]);
    }
    
    /**
     * Iniciar sesión de usuario
     */
    private function loginUser(array $user, bool $remember = false): void
    {
        // Limpiar intentos fallidos
        $this->db->update('usuarios', [
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_login' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $user['id']]);
        
        // Regenerar sesión
        session_regenerate_id(true);
        
        // Guardar en sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['empresa_id'] = $user['empresa_id'];
        $_SESSION['_created'] = time();
        
        // Cargar datos
        $this->user = $user;
        $this->loadEmpresa();
        $this->loadPermisos();
        
        // Token de recordar
        if ($remember) {
            $this->createRememberToken($user['id']);
        }
        
        // Registrar sesión
        $this->logSession($user['id']);
        
        // Log de auditoría
        $this->logActivity('login');
    }
    
    /**
     * Crear token de recordar
     */
    private function createRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        $this->db->update('usuarios', [
            'token_recordar' => $hashedToken
        ], 'id = :id', [':id' => $userId]);
        
        setcookie('remember_token', $token, [
            'expires' => time() + (86400 * 30), // 30 días
            'path' => '/',
            'secure' => SESSION_SECURE,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    /**
     * Registrar sesión activa
     */
    private function logSession(int $userId): void
    {
        $sessionId = session_id();
        
        // Eliminar sesión anterior si existe
        $this->db->delete('user_sessions', 'id = :id', [':id' => $sessionId]);
        
        // Crear nueva sesión
        $this->db->insert('user_sessions', [
            'id' => $sessionId,
            'usuario_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'last_activity' => time()
        ]);
    }
    
    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        if ($this->user) {
            $this->logActivity('logout');
        }
        
        // Eliminar sesión de BD
        if (session_id()) {
            $this->db->delete('user_sessions', 'id = :id', [':id' => session_id()]);
        }
        
        // Eliminar cookie de recordar
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Limpiar variables
        $this->user = null;
        $this->empresa = null;
        $this->permisos = [];
        
        // Destruir sesión
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Verificar si está autenticado
     */
    public function check(): bool
    {
        return $this->user !== null;
    }
    
    /**
     * Verificar si es invitado
     */
    public function guest(): bool
    {
        return !$this->check();
    }
    
    /**
     * Obtener usuario actual
     */
    public function user(): ?array
    {
        return $this->user;
    }
    
    /**
     * Obtener ID del usuario
     */
    public function id(): ?int
    {
        return $this->user['id'] ?? null;
    }
    
    /**
     * Obtener empresa actual
     */
    public function empresa(): ?array
    {
        return $this->empresa;
    }
    
    /**
     * Obtener ID de empresa
     */
    public function empresaId(): ?int
    {
        return $this->user['empresa_id'] ?? null;
    }
    
    /**
     * Verificar permiso
     */
    public function can(string $permiso): bool
    {
        // Superadmin tiene todos los permisos
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return in_array($permiso, $this->permisos);
    }
    
    /**
     * Verificar varios permisos (OR)
     */
    public function canAny(array $permisos): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        foreach ($permisos as $permiso) {
            if ($this->can($permiso)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar todos los permisos (AND)
     */
    public function canAll(array $permisos): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        foreach ($permisos as $permiso) {
            if (!$this->can($permiso)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Verificar si es superadmin
     */
    public function isSuperAdmin(): bool
    {
        return ($this->user['rol_slug'] ?? '') === 'superadmin';
    }
    
    /**
     * Verificar si es admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->user['rol_slug'] ?? '', ['superadmin', 'admin']);
    }
    
    /**
     * Verificar rol
     */
    public function hasRole(string $role): bool
    {
        return ($this->user['rol_slug'] ?? '') === $role;
    }
    
    /**
     * Obtener nombre completo
     */
    public function name(): string
    {
        if (!$this->user) return '';
        return trim($this->user['nombre'] . ' ' . $this->user['apellido']);
    }
    
    /**
     * Registrar actividad
     */
    public function logActivity(string $accion, string $tabla = '', ?int $registroId = null, array $datosAnteriores = [], array $datosNuevos = []): void
    {
        try {
            // Limitar tamaño de datos para evitar problemas con JSON muy grandes
            $datosAntJson = null;
            $datosNuevJson = null;
            
            if (!empty($datosAnteriores)) {
                $datosAntJson = json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE);
                if ($datosAntJson === false || strlen($datosAntJson) > 65000) {
                    $datosAntJson = json_encode(['nota' => 'Datos muy grandes, no almacenados']);
                }
            }
            
            if (!empty($datosNuevos)) {
                $datosNuevJson = json_encode($datosNuevos, JSON_UNESCAPED_UNICODE);
                if ($datosNuevJson === false || strlen($datosNuevJson) > 65000) {
                    $datosNuevJson = json_encode(['nota' => 'Datos muy grandes, no almacenados']);
                }
            }
            
            $this->db->insert('audit_log', [
                'empresa_id' => $this->empresaId(),
                'usuario_id' => $this->id(),
                'tabla' => $tabla ?: 'auth',
                'registro_id' => $registroId,
                'accion' => $accion,
                'datos_anteriores' => $datosAntJson,
                'datos_nuevos' => $datosNuevJson,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'url' => substr($_SERVER['REQUEST_URI'] ?? '', 0, 500)
            ]);
        } catch (\Exception $e) {
            // Log error silenciosamente, no interrumpir el flujo principal
            error_log('Error en logActivity: ' . $e->getMessage());
        }
    }
    
    /**
     * Generar token CSRF
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Verificar token CSRF
     */
    public function verifyCsrfToken(?string $token): bool
    {
        if (!$token || !isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->db->find('usuarios', $userId);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Usuario no encontrado'];
        }
        
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Contraseña actual incorrecta'];
        }
        
        $this->db->update('usuarios', [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])
        ], 'id = :id', [':id' => $userId]);
        
        $this->logActivity('actualizar', 'usuarios', $userId, [], ['password' => '[changed]']);
        
        return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
    }
    
    /**
     * Crear token de recuperación
     */
    public function createPasswordResetToken(string $email): ?string
    {
        $user = $this->db->query("SELECT id FROM usuarios WHERE email = :email AND estado = 'activo'")
            ->fetch([':email' => strtolower(trim($email))]);
        
        if (!$user) {
            return null;
        }
        
        $token = bin2hex(random_bytes(32));
        
        $this->db->insert('user_tokens', [
            'usuario_id' => $user['id'],
            'tipo' => 'password_reset',
            'token' => hash('sha256', $token),
            'expira_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);
        
        return $token;
    }
    
    /**
     * Verificar token de recuperación
     */
    public function verifyPasswordResetToken(string $token): ?array
    {
        return $this->db->query("
            SELECT ut.*, u.email, u.nombre
            FROM user_tokens ut
            JOIN usuarios u ON ut.usuario_id = u.id
            WHERE ut.token = :token 
            AND ut.tipo = 'password_reset'
            AND ut.expira_at > NOW()
            AND ut.usado_at IS NULL
        ")->fetch([':token' => hash('sha256', $token)]);
    }
    
    /**
     * Restablecer contraseña
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        $tokenData = $this->verifyPasswordResetToken($token);
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Token inválido o expirado'];
        }
        
        $this->db->update('usuarios', [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])
        ], 'id = :id', [':id' => $tokenData['usuario_id']]);
        
        $this->db->update('user_tokens', [
            'usado_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $tokenData['id']]);
        
        return ['success' => true, 'message' => 'Contraseña restablecida correctamente'];
    }
}