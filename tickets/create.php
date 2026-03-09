<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/Ticket.php';
require_once '../includes/Email.php';
require_once '../includes/Discord.php';
require_once '../includes/functions.php';
require_once '../includes/CustomFields.php';

requireLogin();

$db = Database::getInstance()->getConnection();
$catStmt = $db->query("SELECT id, name, color FROM ticket_categories ORDER BY name ASC");
$categories = $catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$customFields = new CustomFields();
$activeFields = $customFields->getActiveFields(false); // alle aktiven (intern)

$error = '';
$success = false;
$ticketData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject     = $_POST['subject']      ?? '';
    $description = $_POST['description'] ?? '';
    $categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    $cfErrors = $customFields->validatePost($_POST, false);

    if (empty($subject) || empty($description)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif (!empty($cfErrors)) {
        $error = implode('<br>', $cfErrors);
    } else {
        $ticket = new Ticket();
        $ticketData = $ticket->create($_SESSION['user_id'], $subject, $description, 'medium', $categoryId);

        if ($ticketData) {
            // Custom-Field-Werte speichern
            $customFields->saveFromPost($ticketData['id'], $_POST, false);
            $success = true;
        } else {
            $error = 'Fehler beim Erstellen des Tickets.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Ticket erstellen - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1><?=$translator->translate('tickets_create_new_title')?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <?php if ($success && $ticketData): ?>
            <div class="ticket-code-display">
                <h2><?= escape($ticketData['ticket_code']) ?></h2>
                <p><?=$translator->translate('tickets_create_susses')?></p>
                <p><?=$translator->translate('tickets_create_susses_info')?></p>
            </div>

            <div class="alert alert-success">
                <strong><?=$translator->translate('tickets_create_susses')?></strong><br>
                <?=$translator->translate('tickets_create_susses_info_code', ["s" => escape($ticketData['ticket_code'])])?>
            </div>

            <div style="margin-top: 1.5rem;">
                <a href="<?= SITE_URL ?>/tickets/view-ticket.php?id=<?= $ticketData['id'] ?>" class="btn btn-primary">
                    <?=$translator->translate('btn_show_ticket')?>
                </a>
                <a href="<?= SITE_URL ?>/tickets/create.php" class="btn btn-secondary">
                    <?=$translator->translate('btn_create_other_ticket')?>
                </a>
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-secondary">
                    <?=$translator->translate('back_to_dahboard')?>
                </a>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('tickets_table_subject')?>: *</label>
                            <input type="text" name="subject" class="form-control"
                                   placeholder="Kurze Beschreibung Ihres Problems"
                                   value="<?= escape($_POST['subject'] ?? '') ?>" required>
                        </div>

                        <?php if (!empty($categories)): ?>
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('categories_form')?></label>
                            <select name="category_id" class="form-control">
                                <option value=""><?=$translator->translate('categories_no_form')?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"
                                        <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>
                                        style="border-left: 4px solid <?= escape($cat['color']) ?>">
                                        <?= escape($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('description')?>: *</label>
                            <textarea name="description" class="form-control"
                                      placeholder="<?=$translator->translate('tickets_create_new_description')?>"
                                      rows="8" required><?= escape($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <?php if (!empty($activeFields)): ?>
                        <hr style="margin:1.25rem 0; border-color:var(--border-color);">
                        <h4 style="margin:0 0 1rem; font-size:.95rem;">Weitere Informationen</h4>
                        <?= CustomFields::renderFields($activeFields, [], $_POST) ?>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary"><?=$translator->translate('btn_new_ticket')?></button>
                        <a href="<?= SITE_URL ?>/index.php" class="btn btn-secondary"><?=$translator->translate('cancel')?></a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
