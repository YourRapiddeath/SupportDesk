<?php
class KnowledgeBase {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        // Tabellen werden über database.sql angelegt – keine DDL im PHP-Code
    }
    public function canEdit($userId, $role) {
        if ($role === 'admin') return true;
        $stmt = $this->db->prepare("SELECT id FROM kb_editors WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    }

    public function getCategories() {
        $stmt = $this->db->query("
            SELECT c.*, u.full_name as creator_name,
                   COUNT(a.id) as article_count
            FROM kb_categories c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN kb_articles a ON a.category_id = c.id AND a.is_published = 1
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategoryById($id) {
        $stmt = $this->db->prepare("SELECT * FROM kb_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createCategory($name, $description, $icon, $sortOrder, $userId) {
        $stmt = $this->db->prepare("INSERT INTO kb_categories (name, description, icon, sort_order, created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([trim($name), trim($description), trim($icon) ?: '📁', (int)$sortOrder, $userId]);
        return $this->db->lastInsertId();
    }

    public function updateCategory($id, $name, $description, $icon, $sortOrder) {
        $stmt = $this->db->prepare("UPDATE kb_categories SET name=?, description=?, icon=?, sort_order=? WHERE id=?");
        return $stmt->execute([trim($name), trim($description), trim($icon) ?: '📁', (int)$sortOrder, $id]);
    }

    public function deleteCategory($id) {
        $stmt = $this->db->prepare("DELETE FROM kb_categories WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function getArticlesByCategory($categoryId, $publishedOnly = true) {
        $sql = "SELECT a.*, u.full_name as author_name, u2.full_name as updater_name
                FROM kb_articles a
                LEFT JOIN users u ON a.created_by = u.id
                LEFT JOIN users u2 ON a.updated_by = u2.id
                WHERE a.category_id = ?";
        if ($publishedOnly) $sql .= " AND a.is_published = 1";
        $sql .= " ORDER BY a.updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArticleById($id, $incrementView = false) {
        if ($incrementView) {
            $this->db->prepare("UPDATE kb_articles SET views = views + 1 WHERE id = ?")->execute([$id]);
        }
        $stmt = $this->db->prepare("
            SELECT a.*, c.name as category_name, c.icon as category_icon,
                   u.full_name as author_name, u2.full_name as updater_name
            FROM kb_articles a
            LEFT JOIN kb_categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN users u2 ON a.updated_by = u2.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createArticle($categoryId, $title, $content, $tags, $isPublished, $userId) {
        $stmt = $this->db->prepare("INSERT INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([(int)$categoryId, trim($title), $content, trim($tags), $isPublished ? 1 : 0, $userId]);
        return $this->db->lastInsertId();
    }

    public function updateArticle($id, $categoryId, $title, $content, $tags, $isPublished, $userId) {
        $stmt = $this->db->prepare("UPDATE kb_articles SET category_id=?, title=?, content=?, tags=?, is_published=?, updated_by=? WHERE id=?");
        return $stmt->execute([(int)$categoryId, trim($title), $content, trim($tags), $isPublished ? 1 : 0, $userId, $id]);
    }

    public function deleteArticle($id) {
        $stmt = $this->db->prepare("DELETE FROM kb_articles WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function searchArticles($query) {
        $like = '%' . $query . '%';
        $stmt = $this->db->prepare("
            SELECT a.*, c.name as category_name, c.icon as category_icon,
                   u.full_name as author_name
            FROM kb_articles a
            LEFT JOIN kb_categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.is_published = 1
              AND (a.title LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)
            ORDER BY a.updated_at DESC
            LIMIT 50
        ");
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEditors() {
        $stmt = $this->db->query("
            SELECT ke.*, u.full_name, u.username, u.role,
                   g.full_name as granted_by_name
            FROM kb_editors ke
            JOIN users u ON ke.user_id = u.id
            LEFT JOIN users g ON ke.granted_by = g.id
            ORDER BY ke.granted_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addEditor($userId, $grantedBy) {
        $stmt = $this->db->prepare("INSERT IGNORE INTO kb_editors (user_id, granted_by) VALUES (?,?)");
        return $stmt->execute([$userId, $grantedBy]);
    }

    public function removeEditor($userId) {
        $stmt = $this->db->prepare("DELETE FROM kb_editors WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    public function getAllStats() {
        $cats    = $this->db->query("SELECT COUNT(*) FROM kb_categories")->fetchColumn();
        $arts    = $this->db->query("SELECT COUNT(*) FROM kb_articles WHERE is_published = 1")->fetchColumn();
        $drafts  = $this->db->query("SELECT COUNT(*) FROM kb_articles WHERE is_published = 0")->fetchColumn();
        $views   = $this->db->query("SELECT COALESCE(SUM(views),0) FROM kb_articles")->fetchColumn();
        return compact('cats','arts','drafts','views');
    }
}

