<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/Ticket.php';
require_once '../includes/Email.php';
require_once '../includes/Discord.php';
require_once '../includes/functions.php';
require_once '../includes/GitIntegration.php';
require_once '../includes/YouTrackIntegration.php';
require_once '../includes/CustomFields.php';

requireLogin();
requireRole(['first_level', 'second_level', 'third_level', 'admin']);

$ticket = new Ticket();
$ticketId = $_GET['id'] ?? 0;

$ticketData = $ticket->getById($ticketId);

if (!$ticketData) {
    redirect(SITE_URL . '/index.php');
}

$ticket->markMessagesAsRead($ticketId, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ticket annehmen (mir zuweisen)
    if (isset($_POST['accept_ticket'])) {
        $ticket->assignTicket($ticketId, $_SESSION['user_id'], $_SESSION['user_id']);
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId&msg=accepted");
    }

    // Priorität ändern
    if (isset($_POST['update_priority'])) {
        $newPriority = $_POST['priority'] ?? 'medium';
        $allowed = ['low', 'medium', 'high', 'urgent'];
        if (in_array($newPriority, $allowed)) {
            $db = Database::getInstance()->getConnection();
            $old = $ticketData['priority'];
            $stmt = $db->prepare("UPDATE tickets SET priority = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newPriority, $ticketId]);
            // History
            $hist = $db->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value) VALUES (?,?,?,?,?)");
            $hist->execute([$ticketId, $_SESSION['user_id'], 'Priorität geändert', translatePriority($old), translatePriority($newPriority)]);
            // PM an zugewiesenen Supporter
            if (!empty($ticketData['assigned_to']) && $ticketData['assigned_to'] != $_SESSION['user_id']) {
                $actorStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                $actorStmt->execute([$_SESSION['user_id']]);
                $actorName  = $actorStmt->fetchColumn() ?: 'Unbekannt';
                $ticketUrl  = SITE_URL . "/support/view-ticket.php?id={$ticketId}";
                $catName    = $ticketData['category_name'] ?? '–';
                $pmBody     =
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                    "🎫  {$ticketData['ticket_code']}  –  {$ticketData['subject']}\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "📌 WAS IST PASSIERT?\n" .
                    "   Priorität wurde geändert\n\n" .
                    "📋 DETAILS:\n" .
                    "   " . translatePriority($old) . "  →  " . translatePriority($newPriority) . "\n\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                    "👤 Ausgelöst von:  {$actorName}\n" .
                    "🔖 Status:         " . translateStatus($ticketData['status']) . "\n" .
                    "⚡ Priorität:      " . translatePriority($newPriority) . "\n" .
                    "📂 Kategorie:      {$catName}\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "🔗 TICKET ÖFFNEN:\n" .
                    $ticketUrl;
                $ticket->notifyPM(
                    (int)$ticketData['assigned_to'],
                    0,
                    "⚡ Priorität geändert: {$ticketData['ticket_code']}",
                    $pmBody,
                    $ticketId
                );
            }
        }
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId&msg=priority");
    }

    if (isset($_POST['update_level'])) {
        $newLevel = $_POST['support_level'] ?? 'first_level';
        $allowed = ['first_level', 'second_level', 'third_level'];
        if (in_array($newLevel, $allowed)) {
            $ticket->forwardTicket($ticketId, $_SESSION['user_id'], $newLevel);
        }
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId&msg=level");
    }

    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'] ?? 'open';
        $allowed = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
        if (in_array($newStatus, $allowed)) {
            $oldStatus = $ticketData['status'];
            $ticket->updateStatus($ticketId, $_SESSION['user_id'], $newStatus);
            try {
                if (class_exists('Discord')) {
                    $updatedTicket = $ticket->getById($ticketId);
                    (new Discord())->notifyStatusChange($updatedTicket, $oldStatus, $newStatus);
                }
            } catch (Exception $e) { error_log('[Discord] ' . $e->getMessage()); }
        }
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId&msg=status");
    }

    // Interne Notiz hinzufügen
    if (isset($_POST['add_note'])) {
        $note = trim($_POST['note'] ?? '');
        if (!empty($note)) {
            $ticket->addInternalNote($ticketId, $_SESSION['user_id'], $note);
        }
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId&tab=notes");
    }

    // Nachricht senden + Auto-Zuweisung
    if (isset($_POST['add_message'])) {
        $message = $_POST['message'] ?? '';
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        if (!empty($message)) {
            // Auto-Zuweisung: wenn noch kein Supporter zugewiesen ist
            if (empty($ticketData['assigned_to']) && !$isInternal) {
                $ticket->assignTicket($ticketId, $_SESSION['user_id'], $_SESSION['user_id']);
            }
            $ticket->addMessage($ticketId, $_SESSION['user_id'], $message, $isInternal);
        }
        redirect(SITE_URL . "/support/view-ticket.php?id=$ticketId");
    }
}

$ticketData = $ticket->getById($ticketId);
$messages = $ticket->getMessages($ticketId, true);
$internalNotes = $ticket->getInternalNotes($ticketId);
$history = $ticket->getHistory($ticketId);

// Sortierung
$sort = ($_GET['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
if ($sort === 'desc') {
    $messages = array_reverse($messages);
}

$actionMsg = '';
switch ($_GET['msg'] ?? '') {
    case 'accepted':  $actionMsg = $translator->translate('msg_ticket_accepted'); break;
    case 'priority':  $actionMsg = $translator->translate('msg_priority_updated'); break;
    case 'level':     $actionMsg = $translator->translate('msg_level_updated'); break;
    case 'status':    $actionMsg = $translator->translate('msg_status_updated'); break;
}
// Git-Integration
$gitIntegration   = new GitIntegration();
$gitIntegrations  = $gitIntegration->getAll(true);
$gitIssues        = $gitIntegration->getIssuesForTicket($ticketId);

// YouTrack-Integration
$ytIntegration   = new YouTrackIntegration();
$ytIntegrations  = $ytIntegration->getAll(true);
$ytIssues        = $ytIntegration->getIssuesForTicket($ticketId);
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
        <!--<div class="ticket-code-display">
            <h4><?= escape($ticketData['ticket_code']) ?></h4>
            <p><?= escape($ticketData['subject']) ?></p>
        </div>-->

        <?php if ($actionMsg): ?>
            <div class="alert alert-success"><?= escape($actionMsg) ?></div>
        <?php endif; ?>

        <div class="ticket-layout">
            <!-- Main Content -->
            <div>
                <!-- Ticket Details -->
                <div class="card">
                    <div class="card-header"><?=$translator->translate('ticketview_details')?></div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <strong><?=$translator->translate('ticketview_description')?></strong><br>
                            <?= nl2br(escape($ticketData['description'])) ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong><?=$translator->translate('ticketview_autor')?></strong> <?= escape($ticketData['user_name']) ?> (<?= escape($ticketData['user_email']) ?>)
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong><?=$translator->translate('ticketview_created')?></strong> <?= formatDate($ticketData['created_at']) ?>
                        </div>
                        <?php if ($ticketData['assigned_name']): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong><?=$translator->translate('ticketview_assign_to')?></strong> <?= escape($ticketData['assigned_name']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap:wrap;">
                            <span class="badge badge-<?= $ticketData['status'] ?>"><?= translateStatus($ticketData['status']) ?></span>
                            <span class="badge badge-<?= $ticketData['priority'] ?>"><?= translatePriority($ticketData['priority']) ?></span>
                            <span class="badge"><?= translateLevel($ticketData['support_level']) ?></span>
                        </div>
                        <?php
                        $cfHelper = new CustomFields();
                        $cfFieldsWithVals = $cfHelper->getFieldsWithValues((int)$ticketId);
                        $cfHtml = CustomFields::renderValues($cfFieldsWithVals);
                        if ($cfHtml): ?>
                        <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border-color,#e5e7eb);">
                            <?= $cfHtml ?>
                        </div>
                        <?php endif; ?>
                        <!--<div style="margin-top: 1rem;">
                            <a href="<?= SITE_URL ?>/support/manage-ticket.php?id=<?= $ticketData['id'] ?>" class="btn btn-sm btn-primary"><?= $translator->translate('btn_ticketview_manage') ?></a>
                            <a href="<?= SITE_URL ?>/support/tickets.php" class="btn btn-sm btn-secondary"><?= $translator->translate('back_to_overview') ?></a>
                        </div>-->
                    </div>
                </div>

                <!-- Messages -->
                <div class="card">
                    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
                        <span><?=$translator->translate('ticketview_public_messages')?></span>
                        <div style="display:flex; gap:0.4rem;">
                            <a href="?id=<?= $ticketId ?>&sort=asc<?= isset($_GET['tab']) ? '&tab='.$_GET['tab'] : '' ?>"
                               class="btn btn-sm <?= $sort === 'asc' ? 'btn-primary' : 'btn-secondary' ?>"
                               style="padding:0.2rem 0.6rem; font-size:0.75rem;">↑ <?= $translator->translate('ticketview_oldest_first') ?></a>
                            <a href="?id=<?= $ticketId ?>&sort=desc<?= isset($_GET['tab']) ? '&tab='.$_GET['tab'] : '' ?>"
                               class="btn btn-sm <?= $sort === 'desc' ? 'btn-primary' : 'btn-secondary' ?>"
                               style="padding:0.2rem 0.6rem; font-size:0.75rem;">↓ <?= $translator->translate('ticketview_newest_first') ?></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="message-list">
                            <?php if (empty($messages)): ?>
                                <p style="color: var(--text-light);"><?=$translator->translate('ticketview_no_public_messages')?></p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg):
                                    $roleClass = 'from-user'; $roleBadgeClass = 'user'; $roleBadgeText = $translator->translate('client');
                                    if ($msg['user_role'] === 'admin') { $roleClass = 'from-admin'; $roleBadgeClass = 'admin'; $roleBadgeText = $translator->translate('admin'); }
                                    elseif (in_array($msg['user_role'], ['first_level','second_level','third_level'])) { $roleClass = 'from-supporter'; $roleBadgeClass = 'supporter'; $roleBadgeText = $translator->translate('supporter'); }
                                ?>
                                    <div class="message <?= $roleClass ?> <?= $msg['is_internal'] ? 'message-internal' : '' ?>">
                                        <?php if ($msg['is_internal']): ?><div class="internal-feed-label">🔒 Interne Nachricht</div><?php endif; ?>
                                        <div class="message-header">
                                            <div class="msg-author-row">
                                                <?php if (!empty($msg['user_avatar'])): ?>
                                                    <img src="<?= SITE_URL ?>/<?= escape($msg['user_avatar']) ?>" alt="" class="msg-avatar">
                                                <?php else: ?>
                                                    <div class="msg-avatar msg-avatar-placeholder"><?= mb_strtoupper(mb_substr($msg['user_name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <div class="msg-author-info">
                                                    <span class="message-author"><?= escape($msg['user_name']) ?> <span class="role-badge <?= $roleBadgeClass ?>"><?= $roleBadgeText ?></span></span>
                                                    <?php if (!empty($msg['user_bio'])): ?><span class="msg-bio"><?= escape($msg['user_bio']) ?></span><?php endif; ?>
                                                </div>
                                            </div>
                                            <span class="msg-date"><?= formatDate($msg['created_at']) ?></span>
                                        </div>
                                        <div class="message-content"><?= nl2br(escape($msg['message'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="internal-feed">
                <div class="card" style="margin-bottom:0; overflow:hidden;">
                    <div class="tab-bar">
                        <button class="tab-btn active" onclick="switchTab(event,'tab-actions')">
                            <span class="tab-icon">⚙️</span>Aktionen
                        </button>
                        <button class="tab-btn" onclick="switchTab(event,'tab-internal')">
                            <span class="tab-icon">💬<?php $ic = count(array_filter($messages, function($m){ return $m['is_internal']; })); if ($ic > 0): ?><span class="tab-badge"><?= $ic ?></span><?php endif; ?></span>Intern
                        </button>
                        <button class="tab-btn" onclick="switchTab(event,'tab-notes')">
                            <span class="tab-icon">📝<?php if (!empty($internalNotes)): ?><span class="tab-badge"><?= count($internalNotes) ?></span><?php endif; ?></span>Notizen
                        </button>
                        <button class="tab-btn" onclick="switchTab(event,'tab-history')">
                            <span class="tab-icon">🕒<?php if (!empty($history)): ?><span class="tab-badge"><?= count($history) ?></span><?php endif; ?></span>Verlauf
                        </button>
                    </div>

                    <div id="tab-actions" class="tab-panel active">
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php if (empty($ticketData['assigned_to'])): ?>
                                <form method="POST" style="margin:0;"><button type="submit" name="accept_ticket" class="btn btn-success" style="width:100%; margin-bottom:0;">✋ <?= $translator->translate('btn_tickets_assign') ?></button></form>
                            <?php elseif ($ticketData['assigned_to'] == $_SESSION['user_id']): ?>
                                <div style="color:var(--success); font-weight:600; font-size:0.875rem;">✓ <?= $translator->translate('tickets_assign') ?></div>
                            <?php else: ?>
                                <div style="font-size:0.875rem; color:var(--text-light);"><?= $translator->translate('ticketview_assign_to') ?> <strong><?= escape($ticketData['assigned_name']) ?></strong></div>
                                <form method="POST" style="margin:0;"><button type="submit" name="accept_ticket" class="btn btn-secondary" style="width:100%; margin-bottom:0;"><?= $translator->translate('update') ?></button></form>
                            <?php endif; ?>
                            <hr style="border:none; border-top:1px solid var(--border); margin:0.25rem 0;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="update_status" value="1">
                                <label style="font-size:0.72rem; font-weight:600; display:block; margin-bottom:0.2rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px;"><?= $translator->translate('action_status_label') ?></label>
                                <select name="status" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.85rem;" onchange="this.form.submit()">
                                    <?php foreach (['open','in_progress','pending','resolved','closed'] as $s): ?><option value="<?= $s ?>" <?= $ticketData['status'] === $s ? 'selected' : '' ?>><?= translateStatus($s) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="update_priority" value="1">
                                <label style="font-size:0.72rem; font-weight:600; display:block; margin-bottom:0.2rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px;"><?= $translator->translate('action_priority_label') ?></label>
                                <select name="priority" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.85rem;" onchange="this.form.submit()">
                                    <?php foreach (['low','medium','high','urgent'] as $p): ?><option value="<?= $p ?>" <?= $ticketData['priority'] === $p ? 'selected' : '' ?>><?= translatePriority($p) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="update_level" value="1">
                                <label style="font-size:0.72rem; font-weight:600; display:block; margin-bottom:0.2rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px;"><?= $translator->translate('action_level_label') ?></label>
                                <select name="support_level" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.85rem;" onchange="this.form.submit()">
                                    <?php foreach (['first_level','second_level','third_level'] as $l): ?><option value="<?= $l ?>" <?= $ticketData['support_level'] === $l ? 'selected' : '' ?>><?= translateLevel($l) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                            <hr style="border:none; border-top:1px solid var(--border); margin:0.25rem 0;">
                            <div id="chat-share-box">
                                <button type="button"
                                        onclick="toggleChatShareBox()"
                                        class="btn btn-secondary"
                                        style="width:100%; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                                    💬 <?= $translator->translate('action_send_to_chat') ?>
                                </button>
                            </div>
                            <div id="chat-share-form" style="display:none; margin-top:0.5rem;">
                                <textarea id="chat-share-comment"
                                          class="form-control"
                                          rows="2"
                                          style="font-size:0.82rem; resize:none; margin-bottom:0.4rem;"
                                          placeholder="<?= $translator->translate('chat_share_comment_ph') ?>"></textarea>
                                <div style="display:flex; gap:0.4rem;">
                                    <button type="button" onclick="sendTicketToChat()"
                                            class="btn btn-primary" style="flex:1; font-size:0.82rem;">
                                        📤 <?= $translator->translate('chat_share_btn') ?>
                                    </button>
                                    <button type="button" onclick="toggleChatShareBox()"
                                            class="btn btn-secondary" style="font-size:0.82rem; padding:0 0.65rem;">
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <div id="chat-link-msg" style="display:none; font-size:0.78rem; color:var(--success); text-align:center; margin-top:0.25rem;">✓ <?= $translator->translate('chat_posted_success') ?></div>

                            <?php if (!empty($gitIntegrations)): ?>
                            <hr style="border:none; border-top:1px solid var(--border); margin:0.25rem 0;">
                            <!-- Git Issue Button -->
                            <button type="button" onclick="openGitModal()"
                                    class="btn btn-secondary"
                                    style="width:100%; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                                Issue <?= $translator->translate('action_create_task') ?>
                            </button>

                                <?php if (!empty($ytIntegrations)): ?>
                                <button type="button" onclick="openYtModal()"
                                        class="btn btn-secondary"
                                        style="width:100%; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="#0f4fff"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1.5 14.5L5 11l1.5-1.5 4 4 7-7L19 8l-8.5 8.5z"/></svg>
                                    YouTrack-<?= $translator->translate('action_create_task') ?>
                                </button>
                                <?php endif; ?>

                            <!-- Bestehende Issues -->
                            <?php if (!empty($gitIssues)): ?>
                            <div style="margin-top:0.5rem;">
                                <div style="font-size:0.72rem; font-weight:600; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px; margin-bottom:0.35rem;"><?= $translator->translate('action_linked_issues') ?></div>
                                <?php foreach ($gitIssues as $issue): ?>
                                <a href="<?= escape($issue['issue_url']) ?>" target="_blank" rel="noopener"
                                   style="display:flex; align-items:center; gap:0.4rem; padding:0.3rem 0.5rem; border:1px solid var(--border); border-radius:5px; text-decoration:none; margin-bottom:0.3rem; font-size:0.8rem; background:var(--bg); color:var(--text);">
                                    <?= $issue['provider'] === 'gitlab' ? '🦊' : '🐙' ?>
                                    <span style="font-weight:600; color:var(--primary);">#<?= $issue['issue_number'] ?></span>
                                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= escape(mb_strimwidth($issue['issue_title'], 0, 35, '…')) ?></span>
                                    <span style="font-size:0.7rem; color:var(--text-light); flex-shrink:0;">↗</span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>


                            <hr style="border:none; border-top:1px solid var(--border); margin:0.25rem 0;">

                            <?php if (!empty($ytIssues)): ?>
                            <div style="margin-top:0.5rem;">
                                <div style="font-size:0.72rem; font-weight:600; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px; margin-bottom:0.35rem;">YouTrack-<?= $translator->translate('action_tasks') ?></div>
                                <?php foreach ($ytIssues as $yti): ?>
                                <a href="<?= escape($yti['issue_url']) ?>" target="_blank" rel="noopener"
                                   style="display:flex; align-items:center; gap:0.4rem; padding:0.3rem 0.5rem; border:1px solid var(--border); border-radius:5px; text-decoration:none; margin-bottom:0.3rem; font-size:0.8rem; background:var(--surface); color:var(--text);">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#0f4fff"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1.5 14.5L5 11l1.5-1.5 4 4 7-7L19 8l-8.5 8.5z"/></svg>
                                    <span style="font-weight:600; color:#0f4fff;"><?= escape($yti['issue_id']) ?></span>
                                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= escape(mb_strimwidth($yti['issue_summary'], 0, 35, '…')) ?></span>
                                    <span style="font-size:0.7rem; color:var(--text-light); flex-shrink:0;">↗</span>
                                </a>
                                <?php endforeach; ?>
                            </div>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="tab-internal" class="tab-panel">
                        <div>
                            <?php $internalMessages = array_filter($messages, function($m){ return $m['is_internal']; }); ?>
                            <?php if (empty($internalMessages)): ?>
                                <p style="color:var(--text-light); font-size:0.875rem; text-align:center; padding:1rem 0;"><?= $translator->translate('ticketview_no_internal_messages') ?></p>
                            <?php else: ?>
                                <?php foreach ($internalMessages as $msg):
                                    $rbc = 'user'; $rbt = $translator->translate('client');
                                    if ($msg['user_role'] === 'admin') { $rbc = 'admin'; $rbt = $translator->translate('admin'); }
                                    elseif (in_array($msg['user_role'], ['first_level','second_level','third_level'])) { $rbc = 'supporter'; $rbt = $translator->translate('supporter'); }
                                ?>
                                    <div class="internal-message-item">
                                        <div class="internal-message-header">
                                            <span class="internal-message-author"><?= escape($msg['user_name']) ?> <span class="role-badge <?= $rbc ?>"><?= $rbt ?></span></span>
                                            <span class="internal-message-date"><?= formatDate($msg['created_at']) ?></span>
                                        </div>
                                        <div class="internal-message-content"><?= nl2br(escape($msg['message'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="tab-notes" class="tab-panel">
                        <div style="margin-bottom:0.75rem;">
                            <?php if (empty($internalNotes)): ?>
                                <p style="color:var(--text-light); font-size:0.875rem; text-align:center; padding:0.75rem 0;"><?= $translator->translate('action_no_notes') ?></p>
                            <?php else: ?>
                                <?php foreach ($internalNotes as $note): ?>
                                    <div style="background:var(--background); padding:0.5rem; margin-bottom:0.5rem; border-radius:4px; font-size:0.85rem; border-left:3px solid var(--warning);">
                                        <div style="font-weight:600; margin-bottom:0.2rem;"><?= escape($note['user_name']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-light); margin-bottom:0.25rem;"><?= formatDate($note['created_at']) ?></div>
                                        <div style="line-height:1.4;"><?= nl2br(escape($note['note'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <hr style="border:none; border-top:1px solid var(--border); margin-bottom:0.75rem;">
                        <form method="POST" style="margin:0;">
                            <label style="font-size:0.72rem; font-weight:600; display:block; margin-bottom:0.2rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.4px;"><?= $translator->translate('action_new_note') ?></label>
                            <textarea name="note" class="form-control" rows="3" style="font-size:0.85rem; resize:vertical;" placeholder="<?= $translator->translate('action_internal_note_ph') ?>" required></textarea>
                            <button type="submit" name="add_note" class="btn btn-sm btn-primary" style="width:100%; margin-top:0.5rem; margin-bottom:0;"><?= $translator->translate('action_save_note') ?></button>
                        </form>
                    </div>

                    <div id="tab-history" class="tab-panel">
                        <div>
                            <?php if (empty($history)): ?>
                                <p style="color:var(--text-light); font-size:0.875rem; text-align:center; padding:0.75rem 0;"><?=$translator->translate('ticketview_no_history')?></p>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <div style="margin-bottom:0.75rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border); font-size:0.82rem;">
                                        <strong style="font-size:0.85rem;"><?= escape($h['action']) ?></strong><br>
                                        <?php if ($h['old_value']): ?><span style="color:var(--text-light);"><?= escape($h['old_value']) ?></span> → <span><?= escape($h['new_value']) ?></span><br><?php endif; ?>
                                        <span style="color:var(--text-light); font-size:0.78rem;"><?= escape($h['user_name']) ?> · <?= formatDate($h['created_at']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $dbConn = Database::getInstance()->getConnection();
        $tplStmt = $dbConn->prepare("SELECT id, title, content FROM response_templates WHERE user_id = ? ORDER BY title");
        $tplStmt->execute([$_SESSION['user_id']]);
        $responseTemplates = $tplStmt->fetchAll();

        $globalTemplates = [];
        try {
            $gTplStmt = $dbConn->query("SELECT id, title, content FROM global_templates ORDER BY title");
            $globalTemplates = $gTplStmt->fetchAll();
        } catch (Exception $e) { /* Tabelle existiert noch nicht */ }
        ?>
        <div class="message-form-container" id="messageForm">
            <div class="message-form-bar" onclick="toggleMessageForm()">
                <span>✏️ <?= $translator->translate('ticketview_new_message') ?></span>
                <span class="form-toggle-icon">▲</span>
            </div>
            <div class="message-form-body">
                <form method="POST">
                    <?php if (!empty($responseTemplates) || !empty($globalTemplates)): ?>
                    <div style="margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
                        <select id="templatePicker" class="form-control" style="font-size:0.85rem; padding:0.3rem 0.5rem;"
                                onchange="insertTemplate(this)">
                            <option value="">📄 <?= $translator->translate('templates_select_placeholder') ?></option>
                            <?php if (!empty($globalTemplates)): ?>
                                <optgroup label="🌐 <?= $translator->translate('settings_gtpl_header') ?>">
                                    <?php foreach ($globalTemplates as $tpl): ?>
                                        <option value="<?= escape($tpl['id']) ?>"
                                                data-content="<?= escape($tpl['content']) ?>">
                                            <?= escape($tpl['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($responseTemplates)): ?>
                                <optgroup label="✏️ <?= $translator->translate('templates_my') ?>">
                                    <?php foreach ($responseTemplates as $tpl): ?>
                                        <option value="<?= escape($tpl['id']) ?>"
                                                data-content="<?= escape($tpl['content']) ?>">
                                            <?= escape($tpl['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" style="margin-bottom: 0;">
                        <textarea name="message" id="messageTextarea" class="form-control" rows="2" required placeholder="<?=$translator->translate('ticketview_massage_placeholder')?>"></textarea>
                    </div>
                    <div class="internal-checkbox-container">
                        <input type="checkbox" name="is_internal" id="is_internal">
                        <label for="is_internal"><?= $translator->translate('ticketview_mark_as_intern') ?></label>
                    </div>
                    <button type="submit" name="add_message" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <?=$translator->translate('ticketview_send_message')?>
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script>
    function switchTab(e, id) {
        const card = e.target.closest('.card');
        card.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        card.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        e.target.classList.add('active');
        card.querySelector('#' + id).classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab) {
            const panel = document.getElementById('tab-' + tab);
            const card  = panel ? panel.closest('.card') : null;
            if (card && panel) {
                card.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                card.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                panel.classList.add('active');
                card.querySelectorAll('.tab-btn').forEach(b => {
                    if (b.getAttribute('onclick') && b.getAttribute('onclick').includes('tab-' + tab)) b.classList.add('active');
                });
            }
        }

        const form = document.getElementById('messageForm');
        if (!form) return;
        form.classList.remove('open');

        const messageList = document.querySelector('.message-list');
        if (messageList) {
            const lastMessage = messageList.querySelector('.message:last-child');
            if (lastMessage) {
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting && !form.classList.contains('open')) {
                            form.classList.add('open');
                            observer.disconnect(); // nur einmal auslösen
                        }
                    });
                }, { threshold: 1.0 });
                observer.observe(lastMessage);
            }
        }
    });

    function toggleMessageForm() {
        const form = document.getElementById('messageForm');
        form.classList.toggle('open');
        if (form.classList.contains('open')) form.querySelector('textarea').focus();
    }

    // Platzhalter-Daten aus PHP
    const tplVars = {
        '{{kunde_name}}':     '<?= escape($ticketData['user_name']) ?>',
        '{{ticket_nr}}':      '<?= escape($ticketData['ticket_code']) ?>',
        '{{supporter_name}}': '<?= escape($_SESSION['full_name'] ?? $_SESSION['username']) ?>',
        '{{datum}}':          '<?= date('d.m.Y') ?>',
        '{{betreff}}':        '<?= escape($ticketData['subject']) ?>',
        '{{status}}':         '<?= translateStatus($ticketData['status']) ?>',
        '{{prioritaet}}':     '<?= translatePriority($ticketData['priority']) ?>',
        '{{kategorie}}':      '<?= escape($ticketData['category_name'] ?? '') ?>',
        '{{email}}':          '<?= escape($ticketData['user_email']) ?>',
        '{{firma}}':          '',
    };

    function insertTemplate(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.dataset.content) return;
        let text = opt.dataset.content;
        Object.keys(tplVars).forEach(function(key) {
            text = text.split(key).join(tplVars[key]);
        });
        const ta = document.getElementById('messageTextarea');
        if (ta) { ta.value = text; ta.focus(); }
        sel.selectedIndex = 0;
        const form = document.getElementById('messageForm');
        if (form && !form.classList.contains('open')) form.classList.add('open');
    }

    function toggleChatShareBox() {
        const btn  = document.getElementById('chat-share-box');
        const form = document.getElementById('chat-share-form');
        const showing = form.style.display !== 'none';
        btn.style.display  = showing ? 'block' : 'none';
        form.style.display = showing ? 'none'  : 'block';
        if (!showing) document.getElementById('chat-share-comment').focus();
    }

    async function sendTicketToChat() {
        const msgEl   = document.getElementById('chat-link-msg');
        const comment = (document.getElementById('chat-share-comment')?.value || '').trim();

        const ticketUrl  = '<?= SITE_URL ?>/support/view-ticket.php?id=<?= (int)$ticketId ?>';
        const ticketCode = '<?= escape($ticketData['ticket_code']) ?>';
        const subject    = '<?= escape(addslashes($ticketData['subject'])) ?>';
        const status     = '<?= translateStatus($ticketData['status']) ?>';
        const prio       = '<?= translatePriority($ticketData['priority']) ?>';
        const sender     = '<?= escape(addslashes($_SESSION['full_name'] ?? $_SESSION['username'])) ?>';

        let text = `📋 Ticket ${ticketCode} – ${subject}\n🔗 ${ticketUrl}\nStatus: ${status} | Priorität: ${prio}\nGeteilt von: ${sender}`;
        if (comment) text += `\nKommentar: ${comment}`;

        try {
            const fd = new FormData();
            fd.append('action',  'send');
            fd.append('message', text);
            const r = await fetch('<?= SITE_URL ?>/includes/chat.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                document.getElementById('chat-share-comment').value = '';
                toggleChatShareBox();
                if (msgEl) { msgEl.style.display = 'block'; setTimeout(() => msgEl.style.display = 'none', 3000); }
            }
        } catch(e) {}
    }
    </script>

<?php if (!empty($gitIntegrations)): ?>
<style>
.git-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.git-modal-overlay.open { display: flex !important; }
.git-modal {
    background: var(--surface, #ffffff);
    color: var(--text, #1e293b);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    width: 100%;
    max-width: 580px;
    max-height: 88vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.45);
    padding: 1.5rem;
    position: relative;
}
.git-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.git-modal-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text, #1e293b);
}
.git-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.3rem;
    color: var(--text-light, #64748b);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    line-height: 1;
    transition: background 0.15s;
}
.git-modal-close:hover { background: var(--border, #e2e8f0); }
.git-result-box {
    padding: 0.75rem 1rem;
    border-radius: 7px;
    margin-top: 1rem;
    font-size: 0.875rem;
    display: none;
}
.git-result-box.ok  {
    background: rgba(22,163,74,0.1);
    border: 1px solid rgba(22,163,74,0.3);
    color: var(--success, #16a34a);
}
.git-result-box.err {
    background: rgba(220,38,38,0.08);
    border: 1px solid rgba(220,38,38,0.25);
    color: var(--danger, #dc2626);
}
</style>

<div class="git-modal-overlay" id="gitModal" onclick="if(event.target===this)closeGitModal()">
    <div class="git-modal">
        <div class="git-modal-header">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                Issue erstellen
            </h3>
            <button class="git-modal-close" onclick="closeGitModal()">✕</button>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;">Repository <span style="color:var(--danger)">*</span></label>
            <select id="git-integration-select" class="form-control" onchange="onGitIntegrationChange()">
                <?php foreach ($gitIntegrations as $gi): ?>
                <option value="<?= $gi['id'] ?>"
                        data-provider="<?= $gi['provider'] ?>"
                        data-labels="<?= escape($gi['default_labels'] ?? '') ?>"
                        data-assignee="<?= escape($gi['default_assignee'] ?? '') ?>">
                    <?= $gi['provider'] === 'gitlab' ? '🦊' : '🐙' ?>
                    <?= escape($gi['name']) ?> — <?= escape($gi['owner']) ?>/<?= escape($gi['repo']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;">Issue-Titel <span style="color:var(--danger)">*</span></label>
            <input type="text" id="git-issue-title" class="form-control"
                   value="[<?= escape($ticketData['ticket_code']) ?>] <?= escape($ticketData['subject']) ?>"
                   placeholder="Kurze Beschreibung des Problems">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;"><?= $translator->translate('ticketview_description') ?></label>
            <textarea id="git-issue-body" class="form-control" rows="6" style="font-family:monospace; font-size:0.82rem; resize:vertical;"><?= escape(
"## " . $translator->translate('git_ticket_ref') . ": {$ticketData['ticket_code']}\n\n" .
"**" . $translator->translate('ticket_create_subject') . "** {$ticketData['subject']}\n" .
"**" . $translator->translate('ticketview_autor') . "** {$ticketData['user_name']} ({$ticketData['user_email']})\n" .
"**Status:** " . translateStatus($ticketData['status']) . "\n" .
"**" . $translator->translate('tickets_priority') . ":** " . translatePriority($ticketData['priority']) . "\n" .
"**" . $translator->translate('knowledgebase_category') . "** " . ($ticketData['category_name'] ?? '–') . "\n\n---\n\n" .
"**" . $translator->translate('git_customer_description') . ":**\n\n{$ticketData['description']}\n\n---\n\n" .
"*" . $translator->translate('git_created_from') . " [" . SITE_URL . "/support/view-ticket.php?id={$ticketId}]*"
            ) ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight:600;"><?= $translator->translate('git_labels') ?></label>
                <input type="text" id="git-issue-labels" class="form-control" placeholder="bug, support">
                <small style="color:var(--text-light); font-size:0.75rem;"><?= $translator->translate('git_comma_separated') ?></small>
            </div>
            <div class="form-group" id="git-assignees-group">
                <label class="form-label" style="font-weight:600;" id="git-assignees-label"><?= $translator->translate('git_assignees') ?></label>
                <input type="text" id="git-issue-assignees" class="form-control" placeholder="username">
                <small style="color:var(--text-light); font-size:0.75rem;" id="git-assignees-hint"><?= $translator->translate('git_assignees_hint') ?></small>
            </div>
        </div>

        <div id="git-result" class="git-result-box"></div>

        <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
            <button type="button" class="btn btn-primary" onclick="submitGitIssue()" id="git-submit-btn"
                    style="display:flex; align-items:center; gap:0.4rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= $translator->translate('action_create_task') ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeGitModal()"><?= $translator->translate('cancel') ?></button>
        </div>
    </div>
</div>

<script>
const GIT_API    = '<?= SITE_URL ?>/includes/git-issue.php';
const GIT_TICKET = <?= (int)$ticketId ?>;
const GIT_STR = {
    createIssue:  '<?= addslashes($translator->translate('action_create_task')) ?>',
    creating:     '⏳ <?= addslashes($translator->translate('git_creating')) ?>',
    created:      '✓ <?= addslashes($translator->translate('git_created_another')) ?>',
    errTitle:     '❌ <?= addslashes($translator->translate('git_error_no_title')) ?>',
    errNetwork:   '❌ <?= addslashes($translator->translate('git_error_network')) ?>',
    successPrefix:'✅ <?= addslashes($translator->translate('git_success_prefix')) ?>',
    successSuffix:'<?= addslashes($translator->translate('git_success_suffix')) ?>',
    hintGitHub:   '<?= addslashes($translator->translate('git_hint_github')) ?>',
    hintGitLab:   '<?= addslashes($translator->translate('git_hint_gitlab')) ?>',
};

function openGitModal() {
    document.getElementById('gitModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    onGitIntegrationChange();
}
function closeGitModal() {
    document.getElementById('gitModal').classList.remove('open');
    document.body.style.overflow = '';
    const r = document.getElementById('git-result');
    r.style.display = 'none';
    r.className = 'git-result-box';
    document.getElementById('git-submit-btn').disabled = false;
    document.getElementById('git-submit-btn').textContent = GIT_STR.createIssue;
}

function onGitIntegrationChange() {
    const sel      = document.getElementById('git-integration-select');
    const opt      = sel.options[sel.selectedIndex];
    const provider = opt.dataset.provider || 'github';
    const labels   = opt.dataset.labels   || '';
    const assignee = opt.dataset.assignee || '';

    document.getElementById('git-issue-labels').value    = labels;
    document.getElementById('git-issue-assignees').value = assignee;

    const hint  = document.getElementById('git-assignees-hint');
    if (hint) hint.textContent = provider === 'gitlab' ? GIT_STR.hintGitLab : GIT_STR.hintGitHub;
}

async function submitGitIssue() {
    const btn    = document.getElementById('git-submit-btn');
    const result = document.getElementById('git-result');
    const title  = document.getElementById('git-issue-title').value.trim();
    const body   = document.getElementById('git-issue-body').value.trim();
    const labels = document.getElementById('git-issue-labels').value.trim();
    const assign = document.getElementById('git-issue-assignees').value.trim();
    const intId  = document.getElementById('git-integration-select').value;

    if (!title) {
        result.className = 'git-result-box err';
        result.style.display = 'block';
        result.textContent = GIT_STR.errTitle;
        return;
    }

    btn.disabled    = true;
    btn.textContent = GIT_STR.creating;
    result.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('action',         'create');
        fd.append('integration_id', intId);
        fd.append('ticket_id',      GIT_TICKET);
        fd.append('title',          title);
        fd.append('body',           body);
        fd.append('labels',         labels);
        fd.append('assignees',      assign);

        const r = await fetch(GIT_API, { method:'POST', body:fd });
        const d = await r.json();

        result.style.display = 'block';
        if (d.ok) {
            result.className = 'git-result-box ok';
            result.innerHTML = `${GIT_STR.successPrefix} <a href="${d.url}" target="_blank" rel="noopener" style="color:var(--success); font-weight:700;">#${d.number} ↗</a><br><small>${GIT_STR.successSuffix}</small>`;
            btn.disabled    = false;
            btn.textContent = GIT_STR.created;
        } else {
            result.className = 'git-result-box err';
            result.textContent = '❌ ' + (d.error || '<?= addslashes($translator->translate('git_error_unknown')) ?>');
            btn.disabled    = false;
            btn.textContent = GIT_STR.createIssue;
        }
    } catch(e) {
        result.className = 'git-result-box err';
        result.style.display = 'block';
        result.textContent = GIT_STR.errNetwork + e.message;
        btn.disabled    = false;
        btn.textContent = GIT_STR.createIssue;
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeGitModal(); closeYtModal(); }
});
</script>
<?php endif; ?>

<?php if (!empty($ytIntegrations)): ?>
<style>
.yt-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.6); z-index: 10000;
    align-items: center; justify-content: center; padding: 1rem;
}
.yt-modal-overlay.open { display: flex !important; }
.yt-modal {
    background: var(--surface, #ffffff); color: var(--text, #1e293b);
    border: 1px solid var(--border, #e2e8f0); border-radius: 12px;
    width: 100%; max-width: 580px; max-height: 88vh; overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.45); padding: 1.5rem; position: relative;
}
.yt-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem; padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.yt-modal-header h3 {
    margin: 0; font-size: 1.05rem; font-weight: 700;
    display: flex; align-items: center; gap: 0.5rem; color: var(--text, #1e293b);
}
.yt-modal-close {
    background: none; border: none; cursor: pointer; font-size: 1.3rem;
    color: var(--text-light, #64748b); padding: 0.2rem 0.5rem;
    border-radius: 4px; line-height: 1; transition: background 0.15s;
}
.yt-modal-close:hover { background: var(--border, #e2e8f0); }
.yt-result-box {
    padding: 0.75rem 1rem; border-radius: 7px; margin-top: 1rem;
    font-size: 0.875rem; display: none;
}
.yt-result-box.ok  { background:rgba(22,163,74,0.1);  border:1px solid rgba(22,163,74,0.3);  color:var(--success,#16a34a); }
.yt-result-box.err { background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25); color:var(--danger,#dc2626); }
</style>

<div class="yt-modal-overlay" id="ytModal" onclick="if(event.target===this)closeYtModal()">
    <div class="yt-modal">
        <div class="yt-modal-header">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#0f4fff"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1.5 14.5L5 11l1.5-1.5 4 4 7-7L19 8l-8.5 8.5z"/></svg>
                YouTrack – <?= $translator->translate('action_create_task') ?>
            </h3>
            <button class="yt-modal-close" onclick="closeYtModal()">✕</button>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;"><?= $translator->translate('git_project_integration') ?> <span style="color:var(--danger)">*</span></label>
            <select id="yt-integration-select" class="form-control" onchange="onYtIntegrationChange()">
                <?php foreach ($ytIntegrations as $yti): ?>
                <option value="<?= $yti['id'] ?>"
                        data-type="<?= escape($yti['default_type'] ?? 'Bug') ?>"
                        data-priority="<?= escape($yti['default_priority'] ?? 'Normal') ?>"
                        data-assignee="<?= escape($yti['default_assignee'] ?? '') ?>"
                        data-tags="<?= escape($yti['default_tags'] ?? '') ?>">
                    <?= escape($yti['name']) ?> [<?= escape($yti['project_id']) ?>]
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;"><?= $translator->translate('yt_summary') ?> <span style="color:var(--danger)">*</span></label>
            <input type="text" id="yt-summary" class="form-control"
                   value="[<?= escape($ticketData['ticket_code']) ?>] <?= escape($ticketData['subject']) ?>"
                   placeholder="<?= $translator->translate('git_short_description') ?>">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" style="font-weight:600;"><?= $translator->translate('ticketview_description') ?></label>
            <textarea id="yt-description" class="form-control" rows="6" style="font-family:monospace; font-size:0.82rem; resize:vertical;"><?= escape(
"## " . $translator->translate('git_ticket_ref') . ": {$ticketData['ticket_code']}\n\n" .
"**" . $translator->translate('ticket_create_subject') . "** {$ticketData['subject']}\n" .
"**" . $translator->translate('ticketview_autor') . "** {$ticketData['user_name']} ({$ticketData['user_email']})\n" .
"**Status:** " . translateStatus($ticketData['status']) . "\n" .
"**" . $translator->translate('tickets_priority') . ":** " . translatePriority($ticketData['priority']) . "\n" .
"**" . $translator->translate('knowledgebase_category') . "** " . ($ticketData['category_name'] ?? '–') . "\n\n---\n\n" .
"**" . $translator->translate('git_customer_description') . ":**\n\n{$ticketData['description']}\n\n---\n\n" .
"*" . $translator->translate('git_created_from') . " [" . SITE_URL . "/support/view-ticket.php?id={$ticketId}]*"
            ) ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight:600;"><?= $translator->translate('yt_type') ?></label>
                <select id="yt-type" class="form-control">
                    <?php foreach (['Bug','Feature','Task','User Story','Epic','Exception'] as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight:600;"><?= $translator->translate('action_priority_label') ?></label>
                <select id="yt-priority" class="form-control">
                    <?php foreach (['Show-stopper','Critical','Major','Normal','Minor'] as $p): ?>
                    <option value="<?= $p ?>" <?= $p === 'Normal' ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight:600;"><?= $translator->translate('git_assignees') ?></label>
                <input type="text" id="yt-assignee" class="form-control" placeholder="login-name">
                <small style="color:var(--text-light); font-size:0.75rem;"><?= $translator->translate('yt_login_name_hint') ?></small>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight:600;"><?= $translator->translate('yt_tags') ?></label>
                <input type="text" id="yt-tags" class="form-control" placeholder="support, customer">
                <small style="color:var(--text-light); font-size:0.75rem;"><?= $translator->translate('git_comma_separated') ?></small>
            </div>
        </div>

        <div id="yt-result" class="yt-result-box"></div>

        <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
            <button type="button" class="btn btn-primary" onclick="submitYtIssue()" id="yt-submit-btn"
                    style="display:flex; align-items:center; gap:0.4rem; background:#0f4fff; border-color:#0f4fff;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= $translator->translate('action_create_task') ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeYtModal()"><?= $translator->translate('cancel') ?></button>
        </div>
    </div>
</div>

<script>
const YT_API    = '<?= SITE_URL ?>/includes/youtrack-issue.php';
const YT_TICKET = <?= (int)$ticketId ?>;
const YT_STR = {
    createTask:   '<?= addslashes($translator->translate('action_create_task')) ?>',
    creating:     '⏳ <?= addslashes($translator->translate('yt_creating')) ?>',
    created:      '✓ <?= addslashes($translator->translate('yt_created_another')) ?>',
    errSummary:   '❌ <?= addslashes($translator->translate('yt_error_no_summary')) ?>',
    errNetwork:   '❌ <?= addslashes($translator->translate('git_error_network')) ?>',
    errUnknown:   '<?= addslashes($translator->translate('git_error_unknown')) ?>',
    successPrefix:'✅ <?= addslashes($translator->translate('yt_success_prefix')) ?>',
    successSuffix:'<?= addslashes($translator->translate('git_success_suffix')) ?>',
};

function openYtModal() {
    document.getElementById('ytModal').classList.add('open');
    document.body.style.overflow = 'hidden';
    onYtIntegrationChange();
}
function closeYtModal() {
    document.getElementById('ytModal').classList.remove('open');
    document.body.style.overflow = '';
    const r = document.getElementById('yt-result');
    r.style.display = 'none'; r.className = 'yt-result-box';
    const btn = document.getElementById('yt-submit-btn');
    btn.disabled = false;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> ' + YT_STR.createTask;
}

function onYtIntegrationChange() {
    const sel = document.getElementById('yt-integration-select');
    const opt = sel.options[sel.selectedIndex];
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) { if (el.tagName === 'SELECT') { [...el.options].forEach(o => o.selected = o.value === val); } else { el.value = val; } } };
    setVal('yt-type',     opt.dataset.type     || 'Bug');
    setVal('yt-priority', opt.dataset.priority || 'Normal');
    setVal('yt-assignee', opt.dataset.assignee || '');
    setVal('yt-tags',     opt.dataset.tags     || '');
}

async function submitYtIssue() {
    const btn     = document.getElementById('yt-submit-btn');
    const result  = document.getElementById('yt-result');
    const summary = document.getElementById('yt-summary').value.trim();

    if (!summary) {
        result.className = 'yt-result-box err'; result.style.display = 'block';
        result.textContent = YT_STR.errSummary; return;
    }

    btn.disabled = true; btn.textContent = YT_STR.creating;
    result.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('action',         'create');
        fd.append('integration_id', document.getElementById('yt-integration-select').value);
        fd.append('ticket_id',      YT_TICKET);
        fd.append('summary',        summary);
        fd.append('description',    document.getElementById('yt-description').value.trim());
        fd.append('type',           document.getElementById('yt-type').value);
        fd.append('priority',       document.getElementById('yt-priority').value);
        fd.append('assignee',       document.getElementById('yt-assignee').value.trim());
        fd.append('tags',           document.getElementById('yt-tags').value.trim());

        const r = await fetch(YT_API, { method: 'POST', body: fd });
        const d = await r.json();

        result.style.display = 'block';
        if (d.ok) {
            result.className = 'yt-result-box ok';
            result.innerHTML = `${YT_STR.successPrefix} <a href="${d.url}" target="_blank" rel="noopener" style="color:var(--success,#16a34a); font-weight:700;">${d.issue_id} ↗</a><br><small>${YT_STR.successSuffix}</small>`;
            btn.disabled = false; btn.textContent = YT_STR.created;
        } else {
            result.className = 'yt-result-box err';
            result.textContent = '❌ ' + (d.error || YT_STR.errUnknown);
            btn.disabled = false; btn.textContent = YT_STR.createTask;
        }
    } catch(e) {
        result.className = 'yt-result-box err'; result.style.display = 'block';
        result.textContent = YT_STR.errNetwork + e.message;
        btn.disabled = false; btn.textContent = YT_STR.createTask;
    }
}
</script>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
</body>
</html>