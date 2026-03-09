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
$error = '';
$success = '';

// Handle category assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_categories'])) {
    $userId = $_POST['user_id'] ?? 0;
    $categories = $_POST['categories'] ?? [];

    // Delete existing assignments
    $stmt = $db->prepare("DELETE FROM supporter_categories WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Add new assignments
    if (!empty($categories)) {
        $stmt = $db->prepare("INSERT INTO supporter_categories (user_id, category_id) VALUES (?, ?)");
        foreach ($categories as $categoryId) {
            $stmt->execute([$userId, $categoryId]);
        }
    }

    $success = $translator->translate('supporters_categories_assigned');
}

// Handle adding new supporter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supporter'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'first_level';

    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif (strlen($password) < 6) {
        $error = $translator->translate('supporters_password_too_short');
    } elseif (!in_array($role, ['first_level', 'second_level', 'third_level', 'admin'])) {
        $error = $translator->translate('supporters_invalid_role');
    } else {
        if ($user->register($username, $email, $password, $fullName)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE username = ?");
            $stmt->execute([$role, $username]);
            $success = $translator->translate('supporters_user_added');
        } else {
            $error = $translator->translate('supporters_user_add_mailfail');
        }
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = $_POST['user_id'] ?? 0;
    $newRole = $_POST['new_role'] ?? '';

    if ($user->updateRole($userId, $newRole)) {
        $success = $translator->translate('supporters_user_role_updated');
    } else {
        $error = $translator->translate('supporters_user_role_update_failed');
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'] ?? 0;

    if ($user->delete($userId)) {
        $success = $translator->translate('supporters_user_deleted');
    } else {
        $error = $translator->translate('supporters_user_delete_failed');
    }
}

// Get all supporters with their assigned categories
$stmt = $db->query("
    SELECT u.*,
           GROUP_CONCAT(sc.category_id) as assigned_category_ids
    FROM users u
    LEFT JOIN supporter_categories sc ON u.id = sc.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$supporters = $stmt->fetchAll();

// Get all categories
$stmt = $db->query("SELECT * FROM ticket_categories WHERE is_active = 1 ORDER BY name");
$allCategories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$translator->translate('supporter_title')?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1><?=$translator->translate('supporters_title')?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>

        <!-- Add New Supporter -->
        <div class="card">
            <div class="card-header"><?=$translator->translate('add_new_supporter_title')?></div>
            <div class="card-body">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('supporters_full_name')?></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('supporters_username')?>></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('supporters_email')?></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('supporters_password')?></label>
                            <input type="password" name="password" class="form-control" required>
                            <small style="color: var(--text-light);"><?=$translator->translate('supporters_password_rules')?></small>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('supporters_role')?></label>
                            <select name="role" class="form-control" required>
                                <option value="first_level">First Level Support</option>
                                <option value="second_level">Second Level Support</option>
                                <option value="third_level">Third Level Support</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_supporter" class="btn btn-primary" style="margin-top: 1rem;">
                        <?=$translator->translate('btn_add_supporter')?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Supporters List -->
        <div class="card">
            <div class="card-header">
                <?=$translator->translate('supporters_all', ["supporters" => count($supporters)])?></div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?=$translator->translate('supporters_table_name')?></th>
                            <th><?=$translator->translate('supporters_table_username')?></th>
                            <th><?=$translator->translate('supporters_table_email')?></th>
                            <th><?=$translator->translate('supporters_table_role')?></th>
                            <th><?=$translator->translate('supporters_table_categories')?></th>
                            <th><?=$translator->translate('supporters_table_registered')?></th>
                            <th><?=$translator->translate('supporters_table_actions')?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supporters as $s): ?>
                            <tr>
                                <td><?= escape($s['full_name']) ?></td>
                                <td><?= escape($s['username']) ?></td>
                                <td><?= escape($s['email']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                        <select name="new_role" class="form-control" style="display: inline-block; width: auto; padding: 0.25rem 0.5rem;"
                                                onchange="this.form.submit()">
                                            <option value="user" <?= $s['role'] === 'user' ? 'selected' : '' ?>>Benutzer</option>
                                            <option value="first_level" <?= $s['role'] === 'first_level' ? 'selected' : '' ?>>First Level</option>
                                            <option value="second_level" <?= $s['role'] === 'second_level' ? 'selected' : '' ?>>Second Level</option>
                                            <option value="third_level" <?= $s['role'] === 'third_level' ? 'selected' : '' ?>>Third Level</option>
                                            <option value="admin" <?= $s['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" style="display: none;"></button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (in_array($s['role'], ['first_level', 'second_level', 'third_level'])): ?>
                                        <?php
                                        $assignedCategoryIds = $s['assigned_category_ids'] ? explode(',', $s['assigned_category_ids']) : [];
                                        if (!empty($assignedCategoryIds)):
                                            $assignedCats = array_filter($allCategories, function($cat) use ($assignedCategoryIds) {
                                                return in_array($cat['id'], $assignedCategoryIds);
                                            });
                                        ?>
                                            <?php foreach ($assignedCats as $cat): ?>
                                                <span class="badge" style="background-color: <?= escape($cat['color']) ?>; color: white; margin-right: 0.25rem;">
                                                    <?= escape($cat['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-light); font-size: 0.85rem;"><?=$translator->translate('supporters_no_categories')?></span>
                                        <?php endif; ?>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                onclick="openCategoryModal(<?= $s['id'] ?>, '<?= escape($s['full_name']) ?>', '<?= $s['assigned_category_ids'] ?>')"
                                                style="margin-top: 0.5rem;">
                                            <?=$translator->translate('supporters_assign_categories')?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 0.85rem;">
                                            <?= $s['role'] === 'admin' ? 'Alle Kategorien' : 'N/A' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($s['created_at']) ?></td>
                                <td>
                                    <?php if ($s['username'] !== 'admin' || $s['role'] !== 'admin'): ?>
                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm(<?=$translator->translate('supporters_delete_confirm')?>));">
                                            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger"><?=$translator->translate('delete')?></button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 0.85rem;"><?=$translator->translate('supporters_account_save')?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">
                        Abbrechen
                    </button>
                    <button type="submit" name="assign_categories" class="btn btn-primary">
                        Zuweisen
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
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
