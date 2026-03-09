<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/GitIntegration.php';
require_once '../assets/lang/translator.php';

requireLogin();
requireRole('admin');

$db         = Database::getInstance()->getConnection();
$git        = new GitIntegration();
$translator = new Translator($_SESSION['lang'] ?? 'DE-de');
$msg = ''; $err = '';

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Speichern
    if (isset($_POST['save_integration'])) {
        $data = [
            'id'               => (int)($_POST['id'] ?? 0) ?: null,
            'name'             => trim($_POST['name']             ?? ''),
            'provider'         => $_POST['provider'] === 'gitlab' ? 'gitlab' : 'github',
            'api_url'          => trim($_POST['api_url']          ?? ''),
            'token'            => trim($_POST['token']            ?? ''),
            'owner'            => trim($_POST['owner']            ?? ''),
            'repo'             => trim($_POST['repo']             ?? ''),
            'default_labels'   => trim($_POST['default_labels']   ?? ''),
            'default_assignee' => trim($_POST['default_assignee'] ?? ''),
            'is_active'        => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!$data['name'] || !$data['token'] || !$data['owner'] || !$data['repo']) {
            $err = $translator->translate('git_required_fields');
        } else {
            $id  = $git->save($data);
            $msg = $id ? $translator->translate('git_save_success') : $translator->translate('git_save_error');
        }
    }

    // Löschen
    if (isset($_POST['delete_integration'])) {
        $git->delete((int)($_POST['delete_id'] ?? 0));
        $msg = $translator->translate('git_delete_success');
    }

    // Verbindung testen (AJAX)
    if (isset($_POST['test_connection'])) {
        header('Content-Type: application/json');
        echo json_encode($git->testConnection((int)($_POST['test_id'] ?? 0)));
        exit;
    }
}

$integrations = $git->getAll();
$editId       = (int)($_GET['edit'] ?? 0);
$editItem     = $editId ? $git->getById($editId) : null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('git_page_title') ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .git-header { display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem; }
        .git-badge { display:inline-flex; align-items:center; gap:0.35rem; padding:0.2rem 0.65rem;
            border-radius:12px; font-size:0.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .git-badge.github { background:#24292f; color:#fff; }
        .git-badge.gitlab { background:#fc6d26; color:#fff; }
        .git-badge.inactive { background:var(--border); color:var(--text-light); }
        .integration-card { border:1px solid var(--border); border-radius:10px; padding:1.1rem 1.25rem;
            margin-bottom:1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
            background:var(--card-bg, var(--bg)); transition:border-color .15s; }
        .integration-card:hover { border-color:var(--primary); }
        .integration-card .info { flex:1; min-width:0; }
        .integration-card .info h3 { margin:0 0 0.2rem; font-size:1rem; font-weight:700; }
        .integration-card .info p  { margin:0; font-size:0.82rem; color:var(--text-light); }
        .integration-card .actions { display:flex; gap:0.5rem; flex-shrink:0; }
        .form-section { background:var(--card-bg, var(--bg)); border:1px solid var(--border);
            border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
        .form-section h3 { margin:0 0 1.25rem; font-size:1rem; font-weight:700;
            padding-bottom:0.75rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:0.5rem; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        @media(max-width:640px){ .form-grid-2 { grid-template-columns:1fr; } }
        .provider-toggle { display:flex; gap:0; border:1px solid var(--border); border-radius:7px; overflow:hidden; margin-bottom:1rem; width:fit-content; }
        .provider-toggle label { padding:0.5rem 1.25rem; cursor:pointer; font-weight:600; font-size:0.88rem;
            display:flex; align-items:center; gap:0.4rem; transition:background .15s, color .15s; }
        .provider-toggle input[type=radio] { display:none; }
        .provider-toggle input[type=radio]:checked + label { background:var(--primary); color:#fff; }
        .test-result { margin-top:0.75rem; padding:0.6rem 0.9rem; border-radius:6px; font-size:0.84rem;
            display:none; }
        .test-result.ok  { background:rgba(22,163,74,.12); color:var(--success,#16a34a); border:1px solid rgba(22,163,74,.25); }
        .test-result.err { background:rgba(220,38,38,.1);  color:var(--danger,#dc2626);  border:1px solid rgba(220,38,38,.2); }
        .hint-box { background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2); border-radius:8px;
            padding:1rem 1.1rem; margin-bottom:1.25rem; font-size:0.84rem; }
        .hint-box h4 { margin:0 0 0.5rem; font-size:0.88rem; }
        .hint-box code { background:rgba(0,0,0,.12); padding:0.1rem 0.3rem; border-radius:3px; font-size:0.82rem; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light); }
        .empty-state .icon { font-size:3rem; margin-bottom:1rem; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container" style="padding-top:1.5rem; max-width:960px; margin:0 auto;">

    <div class="git-header">
        <div style="font-size:2rem;">🔗</div>
        <div>
            <h1 style="margin:0; font-size:1.4rem;"><?= $translator->translate('git_page_title') ?></h1>
            <p style="margin:0; font-size:0.85rem; color:var(--text-light);"><?= $translator->translate('git_page_subtitle') ?></p>
        </div>
        <a href="?new=1" class="btn btn-primary btn-sm" style="margin-left:auto;">
            <?= $translator->translate('git_new_btn') ?>
        </a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:1rem;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- ── Formular: Neue / Bearbeiten ── -->
    <?php if (isset($_GET['new']) || $editItem): ?>
    <div class="form-section">
        <h3>
            <?= $editItem ? $translator->translate('git_form_edit_title') : $translator->translate('git_form_new_title') ?>
        </h3>

        <div class="hint-box">
            <h4><?= $translator->translate('git_hint_title') ?></h4>
            <strong><?= $translator->translate('git_hint_github_classic') ?></strong><br>
            → <a href="https://github.com/settings/tokens/new" target="_blank" style="color:var(--primary);"><?= $translator->translate('git_hint_github_classic_url') ?></a><br>
            <?= $translator->translate('git_hint_github_scope') ?><br>
            <?= $translator->translate('git_hint_github_prefix') ?><br><br>
            <strong><?= $translator->translate('git_hint_github_fine') ?></strong><br>
            → <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" style="color:var(--primary);"><?= $translator->translate('git_hint_github_fine_url') ?></a> – <?= $translator->translate('git_hint_github_fine_perm') ?><br>
            <?= $translator->translate('git_hint_github_fine_prefix') ?><br><br>
            <strong><?= $translator->translate('git_hint_gitlab_pat') ?></strong><br>
            → <a href="https://gitlab.com/-/profile/personal_access_tokens" target="_blank" style="color:var(--primary);"><?= $translator->translate('git_hint_gitlab_url') ?></a> – <?= $translator->translate('git_hint_gitlab_scope') ?><br>
            <?= $translator->translate('git_hint_gitlab_prefix') ?><br><br>
            <?= $translator->translate('git_hint_owner') ?><br>
            <?= $translator->translate('git_hint_repo') ?>
        </div>

        <form method="POST">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>

            <!-- Provider-Auswahl -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label" style="font-weight:700;"><?= $translator->translate('git_provider_label') ?></label>
                <div class="provider-toggle">
                    <input type="radio" name="provider" id="p-github" value="github"
                           <?= (!$editItem || $editItem['provider']==='github') ? 'checked' : '' ?>>
                    <label for="p-github">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </label>
                    <input type="radio" name="provider" id="p-gitlab" value="gitlab"
                           <?= ($editItem && $editItem['provider']==='gitlab') ? 'checked' : '' ?>>
                    <label for="p-gitlab">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22.65 14.39L12 22.13 1.35 14.39a.84.84 0 0 1-.3-.94l1.22-3.78 2.44-7.51A.42.42 0 0 1 4.82 2a.43.43 0 0 1 .58 0 .42.42 0 0 1 .11.18l2.44 7.49h8.1l2.44-7.51A.42.42 0 0 1 18.6 2a.43.43 0 0 1 .58 0 .42.42 0 0 1 .11.18l2.44 7.51 1.22 3.78a.84.84 0 0 1-.3.92z"/></svg>
                        GitLab
                    </label>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('git_name_label') ?> <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="<?= $translator->translate('git_name_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('git_name_hint') ?></small>
                </div>
                <div class="form-group" id="api-url-group" style="<?= (!$editItem || $editItem['provider']==='github') ? 'display:none' : '' ?>">
                    <label class="form-label"><?= $translator->translate('git_api_url_label') ?></label>
                    <input type="url" name="api_url" class="form-control" placeholder="https://gitlab.com"
                           value="<?= htmlspecialchars($editItem['api_url'] ?? 'https://gitlab.com') ?>">
                    <small class="text-muted"><?= $translator->translate('git_api_url_hint') ?></small>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('git_owner_label') ?> <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="owner" class="form-control" placeholder="<?= $translator->translate('git_owner_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['owner'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('git_owner_hint') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('git_repo_label') ?> <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="repo" class="form-control" placeholder="<?= $translator->translate('git_repo_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['repo'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $translator->translate('git_token_label') ?> <span style="color:var(--danger)">*</span></label>
                <div style="display:flex; gap:0.5rem;">
                    <input type="password" name="token" id="git-token" class="form-control"
                           placeholder="<?= $translator->translate('git_token_ph') ?>" required
                           value="<?= htmlspecialchars($editItem['token'] ?? '') ?>">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleToken()"
                            style="flex-shrink:0; white-space:nowrap;"><?= $translator->translate('git_token_show_btn') ?></button>
                </div>
                <small class="text-muted"><?= $translator->translate('git_token_hint') ?></small>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('git_default_labels_label') ?></label>
                    <input type="text" name="default_labels" class="form-control"
                           placeholder="<?= $translator->translate('git_default_labels_ph') ?>"
                           value="<?= htmlspecialchars($editItem['default_labels'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('git_default_labels_hint') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('git_default_assignee_label') ?></label>
                    <input type="text" name="default_assignee" class="form-control"
                           placeholder="<?= $translator->translate('git_default_assignee_ph') ?>"
                           value="<?= htmlspecialchars($editItem['default_assignee'] ?? '') ?>">
                    <small class="text-muted"><?= $translator->translate('git_default_assignee_hint') ?></small>
                </div>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1.25rem;">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?>>
                <label for="is_active" style="margin:0; cursor:pointer; font-weight:600;"><?= $translator->translate('git_is_active_label') ?></label>
            </div>

            <!-- Test-Verbindung -->
            <?php if ($editItem): ?>
            <div style="margin-bottom:1.25rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="testConnection(<?= $editItem['id'] ?>)">
                    <?= $translator->translate('git_test_btn') ?>
                </button>
                <div id="test-result" class="test-result"></div>
            </div>
            <?php endif; ?>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button type="submit" name="save_integration" class="btn btn-primary">
                    <?= $translator->translate('git_save_btn') ?>
                </button>
                <a href="git-integration.php" class="btn btn-secondary"><?= $translator->translate('git_cancel_btn') ?></a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Liste der Integrationen ── -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span><?= $translator->translate('git_list_title') ?></span>
            <span style="font-size:0.82rem; color:var(--text-light);"><?= count($integrations) ?> <?= $translator->translate('git_list_count') ?></span>
        </div>
        <div class="card-body" style="padding:1rem;">
            <?php if (empty($integrations)): ?>
            <div class="empty-state">
                <div class="icon">🔗</div>
                <p style="font-weight:600;"><?= $translator->translate('git_empty_title') ?></p>
                <p style="font-size:0.85rem;"><?= $translator->translate('git_empty_text') ?></p>
                <a href="?new=1" class="btn btn-primary btn-sm"><?= $translator->translate('git_empty_btn') ?></a>
            </div>
            <?php else: ?>
            <?php foreach ($integrations as $i): ?>
            <div class="integration-card">
                <div style="font-size:1.8rem; flex-shrink:0;">
                    <?= $i['provider'] === 'gitlab' ? '🦊' : '🐙' ?>
                </div>
                <div class="info">
                    <h3>
                        <?= htmlspecialchars($i['name']) ?>
                        <span class="git-badge <?= $i['provider'] ?>" style="margin-left:0.5rem;">
                            <?= $i['provider'] === 'github' ? 'GitHub' : 'GitLab' ?>
                        </span>
                        <?php if (!$i['is_active']): ?>
                            <span class="git-badge inactive"><?= $translator->translate('git_badge_inactive') ?></span>
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?= htmlspecialchars($i['owner']) ?> / <?= htmlspecialchars($i['repo']) ?>
                        <?php if ($i['default_labels']): ?>
                            &nbsp;·&nbsp; <?= $translator->translate('git_card_labels') ?> <code style="font-size:0.78rem;"><?= htmlspecialchars($i['default_labels']) ?></code>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="testConnection(<?= $i['id'] ?>,'card-test-<?= $i['id'] ?>')">
                        <?= $translator->translate('git_card_test_btn') ?>
                    </button>
                    <a href="?edit=<?= $i['id'] ?>" class="btn btn-secondary btn-sm"><?= $translator->translate('git_card_edit_btn') ?></a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?= addslashes($translator->translate('git_card_delete_confirm')) ?>')">
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

    <!-- ── Letzte erstellte Issues ── -->
    <?php
    $recentIssues = $db->query("
        SELECT gi.*, u.full_name AS creator, t.ticket_code, t.subject AS ticket_subject,
               g.name AS integration_name, g.provider
        FROM git_issues gi
        JOIN users u ON u.id = gi.created_by
        JOIN tickets t ON t.id = gi.ticket_id
        JOIN git_integrations g ON g.id = gi.integration_id
        ORDER BY gi.created_at DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <?php if ($recentIssues): ?>
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header"><?= $translator->translate('git_recent_title') ?></div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border); background:var(--bg);">
                        <th style="padding:0.65rem 1rem; text-align:left; font-weight:600;"><?= $translator->translate('git_recent_col_issue') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left; font-weight:600;"><?= $translator->translate('git_recent_col_ticket') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left; font-weight:600;"><?= $translator->translate('git_recent_col_repo') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left; font-weight:600;"><?= $translator->translate('git_recent_col_creator') ?></th>
                        <th style="padding:0.65rem 1rem; text-align:left; font-weight:600;"><?= $translator->translate('git_recent_col_date') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentIssues as $issue): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:0.65rem 1rem;">
                        <a href="<?= htmlspecialchars($issue['issue_url']) ?>" target="_blank"
                           style="color:var(--primary); text-decoration:none; font-weight:600;">
                            #<?= $issue['issue_number'] ?> <?= htmlspecialchars(mb_strimwidth($issue['issue_title'],0,50,'…')) ?>
                        </a>
                        <span class="git-badge <?= $issue['provider'] ?>" style="margin-left:0.35rem;">
                            <?= $issue['provider'] ?>
                        </span>
                    </td>
                    <td style="padding:0.65rem 1rem;">
                        <a href="<?= SITE_URL ?>/support/view-ticket.php?id=<?= $issue['ticket_id'] ?>"
                           style="color:var(--primary); text-decoration:none;">
                            <?= htmlspecialchars($issue['ticket_code']) ?>
                        </a>
                        <div style="font-size:0.78rem; color:var(--text-light);"><?= htmlspecialchars(mb_strimwidth($issue['ticket_subject'],0,40,'…')) ?></div>
                    </td>
                    <td style="padding:0.65rem 1rem; color:var(--text-light);"><?= htmlspecialchars($issue['integration_name']) ?></td>
                    <td style="padding:0.65rem 1rem;"><?= htmlspecialchars($issue['creator']) ?></td>
                    <td style="padding:0.65rem 1rem; color:var(--text-light); font-size:0.78rem;">
                        <?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const GIT_STR = {
    testLoading:        '<?= addslashes($translator->translate('git_test_loading')) ?>',
    testInvalidResp:    '<?= addslashes($translator->translate('git_test_invalid_response')) ?>',
    testNetworkError:   '<?= addslashes($translator->translate('git_test_network_error')) ?>',
};

document.querySelectorAll('input[name="provider"]').forEach(r => {
    r.addEventListener('change', () => {
        const apiBox = document.getElementById('api-url-group');
        if (apiBox) apiBox.style.display = r.value === 'gitlab' ? '' : 'none';
    });
});

function toggleToken() {
    const t = document.getElementById('git-token');
    if (t) t.type = t.type === 'password' ? 'text' : 'password';
}

async function testConnection(id, resultId = 'test-result') {
    const el = document.getElementById(resultId);
    if (!el) return;
    el.className = 'test-result';
    el.style.display = 'block';
    el.innerHTML = GIT_STR.testLoading;
    try {
        const fd = new FormData();
        fd.append('test_connection', '1');
        fd.append('test_id', id);
        const r = await fetch('git-integration.php', { method:'POST', body:fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch(e) {
            el.className = 'test-result err';
            el.innerHTML = GIT_STR.testInvalidResp + ' <pre style="font-size:0.75rem;margin-top:4px;white-space:pre-wrap;">' + text.substring(0, 300) + '</pre>';
            return;
        }
        el.className = 'test-result ' + (d.ok ? 'ok' : 'err');
        el.textContent = d.ok ? d.info : ('❌ ' + d.error);
    } catch(e) {
        el.className = 'test-result err';
        el.textContent = GIT_STR.testNetworkError + ' ' + e.message;
    }
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
