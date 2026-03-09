<?php

global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();
requireRole('admin');

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Handle adding new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#3b82f6';

    if (empty($name)) {
        $error = $translator->translate("error_category_name");
    } else {
        $stmt = $db->prepare("INSERT INTO ticket_categories (name, description, color) VALUES (?, ?, ?)");
        if ($stmt->execute([$name, $description, $color])) {
            $success = $translator->translate("category_added");
        } else {
            $error = $translator->translate("category_added_faild");
        }
    }
}

// Handle update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = $_POST['category_id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#3b82f6';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $error = $translator->translate("category_added_faild");
    } else {
        $stmt = $db->prepare("UPDATE ticket_categories SET name = ?, description = ?, color = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $color, $isActive, $id])) {
            $success = $translator->translate("category_updated");
        } else {
            $error = $translator->translate('error_reload_category');
        }
    }
}

// Handle delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $id = $_POST['category_id'] ?? 0;

    // Check if category has tickets
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE category_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        $error = $translator->translate("error_category_has_tickets", $result['count']);
        //$error = "Diese Kategorie kann nicht gelöscht werden, da {$result['count']} Ticket(s) damit verknüpft sind.";
    } else {
        $stmt = $db->prepare("DELETE FROM ticket_categories WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = $translator->translate("category_deleted");
        } else {
            $error = $translator->translate('error_category_delete');
        }
    }
}

// Get all categories
$stmt = $db->query("SELECT c.*, COUNT(t.id) as ticket_count
                    FROM ticket_categories c
                    LEFT JOIN tickets t ON c.id = t.category_id
                    GROUP BY c.id
                    ORDER BY c.name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket-Kategorien verwalten - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .category-preview {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            color: white;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .color-input-wrapper input[type="color"] {
            width: 50px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1><?=$translator->translate('categories')?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= escape($success) ?></div>
        <?php endif; ?>

        <!-- Add New Category -->
        <div class="card">
            <div class="card-header"><?=$translator->translate('add_new_categories')?></div>
            <div class="card-body">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 2fr 150px; gap: 1rem; align-items: end;">
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('category_name')?></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('category_description')?></label>
                            <input type="text" name="description" class="form-control" placeholder="Optional">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('category_color')?></label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color" value="#3b82f6">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="add_category" class="btn btn-primary" style="margin-top: 0.5rem;">
                        <?php echo $translator->translate("btn_add_category")?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Categories List -->
        <div class="card">
            <div class="card-header"><?=$translator->translate('catehories_all', ["categories" => count($categories)])?></div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <p style="color: var(--text-light);"><?=$translator->translate('no_categories')?></p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?=$translator->translate("preview")?></th>
                                <th><?=$translator->translate('category_name')?></th>
                                <th><?=$translator->translate('category_description')?></th>
                                <th><?=$translator->translate('category_color')?></th>
                                <th><?=$translator->translate('category_tickets')?></th>
                                <th><?=$translator->translate('category_status')?></th>
                                <th><?=$translator->translate('category_actions')?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="category-preview" style="background-color: <?= escape($cat['color']) ?>;">
                                            <?= escape($cat['name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <input type="text" name="name" class="form-control"
                                                   style="display: inline-block; width: auto; min-width: 150px;"
                                                   value="<?= escape($cat['name']) ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="description" class="form-control"
                                               style="display: inline-block; width: 100%;"
                                               value="<?= escape($cat['description']) ?>">
                                    </td>
                                    <td>
                                        <input type="color" name="color" value="<?= escape($cat['color']) ?>"
                                               style="width: 50px; height: 30px; cursor: pointer;">
                                    </td>
                                    <td><strong><?= $cat['ticket_count'] ?></strong></td>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                                            <input type="checkbox" name="is_active" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                            <span style="font-size: 0.875rem;">Aktiv</span>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="submit" name="update_category" class="btn btn-sm btn-save"><?=$translator->translate("save")?></button>
                                        </form>

                                        <?php if ($cat['ticket_count'] == 0): ?>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm(<?=$translator->translate('category_delete_sure')?>);">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" name="delete_category" class="btn btn-sm btn-danger"><?=$translator->translate("delete")?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
