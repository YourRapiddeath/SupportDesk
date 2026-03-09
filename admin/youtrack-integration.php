<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/YouTrackIntegration.php';
require_once '../assets/lang/translator.php';

requireLogin();
requireRole('admin');

$db         = Database::getInstance()->getConnection();
$yt         = new YouTrackIntegration();
$translator = new Translator($_SESSION['lang'] ?? 'DE-de');
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_integration'])) {
        $data = [
            'id'               => (int)($_POST['id'] ?? 0) ?: null,
            'name'             => trim($_POST['name']             ?? ''),
            'base_url'         => rtrim(trim($_POST['base_url']   ?? ''), '/'),
            'token'            => trim($_POST['token']            ?? ''),
            'project_id'       => trim($_POST['project_id']       ?? ''),
            'default_type'     => trim($_POST['default_type']     ?? 'Bug'),
            'default_priority' => trim($_POST['default_priority'] ?? 'Normal'),
            'default_assignee' => trim($_POST['default_assignee'] ?? ''),
            'default_tags'     => trim($_POST['default_tags']     ?? ''),
            'is_active'        => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!$data['name'] || !$data['base_url'] || !$data['token'] || !$data['project_id']) {
            $err = $translator->translate('yt_required_fields');
        } else {
            $id = $yt->save($data);
            $msg = $id ? $translator->translate('yt_save_success') : $translator->translate('yt_save_error');
        }
    }

    if (isset($_POST['delete_integration'])) {
        $yt->delete((int)($_POST['delete_id'] ?? 0));
        $msg = $translator->translate('yt_delete_success');
    }

    if (isset($_POST['test_connection'])) {
        header('Content-Type: application/json');
        echo json_encode($yt->testConnection((int)($_POST['test_id'] ?? 0)));
        exit;
    }
}

$integrations = $yt->getAll();
$editId       = (int)($_GET['edit'] ?? 0);
$editItem     = $editId ? $yt->getById($editId) : null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('yt_page_title') ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .yt-header { display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem; }
        .yt-badge { display:inline-flex; align-items:center; gap:0.35rem; padding:0.2rem 0.7rem;
            border-radius:12px; font-size:0.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
            background:#0f4fff; color:#fff; }
        .yt-badge.inactive { background:var(--border); color:var(--text-light); }
        .integration-card { border:1px solid var(--border); border-radius:10px; padding:1.1rem 1.25rem;
            margin-bottom:1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
            background:var(--surface); transition:border-color .15s; }
        .integration-card:hover { border-color:var(--primary); }
        .integration-card .info { flex:1; min-width:0; }
        .integration-card .info h3 { margin:0 0 0.2rem; font-size:1rem; font-weight:700; }
        .integration-card .info p  { margin:0; font-size:0.82rem; color:var(--text-light); }
        .integration-card .actions { display:flex; gap:0.5rem; flex-shrink:0; }
        .form-section { background:var(--surface); border:1px solid var(--border);
            border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
        .form-section h3 { margin:0 0 1.25rem; font-size:1rem; font-weight:700;
            padding-bottom:0.75rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:0.5rem; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }
        @media(max-width:640px){ .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
        .test-result { margin-top:0.75rem; padding:0.6rem 0.9rem; border-radius:6px; font-size:0.84rem; display:none; }
        .test-result.ok  { background:rgba(22,163,74,.12); color:var(--success,#16a34a); border:1px solid rgba(22,163,74,.25); }
        .test-result.err { background:rgba(220,38,38,.1);  color:var(--danger,#dc2626);  border:1px solid rgba(220,38,38,.2); }
        .hint-box { background:rgba(15,79,255,.06); border:1px solid rgba(15,79,255,.18); border-radius:8px;
            padding:1rem 1.1rem; margin-bottom:1.25rem; font-size:0.84rem; }
        .hint-box h4 { margin:0 0 0.5rem; font-size:0.88rem; }
        .hint-box code { background:rgba(0,0,0,.1); padding:0.1rem 0.3rem; border-radius:3px; font-size:0.82rem; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light); }
        .empty-state .icon { font-size:3rem; margin-bottom:1rem; }
        .yt-logo { width:22px; height:22px; fill:#0f4fff; flex-shrink:0; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container" style="padding-top:1.5rem; max-width:960px; margin:0 auto;">

    <div class="yt-header">
        <!-- YouTrack Logo SVG -->
        <svg class="yt-logo" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1.5 14.5L5 11l1.5-1.5 4 4 7-7L19 8l-8.5 8.5z"/>
        </svg>
        <div>
            <h1 style="margin:0; font-size:1.4rem;"><?= $translator->translate('yt_page_title') ?></h1>
            <p style="margin:0; font-size:0.85rem; color:var(--text-light);"><?= $translator->translate('yt_page_subtitle') ?></p>
        </div>
        <a href="?new=1" class="btn btn-primary btn-sm" style="margin-left:auto;">
            <?= $translator->translate('yt_new_btn') ?>
        </a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:1rem;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if (isset($_GET['new']) || $editItem): ?>
    <div class="form-section">
        <h3><?= $editItem ? $translator->translate('yt_form_edit_title') : $translator->translate('yt_form_new_title') ?></h3>

        <div class="hint-box">
            <h4><?= $translator->translate('yt_hint_title') ?></h4>
            <strong><?= $translator->translate('yt_hint_token_create') ?></strong><br>
            <?= $translator->translate('yt_hint_cloud') ?> <code>youtrack.jetbrains.com</code> <?= $translator->translate('yt_hint_cloud_path') ?><br>
            <?= $translator->translate('yt_hint_selfhosted') ?> <code>https://ihr-server/youtrack</code> <?= $translator->translate('yt_hint_selfhosted_path') ?><br>
            <?= $translator->translate('yt_hint_permissions') ?><br><br>
            <?= $translator->translate('yt_hint_project_id') ?>
        </div>

        <form method="POST">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>

            <div class="form-grid-2" style="margin-bottom:1rem;">
                <div class="form-group">
                    <label class="form-label" for="yt-name"><?= $translator->translate('yt_name_label') ?> <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="yt-name" name="name" class="form-control"
                           placeholder="<?= $translator->translate('yt_name_ph') ?>"
                           required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('yt_name_hint') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="yt-base-url"><?= $translator->translate('yt_base_url_label') ?> <span style="color:var(--danger)">*</span></label>
                    <input type="url" id="yt-base-url" name="base_url" class="form-control"
                           placeholder="<?= $translator->translate('yt_base_url_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['base_url'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('yt_base_url_hint') ?></small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" for="yt-token"><?= $translator->translate('yt_token_label') ?> <span style="color:var(--danger)">*</span></label>
                <div style="display:flex; gap:0.5rem;">
                    <input type="password" id="yt-token" name="token" class="form-control"
                           placeholder="<?= $translator->translate('yt_token_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['token'] ?? '') ?>">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleYtToken()"
                            style="flex-shrink:0; white-space:nowrap;"><?= $translator->translate('yt_token_show_btn') ?></button>
                </div>
                <small class="text-muted"><?= $translator->translate('yt_token_hint') ?></small>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" for="yt-project"><?= $translator->translate('yt_project_label') ?> <span style="color:var(--danger)">*</span></label>
                <input type="text" id="yt-project" name="project_id" class="form-control"
                       placeholder="<?= $translator->translate('yt_project_ph') ?>" required
                       value="<?= htmlspecialchars($editItem['project_id'] ?? '') ?>">
                <small class="text-muted"><?= $translator->translate('yt_project_hint') ?></small>
            </div>

            <div class="form-grid-3" style="margin-bottom:1rem;">
                <div class="form-group">
                    <label class="form-label" for="yt-type"><?= $translator->translate('yt_type_label') ?></label>
                    <select id="yt-type" name="default_type" class="form-control">
                        <?php foreach (['Bug','Feature','Task','User Story','Epic','Exception'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($editItem['default_type'] ?? 'Bug') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="yt-priority"><?= $translator->translate('yt_priority_label') ?></label>
                    <select id="yt-priority" name="default_priority" class="form-control">
                        <?php foreach (['Show-stopper','Critical','Major','Normal','Minor'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editItem['default_priority'] ?? 'Normal') === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="yt-assignee"><?= $translator->translate('yt_assignee_label') ?></label>
                    <input type="text" id="yt-assignee" name="default_assignee" class="form-control"
                           placeholder="<?= $translator->translate('yt_assignee_ph') ?>"
                           value="<?= htmlspecialchars($editItem['default_assignee'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('yt_assignee_hint') ?></small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" for="yt-tags"><?= $translator->translate('yt_tags_label') ?></label>
                <input type="text" id="yt-tags" name="default_tags" class="form-control"
                       placeholder="<?= $translator->translate('yt_tags_ph') ?>"
                       value="<?= htmlspecialchars($editItem['default_tags'] ?? '') ?>">
                <small class="text-muted"><?= $translator->translate('yt_tags_hint') ?></small>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1.25rem;">
                <input type="checkbox" name="is_active" id="yt-active" value="1"
                       <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?>>
                <label for="yt-active" style="margin:0; cursor:pointer; font-weight:600;"><?= $translator->translate('yt_active_label') ?></label>
            </div>

            <?php if ($editItem): ?>
            <div style="margin-bottom:1.25rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="testConnection(<?= $editItem['id'] ?>)">
                    <?= $translator->translate('yt_test_btn') ?>
                </button>
                <div id="test-result" class="test-result"></div>
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button type="submit" name="save_integration" class="btn btn-primary"><?= $translator->translate('yt_save_btn') ?></button>
                <a href="youtrack-integration.php" class="btn btn-secondary"><?= $translator->translate('yt_cancel_btn') ?></a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Liste -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span><?= $translator->translate('yt_list_title') ?></span>
            <span style="font-size:0.82rem; color:var(--text-light);"><?= count($integrations) ?> <?= $translator->translate('yt_list_count') ?></span>
        </div>
        <div class="card-body" style="padding:1rem;">
            <?php if (empty($integrations)): ?>
            <div class="empty-state">
                <div class="icon">📋</div>
                <p style="font-weight:600;"><?= $translator->translate('yt_empty_title') ?></p>
                <p style="font-size:0.85rem;"><?= $translator->translate('yt_empty_text') ?></p>
                <a href="?new=1" class="btn btn-primary btn-sm"><?= $translator->translate('yt_empty_btn') ?></a>
            </div>
            <?php else: ?>
            <?php foreach ($integrations as $i): ?>
            <div class="integration-card">
                <div style="font-size:1.8rem; flex-shrink:0;">📋</div>
                <div class="info">
                    <h3>
                        <?= htmlspecialchars($i['name']) ?>
                        <span class="yt-badge" style="margin-left:0.4rem;"><?= $translator->translate('yt_badge_label') ?></span>
                        <?php if (!$i['is_active']): ?>
                            <span class="yt-badge inactive"><?= $translator->translate('yt_badge_inactive') ?></span>
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?= htmlspecialchars($i['base_url']) ?> · <?= $translator->translate('yt_card_project') ?> <strong><?= htmlspecialchars($i['project_id']) ?></strong>
                        <?php if ($i['default_type']): ?> · <?= $translator->translate('yt_card_type') ?> <code style="font-size:0.78rem;"><?= htmlspecialchars($i['default_type']) ?></code><?php endif; ?>
                        <?php if ($i['default_priority']): ?> · <?= $translator->translate('yt_card_priority') ?> <code style="font-size:0.78rem;"><?= htmlspecialchars($i['default_priority']) ?></code><?php endif; ?>
                    </p>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="testConnection(<?= $i['id'] ?>,'card-test-<?= $i['id'] ?>')">
                        <?= $translator->translate('yt_card_test_btn') ?>
                    </button>
                    <a href="?edit=<?= $i['id'] ?>" class="btn btn-secondary btn-sm"><?= $translator->translate('yt_card_edit_btn') ?></a>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('<?= addslashes($translator->translate('yt_card_delete_confirm')) ?>')">
                        <input type="hidden" name="delete_id" value="<?= $i['id'] ?>">
                        <button type="submit" name="delete_integration" class="btn btn-secondary btn-sm" style="color:var(--danger);">🗑</button>
                    </form>
                </div>
            </div>
            <div id="card-test-<?= $i['id'] ?>" class="test-result" style="margin-bottom:0.75rem;"></div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Letzte Issues -->
    <?php
    $recentIssues = [];
    try {
        $recentIssues = $db->query("
            SELECT yi.*, u.full_name AS creator, t.ticket_code, t.subject AS ticket_subject,
                   i.name AS integration_name
            FROM youtrack_issues yi
            JOIN users u ON u.id = yi.created_by
            JOIN tickets t ON t.id = yi.ticket_id
            JOIN youtrack_integrations i ON i.id = yi.integration_id
            ORDER BY yi.created_at DESC LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    ?>
    <?php if ($recentIssues): ?>
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header"><?= $translator->translate('yt_recent_title') ?></div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border); background:var(--background);">
                        <th style="padding:0.65rem 1rem; text-align:left;"><?= $translator->translate('yt_recent_col_task') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left;"><?= $translator->translate('yt_recent_col_ticket') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left;"><?= $translator->translate('yt_recent_col_project') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left;"><?= $translator->translate('yt_recent_col_creator') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left;"><?= $translator->translate('yt_recent_col_date') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentIssues as $issue): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:0.65rem 1rem;">
                        <a href="<?= htmlspecialchars($issue['issue_url']) ?>" target="_blank" rel="noopener"
                           style="color:var(--primary); text-decoration:none; font-weight:600;">
                            <?= htmlspecialchars($issue['issue_id']) ?>
                        </a>
                        <div style="font-size:0.78rem; color:var(--text-light);"><?= htmlspecialchars(mb_strimwidth($issue['issue_summary'], 0, 55, '…')) ?></div>
                    </td>
                    <td style="padding:0.65rem 1rem;">
                        <a href="<?= SITE_URL ?>/support/view-ticket.php?id=<?= $issue['ticket_id'] ?>"
                           style="color:var(--primary); text-decoration:none;"><?= htmlspecialchars($issue['ticket_code']) ?></a>
                        <div style="font-size:0.78rem; color:var(--text-light);"><?= htmlspecialchars(mb_strimwidth($issue['ticket_subject'], 0, 40, '…')) ?></div>
                    </td>
                    <td style="padding:0.65rem 1rem; color:var(--text-light);"><?= htmlspecialchars($issue['integration_name']) ?></td>
                    <td style="padding:0.65rem 1rem;"><?= htmlspecialchars($issue['creator']) ?></td>
                    <td style="padding:0.65rem 1rem; font-size:0.78rem; color:var(--text-light);"><?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const YT_STR = {
    testLoading:      '<?= addslashes($translator->translate('yt_test_loading')) ?>',
    testInvalidResp:  '<?= addslashes($translator->translate('yt_test_invalid_response')) ?>',
    testNetworkError: '<?= addslashes($translator->translate('yt_test_network_error')) ?>',
};

function toggleYtToken() {
    const t = document.getElementById('yt-token');
    if (t) t.type = t.type === 'password' ? 'text' : 'password';
}

async function testConnection(id, resultId = 'test-result') {
    const el = document.getElementById(resultId);
    if (!el) return;
    el.className = 'test-result';
    el.style.display = 'block';
    el.innerHTML = YT_STR.testLoading;
    try {
        const fd = new FormData();
        fd.append('test_connection', '1');
        fd.append('test_id', id);
        const r    = await fetch('youtrack-integration.php', { method:'POST', body:fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch(e) {
            el.className = 'test-result err';
            el.innerHTML = YT_STR.testInvalidResp + '<pre style="font-size:0.75rem;margin-top:4px;white-space:pre-wrap;">' + text.substring(0,300) + '</pre>';
            return;
        }
        el.className = 'test-result ' + (d.ok ? 'ok' : 'err');
        el.textContent = d.ok ? d.info : ('❌ ' + d.error);
    } catch(e) {
        el.className = 'test-result err';
        el.textContent = YT_STR.testNetworkError + ' ' + e.message;
    }
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

