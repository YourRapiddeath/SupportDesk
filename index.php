<?php
global $translator;
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/User.php';
require_once 'includes/Ticket.php';
require_once 'includes/Discord.php';
require_once 'includes/functions.php';

$user = new User();
if (!$user->isLoggedIn()) {
    redirect(SITE_URL . '/tickets/public_ticket.php');
}

requireLogin();

$user = new User();
$ticket = new Ticket();
require_once 'includes/CategoryHelper.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$db = Database::getInstance()->getConnection();

$stats = $ticket->getStatistics($userId, $role);

if (in_array($role, ['first_level', 'second_level', 'third_level'])) {
    $unassignedTickets = $ticket->getAll([
        'support_level'     => $role,
        'unassigned'        => true,
        'current_user_id'   => $userId,
        'current_user_role' => $role,
    ]);

    $myTickets = $ticket->getAll([
        'assigned_to'       => $userId,
        'current_user_id'   => $userId,
        'current_user_role' => $role,
    ]);

    $merged = [];
    foreach (array_merge($unassignedTickets, $myTickets) as $t) {
        $merged[$t['id']] = $t;
    }

    usort($merged, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentTickets = array_values($merged);
} elseif ($role === 'admin') {
    $recentTickets = $ticket->getAll();
} else {
    $recentTickets = $ticket->getAll(['user_id' => $userId]);
}


$recentTickets = array_slice($recentTickets, 0, 10);


$unreadCounts = [];
$isClient = !in_array($role, ['first_level', 'second_level', 'third_level', 'admin']);
foreach ($recentTickets as $t) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count
        FROM ticket_messages tm
        WHERE tm.ticket_id = ?
        AND tm.user_id != ?
        " . ($isClient ? "AND tm.is_internal = 0" : "") . "
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
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$translator->translate('dashboard')?> - <?= SITE_NAME ?></title>
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
        /* Betreff-Titelzeile */
        tr.ticket-title-row td {
            background: var(--surface);
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text);
            padding: 10px 12px 2px 12px;
            border-bottom: none !important;
            border-top: 2px solid var(--border) !important;
            border-left: none;
            border-right: none;
        }
        tr.ticket-data-row td {
            background: var(--surface);
            padding-top: 2px;
            padding-bottom: 10px;
            border-top: none !important;
            border-bottom: none !important;
        }
        tr.ticket-spacer-row td {
            padding: 0;
            height: 6px;
            background: transparent !important;
            border: none !important;
        }
        /* Erste Zeile im tbody hat keinen top-border */
        tbody tr.ticket-title-row:first-child td {
            border-top: 1px solid var(--border) !important;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
        }

        .quick-action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            text-align: center;
            gap: 10px;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            background: rgba(255, 255, 255, 0.09);
            text-decoration: none;
            color: inherit;
        }

        .quick-action-card .qa-icon { font-size: 2.4rem; line-height: 1; }
        .quick-action-card h2 { margin: 0; font-size: 1.15rem; font-weight: 600; }
        .quick-action-card p  { margin: 0; font-size: 0.88rem; opacity: 0.75; }

        @media (max-width: 600px) {
            .quick-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h1><?=$translator->translate('dashboard')?></h1>

        <?php if (in_array($role, ['first_level', 'second_level', 'third_level', 'admin'])): ?>
            <!-- Support Staff Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $stats['my_tickets']['total'] ?? 0 ?></h3>
                    <p><?=$translator->translate('dahboard_my_tickets')?></p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['my_tickets']['open'] ?? 0 ?></h3>
                    <p><?=$translator->translate('dashboard_open_tickets')?></p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['my_tickets']['in_progress'] ?? 0 ?></h3>
                    <p><?=$translator->translate('dashboard_in_progress_tickets')?></p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['level_tickets']['unassigned'] ?? 0 ?></h3>
                    <p><?=$translator->translate('dashboard_not_assigned')?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?=$translator->translate('dashboard_ticket_title', ["role" => translateLevel($role)])?></div>
                <div class="card-body">
                    <?php if (empty($recentTickets)): ?>
                        <p><?=$translator->translate('dashboard_no_tickets')?></p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?=$translator->translate('dashboard_table_ticket_code')?></th>
                                    <th><?=$translator->translate('dashboard_table_user')?></th>
                                    <th><?=$translator->translate('dashboard_table_status')?></th>
                                    <th><?=$translator->translate('dashboard_table_prio')?></th>
                                    <th><?=$translator->translate('dashboard_table_category')?></th>
                                    <th><?=$translator->translate('dashboard_table_assigned')?></th>
                                    <th><?=$translator->translate('dashboard_table_created')?></th>
                                    <th colspan="2"><?=$translator->translate('dashboard_table_actions')?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTickets as $t): ?>
                                    <tr class="ticket-title-row">
                                        <td colspan="9"><?= escape($t['subject']) ?></td>
                                    </tr>
                                    <tr class="ticket-data-row">
                                        <td><strong><?= escape($t['ticket_code']) ?></strong></td>
                                        <td><?= escape($t['user_name']) ?></td>
                                        <td><span class="badge badge-<?= $t['status'] ?>"><?= translateStatus($t['status']) ?></span></td>
                                        <td><span class="badge badge-<?= $t['priority'] ?>"><?= translatePriority($t['priority']) ?></span></td>
                                        <td>
                                            <?php if (!empty($t['category_name'])): ?>
                                                <span class="badge" style="background-color:<?= escape($t['category_color']) ?>; color:#fff;">
                                                    <?= escape($t['category_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--text-light); font-size:0.8rem;">–</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $t['assigned_name'] ? escape($t['assigned_name']) : $translator->translate('dashboard_not_assigned') ?></td>
                                        <td><?= formatDate($t['created_at']) ?></td>
                                        <td colspan="2">
                                            <a href="<?= SITE_URL ?>/support/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">
                                                <?=$translator->translate('view')?>
                                                <?php if (isset($unreadCounts[$t['id']]) && $unreadCounts[$t['id']] > 0): ?>
                                                    <span class="unread-badge"><?= $unreadCounts[$t['id']] ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr class="ticket-spacer-row"><td colspan="9"></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Quick-Action Cards
            <div class="quick-actions">
                <a href="<?= SITE_URL ?>/tickets/create.php" class="quick-action-card">
                    <span class="qa-icon">🎫</span>
                    <h2><?=$translator->translate('index_first_ticket_create')?></h2>
                    <p><?=$translator->translate('index_create_ticket_desc')?></p>
                </a>
                <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="quick-action-card">
                    <span class="qa-icon">🔍</span>
                    <h2><?=$translator->translate('index_lookup_ticket')?></h2>
                    <p><?=$translator->translate('index_lookup_ticket_desc')?></p>
                </a>
            </div> -->

            <!-- Info-Box mit Statistiken -->
            <div class="card" style="margin-bottom: 22px;">
                <div class="card-header"><?=$translator->translate('dashboard')?></div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?= $stats['user_tickets']['total'] ?? 0 ?></h3>
                            <p><?=$translator->translate('dashboard_total_tickets')?></p>
                        </div>
                        <div class="stat-card">
                            <h3><?= $stats['user_tickets']['open'] ?? 0 ?></h3>
                            <p><?=$translator->translate('dashboard_open_tickets')?></p>
                        </div>
                        <div class="stat-card">
                            <h3><?= $stats['user_tickets']['in_progress'] ?? 0 ?></h3>
                            <p><?=$translator->translate('dashboard_in_progress_tickets')?></p>
                        </div>
                        <div class="stat-card">
                            <h3><?= $stats['user_tickets']['resolved'] ?? 0 ?></h3>
                            <p><?=$translator->translate('dashboard_closed_tickets')?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?=$translator->translate('index_my_last_title')?></div>
                <div class="card-body">
                    <?php if (empty($recentTickets)): ?>
                        <p><?=$translator->translate('index_my_last_tickets_no')?></p>
                        <a href="<?= SITE_URL ?>/tickets/create.php" class="btn btn-primary">
                            <?=$translator->translate('index_first_ticket_create')?>
                        </a>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?=$translator->translate('dashboard_table_ticket_code')?></th>
                                    <th><?=$translator->translate('dashboard_table_status')?></th>
                                    <th><?=$translator->translate('dashboard_table_prio')?></th>
                                    <th><?=$translator->translate('dashboard_table_category')?></th>
                                    <th><?=$translator->translate('dashboard_table_created')?></th>
                                    <th><?=$translator->translate('dashboard_table_actions')?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTickets as $t): ?>
                                    <tr class="ticket-title-row">
                                        <td colspan="6">Betreff: <?= escape($t['subject']) ?></td>
                                    </tr>
                                    <tr class="ticket-data-row">
                                        <td><strong><?= escape($t['ticket_code']) ?></strong></td>
                                        <td><span class="badge badge-<?= $t['status'] ?>"><?= translateStatus($t['status']) ?></span></td>
                                        <td><span class="badge badge-<?= $t['priority'] ?>"><?= translatePriority($t['priority']) ?></span></td>
                                        <td>
                                            <?php if (!empty($t['category_name'])): ?>
                                                <span class="badge" style="background-color:<?= escape($t['category_color']) ?>; color:#fff;">
                                                    <?= escape($t['category_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--text-light); font-size:0.8rem;">–</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDate($t['created_at']) ?></td>
                                        <td>
                                            <a href="<?= SITE_URL ?>/tickets/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">
                                                <?=$translator->translate('view')?>
                                                <?php if (isset($unreadCounts[$t['id']]) && $unreadCounts[$t['id']] > 0): ?>
                                                    <span class="unread-badge"><?= $unreadCounts[$t['id']] ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr class="ticket-spacer-row"><td colspan="6"></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
