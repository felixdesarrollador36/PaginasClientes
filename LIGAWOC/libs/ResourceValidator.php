<?php
/**
 * ResourceValidator - Valida que un usuario tenga acceso a un recurso
 * 
 * Previene:
 * - Acceso por URL hacking (ej: /profile/user/123 si no eres 123)
 * - Modificación de recursos ajenos
 * - Lectura de información privada
 */

class ResourceValidator {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Verificar que el usuario es dueño del perfil/recurso
     * 
     * @param int $resourceUserId ID del usuario propietario del recurso
     * @param int $currentUserId ID del usuario actual (default: currentUserId())
     * @return bool
     */
    public function isResourceOwner($resourceUserId, $currentUserId = null) {
        if ($currentUserId === null) {
            $currentUserId = currentUserId();
        }
        
        if (!$currentUserId) {
            return false;
        }
        
        return (int)$resourceUserId === (int)$currentUserId;
    }
    
    /**
     * Verificar que es admin o dueño del recurso
     */
    public function isOwnerOrAdmin($resourceUserId, $currentUserId = null) {
        if ($currentUserId === null) {
            $currentUserId = currentUserId();
        }
        
        if (!$currentUserId) {
            return false;
        }
        
        // Es admin
        if (isAdmin()) {
            return true;
        }
        
        // Es dueño
        return (int)$resourceUserId === (int)$currentUserId;
    }
    
    /**
     * Verificar propiedad de item de tienda
     */
    public function userOwnsShopItem($itemId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $item = $this->db->fetch(
            "SELECT designer_id FROM shop_items WHERE id = ?",
            [$itemId]
        );
        
        return $item && (int)$item['designer_id'] === (int)$userId;
    }
    
    /**
     * Verificar propiedad de equipo
     */
    public function userOwnsTeam($teamId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $team = $this->db->fetch(
            "SELECT leader_id FROM teams WHERE id = ?",
            [$teamId]
        );
        
        return $team && (int)$team['leader_id'] === (int)$userId;
    }
    
    /**
     * Verificar que usuario es miembro del equipo
     */
    public function userIsTeamMember($teamId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $member = $this->db->fetch(
            "SELECT id FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        return (bool)$member;
    }
    
    /**
     * Verificar propiedad de noticia
     */
    public function userOwnsNews($newsId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $news = $this->db->fetch(
            "SELECT author_id FROM news WHERE id = ?",
            [$newsId]
        );
        
        return $news && (int)$news['author_id'] === (int)$userId;
    }
    
    /**
     * Verificar que usuario tiene acceso a inventario (suyo o es admin)
     */
    public function UserCanAccessInventory($userId, $currentUserId = null) {
        if ($currentUserId === null) {
            $currentUserId = currentUserId();
        }
        
        if (!$currentUserId) {
            return false;
        }
        
        // Es op es el admin
        if (isAdmin()) {
            return true;
        }
        
        // Es el usuario propietario del inventario
        return (int)$userId === (int)$currentUserId;
    }
    
    /**
     * Verificar acceso a stats de usuario (público, pero algunos datos privados)
     */
    public function canViewUserStats($userId, $currentUserId = null) {
        // Stats públicas pueden ser vistas por todos
        return true;
    }
    
    /**
     * Verificar acceso a información privada del usuario
     */
    public function canViewPrivateUserInfo($userId, $currentUserId = null) {
        if ($currentUserId === null) {
            $currentUserId = currentUserId();
        }
        
        if (!$currentUserId) {
            return false;
        }
        
        // Es admin puede ver todo
        if (isAdmin()) {
            return true;
        }
        
        // Es el usuario mismo
        return (int)$userId === (int)$currentUserId;
    }
    
    /**
     * Verificar acceso a torneo
     * algunos torneos son privados
     */
    public function userCanAccessTournament($tournamentId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return true; // Torneos públicos pueden ser vistos sin login
        }
        
        $tournament = $this->db->fetch(
            "SELECT is_private, creator_id FROM tournaments WHERE id = ?",
            [$tournamentId]
        );
        
        if (!$tournament) {
            return false;
        }
        
        // Torneo público
        if (!$tournament['is_private']) {
            return true;
        }
        
        // Es creador
        if ((int)$tournament['creator_id'] === (int)$userId) {
            return true;
        }
        
        // Es admin
        if (isAdmin()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar que usuario puede editar recurso
     */
    public function canEditResource($resourceType, $resourceId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        // Admins pueden editar casi todo
        if (isSuperAdmin()) {
            return true;
        }
        
        switch ($resourceType) {
            case 'shop_item':
                return $this->userOwnsShopItem($resourceId, $userId);
            
            case 'team':
                return $this->userOwnsTeam($resourceId, $userId);
            
            case 'news':
                return $this->userOwnsNews($resourceId, $userId) || isAdmin();
            
            case 'user':
                return (int)$resourceId === (int)$userId || isAdmin();
            
            default:
                return false;
        }
    }
    
    /**
     * Verificar permisos de torneo específico
     */
    public function canManageTournament($tournamentId, $userId = null) {
        if ($userId === null) {
            $userId = currentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        if (!isAdmin()) {
            return false;
        }
        
        return true;
    }
}

// Helper function
function validateResourceAccess($resourceType, $resourceId, $userId = null) {
    $validator = new ResourceValidator();
    return $validator->canEditResource($resourceType, $resourceId, $userId);
}
