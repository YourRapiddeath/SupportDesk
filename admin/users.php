<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();
requireRole('admin');

$user = new User();
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle category assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_categories'])) {
    $userId = $_POST['user_id'] ?? 0;
    $categories = $_POST['categories'] ?? [];

    $stmt = $db->prepare("DELETE FROM supporter_categories WHERE user_id = ?");
    $stmt->execute([$userId]);

    if (!empty($categories)) {
        $stmt = $db->prepare("INSERT INTO supporter_categories (user_id, category_id) VALUES (?, ?)");
        foreach ($categories as $categoryId) {
            $stmt->execute([$userId, $categoryId]);
        }
    }

    $message = $translator->translate('supporters_categories_assigned');
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = $_POST['user_id'];
    $newRole = $_POST['role'];

    if ($user->updateRole($userId, $newRole)) {
        $message = $translator->translate('supporters_role_updated');
    } else {
        $error = $translator->translate('supporters_role_update_fail');
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];

    if ($user->delete($userId)) {
        $message = $translator->translate('supporters_user_deleted');
    } else {
        $error = $translator->translate('supporters_user_delete_fail');
    }
}

// Get all users with their assigned categories
$stmt = $db->query("
    SELECT u.*,
           GROUP_CONCAT(sc.category_id) as assigned_category_ids
    FROM users u
    LEFT JOIN supporter_categories sc ON u.id = sc.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Get all categories
$stmt = $db->query("SELECT * FROM ticket_categories WHERE is_active = 1 ORDER BY name");
$allCategories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .category-info {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .category-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 0.5rem;
            padding: 0.75rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s, visibility 0.2s;
            white-space: nowrap;
            min-width: 200px;
        }

        .category-info:hover .category-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .category-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--border);
        }

        .category-count {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-light);
            color: white;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .category-tooltip-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1>Benutzerverwaltung</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><?= $translator->translate('users_all') ?></div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= $translator->translate('supporters_table_username') ?></th>
                            <th><?= $translator->translate('supporters_table_name') ?></th>
                            <th><?= $translator->translate('supporters_table_role') ?></th>
                            <th><?= $translator->translate('supporters_table_categories') ?></th>
                            <th><?= $translator->translate('supporters_table_registered') ?></th>
                            <th><?= $translator->translate('users_last_login') ?></th>
                            <th><?= $translator->translate('tickets_table_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= escape($u['username']) ?><br><small><small><?= escape($u['email']) ?></small></small></td>
                                <td><?= escape($u['full_name']) ?></td>
                                <td>
                                    <form method="POST" id="save_role_<?= $u['id'] ?>" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="role" class="form-control" style="display: inline-block; width: auto; padding: 0.25rem 0.5rem;">
                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>><?= $translator->translate('role_user') ?></option>
                                            <option value="first_level" <?= $u['role'] === 'first_level' ? 'selected' : '' ?>><?= $translator->translate('level_first_level') ?></option>
                                            <option value="second_level" <?= $u['role'] === 'second_level' ? 'selected' : '' ?>><?= $translator->translate('level_second_level') ?></option>
                                            <option value="third_level" <?= $u['role'] === 'third_level' ? 'selected' : '' ?>><?= $translator->translate('level_third_level') ?></option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>><?= $translator->translate('role_admin') ?></option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if (in_array($u['role'], ['first_level', 'second_level', 'third_level'])): ?>
                                        <?php
                                        $assignedCategoryIds = $u['assigned_category_ids'] ? explode(',', $u['assigned_category_ids']) : [];
                                        $categoryCount = count($assignedCategoryIds);
                                        if (!empty($assignedCategoryIds)):
                                            $assignedCats = array_filter($allCategories, function($cat) use ($assignedCategoryIds) {
                                                return in_array($cat['id'], $assignedCategoryIds);
                                            });
                                        ?>
                                            <div class="category-info">
                                                <span class="category-count">
                                                    📁 <?= $categoryCount ?> <?= $translator->translate('users_categories_count') ?>
                                                </span>
                                                <div class="category-tooltip">
                                                    <strong style="display: block; margin-bottom: 0.5rem; color: var(--text);"><?= $translator->translate('supporters_table_categories') ?>:</strong>
                                                    <?php foreach ($assignedCats as $cat): ?>
                                                        <div class="category-tooltip-item">
                                                            <span class="badge" style="background-color: <?= escape($cat['color']) ?>; color: white; font-size: 0.75rem;">
                                                                <?= escape($cat['name']) ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-size: 0.85rem;"><?= $translator->translate('supporters_no_categories') ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                onclick="openCategoryModal(<?= $u['id'] ?>, '<?= escape($u['full_name']) ?>', '<?= $u['assigned_category_ids'] ?>')"
                                                style="margin-top: 0.5rem; font-size: 0.75rem;">
                                            <?= $translator->translate('update') ?>
                                        </button>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <span style="color: var(--text-light); font-size: 0.85rem;"><?= $translator->translate('users_all_categories') ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 0.85rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($u['created_at']) ?></td>
                                <td><?= $u['last_login'] ? formatDate($u['last_login']) : $translator->translate('users_never') ?></td>
                                <td>
                                    <?php if ($u['username'] !== 'admin' || $u['role'] !== 'admin'): ?>
                                        <button type="submit" name="update_role" class="btn btn-save btn-sm" form="save_role_<?= $u['id'] ?>" style="width: 100%;"><?= $translator->translate('save') ?></button><br>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?= addslashes($translator->translate('supporters_delete_confirm')) ?>');">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger" style="width: 100%;"><?= $translator->translate('delete') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-secondary"><?= $translator->translate('back_to_dahboard') ?></a>
        </div>
    </div>

    <!-- Category Assignment Modal -->
    <div id="categoryModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--surface); border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 1rem;"><?= $translator->translate('supporters_assign_categories') ?></h3>
            <p style="color: var(--text-light); margin-bottom: 1.5rem;">
                <?= $translator->translate('modal_for') ?>: <strong id="modal-user-name"></strong>
            </p>

            <form method="POST" id="categoryForm">
                <input type="hidden" name="user_id" id="modal-user-id">

                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">
                        <?= $translator->translate('modal_select_categories') ?>
                    </label>
                    <?php if (!empty($allCategories)): ?>
                        <?php foreach ($allCategories as $cat): ?>
                            <div style="margin-bottom: 0.75rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px;">
                                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                                    <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" class="category-checkbox"
                                           style="width: 18px; height: 18px;">
                                    <span class="badge" style="background-color: <?= escape($cat['color']) ?>; color: white;">
                                        <?= escape($cat['name']) ?>
                                    </span>
                                    <?php if ($cat['description']): ?>
                                        <span style="color: var(--text-light); font-size: 0.85rem;">
                                            - <?= escape($cat['description']) ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-light);"><?= $translator->translate('users_no_categories_available') ?></p>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">
                        <?= $translator->translate('cancel') ?>
                    </button>
                    <button type="submit" name="assign_categories" class="btn btn-primary">
                        <?= $translator->translate('btn_add_supporter') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openCategoryModal(userId, userName, assignedCategoryIds) {
        document.getElementById('modal-user-id').value = userId;
        document.getElementById('modal-user-name').textContent = userName;

        // Uncheck all checkboxes first
        document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);

        // Check assigned categories
        if (assignedCategoryIds) {
            const assignedIds = assignedCategoryIds.split(',');
            assignedIds.forEach(id => {
                const checkbox = document.querySelector(`.category-checkbox[value="${id}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }

        const modal = document.getElementById('categoryModal');
        modal.style.display = 'flex';
    }

    function closeCategoryModal() {
        document.getElementById('categoryModal').style.display = 'none';
    }

    // Close modal when clicking outside
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCategoryModal();
        }
    });
    </script><?php include '../includes/footer.php'; ?>
</body>
</html>
