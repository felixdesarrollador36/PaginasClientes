<?php
/**
 * AuditLogger - Sistema de auditoría para cambios administrativos
 * 
 * Registra:
 * - Quién hizo el cambio (admin_id)
 * - Qué se cambió (action)
 * - Cuándo se cambió (timestamp)
 * - En qué recurso (target_id, target_type)
 * - Valores anteriores y nuevos
 * - IP address
 * - Notas/motivo
 */

class AuditLogger {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Registrar una acción en la auditoría
     * 
     * @param string $action Acción realizada (USER_BAN, USER_PROMOTE, ITEM_APPROVE, etc)
     * @param string $targetType Tipo de recurso (user, item, team, tournament, etc)
     * @param int $targetId ID del recurso afectado
     * @param mixed $oldValue Valor anterior (puede ser array o string)
     * @param mixed $newValue Valor nuevo (puede ser array o string)
     * @param string $notes Notas adicionales (motivo, descripción)
     * @return bool
     */
    public function log($action, $targetType, $targetId, $oldValue = null, $newValue = null, $notes = '') {
        try {
            // Obtener info del usuario actual
            $userId = currentUserId();
            if (!$userId) {
                return false;
            }
            
            // Convertir arrays a JSON
            if (is_array($oldValue)) {
                $oldValue = json_encode($oldValue);
            }
            if (is_array($newValue)) {
                $newValue = json_encode($newValue);
            }
            
            // Limitar longitud
            $oldValue = substr($oldValue ?? '', 0, 500);
            $newValue = substr($newValue ?? '', 0, 500);
            $notes = substr($notes, 0, 1000);
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            
            $this->db->insert(
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, old_value, new_value, notes, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$userId, $action, $targetType, $targetId, $oldValue, $newValue, $notes, $ipAddress, $userAgent]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error logging audit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener logs de auditoría para búsqueda y filtrado
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $query = "SELECT al.*, u.username as admin_username FROM audit_logs al 
                  LEFT JOIN users u ON al.admin_id = u.id WHERE 1=1";
        $params = [];
        
        if (!empty($filters['action'])) {
            $query .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['targetType'])) {
            $query .= " AND al.target_type = ?";
            $params[] = $filters['targetType'];
        }
        
        if (!empty($filters['targetId'])) {
            $query .= " AND al.target_id = ?";
            $params[] = intval($filters['targetId']);
        }
        
        if (!empty($filters['adminId'])) {
            $query .= " AND al.admin_id = ?";
            $params[] = intval($filters['adminId']);
        }
        
        if (!empty($filters['startDate'])) {
            $query .= " AND al.created_at >= ?";
            $params[] = $filters['startDate'] . ' 00:00:00';
        }
        
        if (!empty($filters['endDate'])) {
            $query .= " AND al.created_at <= ?";
            $params[] = $filters['endDate'] . ' 23:59:59';
        }
        
        $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Obtener logs para un recurso específico (ej: todos los cambios a un usuario)
     */
    public function getResourceHistory($targetType, $targetId) {
        return $this->db->fetchAll(
            "SELECT al.*, u.username as admin_username FROM audit_logs al 
             LEFT JOIN users u ON al.admin_id = u.id 
             WHERE al.target_type = ? AND al.target_id = ? 
             ORDER BY al.created_at DESC",
            [$targetType, $targetId]
        );
    }
    
    /**
     * Obtener logs de un admin específico
     */
    public function getAdminActions($adminId, $limit = 100) {
        return $this->db->fetchAll(
            "SELECT * FROM audit_logs 
             WHERE admin_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$adminId, $limit]
        );
    }
    
    /**
     * Detectar actividad sospechosa
     * Retorna acciones que pueden indicar abuso
     */
    public function getSuspiciousActivity($hours = 24) {
        return $this->db->fetchAll(
            "SELECT action, target_type, COUNT(*) as count, admin_id, u.username  
             FROM audit_logs al
             LEFT JOIN users u ON al.admin_id = u.id
             WHERE al.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
             GROUP BY action, admin_id
             HAVING count > 10
             ORDER BY count DESC",
            [$hours]
        );
    }
    
    /**
     * Estadísticas de auditoría
     */
    public function getStats($days = 7) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        return [
            'total_actions' => $this->db->fetch(
                "SELECT COUNT(*) as c FROM audit_logs WHERE created_at >= ?",
                [$startDate]
            )['c'],
            'by_action' => $this->db->fetchAll(
                "SELECT action, COUNT(*) as count FROM audit_logs 
                 WHERE created_at >= ? 
                 GROUP BY action ORDER BY count DESC",
                [$startDate]
            ),
            'by_admin' => $this->db->fetchAll(
                "SELECT u.username, COUNT(*) as count FROM audit_logs al
                 LEFT JOIN users u ON al.admin_id = u.id
                 WHERE al.created_at >= ? 
                 GROUP BY al.admin_id ORDER BY count DESC",
                [$startDate]
            ),
            'by_type' => $this->db->fetchAll(
                "SELECT target_type, COUNT(*) as count FROM audit_logs 
                 WHERE created_at >= ? 
                 GROUP BY target_type",
                [$startDate]
            )
        ];
    }
}

// Helper function para auditoría rápida
function auditLog($action, $targetType, $targetId, $oldValue = null, $newValue = null, $notes = '') {
    $logger = new AuditLogger();
    return $logger->log($action, $targetType, $targetId, $oldValue, $newValue, $notes);
}
