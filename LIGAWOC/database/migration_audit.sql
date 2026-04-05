-- ====================================
-- TABLA DE AUDITORÍA
-- ====================================
-- Registra todos los cambios administrativos

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'USER_BAN, USER_PROMOTE, ITEM_APPROVE, etc',
    target_type VARCHAR(50) NOT NULL COMMENT 'user, item, team, tournament, etc',
    target_id INT NOT NULL COMMENT 'ID del recurso afectado',
    old_value VARCHAR(500) COMMENT 'Valor anterior (JSON si es array)',
    new_value VARCHAR(500) COMMENT 'Valor nuevo (JSON si es array)',
    notes VARCHAR(1000) COMMENT 'Justificación o notas',
    ip_address VARCHAR(45) COMMENT 'IP del administrador',
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id),
    INDEX idx_admin (admin_id),
    INDEX idx_created (created_at),
    INDEX idx_search (admin_id, action, target_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sistema de auditoría para cambios administrativos';

-- ====================================
-- ACTUALIZAR TABLA USERS
-- ====================================
-- Añadir timestamp de último login si no existe

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) DEFAULT 0 COMMENT 'Usuario baneado';
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason VARCHAR(255) COMMENT 'Razón del ban';
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_date TIMESTAMP NULL COMMENT 'Fecha del ban';
ALTER TABLE users ADD COLUMN IF NOT EXISTS main_hero VARCHAR(50) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS current_rank VARCHAR(30) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(30) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_brand VARCHAR(100) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS discord VARCHAR(100) DEFAULT NULL;

-- ====================================
-- ÍNDICES ADICIONALES PARA SEGURIDAD
-- ====================================

ALTER TABLE users 
    ADD INDEX IF NOT EXISTS idx_banned (is_banned),
    ADD INDEX IF NOT EXISTS idx_role (role),
    ADD INDEX IF NOT EXISTS idx_created_at (created_at);
