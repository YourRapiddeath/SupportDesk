<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();

$db     = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['first_level', 'second_level', 'third_level', 'admin'])) {
    redirect(SITE_URL . '/index.php');
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $tTitle   = trim($_POST['template_title'] ?? '');
    $tContent = trim($_POST['template_content'] ?? '');
    if (!empty($tTitle) && !empty($tContent)) {
        $stmt = $db->prepare("INSERT INTO response_templates (user_id, title, content) VALUES (?,?,?)");
        $stmt->execute([$userId, $tTitle, $tContent]);
        $success = $translator->translate('tpl_user_saved');
    } else {
        $error = $translator->translate('tpl_user_error_empty');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $tId      = (int)($_POST['template_id'] ?? 0);
    $tTitle   = trim($_POST['template_title'] ?? '');
    $tContent = trim($_POST['template_content'] ?? '');
    if ($tId && !empty($tTitle) && !empty($tContent)) {
        $stmt = $db->prepare("UPDATE response_templates SET title=?, content=?, updated_at=NOW() WHERE id=? AND user_id=?");
        $stmt->execute([$tTitle, $tContent, $tId, $userId]);
        $success = $translator->translate('tpl_user_updated');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $tId = (int)($_POST['template_id'] ?? 0);
    if ($tId) {
        $stmt = $db->prepare("DELETE FROM response_templates WHERE id=? AND user_id=?");
        $stmt->execute([$tId, $userId]);
        $success = $translator->translate('tpl_user_deleted');
    }
}

$stmtT = $db->prepare("SELECT * FROM response_templates WHERE user_id=? ORDER BY title");
$stmtT->execute([$userId]);
$templates = $stmtT->fetchAll();

$placeholders = [
    '{{kunde_name}}'     => $translator->translate('tpl_ph_customer_name'),
    '{{ticket_nr}}'      => $translator->translate('tpl_ph_ticket_nr'),
    '{{supporter_name}}' => $translator->translate('tpl_ph_supporter_name'),
    '{{datum}}'          => $translator->translate('tpl_ph_date'),
    '{{betreff}}'        => $translator->translate('tpl_ph_subject'),
    '{{status}}'         => $translator->translate('tpl_ph_status'),
    '{{prioritaet}}'     => $translator->translate('tpl_ph_priority'),
    '{{kategorie}}'      => $translator->translate('tpl_ph_category'),
    '{{firma}}'          => $translator->translate('tpl_ph_company'),
    '{{email}}'          => $translator->translate('tpl_ph_email'),
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('tpl_user_page_title') ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .templates-container { max-width: 960px; margin: 0 auto; }

        .placeholder-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
        }
        .placeholder-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            font-size: 0.78rem;
            cursor: pointer;
            transition: background .15s;
            user-select: none;
        }
        .placeholder-chip:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .placeholder-chip code {
            font-size: 0.78rem;
            background: none;
            padding: 0;
            font-weight: 600;
        }

        .template-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow .15s;
        }
        .template-card:hover { box-shadow: 0 2px 8px var(--shadow); }
        .template-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: var(--surface);
            cursor: pointer;
            border-bottom: 1px solid transparent;
        }
        .template-card.open .template-card-header {
            border-bottom-color: var(--border);
        }
        .template-card-header-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        .template-card-date {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        .template-card-body { display: none; padding: 1rem; }
        .template-card.open .template-card-body { display: block; }
        .toggle-icon { font-size: 0.7rem; transition: transform .2s; }
        .template-card.open .toggle-icon { transform: rotate(180deg); }

        .preview-box {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.6;
            white-space: pre-wrap;
            color: var(--text-light);
            margin-top: 0.5rem;
            min-height: 2.5rem;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container templates-container">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem;">
        <h1 style="margin:0;">✏️ <?= $translator->translate('tpl_user_page_title') ?></h1>
        <a href="<?= SITE_URL ?>/users/profile.php" class="btn btn-secondary">← <?= $translator->translate('tpl_user_back_btn') ?></a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

    <!-- Neue Vorlage erstellen -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><?= $translator->translate('tpl_user_new_header') ?></div>
        <div class="card-body">

            <p style="font-size:0.8rem; color:var(--text-light); margin-bottom:0.5rem;">
                <?= $translator->translate('tpl_user_ph_hint') ?>
            </p>
            <div class="placeholder-chips">
                <?php foreach ($placeholders as $ph => $desc): ?>
                    <span class="placeholder-chip" onclick="insertPlaceholder('new_content', '<?= $ph ?>')" title="<?= htmlspecialchars($desc) ?>">
                        <code><?= $ph ?></code>
                        <span style="color:var(--text-light); font-size:0.72rem;"><?= htmlspecialchars($desc) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>

            <form method="POST">
                <div style="display:grid; grid-template-columns:280px 1fr; gap:1rem; align-items:start;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?= $translator->translate('tpl_user_label_title') ?></label>
                        <input type="text" name="template_title" class="form-control"
                               placeholder="<?= $translator->translate('tpl_user_title_ph') ?>" required>
                        <small style="color:var(--text-light);"><?= $translator->translate('tpl_user_title_hint') ?></small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?= $translator->translate('tpl_user_label_content') ?></label>
                        <textarea id="new_content" name="template_content" class="form-control" rows="5"
                                  placeholder="<?= $translator->translate('tpl_user_content_ph') ?>"
                                  oninput="updatePreview(this, 'new_preview')" required></textarea>
                    </div>
                </div>

                <div style="margin-top:0.75rem;">
                    <label style="font-size:0.8rem; color:var(--text-light);"><?= $translator->translate('tpl_user_preview_label') ?>:</label>
                    <div id="new_preview" class="preview-box">–</div>
                </div>

                <button type="submit" name="add_template" class="btn btn-primary" style="margin-top:1rem;">
                    + <?= $translator->translate('tpl_user_save_btn') ?>
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span><?= $translator->translate('tpl_user_list_header') ?></span>
            <span style="font-size:0.8rem; color:var(--text-light);"><?= count($templates) ?> <?= $translator->translate('tpl_user_count_label') ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <p style="color:var(--text-light); text-align:center; padding:1rem 0;">
                    <?= $translator->translate('tpl_user_empty') ?>
                </p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <?php foreach ($templates as $tpl): ?>
                        <div class="template-card" id="tcard_<?= $tpl['id'] ?>">
                            <div class="template-card-header" onclick="toggleCard(<?= $tpl['id'] ?>)">
                                <div class="template-card-header-left">
                                    <span>📄</span>
                                    <span><?= escape($tpl['title']) ?></span>
                                </div>
                                <div style="display:flex; align-items:center; gap:1rem;">
                                    <span class="template-card-date">
                                        <?= $translator->translate('tpl_user_modified') ?>: <?= date('d.m.Y H:i', strtotime($tpl['updated_at'])) ?>
                                    </span>
                                    <span class="toggle-icon">▼</span>
                                </div>
                            </div>
                            <div class="template-card-body">
                                <!-- Platzhalter-Chips für Edit -->
                                <div class="placeholder-chips" style="margin-bottom:0.75rem;">
                                    <?php foreach ($placeholders as $ph => $desc): ?>
                                        <span class="placeholder-chip"
                                              onclick="insertPlaceholder('edit_content_<?= $tpl['id'] ?>', '<?= $ph ?>')"
                                              title="<?= htmlspecialchars($desc) ?>">
                                            <code><?= $ph ?></code>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                    <div style="display:grid; grid-template-columns:280px 1fr; gap:1rem; align-items:start;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" style="font-size:0.85rem;"><?= $translator->translate('tpl_user_label_title') ?></label>
                                            <input type="text" name="template_title" class="form-control"
                                                   value="<?= escape($tpl['title']) ?>" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label class="form-label" style="font-size:0.85rem;"><?= $translator->translate('tpl_user_label_content') ?></label>
                                            <textarea id="edit_content_<?= $tpl['id'] ?>"
                                                      name="template_content" class="form-control" rows="5" required
                                                      oninput="updatePreview(this, 'edit_preview_<?= $tpl['id'] ?>')"><?= escape($tpl['content']) ?></textarea>
                                        </div>
                                    </div>

                                    <div style="margin-top:0.75rem;">
                                        <label style="font-size:0.8rem; color:var(--text-light);"><?= $translator->translate('tpl_user_preview_label') ?>:</label>
                                        <div id="edit_preview_<?= $tpl['id'] ?>" class="preview-box"><?= escape($tpl['content']) ?></div>
                                    </div>

                                    <div style="display:flex; gap:0.5rem; margin-top:0.75rem;">
                                        <button type="submit" name="update_template" class="btn btn-sm btn-primary">💾 <?= $translator->translate('tpl_user_update_btn') ?></button>
                                        <button type="submit" name="delete_template" class="btn btn-sm btn-danger"
                                                onclick="return confirm('<?= addslashes($translator->translate('tpl_user_delete_confirm')) ?>')">🗑 <?= $translator->translate('tpl_user_delete_btn') ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCard(id) {
    const card = document.getElementById('tcard_' + id);
    card.classList.toggle('open');
}

function insertPlaceholder(textareaId, placeholder) {
    const ta = document.getElementById(textareaId);
    if (!ta) return;
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + placeholder + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + placeholder.length;
    ta.focus();
    updatePreview(ta, ta.id.replace('new_content', 'new_preview').replace('edit_content_', 'edit_preview_'));
}

const previewVars = {
    '{{kunde_name}}':     '<?= addslashes($translator->translate('tpl_preview_customer_name')) ?>',
    '{{ticket_nr}}':      'TKT-20260001',
    '{{supporter_name}}': '<?= addslashes(escape($_SESSION['full_name'] ?? $_SESSION['username'])) ?>',
    '{{datum}}':          '<?= date('d.m.Y') ?>',
    '{{betreff}}':        '<?= addslashes($translator->translate('tpl_preview_subject')) ?>',
    '{{status}}':         '<?= addslashes($translator->translate('tpl_preview_status')) ?>',
    '{{prioritaet}}':     '<?= addslashes($translator->translate('tpl_preview_priority')) ?>',
    '{{kategorie}}':      '<?= addslashes($translator->translate('tpl_preview_category')) ?>',
    '{{firma}}':          '<?= addslashes($translator->translate('tpl_preview_company')) ?>',
    '{{email}}':          'max@mustermann.de',
};

function updatePreview(ta, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    let text = ta.value || '–';
    Object.keys(previewVars).forEach(function(key) {
        text = text.split(key).join('<strong style="color:var(--primary);">' + previewVars[key] + '</strong>');
    });
    preview.innerHTML = text.replace(/\n/g, '<br>') || '–';
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('textarea[id^="edit_content_"]').forEach(function(ta) {
        const previewId = ta.id.replace('edit_content_', 'edit_preview_');
        updatePreview(ta, previewId);
    });
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

