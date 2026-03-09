<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/Ticket.php';
require_once '../includes/functions.php';

requireLogin();
requireRole(['first_level', 'second_level', 'third_level', 'admin']);

$ticket = new Ticket();
$db = Database::getInstance()->getConnection();

// Get filter parameters
$filterLevel  = $_GET['level']  ?? 'own';   // 'own' = eigene Rolle (mit Hierarchie)
$filterStatus = $_GET['status'] ?? '';

// Build filters
$filters = [
    'current_user_id'   => $_SESSION['user_id'],
    'current_user_role' => $_SESSION['role'],
];

if ($_SESSION['role'] === 'admin') {
    // Admin: alle Level, außer explizit gefiltert
    if ($filterLevel !== 'all' && $filterLevel !== 'own' && $filterLevel !== '') {
        $filters['support_level'] = $filterLevel;
    }
} else {
    if ($filterLevel === 'own' || $filterLevel === '') {
        // Standard: eigene Rolle → Hierarchie greift automatisch in getAll()
        $filters['support_level'] = $_SESSION['role'];
    } elseif ($filterLevel !== 'all') {
        $filters['support_level'] = $filterLevel;
    }
    // 'all' = kein Level-Filter → alle sichtbaren Tickets
}

if ($filterStatus) {
    $filters['status'] = $filterStatus;
}

$tickets = $ticket->getAll($filters);

// Get unread message counts for each ticket
$unreadCounts = [];
foreach ($tickets as $t) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count
        FROM ticket_messages tm
        WHERE tm.ticket_id = ?
        AND tm.user_id != ?
        AND NOT EXISTS (
            SELECT 1 FROM message_read_status mrs
            WHERE mrs.message_id = tm.id AND mrs.user_id = ?
        )
    ");
    $stmt->execute([$t['id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $result = $stmt->fetch();
    $unreadCounts[$t['id']] = $result['unread_count'];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('tickets_list_page_title') ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .unread-badge {
            display: inline-block;
            min-width: 20px;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: bold;
            line-height: 1;
            color: #fff;
            background-color: #dc3545;
            border-radius: 10px;
            text-align: center;
            vertical-align: middle;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <h1><?= $translator->translate('tickets_list_heading') ?></h1>

        <!-- Filters -->
        <div class="card">
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="form-group" style="margin-bottom: 0; flex: 1;">
                            <label class="form-label"><?= $translator->translate('tickets_filter_level') ?>:</label>
                            <select name="level" class="form-control">
                                <option value="all" <?= $filterLevel === 'all' ? 'selected' : '' ?>><?= $translator->translate('tickets_filter_level_all') ?></option>
                                <option value="first_level"  <?= $filterLevel === 'first_level'  ? 'selected' : '' ?>><?= $translator->translate('tickets_filter_level_first') ?></option>
                                <option value="second_level" <?= $filterLevel === 'second_level' ? 'selected' : '' ?>><?= $translator->translate('tickets_filter_level_second') ?></option>
                                <option value="third_level"  <?= $filterLevel === 'third_level'  ? 'selected' : '' ?>><?= $translator->translate('tickets_filter_level_third') ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label class="form-label"><?= $translator->translate('tickets_filter_status') ?>:</label>
                        <select name="status" class="form-control">
                            <option value=""><?= $translator->translate('tickets_filter_status_all') ?></option>
                            <option value="open"        <?= $filterStatus === 'open'        ? 'selected' : '' ?>><?= $translator->translate('status_open') ?></option>
                            <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>><?= $translator->translate('status_in_progress') ?></option>
                            <option value="pending"     <?= $filterStatus === 'pending'     ? 'selected' : '' ?>><?= $translator->translate('status_pending') ?></option>
                            <option value="resolved"    <?= $filterStatus === 'resolved'    ? 'selected' : '' ?>><?= $translator->translate('status_resolved') ?></option>
                            <option value="closed"      <?= $filterStatus === 'closed'      ? 'selected' : '' ?>><?= $translator->translate('status_closed') ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= $translator->translate('tickets_filter_btn') ?></button>
                    <a href="<?= SITE_URL ?>/support/tickets.php" class="btn btn-secondary"><?= $translator->translate('tickets_filter_reset') ?></a>
                </form>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <p><?= $translator->translate('tickets_list_empty') ?></p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= $translator->translate('tickets_col_code') ?></th>
                                <th><?= $translator->translate('tickets_col_subject') ?></th>
                                <th><?= $translator->translate('tickets_col_user') ?></th>
                                <th><?= $translator->translate('tickets_col_status') ?></th>
                                <th><?= $translator->translate('tickets_col_priority') ?></th>
                                <th><?= $translator->translate('tickets_col_level') ?></th>
                                <th><?= $translator->translate('tickets_col_assigned') ?></th>
                                <th><?= $translator->translate('tickets_col_created') ?></th>
                                <th><?= $translator->translate('tickets_col_action') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td><strong><?= escape($t['ticket_code']) ?></strong></td>
                                    <td><?= escape($t['subject']) ?></td>
                                    <td><?= escape($t['user_name']) ?></td>
                                    <td><span class="badge badge-<?= $t['status'] ?>"><?= translateStatus($t['status']) ?></span></td>
                                    <td><span class="badge badge-<?= $t['priority'] ?>"><?= translatePriority($t['priority']) ?></span></td>
                                    <td><?= translateLevel($t['support_level']) ?></td>
                                    <td><?= $t['assigned_name'] ? escape($t['assigned_name']) : $translator->translate('tickets_not_assigned') ?></td>
                                    <td><?= formatDate($t['created_at']) ?></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/support/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">
                                            <?= $translator->translate('tickets_btn_view') ?>
                                            <?php if (isset($unreadCounts[$t['id']]) && $unreadCounts[$t['id']] > 0): ?>
                                                <span class="unread-badge"><?= $unreadCounts[$t['id']] ?></span>
                                            <?php endif; ?>
                                        </a>
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
