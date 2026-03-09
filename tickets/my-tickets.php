<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/Ticket.php';
require_once '../includes/functions.php';

requireLogin();

$ticket = new Ticket();
$myTickets = $ticket->getAll(['user_id' => $_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Tickets - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Meine Tickets</h1>
            <a href="<?= SITE_URL ?>/tickets/create.php" class="btn btn-primary">Neues Ticket erstellen</a>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($myTickets)): ?>
                    <p>Sie haben noch keine Tickets erstellt.</p>
                    <a href="<?= SITE_URL ?>/tickets/create.php" class="btn btn-primary">Erstes Ticket erstellen</a>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= $translator->translate('tickets_table_code') ?></th>
                                <th><?= $translator->translate('tickets_table_subject') ?></th>
                                <th><?= $translator->translate('tickets_table_status') ?></th>
                                <th><?= $translator->translate('tickets_table_prio') ?></th>
                                <th><?= $translator->translate('action_level_label') ?></th>
                                <th><?= $translator->translate('tickets_table_created') ?></th>
                                <th><?= $translator->translate('tickets_last_update') ?></th>
                                <th><?= $translator->translate('tickets_table_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myTickets as $t): ?>
                                <tr>
                                    <td><strong><?= escape($t['ticket_code']) ?></strong></td>
                                    <td><?= escape($t['subject']) ?></td>
                                    <td><span class="badge badge-<?= $t['status'] ?>"><?= translateStatus($t['status']) ?></span></td>
                                    <td><span class="badge badge-<?= $t['priority'] ?>"><?= translatePriority($t['priority']) ?></span></td>
                                    <td><?= translateLevel($t['support_level']) ?></td>
                                    <td><?= formatDate($t['created_at']) ?></td>
                                    <td><?= formatDate($t['updated_at']) ?></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/tickets/view-ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">
                                            Ansehen
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
