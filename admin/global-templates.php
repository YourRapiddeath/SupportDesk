<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();
requireRole('admin');

$db      = Database::getInstance()->getConnection();
$success = '';
$error   = '';

// Tabelle via database.sql angelegt

// Hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    $title   = trim($_POST['template_title'] ?? '');
    $content = trim($_POST['template_content'] ?? '');
    if (!empty($title) && !empty($content)) {
        $stmt = $db->prepare("INSERT INTO global_templates (title, content, created_by) VALUES (?,?,?)");
        $stmt->execute([$title, $content, $_SESSION['user_id']]);
        $success = 'Globale Vorlage gespeichert!';
    } else {
        $error = 'Titel und Inhalt dürfen nicht leer sein.';
    }
}

// Aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $id      = (int)($_POST['template_id'] ?? 0);
    $title   = trim($_POST['template_title'] ?? '');
    $content = trim($_POST['template_content'] ?? '');
    if ($id && !empty($title) && !empty($content)) {
        $stmt = $db->prepare("UPDATE global_templates SET title=?, content=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$title, $content, $id]);
        $success = 'Vorlage aktualisiert!';
    }
}

// Löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $id = (int)($_POST['template_id'] ?? 0);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM global_templates WHERE id=?");
        $stmt->execute([$id]);
        $success = 'Vorlage gelöscht!';
    }
}

// Laden
$stmt      = $db->query("SELECT g.*, u.full_name AS creator FROM global_templates g LEFT JOIN users u ON u.id = g.created_by ORDER BY g.title");
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Globale Vorlagen – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
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
        .placeholder-chip code { font-size: 0.78rem; background: none; padding: 0; font-weight: 600; }

        .template-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow .15s;
            margin-bottom: 0.75rem;
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
        .template-card.open .template-card-header { border-bottom-color: var(--border); }
        .template-card-header-left { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; }
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
        .global-badge {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem;">
        <div>
            <h1 style="margin:0;">🌐 Globale Antwortvorlagen</h1>
            <p style="margin:0.25rem 0 0; color:var(--text-light); font-size:0.875rem;">
                Diese Vorlagen stehen allen Supportern zur Verfügung.
            </p>
        </div>
        <a href="<?= SITE_URL ?>/admin/settings.php" class="btn btn-secondary">← Zurück zu Einstellungen</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

    <!-- Neue Vorlage -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">Neue globale Vorlage erstellen</div>
        <div class="card-body">
            <p style="font-size:0.8rem; color:var(--text-light); margin-bottom:0.5rem;">
                Klicke auf einen Platzhalter um ihn ins Textfeld einzufügen:
            </p>
            <?php
            $placeholders = [
                '{{kunde_name}}'     => 'Name des Kunden',
                '{{ticket_nr}}'      => 'Ticket-Nummer',
                '{{supporter_name}}' => 'Name des Supporters',
                '{{datum}}'          => 'Heutiges Datum',
                '{{betreff}}'        => 'Ticket-Betreff',
                '{{status}}'         => 'Aktueller Status',
                '{{prioritaet}}'     => 'Priorität',
                '{{kategorie}}'      => 'Kategorie',
                '{{email}}'          => 'E-Mail des Kunden',
                '{{firma}}'          => 'Firma des Kunden',
            ];
            ?>
            <div class="placeholder-chips">
                <?php foreach ($placeholders as $ph => $desc): ?>
                    <span class="placeholder-chip" onclick="insertPlaceholder('new_content','<?= $ph ?>')" title="<?= $desc ?>">
                        <code><?= $ph ?></code>
                        <span style="color:var(--text-light); font-size:0.72rem;"><?= $desc ?></span>
                    </span>
                <?php endforeach; ?>
            </div>

            <form method="POST">
                <div style="display:grid; grid-template-columns:280px 1fr; gap:1rem; align-items:start;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Titel</label>
                        <input type="text" name="template_title" class="form-control"
                               placeholder="z.B. Willkommen, Lösung, Abschluss…" required>
                        <small style="color:var(--text-light);">Kurzer, aussagekräftiger Name</small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Inhalt</label>
                        <textarea id="new_content" name="template_content" class="form-control" rows="5"
                                  placeholder="Guten Tag {{kunde_name}},&#10;&#10;vielen Dank für Ihre Anfrage ({{ticket_nr}}).&#10;&#10;Mit freundlichen Grüßen&#10;{{supporter_name}}"
                                  oninput="updatePreview(this,'new_preview')" required></textarea>
                    </div>
                </div>
                <div style="margin-top:0.75rem;">
                    <label style="font-size:0.8rem; color:var(--text-light);">Vorschau:</label>
                    <div id="new_preview" class="preview-box">–</div>
                </div>
                <button type="submit" name="add_template" class="btn btn-primary" style="margin-top:1rem;">
                    + Vorlage speichern
                </button>
            </form>
        </div>
    </div>

    <!-- Vorhandene Vorlagen -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span>Alle globalen Vorlagen</span>
            <span style="font-size:0.8rem; color:var(--text-light);"><?= count($templates) ?> Vorlage(n)</span>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <p style="color:var(--text-light); text-align:center; padding:1rem 0;">
                    Noch keine globalen Vorlagen vorhanden.
                </p>
            <?php else: ?>
                <?php foreach ($templates as $tpl): ?>
                    <div class="template-card" id="tcard_<?= $tpl['id'] ?>">
                        <div class="template-card-header" onclick="toggleCard(<?= $tpl['id'] ?>)">
                            <div class="template-card-header-left">
                                <span>🌐</span>
                                <span><?= escape($tpl['title']) ?></span>
                                <span class="global-badge">Global</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <span style="font-size:0.75rem; color:var(--text-light);">
                                    von <?= escape($tpl['creator']) ?> · <?= date('d.m.Y H:i', strtotime($tpl['updated_at'])) ?>
                                </span>
                                <span class="toggle-icon">▼</span>
                            </div>
                        </div>
                        <div class="template-card-body">
                            <div class="placeholder-chips" style="margin-bottom:0.75rem;">
                                <?php foreach ($placeholders as $ph => $desc): ?>
                                    <span class="placeholder-chip"
                                          onclick="insertPlaceholder('edit_content_<?= $tpl['id'] ?>','<?= $ph ?>')"
                                          title="<?= $desc ?>">
                                        <code><?= $ph ?></code>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                <div style="display:grid; grid-template-columns:280px 1fr; gap:1rem; align-items:start;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" style="font-size:0.85rem;">Titel</label>
                                        <input type="text" name="template_title" class="form-control"
                                               value="<?= escape($tpl['title']) ?>" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" style="font-size:0.85rem;">Inhalt</label>
                                        <textarea id="edit_content_<?= $tpl['id'] ?>"
                                                  name="template_content" class="form-control" rows="5" required
                                                  oninput="updatePreview(this,'edit_preview_<?= $tpl['id'] ?>')"><?= escape($tpl['content']) ?></textarea>
                                    </div>
                                </div>
                                <div style="margin-top:0.75rem;">
                                    <label style="font-size:0.8rem; color:var(--text-light);">Vorschau:</label>
                                    <div id="edit_preview_<?= $tpl['id'] ?>" class="preview-box"><?= escape($tpl['content']) ?></div>
                                </div>
                                <div style="display:flex; gap:0.5rem; margin-top:0.75rem;">
                                    <button type="submit" name="update_template" class="btn btn-sm btn-primary">💾 Speichern</button>
                                    <button type="submit" name="delete_template" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Vorlage löschen?')">🗑 Löschen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCard(id) {
    document.getElementById('tcard_' + id).classList.toggle('open');
}

function insertPlaceholder(taId, ph) {
    const ta = document.getElementById(taId);
    if (!ta) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0, s) + ph + ta.value.substring(e);
    ta.selectionStart = ta.selectionEnd = s + ph.length;
    ta.focus();
    const previewId = taId === 'new_content' ? 'new_preview' : taId.replace('edit_content_', 'edit_preview_');
    updatePreview(ta, previewId);
}

const previewVars = {
    '{{kunde_name}}':     'Max Mustermann',
    '{{ticket_nr}}':      'TKT-20260001',
    '{{supporter_name}}': 'Anna Support',
    '{{datum}}':          '<?= date('d.m.Y') ?>',
    '{{betreff}}':        'Beispiel-Betreff',
    '{{status}}':         'Offen',
    '{{prioritaet}}':     'Mittel',
    '{{kategorie}}':      'Allgemein',
    '{{email}}':          'max@mustermann.de',
    '{{firma}}':          'Musterfirma GmbH',
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
        updatePreview(ta, ta.id.replace('edit_content_', 'edit_preview_'));
    });
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

