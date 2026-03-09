<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();
requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Tabellen werden über database.sql angelegt – keine DDL hier

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Feld hinzufügen
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $label       = trim($_POST['field_label'] ?? '');
        $type        = $_POST['field_type']  ?? 'text';
        $required    = isset($_POST['is_required'])   ? 1 : 0;
        $active      = isset($_POST['is_active'])     ? 1 : 0;
        $showList    = isset($_POST['show_in_list'])  ? 1 : 0;
        $showPublic  = isset($_POST['show_public'])   ? 1 : 0;
        $placeholder = trim($_POST['placeholder']     ?? '');
        $helpText    = trim($_POST['help_text']       ?? '');
        $options     = trim($_POST['field_options']   ?? '');
        $sortOrder   = max(0, (int)($_POST['sort_order'] ?? 99));

        // Optionen für Select validieren/normalisieren
        $optionsJson = null;
        if ($type === 'select' && !empty($options)) {
            $lines = array_filter(array_map('trim', explode("\n", $options)));
            $optionsJson = json_encode(array_values($lines));
        }

        if (empty($label)) {
            $error = $translator->translate('tf_error_label_empty');
        } else {
            // field_name aus Label generieren (slug)
            $fieldName = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
            $fieldName = 'cf_' . trim($fieldName, '_');

            // Eindeutigkeit sicherstellen
            $check = $db->prepare("SELECT COUNT(*) FROM ticket_custom_fields WHERE field_name = ?");
            $check->execute([$fieldName]);
            if ($check->fetchColumn() > 0) {
                $fieldName .= '_' . time();
            }

            $stmt = $db->prepare("INSERT INTO ticket_custom_fields
                (field_name, field_label, field_type, field_options, is_required, is_active, show_in_list, show_public, sort_order, placeholder, help_text)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            if ($stmt->execute([$fieldName, $label, $type, $optionsJson, $required, $active, $showList, $showPublic, $sortOrder, $placeholder ?: null, $helpText ?: null])) {
                $message = '✅ ' . sprintf($translator->translate('tf_msg_added'), htmlspecialchars($label));
            } else {
                $error = $translator->translate('tf_error_save');
            }
        }
    }

    // Feld bearbeiten
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id          = (int)$_POST['field_id'];
        $label       = trim($_POST['field_label'] ?? '');
        $type        = $_POST['field_type']  ?? 'text';
        $required    = isset($_POST['is_required'])   ? 1 : 0;
        $active      = isset($_POST['is_active'])     ? 1 : 0;
        $showList    = isset($_POST['show_in_list'])  ? 1 : 0;
        $showPublic  = isset($_POST['show_public'])   ? 1 : 0;
        $placeholder = trim($_POST['placeholder']     ?? '');
        $helpText    = trim($_POST['help_text']       ?? '');
        $options     = trim($_POST['field_options']   ?? '');
        $sortOrder   = max(0, (int)($_POST['sort_order'] ?? 99));

        $optionsJson = null;
        if ($type === 'select' && !empty($options)) {
            $lines = array_filter(array_map('trim', explode("\n", $options)));
            $optionsJson = json_encode(array_values($lines));
        }

        if (empty($label)) {
            $error = $translator->translate('tf_error_label_empty');
        } else {
            $stmt = $db->prepare("UPDATE ticket_custom_fields SET
                field_label=?, field_type=?, field_options=?, is_required=?, is_active=?,
                show_in_list=?, show_public=?, sort_order=?, placeholder=?, help_text=?
                WHERE id=?");
            if ($stmt->execute([$label, $type, $optionsJson, $required, $active, $showList, $showPublic, $sortOrder, $placeholder ?: null, $helpText ?: null, $id])) {
                $message = '✅ ' . $translator->translate('tf_msg_updated');
            } else {
                $error = $translator->translate('tf_error_update');
            }
        }
    }

    // Feld löschen
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['field_id'];
        $db->prepare("DELETE FROM ticket_field_values WHERE field_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM ticket_custom_fields WHERE id = ?")->execute([$id]);
        $message = '✅ ' . $translator->translate('tf_msg_deleted');
    }

    // Reihenfolge speichern (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            $stmt = $db->prepare("UPDATE ticket_custom_fields SET sort_order=? WHERE id=?");
            foreach ($order as $pos => $fid) {
                $stmt->execute([$pos, (int)$fid]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Felder laden
$fields = $db->query("SELECT * FROM ticket_custom_fields ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

$fieldTypes = [
    'text'     => $translator->translate('tf_type_text'),
    'textarea' => $translator->translate('tf_type_textarea'),
    'number'   => $translator->translate('tf_type_number'),
    'select'   => $translator->translate('tf_type_select'),
    'checkbox' => $translator->translate('tf_type_checkbox'),
    'date'     => $translator->translate('tf_type_date'),
    'email'    => $translator->translate('tf_type_email'),
    'url'      => $translator->translate('tf_type_url'),
    'phone'    => $translator->translate('tf_type_phone'),
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('tf_page_title') ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .field-table { width:100%; border-collapse:collapse; }
        .field-table th, .field-table td { padding:.65rem .9rem; text-align:left; border-bottom:1px solid var(--border-color,#e5e7eb); font-size:.88rem; }
        .field-table th { background:var(--bg-secondary,#f3f4f6); font-weight:600; }
        .field-table tr:hover td { background:var(--bg-hover,rgba(0,0,0,.025)); }
        .badge { display:inline-block; padding:.2rem .55rem; border-radius:999px; font-size:.75rem; font-weight:600; }
        .badge-required { background:#fee2e2; color:#b91c1c; }
        .badge-optional { background:#f0f0f0; color:#666; }
        .badge-active   { background:#dcfce7; color:#166534; }
        .badge-inactive { background:#f3f4f6; color:#9ca3af; }
        .badge-type     { background:#dbeafe; color:#1e40af; }
        .drag-handle    { cursor:grab; color:#aaa; font-size:1.1rem; user-select:none; }
        .drag-handle:active { cursor:grabbing; }
        .sortable-ghost { opacity:.4; background:var(--primary-light,#eff6ff)!important; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--bg-card,#fff); border-radius:12px; padding:1.75rem; max-width:560px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 8px 40px rgba(0,0,0,.25); }
        .modal-box h3 { margin:0 0 1.25rem; font-size:1.15rem; }
        .modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.25rem; }
        .options-hint { font-size:.78rem; color:var(--text-light,#6b7280); margin-top:.3rem; }
        .field-preview-row { display:flex; align-items:center; gap:.5rem; padding:.5rem .75rem; border-radius:8px; background:var(--bg-secondary,#f9fafb); margin-bottom:.4rem; cursor:pointer; transition:background .15s; }
        .field-preview-row:hover { background:var(--primary-light,#eff6ff); }
        .field-preview-row .drag-handle { margin-right:.25rem; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light,#9ca3af); }
        .empty-state svg { width:56px; height:56px; margin-bottom:1rem; opacity:.35; }
        .toggle-switch { position:relative; display:inline-block; width:42px; height:22px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .slider { position:absolute; inset:0; background:#ccc; border-radius:999px; cursor:pointer; transition:.2s; }
        .slider:before { content:''; position:absolute; width:16px; height:16px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
        input:checked + .slider { background:var(--primary,#667eea); }
        input:checked + .slider:before { transform:translateX(20px); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        @media(max-width:600px){ .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container" style="max-width:1100px; padding-top:1.5rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:.75rem;">
        <div>
            <h1 style="margin:0; font-size:1.5rem;">🗂️ <?= $translator->translate('tf_page_title') ?></h1>
            <p style="margin:.25rem 0 0; color:var(--text-light,#6b7280); font-size:.88rem;">
                <?= $translator->translate('tf_page_subtitle') ?>
            </p>
        </div>
        <div style="display:flex; gap:.6rem;">
            <a href="settings.php" class="btn btn-secondary" style="font-size:.85rem;">⚙️ <?= $translator->translate('tf_btn_settings') ?></a>
            <button class="btn btn-primary" onclick="openAddModal()" style="font-size:.85rem;">➕ <?= $translator->translate('tf_btn_new') ?></button>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?= $message ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"   style="margin-bottom:1rem;"><?= escape($error) ?></div><?php endif; ?>

    <!-- Info-Box -->
    <div class="card" style="margin-bottom:1.5rem; border-left:4px solid var(--primary,#667eea);">
        <div class="card-body" style="padding:.85rem 1rem;">
            <strong>ℹ️ <?= $translator->translate('tf_info_title') ?>:</strong>
            <ul style="margin:.5rem 0 0 1.25rem; font-size:.85rem; line-height:1.7; color:var(--text-light,#555);">
                <li><?= $translator->translate('tf_info_1') ?></li>
                <li><strong><?= $translator->translate('tf_info_2_label') ?></strong> <?= $translator->translate('tf_info_2_text') ?></li>
                <li><strong><?= $translator->translate('tf_info_3_label') ?></strong>: <?= $translator->translate('tf_info_3_text') ?></li>
                <li><?= $translator->translate('tf_info_4') ?></li>
                <li><?= $translator->translate('tf_info_5') ?></li>
            </ul>
        </div>
    </div>

    <!-- Feld-Tabelle -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span>📋 <?= $translator->translate('tf_table_header') ?> (<?= count($fields) ?>)</span>
            <span style="font-size:.8rem; color:var(--text-light);"><?= $translator->translate('tf_drag_hint') ?></span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($fields)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p style="font-size:1rem; font-weight:600;"><?= $translator->translate('tf_empty_title') ?></p>
                    <p style="font-size:.85rem;"><?= $translator->translate('tf_empty_hint') ?></p>
                    <button class="btn btn-primary" onclick="openAddModal()" style="margin-top:.75rem;">➕ <?= $translator->translate('tf_empty_btn') ?></button>
                </div>
            <?php else: ?>
                <table class="field-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th><?= $translator->translate('tf_col_label') ?></th>
                            <th><?= $translator->translate('tf_col_type') ?></th>
                            <th><?= $translator->translate('tf_col_status') ?></th>
                            <th><?= $translator->translate('tf_col_required') ?></th>
                            <th><?= $translator->translate('tf_col_public') ?></th>
                            <th><?= $translator->translate('tf_col_in_list') ?></th>
                            <th><?= $translator->translate('tf_col_order') ?></th>
                            <th><?= $translator->translate('tf_col_actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="fields-sortable">
                        <?php foreach ($fields as $field): ?>
                        <tr data-id="<?= $field['id'] ?>">
                            <td><span class="drag-handle" title="<?= $translator->translate('tf_drag_title') ?>">⠿</span></td>
                            <td>
                                <strong><?= escape($field['field_label']) ?></strong>
                                <div style="font-size:.75rem; color:var(--text-light,#9ca3af); font-family:monospace;"><?= escape($field['field_name']) ?></div>
                                <?php if ($field['help_text']): ?>
                                    <div style="font-size:.75rem; color:var(--text-light,#9ca3af); margin-top:.2rem;">💬 <?= escape($field['help_text']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-type"><?= htmlspecialchars($fieldTypes[$field['field_type']] ?? $field['field_type']) ?></span></td>
                            <td>
                                <?php if ($field['is_active']): ?>
                                    <span class="badge badge-active">✓ <?= $translator->translate('tf_badge_active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">✗ <?= $translator->translate('tf_badge_inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($field['is_required']): ?>
                                    <span class="badge badge-required">★ <?= $translator->translate('tf_badge_required') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-optional"><?= $translator->translate('tf_badge_optional') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $field['show_public'] ? '✅' : '❌' ?></td>
                            <td><?= $field['show_in_list'] ? '✅' : '❌' ?></td>
                            <td style="color:var(--text-light);"><?= (int)$field['sort_order'] ?></td>
                            <td>
                                <button class="btn btn-secondary" style="padding:.3rem .7rem; font-size:.8rem;"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($field)) ?>)">
                                    ✏️ <?= $translator->translate('tf_btn_edit') ?>
                                </button>
                                <button class="btn btn-danger" style="padding:.3rem .7rem; font-size:.8rem; margin-left:.3rem;"
                                    onclick="confirmDelete(<?= $field['id'] ?>, '<?= escape($field['field_label']) ?>')">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══ Modal: Feld hinzufügen / bearbeiten ═══ -->
<div class="modal-overlay" id="fieldModal">
    <div class="modal-box">
        <h3 id="modalTitle">➕ <?= $translator->translate('tf_modal_add_title') ?></h3>
        <form method="POST" id="fieldForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="field_id" id="formFieldId" value="">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('tf_form_label') ?> <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="field_label" id="fLabel" class="form-control"
                           placeholder="<?= $translator->translate('tf_form_label_ph') ?>" required>
                    <small style="color:var(--text-light);"><?= $translator->translate('tf_form_label_hint') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('tf_form_type') ?></label>
                    <select name="field_type" id="fType" class="form-control" onchange="onTypeChange()">
                        <?php foreach ($fieldTypes as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" id="optionsGroup" style="display:none;">
                <label class="form-label"><?= $translator->translate('tf_form_options') ?></label>
                <textarea name="field_options" id="fOptions" class="form-control" rows="4"
                          placeholder="<?= $translator->translate('tf_form_options_ph') ?>"></textarea>
                <div class="options-hint"><?= $translator->translate('tf_form_options_hint') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= $translator->translate('tf_form_placeholder') ?></label>
                <input type="text" name="placeholder" id="fPlaceholder" class="form-control"
                       placeholder="<?= $translator->translate('tf_form_placeholder_ph') ?>">
            </div>

            <div class="form-group">
                <label class="form-label"><?= $translator->translate('tf_form_help_text') ?></label>
                <input type="text" name="help_text" id="fHelpText" class="form-control"
                       placeholder="<?= $translator->translate('tf_form_help_text_ph') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('tf_form_sort_order') ?></label>
                    <input type="number" name="sort_order" id="fSortOrder" class="form-control" value="99" min="0">
                </div>
            </div>

            <!-- Toggle-Optionen -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-top:.5rem;">
                <label style="display:flex; align-items:center; gap:.75rem; cursor:pointer; padding:.6rem .75rem; border-radius:8px; background:var(--bg-secondary,#f9fafb);">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_required" id="fRequired">
                        <span class="slider"></span>
                    </label>
                    <span>
                        <strong><?= $translator->translate('tf_toggle_required') ?></strong><br>
                        <small style="color:var(--text-light);"><?= $translator->translate('tf_toggle_required_hint') ?></small>
                    </span>
                </label>
                <label style="display:flex; align-items:center; gap:.75rem; cursor:pointer; padding:.6rem .75rem; border-radius:8px; background:var(--bg-secondary,#f9fafb);">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" id="fActive" checked>
                        <span class="slider"></span>
                    </label>
                    <span>
                        <strong><?= $translator->translate('tf_toggle_active') ?></strong><br>
                        <small style="color:var(--text-light);"><?= $translator->translate('tf_toggle_active_hint') ?></small>
                    </span>
                </label>
                <label style="display:flex; align-items:center; gap:.75rem; cursor:pointer; padding:.6rem .75rem; border-radius:8px; background:var(--bg-secondary,#f9fafb);">
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_public" id="fShowPublic" checked>
                        <span class="slider"></span>
                    </label>
                    <span>
                        <strong><?= $translator->translate('tf_toggle_public') ?></strong><br>
                        <small style="color:var(--text-light);"><?= $translator->translate('tf_toggle_public_hint') ?></small>
                    </span>
                </label>
                <label style="display:flex; align-items:center; gap:.75rem; cursor:pointer; padding:.6rem .75rem; border-radius:8px; background:var(--bg-secondary,#f9fafb);">
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_in_list" id="fShowList">
                        <span class="slider"></span>
                    </label>
                    <span>
                        <strong><?= $translator->translate('tf_toggle_in_list') ?></strong><br>
                        <small style="color:var(--text-light);"><?= $translator->translate('tf_toggle_in_list_hint') ?></small>
                    </span>
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?= $translator->translate('tf_btn_cancel') ?></button>
                <button type="submit" class="btn btn-primary" id="modalSubmitBtn"><?= $translator->translate('tf_btn_save_field') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Löschen-Bestätigung -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width:420px;">
        <h3>🗑️ <?= $translator->translate('tf_delete_title') ?></h3>
        <p><?= $translator->translate('tf_delete_confirm_text') ?> <strong id="deleteFieldName"></strong>?</p>
        <div class="alert alert-error" style="font-size:.85rem; padding:.65rem .9rem;">
            ⚠️ <?= $translator->translate('tf_delete_warning') ?>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="field_id" id="deleteFieldId">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').classList.remove('open')"><?= $translator->translate('tf_btn_cancel') ?></button>
                <button type="submit" class="btn btn-danger"><?= $translator->translate('tf_btn_delete') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Sortable.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// ── Drag & Drop Sortierung ────────────────────────────────────────────────────
const sortableTbody = document.getElementById('fields-sortable');
if (sortableTbody) {
    Sortable.create(sortableTbody, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            const order = [...sortableTbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(order))
            });
        }
    });
}

// ── Modal-Steuerung ───────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent = '➕ <?= addslashes($translator->translate('tf_modal_add_title')) ?>';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formFieldId').value = '';
    document.getElementById('fLabel').value = '';
    document.getElementById('fType').value = 'text';
    document.getElementById('fOptions').value = '';
    document.getElementById('fPlaceholder').value = '';
    document.getElementById('fHelpText').value = '';
    document.getElementById('fSortOrder').value = 99;
    document.getElementById('fRequired').checked = false;
    document.getElementById('fActive').checked = true;
    document.getElementById('fShowPublic').checked = true;
    document.getElementById('fShowList').checked = false;
    document.getElementById('modalSubmitBtn').textContent = '<?= addslashes($translator->translate('tf_btn_add_field')) ?>';
    onTypeChange();
    document.getElementById('fieldModal').classList.add('open');
}

function openEditModal(field) {
    document.getElementById('modalTitle').textContent = '✏️ <?= addslashes($translator->translate('tf_modal_edit_title')) ?>';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formFieldId').value = field.id;
    document.getElementById('fLabel').value = field.field_label;
    document.getElementById('fType').value = field.field_type;
    document.getElementById('fPlaceholder').value = field.placeholder || '';
    document.getElementById('fHelpText').value = field.help_text || '';
    document.getElementById('fSortOrder').value = field.sort_order;
    document.getElementById('fRequired').checked = field.is_required == 1;
    document.getElementById('fActive').checked = field.is_active == 1;
    document.getElementById('fShowPublic').checked = field.show_public == 1;
    document.getElementById('fShowList').checked = field.show_in_list == 1;
    document.getElementById('modalSubmitBtn').textContent = '<?= addslashes($translator->translate('tf_btn_save_changes')) ?>';

    // Select-Optionen
    if (field.field_options) {
        try {
            const opts = JSON.parse(field.field_options);
            document.getElementById('fOptions').value = opts.join('\n');
        } catch(e) {
            document.getElementById('fOptions').value = '';
        }
    } else {
        document.getElementById('fOptions').value = '';
    }

    onTypeChange();
    document.getElementById('fieldModal').classList.add('open');
}

function closeModal() {
    document.getElementById('fieldModal').classList.remove('open');
}

function confirmDelete(id, name) {
    document.getElementById('deleteFieldId').value = id;
    document.getElementById('deleteFieldName').textContent = '"' + name + '"';
    document.getElementById('deleteModal').classList.add('open');
}

function onTypeChange() {
    const type = document.getElementById('fType').value;
    document.getElementById('optionsGroup').style.display = type === 'select' ? '' : 'none';
}

// Außerhalb klicken schließt Modal
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

