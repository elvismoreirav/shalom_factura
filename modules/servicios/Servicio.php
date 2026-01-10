<?php
/**
 * SHALOM FACTURA - Modelo de Servicio
 */

namespace Shalom\Modules\Servicios;

use Shalom\Core\Database;
use Shalom\Core\Auth;
use Shalom\Core\Helpers;

class Servicio
{
    private Database $db;
    private Auth $auth;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
    }
    
    /**
     * Obtener todos los servicios
     */
    public function getAll(array $filters = [], int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $where = ['s.empresa_id = :empresa_id', 's.deleted_at IS NULL'];
        $params = [':empresa_id' => $this->auth->empresaId()];
        
        if (!empty($filters['categoria_id'])) {
            $where[] = 's.categoria_id = :categoria_id';
            $params[':categoria_id'] = $filters['categoria_id'];
        }
        
        if (!empty($filters['tipo'])) {
            $where[] = 's.tipo = :tipo';
            $params[':tipo'] = $filters['tipo'];
        }
        
        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $where[] = 's.activo = :activo';
            $params[':activo'] = $filters['activo'];
        }
        
        if (!empty($filters['buscar'])) {
            $search = '%' . $this->db->escapeLike($filters['buscar']) . '%';
            $where[] = '(s.codigo LIKE :buscar OR s.nombre LIKE :buscar2)';
            $params[':buscar'] = $search;
            $params[':buscar2'] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->query("SELECT COUNT(*) FROM servicios s WHERE $whereClause")->fetchColumn($params);
        
        $servicios = $this->db->query("
            SELECT 
                s.*,
                cs.nombre as categoria_nombre,
                ci.nombre as impuesto_nombre,
                ci.porcentaje as impuesto_porcentaje
            FROM servicios s
            LEFT JOIN categorias_servicio cs ON s.categoria_id = cs.id
            JOIN cat_impuestos ci ON s.impuesto_id = ci.id
            WHERE $whereClause
            ORDER BY s.nombre
            LIMIT $limit OFFSET $offset
        ")->fetchAll($params);
        
        return [
            'data' => $servicios,
            'total' => (int) $total,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Obtener servicio por ID
     */
    public function getById(int $id): ?array
    {
        return $this->db->query("
            SELECT s.*, cs.nombre as categoria_nombre, ci.nombre as impuesto_nombre
            FROM servicios s
            LEFT JOIN categorias_servicio cs ON s.categoria_id = cs.id
            JOIN cat_impuestos ci ON s.impuesto_id = ci.id
            WHERE s.id = :id AND s.empresa_id = :empresa_id AND s.deleted_at IS NULL
        ")->fetch([
            ':id' => $id,
            ':empresa_id' => $this->auth->empresaId()
        ]) ?: null;
    }
    
    /**
     * Crear servicio
     */
    public function create(array $data): array
    {
        $validation = $this->validate($data);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Verificar código único
        $exists = $this->db->exists('servicios', 
            'empresa_id = :empresa_id AND codigo = :codigo AND deleted_at IS NULL',
            [':empresa_id' => $this->auth->empresaId(), ':codigo' => $data['codigo']]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Ya existe un servicio con este código'];
        }
        
        try {
            // Convertir cadenas vacías a null para campos opcionales
            $periodoRecurrencia = !empty($data['periodo_recurrencia']) ? $data['periodo_recurrencia'] : null;
            $categoriaId = !empty($data['categoria_id']) ? $data['categoria_id'] : null;
            
            $id = $this->db->insert('servicios', [
                'uuid' => Helpers::uuid(),
                'empresa_id' => $this->auth->empresaId(),
                'categoria_id' => $categoriaId,
                'codigo' => $data['codigo'],
                'codigo_auxiliar' => $data['codigo_auxiliar'] ?? null,
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'precio_unitario' => $data['precio_unitario'],
                'costo' => $data['costo'] ?? 0,
                'impuesto_id' => $data['impuesto_id'],
                'tipo' => $data['tipo'] ?? 'servicio',
                'unidad_medida' => $data['unidad_medida'] ?? 'UNIDAD',
                'es_recurrente' => $data['es_recurrente'] ?? 0,
                'periodo_recurrencia' => $periodoRecurrencia,
                'activo' => 1,
                'created_by' => $this->auth->id()
            ]);
            
            $this->auth->logActivity('crear', 'servicios', $id, [], $data);
            
            return ['success' => true, 'message' => 'Servicio creado correctamente', 'id' => $id];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al crear el servicio'];
        }
    }
    
    /**
     * Actualizar servicio
     */
    public function update(int $id, array $data): array
    {
        $servicio = $this->getById($id);
        if (!$servicio) {
            return ['success' => false, 'message' => 'Servicio no encontrado'];
        }
        
        $validation = $this->validate($data, $id);
        if (!$validation['success']) {
            return $validation;
        }
        
        $exists = $this->db->exists('servicios', 
            'empresa_id = :empresa_id AND codigo = :codigo AND id != :id AND deleted_at IS NULL',
            [':empresa_id' => $this->auth->empresaId(), ':codigo' => $data['codigo'], ':id' => $id]
        );
        
        if ($exists) {
            return ['success' => false, 'message' => 'Ya existe otro servicio con este código'];
        }
        
        try {
            // Convertir cadenas vacías a null para campos opcionales
            $periodoRecurrencia = !empty($data['periodo_recurrencia']) ? $data['periodo_recurrencia'] : null;
            $categoriaId = !empty($data['categoria_id']) ? $data['categoria_id'] : null;
            
            $this->db->update('servicios', [
                'categoria_id' => $categoriaId,
                'codigo' => $data['codigo'],
                'codigo_auxiliar' => $data['codigo_auxiliar'] ?? null,
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'precio_unitario' => $data['precio_unitario'],
                'costo' => $data['costo'] ?? 0,
                'impuesto_id' => $data['impuesto_id'],
                'tipo' => $data['tipo'] ?? 'servicio',
                'unidad_medida' => $data['unidad_medida'] ?? 'UNIDAD',
                'es_recurrente' => $data['es_recurrente'] ?? 0,
                'periodo_recurrencia' => $periodoRecurrencia
            ], 'id = :id', [':id' => $id]);
            
            $this->auth->logActivity('actualizar', 'servicios', $id, $servicio, $data);
            
            return ['success' => true, 'message' => 'Servicio actualizado correctamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al actualizar el servicio'];
        }
    }
    
    /**
     * Eliminar servicio
     */
    public function delete(int $id): array
    {
        $servicio = $this->getById($id);
        if (!$servicio) {
            return ['success' => false, 'message' => 'Servicio no encontrado'];
        }
        
        $this->db->softDelete('servicios', $id);
        $this->auth->logActivity('eliminar', 'servicios', $id, $servicio);
        
        return ['success' => true, 'message' => 'Servicio eliminado correctamente'];
    }
    
    /**
     * Cambiar estado
     */
    public function cambiarEstado(int $id, bool $activo): array
    {
        $servicio = $this->getById($id);
        if (!$servicio) {
            return ['success' => false, 'message' => 'Servicio no encontrado'];
        }
        
        $this->db->update('servicios', ['activo' => $activo ? 1 : 0], 'id = :id', [':id' => $id]);
        
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    }
    
    /**
     * Validar datos
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        if (empty($data['codigo'])) {
            return ['success' => false, 'message' => 'El código es requerido'];
        }
        
        if (empty($data['nombre'])) {
            return ['success' => false, 'message' => 'El nombre es requerido'];
        }
        
        if (!isset($data['precio_unitario']) || $data['precio_unitario'] < 0) {
            return ['success' => false, 'message' => 'El precio unitario es inválido'];
        }
        
        if (empty($data['impuesto_id'])) {
            return ['success' => false, 'message' => 'El tipo de IVA es requerido'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtener categorías
     */
    public function getCategorias(): array
    {
        return $this->db->select('categorias_servicio', ['*'], 
            'empresa_id = :empresa_id AND activo = 1',
            [':empresa_id' => $this->auth->empresaId()],
            'orden, nombre'
        );
    }
    
    /**
     * Crear categoría
     */
    public function createCategoria(array $data): array
    {
        if (empty($data['nombre'])) {
            return ['success' => false, 'message' => 'El nombre es requerido'];
        }
        
        $id = $this->db->insert('categorias_servicio', [
            'empresa_id' => $this->auth->empresaId(),
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'color' => $data['color'] ?? '#1e4d39',
            'activo' => 1
        ]);
        
        return ['success' => true, 'message' => 'Categoría creada correctamente', 'id' => $id];
    }
    
    /**
     * Obtener impuestos IVA
     */
    public function getImpuestosIva(): array
    {
        return $this->db->select('cat_impuestos', ['*'], 
            'tipo = "IVA" AND activo = 1',
            [],
            'porcentaje DESC'
        );
    }
}
