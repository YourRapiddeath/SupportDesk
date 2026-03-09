<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/Ticket.php';
require_once '../includes/Email.php';
require_once '../includes/Discord.php';
require_once '../includes/functions.php';

requireLogin();
requireRole(['first_level', 'second_level', 'third_level', 'admin']);

$ticket = new Ticket();
$user = new User();
$ticketId = $_GET['id'] ?? 0;

$ticketData = $ticket->getById($ticketId);

if (!$ticketData) {
    redirect(SITE_URL . '/index.php');
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        $oldStatus = $ticketData['status'];
        $ticket->updateStatus($ticketId, $_SESSION['user_id'], $newStatus);
        $message = 'Status erfolgreich aktualisiert!';
        $ticketData = $ticket->getById($ticketId);
        // Discord Status-Benachrichtigung
        try {
            if (class_exists('Discord')) {
                (new Discord())->notifyStatusChange($ticketData, $oldStatus, $newStatus);
            }
        } catch (Exception $e) { error_log('[Discord] ' . $e->getMessage()); }
    } elseif (isset($_POST['assign_ticket'])) {
        $supporterId = $_POST['supporter_id'];
        $ticket->assignTicket($ticketId, $_SESSION['user_id'], $supporterId);
        $message = 'Ticket erfolgreich zugewiesen!';
        $ticketData = $ticket->getById($ticketId);
    } elseif (isset($_POST['forward_ticket'])) {
        $newLevel = $_POST['support_level'];
        $ticket->forwardTicket($ticketId, $_SESSION['user_id'], $newLevel);
        $message = 'Ticket erfolgreich weitergeleitet!';
        $ticketData = $ticket->getById($ticketId);
    }
}

$currentLevel = $ticketData['support_level'];
$supporters = $user->getSupporters($currentLevel);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket verwalten - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1><?=$translator->translate('tickets_manage_title')?></h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= escape($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="ticket-code-display">
            <h2><?= escape($ticketData['ticket_code']) ?></h2>
            <p><?= escape($ticketData['subject']) ?></p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="card">
                <div class="card-header"><?=$translator->translate('tickets_status_update')?></div>
                <div class="card-body">
                    <p>
                        <strong><?=$translator->translate('tickets_status_current')?></strong>
                        <span class="badge badge-<?= $ticketData['status'] ?>">
                            <?= translateStatus($ticketData['status']) ?>
                        </span>
                    </p>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('tickets_new_status')?></label>
                            <select name="status" class="form-control" required>
                                <option value="open" <?= $ticketData['status'] === 'open' ? 'selected' : '' ?>>
                                    <?= $translator->translate('tickets_status_open') ?>
                                </option>
                                <option value="in_progress" <?= $ticketData['status'] === 'in_progress' ? 'selected' : '' ?>>
                                    <?= $translator->translate('tickets_in_progress') ?>
                                </option>
                                <option value="pending" <?= $ticketData['status'] === 'pending' ? 'selected' : '' ?>>
                                    <?= $translator->translate('tickets_status_pending') ?>
                                </option>
                                <option value="resolved" <?= $ticketData['status'] === 'resolved' ? 'selected' : '' ?>>
                                    <?= $translator->translate('tickets_status_resolved') ?>
                                </option>
                                <option value="closed" <?= $ticketData['status'] === 'closed' ? 'selected' : '' ?>>
                                    <?=$translator->translate('tickets_status_closed')?>
                                </option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary"><?=$translator->translate('btn_tickets_update_status')?></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?=$translator->translate('tickets_assign')?></div>
                <div class="card-body">
                    <p>
                        <strong><?=$translator->translate('tickets_assign_to')?></strong>
                        <?= $ticketData['assigned_name'] ? escape($ticketData['assigned_name']) : 'Nicht zugewiesen' ?>
                    </p>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('supporter_select') ?></label>
                            <select name="supporter_id" class="form-control" required>
                                <option value=""><?=$translator->translate('tickets_please_select')?>></option>
                                <?php foreach ($supporters as $supporter): ?>
                                    <option value="<?= $supporter['id'] ?>"
                                            <?= $ticketData['assigned_to'] == $supporter['id'] ? 'selected' : '' ?>>
                                        <?= escape($supporter['full_name']) ?> (<?= escape($supporter['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_ticket" class="btn btn-success"><?=$translator->translate('btn_tickets_assign')?></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?=$translator->translate('tickets_forward')?></div>
                <div class="card-body">
                    <p>
                        <strong>Aktuelles Level:</strong>
                        <span class="badge"><?= translateLevel($ticketData['support_level']) ?></span>
                    </p>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?=$translator->translate('tickets_forward_to')?></label>
                            <select name="support_level" class="form-control" required>
                                <option value="first_level" <?= $ticketData['support_level'] === 'first_level' ? 'selected' : '' ?>>
                                    First Level Support
                                </option>
                                <option value="second_level" <?= $ticketData['support_level'] === 'second_level' ? 'selected' : '' ?>>
                                    Second Level Support
                                </option>
                                <option value="third_level" <?= $ticketData['support_level'] === 'third_level' ? 'selected' : '' ?>>
                                    Third Level Support
                                </option>
                            </select>
                        </div>
                        <button type="submit" name="forward_ticket" class="btn btn-warning"><?=$translator->translate('btn_tickets_forward')?></button>
                    </form>

                    <div class="alert alert-info" style="margin-top: 1rem; margin-bottom: 0;">
                        <?= $translator->translate('tickets_info_forward') ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?=$translator->translate('tickets_informations')?></div>
                <div class="card-body">
                    <div style="margin-bottom: 0.75rem;">
                        <strong><?=$translator->translate('tickets_created_by')?></strong><br>
                        <?= escape($ticketData['user_name']) ?><br>
                        <a href="mailto:<?= escape($ticketData['user_email']) ?>"><?= escape($ticketData['user_email']) ?></a>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <strong><?=$translator->translate('tickets_priority')?></strong><br>
                        <span class="badge badge-<?= $ticketData['priority'] ?>">
                            <?= translatePriority($ticketData['priority']) ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <strong><?=$translator->translate('tickets_created_at')?></strong><br>
                        <?= formatDate($ticketData['created_at']) ?>
                    </div>

                    <div style="margin-bottom: 0.75rem;">
                        <strong><?=$translator->translate('tickets_last_update')?></strong><br>
                        <?= formatDate($ticketData['updated_at']) ?>
                    </div>

                    <?php if ($ticketData['resolved_at']): ?>
                        <div style="margin-bottom: 0.75rem;">
                            <strong><?=$translator->translate('tickets_resolved_at')?></strong><br>
                            <?= formatDate($ticketData['resolved_at']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <a href="<?= SITE_URL ?>/support/view-ticket.php?id=<?= $ticketId ?>" class="btn btn-secondary">
                <?=$translator->translate('back_to_ticket')?>
            </a>
            <a href="<?= SITE_URL ?>/support/tickets.php" class="btn btn-secondary">
                <?=$translator->translate('back_to_ticket_overview')?>
            </a>
        </div>
    </div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
