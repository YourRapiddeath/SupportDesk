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

$ticket = new Ticket();
$ticketId = $_GET['id'] ?? 0;

$ticketData = $ticket->getById($ticketId);

if (!$ticketData) {
    redirect(SITE_URL . '/index.php');
}

// Check access rights
$isOwner = $ticketData['user_id'] == $_SESSION['user_id'];
$isSupport = in_array($_SESSION['role'], ['first_level', 'second_level', 'third_level', 'admin']);

if (!$isOwner && !$isSupport) {
    redirect(SITE_URL . '/index.php');
}

$messages = $ticket->getMessages($ticketId, $isSupport);
$history = $ticket->getHistory($ticketId);

// Alle Nachrichten dieses Tickets für den aktuellen Nutzer als gelesen markieren
$ticket->markMessagesAsRead($ticketId, $_SESSION['user_id']);

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_message'])) {
    $message = $_POST['message'] ?? '';
    if (!empty($message)) {
        $ticket->addMessage($ticketId, $_SESSION['user_id'], $message, false);
        redirect(SITE_URL . "/tickets/view-ticket.php?id=$ticketId");
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= escape($ticketData['ticket_code']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="ticket-code-display">
            <h2><?= escape($ticketData['ticket_code']) ?></h2>
            <p><?= escape($ticketData['subject']) ?></p>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Main Content -->
            <div>
                <!-- Ticket Details -->
                <div class="card">
                    <div class="card-header">Ticket-Details</div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <strong>Beschreibung:</strong><br>
                            <?= nl2br(escape($ticketData['description'])) ?>
                        </div>
                        <?php
                        $cfHelper = new CustomFields();
                        $cfFieldsWithVals = $cfHelper->getFieldsWithValues((int)$ticketId);
                        $cfHtml = CustomFields::renderValues($cfFieldsWithVals);
                        if ($cfHtml): ?>
                        <div style="padding-top:.75rem; border-top:1px solid var(--border-color,#e5e7eb);">
                            <?= $cfHtml ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 0.5rem;">
                            <strong>Erstellt von:</strong> <?= escape($ticketData['user_name']) ?>
                        </div>

                        <div style="margin-bottom: 0.5rem;">
                            <strong>Erstellt am:</strong> <?= formatDate($ticketData['created_at']) ?>
                        </div>

                        <?php if ($ticketData['assigned_name']): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Zugewiesen an:</strong> <?= escape($ticketData['assigned_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Nachrichten</div>
                    <div class="card-body">
                        <div class="message-list">
                            <?php if (empty($messages)): ?>
                                <p style="color: var(--text-light);">Noch keine Nachrichten vorhanden.</p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message <?= $msg['is_internal'] ? 'internal' : '' ?>">
                                        <div class="message-header">
                                            <div class="msg-author-row">
                                                <?php if (!empty($msg['user_avatar'])): ?>
                                                    <img src="<?= SITE_URL ?>/<?= escape($msg['user_avatar']) ?>"
                                                         alt="" class="msg-avatar">
                                                <?php else: ?>
                                                    <div class="msg-avatar msg-avatar-placeholder">
                                                        <?= mb_strtoupper(mb_substr($msg['user_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="msg-author-info">
                                                    <span class="message-author">
                                                        <?= escape($msg['user_name']) ?>
                                                        <?php if ($msg['is_internal']): ?>
                                                            <span class="badge badge-urgent">INTERN</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if (!empty($msg['user_bio'])): ?>
                                                        <span class="msg-bio"><?= escape($msg['user_bio']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <span class="msg-date"><?= formatDate($msg['created_at']) ?></span>
                                        </div>
                                        <div class="message-content">
                                            <?= nl2br(escape($msg['message'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($ticketData['status'] !== 'closed'): ?>
                            <form method="POST">
                                <div class="form-group">
                                    <label class="form-label"><?= $translator->translate('ticketview_new_message') ?></label>
                                    <textarea name="message" class="form-control" rows="4" required></textarea>
                                </div>
                                <button type="submit" name="add_message" class="btn btn-primary">
                                    <?= $translator->translate('ticketview_send_message') ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header"><?= $translator->translate('tickets_status_current') ?></div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <strong><?= $translator->translate('tickets_status_current') ?>:</strong><br>
                            <span class="badge badge-<?= $ticketData['status'] ?>">
                                <?= translateStatus($ticketData['status']) ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <strong><?= $translator->translate('tickets_priority') ?>:</strong><br>
                            <span class="badge badge-<?= $ticketData['priority'] ?>">
                                <?= translatePriority($ticketData['priority']) ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <strong><?= $translator->translate('ticketview_support_level') ?></strong><br>
                            <span class="badge">
                                <?= translateLevel($ticketData['support_level']) ?>
                            </span>
                        </div>

                        <?php if ($isSupport): ?>
                            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border);">
                            <a href="<?= SITE_URL ?>/support/manage-ticket.php?id=<?= $ticketId ?>" class="btn btn-sm btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                                <?= $translator->translate('btn_ticketview_manage') ?>
                            </a>
                        <?php endif; ?>

                        <a href="<?= SITE_URL ?>/index.php" class="btn btn-sm btn-secondary" style="width: 100%;">
                            <?= $translator->translate('back_to_dahboard') ?>
                        </a>
                    </div>
                </div>

                <?php if ($isSupport): ?>
                    <div class="card">
                        <div class="card-header"><?= $translator->translate('ticketview_history') ?></div>
                        <div class="card-body">
                            <?php if (empty($history)): ?>
                                <p style="color: var(--text-light); font-size: 0.875rem;"><?= $translator->translate('ticketview_no_history') ?></p>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); font-size: 0.875rem;">
                                        <strong><?= escape($h['action']) ?></strong><br>
                                        <?php if ($h['old_value']): ?>
                                            <?= escape($h['old_value']) ?> → <?= escape($h['new_value']) ?><br>
                                        <?php endif; ?>
                                        <span style="color: var(--text-light);">
                                            <?= escape($h['user_name']) ?> • <?= formatDate($h['created_at']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
