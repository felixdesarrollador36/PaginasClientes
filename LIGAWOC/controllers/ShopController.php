<?php
/**
 * Liga WOC - Shop Controller
 */
class ShopController {
    public $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getUserCoins($userId) {
        $result = $this->db->fetch("SELECT coins FROM user_coins WHERE user_id = ?", [$userId]);
        return $result ? $result['coins'] : 0;
    }

    public function getOrCreateUserCoins($userId) {
        $result = $this->db->fetch("SELECT * FROM user_coins WHERE user_id = ?", [$userId]);
        if (!$result) {
            $this->db->insert("INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, 0, 0)", [$userId]);
            return 0;
        }
        return $result['coins'];
    }

    public function addCoins($userId, $amount, $reason = 'purchase') {
        $this->db->update(
            "INSERT INTO user_coins (user_id, coins, lifetime_coins) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE coins = coins + ?, lifetime_coins = lifetime_coins + ?",
            [$userId, $amount, $amount, $amount, $amount]
        );
    }

    public function deductCoins($userId, $amount) {
        $current = $this->getUserCoins($userId);
        if ($current < $amount) {
            return false;
        }
        $this->db->update("UPDATE user_coins SET coins = coins - ? WHERE user_id = ?", [$amount, $userId]);
        return true;
    }

    public function getCoinPackages() {
        return $this->db->fetchAll(
            "SELECT * FROM coin_packages WHERE is_active = 1 ORDER BY sort_order ASC"
        );
    }

    public function getCategories() {
        return $this->db->fetchAll(
            "SELECT * FROM item_categories WHERE is_active = 1 ORDER BY sort_order ASC"
        );
    }

    public function getItems($categoryId = null, $search = '') {
        $sql = "SELECT si.*, ic.name as category_name, ic.slug as category_slug, u.username as designer_name 
                FROM shop_items si 
                JOIN item_categories ic ON si.category_id = ic.id 
                JOIN users u ON si.designer_id = u.id 
                WHERE si.is_active = 1";
        $params = [];

        if ($categoryId) {
            $sql .= " AND si.category_id = ?";
            $params[] = $categoryId;
        }

        if ($search) {
            $sql .= " AND (si.name LIKE ? OR si.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY si.is_featured DESC, si.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getFeaturedItems($limit = 6) {
        return $this->db->fetchAll(
            "SELECT si.*, ic.name as category_name, ic.slug as category_slug, u.username as designer_name 
             FROM shop_items si 
             JOIN item_categories ic ON si.category_id = ic.id 
             JOIN users u ON si.designer_id = u.id 
             WHERE si.is_active = 1 AND si.is_featured = 1 
             ORDER BY si.total_sales DESC 
             LIMIT ?",
            [$limit]
        );
    }

    public function getItem($itemId) {
        return $this->db->fetch(
            "SELECT si.*, ic.name as category_name, ic.slug as category_slug, u.username as designer_name 
             FROM shop_items si 
             JOIN item_categories ic ON si.category_id = ic.id 
             JOIN users u ON si.designer_id = u.id 
             WHERE si.id = ?",
            [$itemId]
        );
    }

    public function purchaseItem($userId, $itemId) {
        $item = $this->getItem($itemId);
        if (!$item) {
            return ['success' => false, 'error' => 'Item no encontrado'];
        }

        $owned = $this->db->fetch(
            "SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?",
            [$userId, $itemId]
        );
        if ($owned) {
            return ['success' => false, 'error' => 'Ya tienes este item'];
        }

        $coins = $this->getUserCoins($userId);
        if ($coins < $item['price_coins']) {
            return ['success' => false, 'error' => 'No tienes suficientes monedas'];
        }

        $this->db->update("UPDATE user_coins SET coins = coins - ? WHERE user_id = ?", [$item['price_coins'], $userId]);

        $this->db->insert(
            "INSERT INTO user_inventory (user_id, item_id) VALUES (?, ?)",
            [$userId, $itemId]
        );

        $this->db->update("UPDATE shop_items SET total_sales = total_sales + 1 WHERE id = ?", [$itemId]);

        return ['success' => true, 'item' => $item];
    }

    public function getUserInventory($userId) {
        return $this->db->fetchAll(
            "SELECT ui.*, si.name, si.image, si.category_id, ic.name as category_name, ic.slug as category_slug, ic.type
             FROM user_inventory ui
             JOIN shop_items si ON ui.item_id = si.id
             JOIN item_categories ic ON si.category_id = ic.id
             WHERE ui.user_id = ?
             ORDER BY ui.is_equipped DESC, ui.purchased_at DESC",
            [$userId]
        );
    }

    public function getEquippedItems($userId) {
        return $this->db->fetchAll(
            "SELECT ui.*, si.name, si.image, si.category_id, ic.name as category_name, ic.type
             FROM user_inventory ui
             JOIN shop_items si ON ui.item_id = si.id
             JOIN item_categories ic ON si.category_id = ic.id
             WHERE ui.user_id = ? AND ui.is_equipped = 1",
            [$userId]
        );
    }

    public function equipItem($userId, $itemId) {
        // First check if user owns this item
        $inventory = $this->db->fetch(
            "SELECT ui.*, si.category_id FROM user_inventory ui 
             JOIN shop_items si ON ui.item_id = si.id
             WHERE ui.user_id = ? AND ui.item_id = ?",
            [$userId, $itemId]
        );

        if (!$inventory) {
            return ['success' => false, 'error' => 'No tienes este item'];
        }

        $categoryId = $inventory['category_id'];

        // Get the type of this category
        $category = $this->db->fetch("SELECT type FROM item_categories WHERE id = ?", [$categoryId]);
        if (!$category) {
            return ['success' => false, 'error' => 'Categoría no encontrada'];
        }
        $type = $category['type'];

        // Unequip all items of same type
        $this->db->update(
            "UPDATE user_inventory ui 
             JOIN shop_items si ON ui.item_id = si.id
             SET ui.is_equipped = 0, ui.equipped_at = NULL 
             WHERE ui.user_id = ? AND si.category_id IN (
                 SELECT id FROM item_categories WHERE type = ?
             )",
            [$userId, $type]
        );

        // Equip this item
        $this->db->update(
            "UPDATE user_inventory SET is_equipped = 1, equipped_at = NOW() 
             WHERE user_id = ? AND item_id = ?",
            [$userId, $itemId]
        );

        return ['success' => true];
    }

    public function unequipItem($userId, $itemId) {
        $this->db->update(
            "UPDATE user_inventory SET is_equipped = 0, equipped_at = NULL 
             WHERE user_id = ? AND item_id = ?",
            [$userId, $itemId]
        );
        return ['success' => true];
    }

    public function equipMarco($userId, $itemId) {
        if ($itemId == 0) {
            $this->db->update(
                "UPDATE user_inventory ui 
                 JOIN shop_items si ON ui.item_id = si.id
                 JOIN item_categories ic ON si.category_id = ic.id
                 SET ui.is_equipped = 0, ui.equipped_at = NULL 
                 WHERE ui.user_id = ? AND ic.type = 'marco'",
                [$userId]
            );
            return ['success' => true];
        }
        return $this->equipItem($userId, $itemId);
    }

    public function equipPortada($userId, $itemId) {
        if ($itemId == 0) {
            $this->db->update(
                "UPDATE user_inventory ui 
                 JOIN shop_items si ON ui.item_id = si.id
                 JOIN item_categories ic ON si.category_id = ic.id
                 SET ui.is_equipped = 0, ui.equipped_at = NULL 
                 WHERE ui.user_id = ? AND ic.type = 'portada'",
                [$userId]
            );
            return ['success' => true];
        }
        return $this->equipItem($userId, $itemId);
    }

    public function createItem($designerId, $categoryId, $name, $description, $image, $price) {
        $this->db->insert(
            "INSERT INTO shop_items (designer_id, category_id, name, description, image, price_coins) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$designerId, $categoryId, $name, $description, $image, $price]
        );
        return $this->db->getConnection()->lastInsertId();
    }

    public function getPendingItems() {
        return $this->db->fetchAll(
            "SELECT si.*, ic.name as category_name, u.username as designer_name 
             FROM shop_items si 
             JOIN item_categories ic ON si.category_id = ic.id 
             JOIN users u ON si.designer_id = u.id 
             WHERE si.is_active = 0 
             ORDER BY si.created_at ASC"
        );
    }

    public function approveItem($itemId, $adminId) {
        $this->db->update(
            "UPDATE shop_items SET is_active = 1, approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$adminId, $itemId]
        );
    }

    public function rejectItem($itemId) {
        $this->db->delete("DELETE FROM shop_items WHERE id = ?", [$itemId]);
    }

    public function isDesigner($userId) {
        $result = $this->db->fetch("SELECT role FROM users WHERE id = ?", [$userId]);
        return $result && in_array($result['role'], ['designer', 'admin', 'superadmin']);
    }

    public function applyAsDesigner($userId, $portfolio) {
        $existing = $this->db->fetch(
            "SELECT id FROM designer_applications WHERE user_id = ? AND status = 'pending'",
            [$userId]
        );
        if ($existing) {
            return ['success' => false, 'error' => 'Ya tienes una solicitud pendiente'];
        }

        $this->db->insert(
            "INSERT INTO designer_applications (user_id, portfolio) VALUES (?, ?)",
            [$userId, $portfolio]
        );
        return ['success' => true];
    }

    public function getDesignerStats($designerId) {
        $stats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_items,
                SUM(total_sales) as total_sales,
                (SELECT SUM(si.price_coins) FROM shop_items si JOIN user_inventory ui ON ui.item_id = si.id WHERE si.designer_id = ? AND ui.purchased_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_revenue
             FROM shop_items WHERE designer_id = ? AND is_active = 1",
            [$designerId, $designerId]
        );
        return $stats;
    }
}
