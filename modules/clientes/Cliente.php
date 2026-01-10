<?php
/**
 * SHALOM FACTURA - Modelo de Cliente
 */

namespace Shalom\Modules\Clientes;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Core\Helpers;

class Cliente
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * Obtener todos los clientes con filtros
     */
    public function getAll(array $filters = [], int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $where = ['c.empresa_id = :empresa_id', 'c.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];
        
        if (!empty($filters['estado'])) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }
        
        if (!empty($filters['tipo_identificacion'])) {
            $where[] = 'c.tipo_identificacion_id = :tipo_id';
            $params[':tipo_id'] = $filters['tipo_identificacion'];
        }
        
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(c.razon_social LIKE :buscar OR c.identificacion LIKE :buscar2 OR c.email LIKE :buscar3)';
            $params[':buscar'] = $search;
            $params[':buscar2'] = $search;
            $params[':buscar3'] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->query("
            SELECT COUNT(*) FROM clientes c WHERE $whereClause
        ")->fetchColumn($params);
        
        $clientes = $this->db->query("
            SELECT 
                c.*,
                ti.nombre as tipo_identificacion_nombre,
                (SELECT COUNT(*) FROM facturas f WHERE f.cliente_id = c.id AND f.estado = 'emitida') as total_facturas,
                (SELECT COALESCE(SUM(total), 0) FROM facturas f WHERE f.cliente_id = c.id AND f.estado = 'emitida') as total_facturado
            FROM clientes c
            JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            WHERE $whereClause
            ORDER BY c.razon_social
            LIMIT $limit OFFSET $offset
        ")->fetchAll($params);
        
        return [
            'data' => $clientes,
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener cliente por ID
     */
    public function getById(int $id): ?array
    {
        return $this->db->query("
            SELECT c.*, ti.nombre as tipo_identificacion_nombre, ti.codigo as tipo_identificacion_codigo
            FROM clientes c
            JOIN cat_tipos_identificacion ti ON c.tipo_identificacion_id = ti.id
            WHERE c.id = :id AND c.empresa_id = :empresa_id AND c.deleted_at IS NULL
        ")->fetch([
            ':id' => $id,
            ':empresa_id' => $this->auth->empresaId()
        ]) ?: null;
    }
    
    /**
     * Crear cliente
     */
    public function create(array $data): array
    {
        // Validar
        $validation = $this->validate($data);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Verificar duplicado
        $exists = $this->db->exists('clientes', 
            'empresa_id = :empresa_id AND identificacion = :identificacion AND deleted_at IS NULL',
            [':empresa_id' => $this->auth->empresaId(), ':identificacion' => $data['identificacion']]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Ya existe un cliente con esta identificación'];
        }
        
        try {
            $id = $this->db->insert('clientes', [
                'uuid' => Helpers::uuid(),
                'empresa_id' => $this->auth->empresaId(),
                'tipo_identificacion_id' => $data['tipo_identificacion_id'],
                'identificacion' => $data['identificacion'],
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'celular' => $data['celular'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'ciudad' => $data['ciudad'] ?? null,
                'provincia' => $data['provincia'] ?? null,
                'es_contribuyente_especial' => $data['es_contribuyente_especial'] ?? 0,
                'aplica_retencion' => $data['aplica_retencion'] ?? 0,
                'porcentaje_descuento' => $data['porcentaje_descuento'] ?? 0,
                'dias_credito' => $data['dias_credito'] ?? 0,
                'limite_credito' => $data['limite_credito'] ?? 0,
                'notas' => $data['notas'] ?? null,
                'contacto_nombre' => $data['contacto_nombre'] ?? null,
                'contacto_cargo' => $data['contacto_cargo'] ?? null,
                'estado' => 'activo',
                'created_by' => $this->auth->id()
            ]);
            
            $this->auth->logActivity('crear', 'clientes', $id, [], $data);
            
            return [
                'success' => true,
                'message' => 'Cliente creado correctamente',
                'id' => $id
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al crear el cliente: ' . $e->getMessage()];
        }
    }
    
    /**
     * Actualizar cliente
     */
    public function update(int $id, array $data): array
    {
        $cliente = $this->getById($id);
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente no encontrado'];
        }
        
        $validation = $this->validate($data, $id);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Verificar duplicado
        $exists = $this->db->exists('clientes', 
            'empresa_id = :empresa_id AND identificacion = :identificacion AND id != :id AND deleted_at IS NULL',
            [':empresa_id' => $this->auth->empresaId(), ':identificacion' => $data['identificacion'], ':id' => $id]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Ya existe otro cliente con esta identificación'];
        }
        
        try {
            $this->db->update('clientes', [
                'tipo_identificacion_id' => $data['tipo_identificacion_id'],
                'identificacion' => $data['identificacion'],
                'razon_social' => $data['razon_social'],
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'celular' => $data['celular'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'ciudad' => $data['ciudad'] ?? null,
                'provincia' => $data['provincia'] ?? null,
                'es_contribuyente_especial' => $data['es_contribuyente_especial'] ?? 0,
                'aplica_retencion' => $data['aplica_retencion'] ?? 0,
                'porcentaje_descuento' => $data['porcentaje_descuento'] ?? 0,
                'dias_credito' => $data['dias_credito'] ?? 0,
                'limite_credito' => $data['limite_credito'] ?? 0,
                'notas' => $data['notas'] ?? null,
                'contacto_nombre' => $data['contacto_nombre'] ?? null,
                'contacto_cargo' => $data['contacto_cargo'] ?? null
            ], 'id = :id', [':id' => $id]);
            
            $this->auth->logActivity('actualizar', 'clientes', $id, $cliente, $data);
            
            return ['success' => true, 'message' => 'Cliente actualizado correctamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al actualizar el cliente'];
        }
    }
    
    /**
     * Eliminar cliente (soft delete)
     */
    public function delete(int $id): array
    {
        $cliente = $this->getById($id);
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente no encontrado'];
        }
        
        // Verificar si tiene facturas
        $tieneFacturas = $this->db->exists('facturas', 
            'cliente_id = :cliente_id AND deleted_at IS NULL',
            [':cliente_id' => $id]
        );
        
        if ($tieneFacturas) {
            return ['success' => false, 'message' => 'No se puede eliminar el cliente porque tiene facturas asociadas'];
        }
        
        $this->db->softDelete('clientes', $id);
        $this->auth->logActivity('eliminar', 'clientes', $id, $cliente);
        
        return ['success' => true, 'message' => 'Cliente eliminado correctamente'];
    }
    
    /**
     * Cambiar estado
     */
    public function cambiarEstado(int $id, string $estado): array
    {
        $cliente = $this->getById($id);
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente no encontrado'];
        }
        
        if (!in_array($estado, ['activo', 'inactivo'])) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }
        
        $this->db->update('clientes', ['estado' => $estado], 'id = :id', [':id' => $id]);
        $this->auth->logActivity('actualizar', 'clientes', $id, ['estado' => $cliente['estado']], ['estado' => $estado]);
        
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    }
    
    /**
     * Validar datos
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        if (empty($data['tipo_identificacion_id'])) {
            return ['success' => false, 'message' => 'El tipo de identificación es requerido'];
        }
        
        if (empty($data['identificacion'])) {
            return ['success' => false, 'message' => 'La identificación es requerida'];
        }
        
        if (empty($data['razon_social'])) {
            return ['success' => false, 'message' => 'La razón social es requerida'];
        }
        
        // Validar formato según tipo
        $tipoId = $data['tipo_identificacion_id'];
        $identificacion = $data['identificacion'];
        
        // Cédula
        if ($tipoId == 2 && !Helpers::validarCedula($identificacion)) {
            return ['success' => false, 'message' => 'La cédula ingresada no es válida'];
        }
        
        // RUC
        if ($tipoId == 1 && !Helpers::validarRuc($identificacion)) {
            return ['success' => false, 'message' => 'El RUC ingresado no es válido'];
        }
        
        // Email
        if (!empty($data['email']) && !Helpers::validarEmail($data['email'])) {
            return ['success' => false, 'message' => 'El email ingresado no es válido'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtener tipos de identificación
     */
    public function getTiposIdentificacion(): array
    {
        return $this->db->select('cat_tipos_identificacion', ['*'], 'activo = 1', [], 'id');
    }
}
