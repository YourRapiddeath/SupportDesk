<?php
class CategoryHelper {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get assigned category IDs for a user
     */
    public function getUserCategories($userId) {
        $stmt = $this->db->prepare("SELECT category_id FROM supporter_categories WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Check if user can access a specific category
     */
    public function canAccessCategory($userId, $categoryId) {
        // Admin can access all categories
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }

        // Regular users can access all categories
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
            return true;
        }

        // Support staff needs category assignment
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM supporter_categories
            WHERE user_id = ? AND category_id = ?
        ");
        $stmt->execute([$userId, $categoryId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Get WHERE clause condition for ticket filtering based on category permissions
     */
    public function getCategoryFilterCondition($userId, $role) {
        // Admin sees all tickets
        if ($role === 'admin') {
            return '';
        }

        // Regular users see only their tickets
        if ($role === 'user') {
            return '';
        }

        // Support staff - check if they have category restrictions
        $assignedCategories = $this->getUserCategories($userId);

        if (empty($assignedCategories)) {
            // No categories assigned = can't see any tickets
            return "AND t.id IS NULL"; // This will return no results
        }

        // Has assigned categories - filter by them
        $categoryIds = implode(',', array_map('intval', $assignedCategories));
        return "AND (t.category_id IN ($categoryIds) OR t.category_id IS NULL)";
    }

    /**
     * Check if user has any category restrictions
     */
    public function hasCategoryRestrictions($userId, $role) {
        if ($role === 'admin' || $role === 'user') {
            return false;
        }

        $assignedCategories = $this->getUserCategories($userId);
        return !empty($assignedCategories);
    }
}
?>
