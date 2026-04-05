<?php
/**
 * Liga WOC - News Controller
 */

class NewsController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create() {
        if (!isAdmin()) redirect('dashboard');
        verifyCsrf();

        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $publish_date = $_POST['publish_date'] ?? date('Y-m-d H:i:s');

        if (empty($title) || empty($content)) {
            setFlash('error', 'El título y contenido son obligatorios.');
            redirect('admin/news');
        }

        $slug = slugify($title);
        $existing = $this->db->fetch("SELECT id FROM news WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug .= '-' . time();
        }

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->uploadImage($_FILES['image']);
        }

        $this->db->insert(
            "INSERT INTO news (title, slug, content, excerpt, image, category_id, author_id, is_published, is_featured, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$title, $slug, $content, $excerpt, $imagePath, $category_id ?: null, currentUserId(), $is_published, $is_featured, $publish_date]
        );

        setFlash('success', 'Noticia creada exitosamente.');
        redirect('admin/news');
    }

    public function update($id) {
        if (!isAdmin()) redirect('dashboard');
        verifyCsrf();

        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        $sql = "UPDATE news SET title = ?, content = ?, excerpt = ?, category_id = ?, is_published = ?, is_featured = ?";
        $params = [$title, $content, $excerpt, $category_id ?: null, $is_published, $is_featured];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->uploadImage($_FILES['image']);
            if ($imagePath) {
                $sql .= ", image = ?";
                $params[] = $imagePath;
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $this->db->update($sql, $params);
        setFlash('success', 'Noticia actualizada.');
        redirect('admin/news');
    }

    public function delete($id) {
        if (!isAdmin()) redirect('dashboard');
        $this->db->delete("DELETE FROM news WHERE id = ?", [$id]);
        setFlash('success', 'Noticia eliminada.');
        redirect('admin/news');
    }

    public function getPublished($page = 1, $category = null) {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $params = [];
        $where = "WHERE n.is_published = 1 AND (n.publish_date IS NULL OR n.publish_date <= NOW())";

        if ($category) {
            $where .= " AND c.slug = ?";
            $params[] = $category;
        }

        $total = $this->db->fetch(
            "SELECT COUNT(*) as total FROM news n LEFT JOIN news_categories c ON n.category_id = c.id $where",
            $params
        )['total'];

        $params[] = ITEMS_PER_PAGE;
        $params[] = $offset;

        $news = $this->db->fetchAll(
            "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color, u.username as author_name, u.avatar as author_avatar
             FROM news n 
             LEFT JOIN news_categories c ON n.category_id = c.id 
             LEFT JOIN users u ON n.author_id = u.id
             $where 
             ORDER BY n.is_featured DESC, n.publish_date DESC, n.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );

        return ['news' => $news, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];
    }

    public function getBySlug($slug) {
        $article = $this->db->fetch(
            "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color, u.username as author_name, u.avatar as author_avatar
             FROM news n 
             LEFT JOIN news_categories c ON n.category_id = c.id 
             LEFT JOIN users u ON n.author_id = u.id
             WHERE n.slug = ?",
            [$slug]
        );
        if ($article) {
            $this->db->update("UPDATE news SET views = views + 1 WHERE id = ?", [$article['id']]);
        }
        return $article;
    }

    public function getFeatured($limit = 3) {
        return $this->db->fetchAll(
            "SELECT n.*, c.name as category_name, c.color as category_color, u.username as author_name
             FROM news n 
             LEFT JOIN news_categories c ON n.category_id = c.id 
             LEFT JOIN users u ON n.author_id = u.id
             WHERE n.is_published = 1 AND n.is_featured = 1
             ORDER BY n.publish_date DESC 
             LIMIT ?",
            [$limit]
        );
    }

    public function getCategories() {
        return $this->db->fetchAll("SELECT * FROM news_categories ORDER BY name");
    }

    public function getAll($page = 1) {
        $offset = ($page - 1) * ITEMS_PER_PAGE;
        $total = $this->db->fetch("SELECT COUNT(*) as total FROM news")['total'];
        $news = $this->db->fetchAll(
            "SELECT n.*, c.name as category_name, u.username as author_name
             FROM news n
             LEFT JOIN news_categories c ON n.category_id = c.id
             LEFT JOIN users u ON n.author_id = u.id
             ORDER BY n.created_at DESC
             LIMIT ? OFFSET ?",
            [ITEMS_PER_PAGE, $offset]
        );
        return ['news' => $news, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE)];
    }

    private function uploadImage($file) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) return null;
        if ($file['size'] > MAX_UPLOAD_SIZE) return null;

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'news_' . time() . '_' . uniqid() . '.' . $ext;
        $uploadDir = UPLOAD_PATH . 'news/';
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return 'news/' . $filename;
        }
        return null;
    }
}
