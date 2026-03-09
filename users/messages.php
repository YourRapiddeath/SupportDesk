<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();
requireRole(['first_level','second_level','third_level','admin']);

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postfach – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .pm-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.25rem;
            align-items: start;
            max-width: 1300px;
            margin: 0 auto;
        }

        .pm-sidebar {
            display: flex; flex-direction: column; gap: 0.75rem;
            max-height: calc(100vh - 120px); overflow-y: auto;
        }

        .pm-compose-btn {
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; padding: 0.75rem 1rem;
            background: var(--primary); color: #fff;
            border: none; border-radius: 10px; font-weight: 700;
            font-size: 0.95rem; cursor: pointer; width: 100%;
            transition: opacity .15s;
        }
        .pm-compose-btn:hover { opacity: 0.88; }

        .pm-nav { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .pm-nav-item {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.75rem 1rem; cursor: pointer;
            font-size: 0.9rem; font-weight: 500;
            border-left: 3px solid transparent;
            transition: background .12s, border-color .12s;
            color: var(--text);
        }
        .pm-nav-item:hover { background: var(--background); }
        .pm-nav-item.active { border-left-color: var(--primary); background: var(--background); font-weight: 700; color: var(--primary); }
        .pm-nav-item .pm-nav-count {
            margin-left: auto; background: var(--danger);
            color: #fff; font-size: 0.72rem; font-weight: 700;
            padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center;
        }

        .pm-supporter-list {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            overflow-y: auto; max-height: 320px;
        }
        .pm-supporter-item {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.65rem 1rem; cursor: pointer;
            font-size: 0.85rem; border-bottom: 1px solid var(--separator);
            transition: background .1s; color: var(--text);
        }
        .pm-supporter-item:last-child { border-bottom: none; }
        .pm-supporter-item:hover { background: var(--background); }
        .pm-supporter-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            object-fit: cover; background: var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
        }
        .pm-supporter-avatar img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        .pm-main {
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            height: calc(100vh - 120px);
            display: flex; flex-direction: column; overflow: hidden;
        }
        .pm-main-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 0.75rem; font-weight: 700; font-size: 1rem;
            flex-shrink: 0;
        }
        .pm-main-body {
            flex: 1; overflow-y: auto; overflow-x: hidden;
        }

        .pm-msg-item {
            display: flex; align-items: flex-start; gap: 0.75rem;
            padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--separator);
            cursor: pointer; transition: background .1s;
        }
        .pm-msg-item:hover { background: var(--background); }
        .pm-msg-item.unread { background: rgba(var(--primary-rgb, 29,78,216), 0.05); }
        .pm-msg-item.unread .pm-msg-subject { font-weight: 800; }
        .pm-msg-avatar {
            width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
            background: var(--primary); display: flex; align-items: center;
            justify-content: center; color: #fff; font-weight: 700; font-size: 0.9rem;
            overflow: hidden;
        }
        .pm-msg-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .pm-msg-body { flex: 1; min-width: 0; }
        .pm-msg-meta { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2px; }
        .pm-msg-from { font-size: 0.88rem; font-weight: 600; color: var(--text); }
        .pm-msg-date { font-size: 0.75rem; color: var(--text-light); margin-left: auto; white-space: nowrap; }
        .pm-msg-subject { font-size: 0.88rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pm-msg-preview { font-size: 0.78rem; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        .pm-unread-dot { width: 8px; height: 8px; background: var(--primary); border-radius: 50%; flex-shrink: 0; margin-top: 5px; }

        .pm-view {
            padding: 1.5rem; overflow-y: auto;
        }
        .pm-view-header { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .pm-view-subject { font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; }
        .pm-view-meta { font-size: 0.82rem; color: var(--text-light); display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .pm-msg-bubble {
            display: flex; gap: 0.75rem; margin-bottom: 1.25rem;
        }
        .pm-msg-bubble.mine { flex-direction: row-reverse; }
        .pm-msg-bubble-avatar {
            width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
            background: var(--primary); display: flex; align-items: center;
            justify-content: center; color: #fff; font-weight: 700; font-size: 0.85rem; overflow: hidden;
        }
        .pm-msg-bubble-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .pm-msg-bubble-content { max-width: 70%; }
        .pm-msg-bubble-name { font-size: 0.75rem; color: var(--text-light); margin-bottom: 3px; }
        .pm-msg-bubble.mine .pm-msg-bubble-name { text-align: right; }
        .pm-msg-bubble-text {
            background: var(--background); border: 1px solid var(--border);
            border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.88rem;
            line-height: 1.6; white-space: pre-wrap; word-break: break-word;
        }
        .pm-msg-bubble.mine .pm-msg-bubble-text {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }
        /* Ticket-Button in PM */
        .pm-ticket-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            margin-top: 0.75rem; padding: 0.5rem 1rem;
            background: var(--primary); color: #fff;
            border-radius: 8px; font-size: 0.82rem; font-weight: 700;
            text-decoration: none; border: none; cursor: pointer;
            transition: opacity .15s;
        }
        .pm-ticket-btn:hover { opacity: 0.85; color: #fff; }
        .pm-msg-bubble.mine .pm-ticket-btn {
            background: rgba(255,255,255,0.25); color: #fff; border: 1px solid rgba(255,255,255,0.5);
        }
        .pm-msg-bubble-time { font-size: 0.72rem; color: var(--text-light); margin-top: 3px; }
        .pm-msg-bubble.mine .pm-msg-bubble-time { text-align: right; }

        .pm-compose {
            padding: 1.25rem; overflow-y: auto;
        }
        .pm-compose-header { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .pm-reply-box {
            border-top: 1px solid var(--border); padding: 1rem 1.25rem;
            background: var(--background); border-radius: 0 0 12px 12px;
            flex-shrink: 0;
        }
        .pm-compose textarea,
        .pm-reply-box textarea {
            resize: vertical;
            min-height: 80px;
            max-height: 320px;
            overflow-y: auto;
        }

        .pm-empty { padding: 3rem; text-align: center; color: var(--text-light); }
        .pm-empty-icon { font-size: 3rem; margin-bottom: 0.75rem; }

        .pm-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }

        @media (max-width: 768px) {
            .pm-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container" style="padding-top: 1.5rem;">
    <div class="pm-layout">
        <div class="pm-sidebar">
            <button class="pm-compose-btn" onclick="pmShowCompose()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= $translator->translate('pm_new_message_title') ?>
            </button>

            <nav class="pm-nav">
                <div class="pm-nav-item active" id="nav-inbox" onclick="pmLoadInbox()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                    <?= $translator->translate('pm_inbox') ?>
                    <span class="pm-nav-count" id="inbox-badge" style="display:none;">0</span>
                </div>
                <div class="pm-nav-item" id="nav-sent" onclick="pmLoadSent()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <?= $translator->translate('pm_sent') ?>
                </div>
                <div class="pm-nav-item" id="nav-trash" onclick="pmLoadTrash()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    <?= $translator->translate('pm_trash') ?>
                    <span class="pm-nav-count" id="trash-badge" style="display:none;background:var(--text-light);">0</span>
                </div>
            </nav>

            <div style="font-size:0.78rem; font-weight:700; color:var(--text-light); padding: 0.25rem 0.25rem 0.25rem 0.5rem; text-transform:uppercase; letter-spacing:.05em;">
                <?= $translator->translate('pm_to') ?>
            </div>
            <div class="pm-supporter-list" id="pm-supporter-list">
                <div style="padding:1rem; font-size:0.82rem; color:var(--text-light);">Lade Supporter…</div>
            </div>
        </div>

        <div class="pm-main" id="pm-main">
            <div class="pm-empty">
                <div class="pm-empty-icon">📬</div>
                <div style="font-weight:600; margin-bottom:0.5rem;"><?= $translator->translate('pm_welcome_title') ?></div>
                <div style="font-size:0.85rem;"><?= $translator->translate('pm_welcome_hint') ?></div>
            </div>
        </div>
    </div>
</div>

<script>
const PM_API    = '../includes/pm.php';
const MY_ID     = <?= (int)$userId ?>;
const MY_NAME   = '<?= addslashes($userName) ?>';
const PM_STR = {
    inbox:              '<?= addslashes($translator->translate('pm_inbox')) ?>',
    sent:               '<?= addslashes($translator->translate('pm_sent')) ?>',
    trash:              '<?= addslashes($translator->translate('pm_trash')) ?>',
    noInbox:            '<?= addslashes($translator->translate('pm_no_inbox')) ?>',
    noSent:             '<?= addslashes($translator->translate('pm_no_sent')) ?>',
    trashEmpty:         '<?= addslashes($translator->translate('pm_trash_empty')) ?>',
    trashEmptyHint:     '<?= addslashes($translator->translate('pm_trash_empty_hint')) ?>',
    noSubject:          '<?= addslashes($translator->translate('pm_no_subject')) ?>',
    loading:            '<?= addslashes($translator->translate('pm_loading')) ?>',
    justNow:            '<?= addslashes($translator->translate('pm_just_now')) ?>',
    today:              '<?= addslashes($translator->translate('pm_today')) ?>',
    yesterday:          '<?= addslashes($translator->translate('pm_yesterday')) ?>',
    system:             '🤖 <?= addslashes($translator->translate('pm_system')) ?>',
    me:                 '<?= addslashes($translator->translate('pm_me')) ?>',
    to:                 '<?= addslashes($translator->translate('pm_to')) ?>',
    from:               '<?= addslashes($translator->translate('pm_from')) ?>',
    recipientSelect:    '– <?= addslashes($translator->translate('pm_recipient_select')) ?> –',
    subject:            '<?= addslashes($translator->translate('pm_subject')) ?>',
    subjectPh:          '<?= addslashes($translator->translate('pm_subject_ph')) ?>',
    message:            '<?= addslashes($translator->translate('pm_message')) ?>',
    messagePh:          '<?= addslashes($translator->translate('pm_message_ph')) ?>',
    send:               '<?= addslashes($translator->translate('pm_send')) ?>',
    cancel:             '<?= addslashes($translator->translate('cancel')) ?>',
    reply:              '<?= addslashes($translator->translate('pm_reply')) ?>',
    replyPh:            '<?= addslashes($translator->translate('pm_reply_ph')) ?>',
    replySend:          '<?= addslashes($translator->translate('pm_reply_send')) ?>',
    errRequired:        '<?= addslashes($translator->translate('pm_error_required')) ?>',
    errSend:            '<?= addslashes($translator->translate('pm_error_send')) ?>',
    errNetwork:         '<?= addslashes($translator->translate('pm_error_network')) ?>',
    errLoad:            '<?= addslashes($translator->translate('pm_error_load')) ?>',
    toastSent:          '✅ <?= addslashes($translator->translate('pm_toast_sent')) ?>',
    toastTrash:         '🗑 <?= addslashes($translator->translate('pm_toast_trash')) ?>',
    toastRestored:      '♻️ <?= addslashes($translator->translate('pm_toast_restored')) ?>',
    toastDeleted:       '🗑 <?= addslashes($translator->translate('pm_toast_deleted')) ?>',
    toastEmptied:       '🗑 <?= addslashes($translator->translate('pm_toast_emptied')) ?>',
    confirmDelete:      '<?= addslashes($translator->translate('pm_confirm_delete')) ?>',
    confirmDeletePerm:  '<?= addslashes($translator->translate('pm_confirm_delete_permanent')) ?>',
    confirmEmpty:       '<?= addslashes($translator->translate('pm_confirm_empty_trash')) ?>',
    confirmTrash:       '<?= addslashes($translator->translate('pm_confirm_trash')) ?>',
    btnTrash:           '🗑 <?= addslashes($translator->translate('pm_btn_trash')) ?>',
    btnRestore:         '♻️ <?= addslashes($translator->translate('pm_restore')) ?>',
    btnDeletePerm:      '🗑 <?= addslashes($translator->translate('pm_delete_permanent')) ?>',
    btnEmptyTrash:      '🗑 <?= addslashes($translator->translate('pm_empty_trash')) ?>',
    btnOpenTicket:      '<?= addslashes($translator->translate('pm_open_ticket')) ?>',
    newMessage:         '<?= addslashes($translator->translate('pm_new_message_title')) ?>',
    replyTo:            '<?= addslashes($translator->translate('pm_reply_to')) ?>',
    noEvents:           '<?= addslashes($translator->translate('pm_no_events')) ?>',
};

const SITE_URL  = '<?= rtrim(SITE_URL, '/') ?>';
const BASE_URL  = SITE_URL || (window.location.protocol + '//' + window.location.host);

let pmCurrentView  = 'inbox';
let pmCurrentMsgId = null;

// ── Initialisierung ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    pmLoadSupporters();
    pmLoadInbox();
    pmRefreshBadge();
    pmRefreshTrashBadge();
    setInterval(pmRefreshBadge, 15000);
    setInterval(pmRefreshTrashBadge, 30000);
});

// ── Badge aktualisieren ──────────────────────────────────────────────────────
async function pmRefreshBadge() {
    try {
        const r = await fetch(PM_API + '?action=unread_count');
        const d = await r.json();
        const badge = document.getElementById('inbox-badge');
        if (d.count > 0) {
            badge.textContent = d.count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
        // Navbar-Badge aktualisieren
        const navBadge = document.getElementById('pm-navbar-badge');
        if (navBadge) {
            navBadge.textContent = d.count;
            navBadge.style.display = d.count > 0 ? 'flex' : 'none';
        }
    } catch(e) {}
}
async function pmLoadSupporters() {
    const list = document.getElementById('pm-supporter-list');
    try {
        const r = await fetch(PM_API + '?action=supporters');
        const supporters = await r.json();
        if (!supporters.length) {
            list.innerHTML = `<div style="padding:0.75rem 1rem; font-size:0.82rem; color:var(--text-light);">${PM_STR.recipientSelect}</div>`;
            return;
        }
        list.innerHTML = supporters.map(s => `
            <div class="pm-supporter-item" onclick="pmShowCompose(${s.id}, ${JSON.stringify(s.full_name)})">
                <div class="pm-supporter-avatar">
                    ${s.avatar ? `<img src="${BASE_URL}/${s.avatar}" alt="">` : pmInitials(s.full_name)}
                </div>
                <div>
                    <div style="font-weight:600; font-size:0.85rem;">${pmEscape(s.full_name)}</div>
                    <div style="font-size:0.72rem; color:var(--text-light);">${pmTranslateRole(s.role)}</div>
                </div>
            </div>
        `).join('');
    } catch(e) {
        list.innerHTML = `<div style="padding:0.75rem 1rem; font-size:0.82rem; color:var(--danger);">${PM_STR.errLoad}</div>`;
    }
}

async function pmLoadInbox() {
    pmSetNav('inbox');
    const main = document.getElementById('pm-main');
    main.innerHTML = pmLoadingHtml(PM_STR.inbox);
    try {
        const r = await fetch(PM_API + '?action=inbox');
        const msgs = await r.json();
        main.innerHTML = pmRenderList(msgs, 'inbox');
    } catch(e) {
        main.innerHTML = `<div class="pm-empty"><div class="pm-empty-icon">⚠️</div><div>${PM_STR.errLoad}</div></div>`;
    }
}

async function pmLoadSent() {
    pmSetNav('sent');
    const main = document.getElementById('pm-main');
    main.innerHTML = pmLoadingHtml(PM_STR.sent);
    try {
        const r = await fetch(PM_API + '?action=sent');
        const msgs = await r.json();
        main.innerHTML = pmRenderList(msgs, 'sent');
    } catch(e) {
        main.innerHTML = `<div class="pm-empty"><div class="pm-empty-icon">⚠️</div><div>${PM_STR.errLoad}</div></div>`;
    }
}

async function pmViewMessage(id) {
    pmCurrentMsgId = id;
    const main = document.getElementById('pm-main');
    main.innerHTML = `<div class="pm-main-header"><span>⏳ ${PM_STR.loading}</span></div>`;
    try {
        const r = await fetch(PM_API + '?action=view&id=' + id);
        const d = await r.json();
        if (d.error) { main.innerHTML = `<div class="pm-empty"><div class="pm-empty-icon">❌</div><div>${PM_STR.errLoad}</div></div>`; return; }
        main.innerHTML = pmRenderView(d.message, d.thread);
        pmRefreshBadge();
        requestAnimationFrame(() => {
            const last = document.getElementById('pm-last-msg');
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'end' });
            } else {
                const body = document.getElementById('pm-body-scroll');
                if (body) body.scrollTop = body.scrollHeight;
            }
        });
    } catch(e) {
        main.innerHTML = `<div class="pm-empty"><div class="pm-empty-icon">⚠️</div><div>${PM_STR.errLoad}</div></div>`;
    }
}

function pmShowCompose(receiverId = null, receiverName = null) {
    pmSetNav(null);
    const main = document.getElementById('pm-main');

    // Supporter-Optionen laden
    fetch(PM_API + '?action=supporters').then(r => r.json()).then(supporters => {
        const options = supporters.map(s =>
            `<option value="${s.id}" ${receiverId && s.id == receiverId ? 'selected' : ''}>${pmEscape(s.full_name)} (${pmTranslateRole(s.role)})</option>`
        ).join('');

        main.innerHTML = `
            <div class="pm-main-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                ${PM_STR.newMessage}
            </div>
            <div class="pm-main-body">
            <div class="pm-compose">
                <div class="form-group">
                    <label class="form-label">${PM_STR.to} <span style="color:var(--danger)">*</span></label>
                    <select id="pm-receiver" class="form-control">
                        <option value="">${PM_STR.recipientSelect}</option>
                        ${options}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">${PM_STR.subject} <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="pm-subject" class="form-control" placeholder="${PM_STR.subjectPh}" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">${PM_STR.message} <span style="color:var(--danger)">*</span></label>
                    <textarea id="pm-message" class="form-control" rows="10" placeholder="${PM_STR.messagePh}"></textarea>
                </div>
                <div style="display:flex; gap:0.75rem; align-items:center;">
                    <button class="btn btn-primary" onclick="pmSend()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        ${PM_STR.send}
                    </button>
                    <button class="btn btn-secondary" onclick="pmLoadInbox()">${PM_STR.cancel}</button>
                    <span id="pm-send-status" style="font-size:0.82rem; color:var(--danger); display:none;"></span>
                </div>
            </div>
            </div>
        `;
    });
}

async function pmSend(parentId = null) {
    const receiverId = parentId
        ? document.getElementById('pm-reply-receiver')?.value
        : document.getElementById('pm-receiver')?.value;
    const subject  = parentId ? '' : (document.getElementById('pm-subject')?.value.trim() || '');
    const message  = parentId
        ? document.getElementById('pm-reply-text')?.value.trim()
        : document.getElementById('pm-message')?.value.trim();
    const statusEl = document.getElementById(parentId ? 'pm-reply-status' : 'pm-send-status');

    if (!receiverId || !message) {
        if (statusEl) { statusEl.textContent = PM_STR.errRequired; statusEl.style.display = 'inline'; }
        return;
    }

    const fd = new FormData();
    fd.append('action',      'send');
    fd.append('receiver_id', receiverId);
    fd.append('subject',     subject);
    fd.append('message',     message);
    if (parentId) fd.append('parent_id', parentId);

    try {
        const r = await fetch(PM_API, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            if (parentId) {
                pmViewMessage(parentId);
                pmRefreshBadge();
            } else {
                pmLoadSent();
                pmShowToast(PM_STR.toastSent);
            }
        } else {
            if (statusEl) { statusEl.textContent = d.error || PM_STR.errSend; statusEl.style.display = 'inline'; }
        }
    } catch(e) {
        if (statusEl) { statusEl.textContent = PM_STR.errNetwork; statusEl.style.display = 'inline'; }
    }
}

async function pmDelete(id) {
    if (!confirm(PM_STR.confirmTrash)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    await fetch(PM_API, { method: 'POST', body: fd });
    pmShowToast(PM_STR.toastTrash);
    pmRefreshTrashBadge();
    if (pmCurrentView === 'sent') pmLoadSent();
    else pmLoadInbox();
    pmRefreshBadge();
}

async function pmLoadTrash() {
    pmSetNav('trash');
    const main = document.getElementById('pm-main');
    main.innerHTML = pmLoadingHtml(PM_STR.trash);
    try {
        const r    = await fetch(PM_API + '?action=trash');
        const msgs = await r.json();
        main.innerHTML = pmRenderTrash(msgs);
        pmRefreshTrashBadge();
    } catch(e) {
        main.innerHTML = `<div class="pm-empty"><div class="pm-empty-icon">⚠️</div><div>${PM_STR.errLoad}</div></div>`;
    }
}

async function pmRestore(id) {
    const fd = new FormData();
    fd.append('action', 'restore');
    fd.append('id', id);
    await fetch(PM_API, { method: 'POST', body: fd });
    pmShowToast(PM_STR.toastRestored);
    pmLoadTrash();
    pmRefreshBadge();
    pmRefreshTrashBadge();
}

async function pmDeletePermanent(id) {
    if (!confirm(PM_STR.confirmDeletePerm)) return;
    const fd = new FormData();
    fd.append('action', 'delete_permanent');
    fd.append('id', id);
    await fetch(PM_API, { method: 'POST', body: fd });
    pmShowToast(PM_STR.toastDeleted);
    pmLoadTrash();
    pmRefreshTrashBadge();
}

async function pmEmptyTrash() {
    if (!confirm(PM_STR.confirmEmpty)) return;
    const fd = new FormData();
    fd.append('action', 'empty_trash');
    await fetch(PM_API, { method: 'POST', body: fd });
    pmShowToast(PM_STR.toastEmptied);
    pmLoadTrash();
    pmRefreshTrashBadge();
}

async function pmRefreshTrashBadge() {
    try {
        const r    = await fetch(PM_API + '?action=trash');
        const msgs = await r.json();
        const badge = document.getElementById('trash-badge');
        if (badge) {
            badge.textContent    = msgs.length;
            badge.style.display  = msgs.length > 0 ? 'inline-block' : 'none';
        }
    } catch(e) {}
}

function pmRenderList(msgs, type) {
    const title = type === 'inbox' ? PM_STR.inbox : PM_STR.sent;
    const icon  = type === 'inbox'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';

    if (!msgs.length) {
        return `<div class="pm-main-header">${icon} ${title}</div>
                <div class="pm-main-body"><div class="pm-empty"><div class="pm-empty-icon">${type === 'inbox' ? '📭' : '📤'}</div>
                <div style="font-weight:600;">${type === 'inbox' ? PM_STR.noInbox : PM_STR.noSent}</div>
                <div style="font-size:0.85rem; margin-top:0.25rem;">${PM_STR.noEvents}</div></div></div>`;
    }

    const items = msgs.map(m => {
        const isUnread   = type === 'inbox' && parseInt(m.is_read) === 0;
        const isSystem   = m.is_system_sender == 1;
        const person     = type === 'inbox'
            ? (isSystem ? PM_STR.system : (m.sender_name   || '–'))
            : (m.receiver_name || '–');
        const avatar     = type === 'inbox' ? m.sender_avatar : m.receiver_avatar;
        const dateStr    = m.last_activity || m.created_at;
        const preview    = (m.message || '').replace(/\n/g, ' ').substring(0, 80) + ((m.message || '').length > 80 ? '…' : '');
        const avatarHtml = (type === 'inbox' && isSystem)
            ? `<div style="width:40px;height:40px;border-radius:50%;background:var(--text-light);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">🤖</div>`
            : `<div class="pm-msg-avatar">${avatar ? `<img src="${BASE_URL}/${pmEscape(avatar)}" alt="">` : pmInitials(person)}</div>`;
        return `
            <div class="pm-msg-item ${isUnread ? 'unread' : ''}" onclick="pmViewMessage(${m.id})">
                ${isUnread ? '<div class="pm-unread-dot"></div>' : '<div style="width:8px;flex-shrink:0;"></div>'}
                ${avatarHtml}
                <div class="pm-msg-body">
                    <div class="pm-msg-meta">
                        <span class="pm-msg-from">${pmEscape(person)}</span>
                        <span class="pm-msg-date">${pmFormatDate(dateStr)}</span>
                    </div>
                    <div class="pm-msg-subject">${pmEscape(m.subject || PM_STR.noSubject)}</div>
                    <div class="pm-msg-preview">${pmEscape(preview)}</div>
                </div>
            </div>`;
    }).join('');

    return `<div class="pm-main-header">${icon} ${title} <span style="font-size:0.78rem; color:var(--text-light); font-weight:400;">(${msgs.length})</span></div><div class="pm-main-body">${items}</div>`;
}

function pmRenderTrash(msgs) {
    const trashIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';

    const emptyBtn = msgs.length > 0
        ? `<button class="btn btn-secondary btn-sm" style="color:var(--danger);font-size:0.78rem;" onclick="pmEmptyTrash()">
               ${PM_STR.btnEmptyTrash}
           </button>`
        : '';

    if (!msgs.length) {
        return `<div class="pm-main-header" style="justify-content:space-between;">
                    <span>${trashIcon} ${PM_STR.trash}</span>
                </div>
                <div class="pm-main-body">
                    <div class="pm-empty">
                        <div class="pm-empty-icon">🗑️</div>
                        <div style="font-weight:600; margin-bottom:0.5rem;">${PM_STR.trashEmpty}</div>
                        <div style="font-size:0.85rem;">${PM_STR.trashEmptyHint}</div>
                    </div>
                </div>`;
    }

    const items = msgs.map(m => {
        const isSystem  = m.is_system_sender == 1;
        const isSent    = m.trashed_as === 'sender';
        const person    = isSent
            ? (m.receiver_name || '–')
            : (isSystem ? '🤖 System' : (m.sender_name || '–'));
        const avatar    = isSent ? m.receiver_avatar : m.sender_avatar;
        const preview   = (m.message || '').replace(/\n/g,' ').substring(0, 70) + ((m.message||'').length>70?'…':'');
        const avatarHtml = (isSystem && !isSent)
            ? `<div class="pm-msg-avatar" style="background:var(--text-light);font-size:1.1rem;display:flex;align-items:center;justify-content:center;">🤖</div>`
            : `<div class="pm-msg-avatar">${avatar ? `<img src="${BASE_URL}/${pmEscape(avatar)}" alt="">` : pmInitials(person)}</div>`;

        return `
            <div class="pm-msg-item" style="opacity:0.75;">
                <div style="width:8px;flex-shrink:0;"></div>
                ${avatarHtml}
                <div class="pm-msg-body" style="flex:1;min-width:0;">
                    <div class="pm-msg-meta">
                        <span class="pm-msg-from">${isSent ? '→ ' : ''}${pmEscape(person)}</span>
                        <span class="pm-msg-date">${pmFormatDate(m.last_activity || m.created_at)}</span>
                    </div>
                    <div class="pm-msg-subject">${pmEscape(m.subject || PM_STR.noSubject)}</div>
                    <div class="pm-msg-preview">${pmEscape(preview)}</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.3rem;flex-shrink:0;margin-left:0.5rem;">
                    <button class="btn btn-secondary btn-sm" style="font-size:0.72rem;padding:0.2rem 0.5rem;white-space:nowrap;"
                            onclick="event.stopPropagation();pmRestore(${m.id})" title="${PM_STR.btnRestore}">
                        ${PM_STR.btnRestore}
                    </button>
                    <button class="btn btn-secondary btn-sm" style="font-size:0.72rem;padding:0.2rem 0.5rem;color:var(--danger);white-space:nowrap;"
                            onclick="event.stopPropagation();pmDeletePermanent(${m.id})" title="${PM_STR.btnDeletePerm}">
                        ${PM_STR.btnDeletePerm}
                    </button>
                </div>
            </div>`;
    }).join('');

    return `<div class="pm-main-header" style="justify-content:space-between; flex-shrink:0;">
                <span>${trashIcon} ${PM_STR.trash} <span style="font-size:0.78rem; color:var(--text-light); font-weight:400;">(${msgs.length})</span></span>
                ${emptyBtn}
            </div>
            <div class="pm-main-body">${items}</div>`;
}

function pmRenderView(msg, thread) {
    const backLabel = pmCurrentView === 'sent' ? PM_STR.sent : PM_STR.inbox;
    const backFn    = pmCurrentView === 'sent' ? 'pmLoadSent()' : 'pmLoadInbox()';
    const backBtn   = `<button class="btn btn-secondary btn-sm" onclick="${backFn}">← ${backLabel}</button>`;
    const delBtn    = `<button class="btn btn-secondary btn-sm" style="color:var(--danger);" onclick="pmDelete(${msg.id})" title="${PM_STR.btnTrash}">${PM_STR.btnTrash}</button>`;

    const isSystemThread = msg.is_system_sender == 1 && (!msg.message || msg.message.trim() === '');
    const displayMsgs    = isSystemThread ? thread : [msg, ...thread];

    const bubbles = displayMsgs.map((m, idx) => {
        const mine       = m.sender_id == MY_ID;
        const isSystem   = m.is_system_sender == 1;
        const dispName   = isSystem ? PM_STR.system : (mine ? PM_STR.me : pmEscape(m.sender_name || '–'));
        const avatarHtml = isSystem
            ? `<div class="pm-msg-bubble-avatar" style="background:var(--text-light);font-size:1.1rem;">🤖</div>`
            : `<div class="pm-msg-bubble-avatar">${m.sender_avatar ? `<img src="${BASE_URL}/${pmEscape(m.sender_avatar)}" alt="">` : pmInitials(m.sender_name || '?')}</div>`;
        const isLast = idx === displayMsgs.length - 1;
        return `
            <div class="pm-msg-bubble ${mine ? 'mine' : ''}" ${isLast ? 'id="pm-last-msg"' : ''}>
                ${avatarHtml}
                <div class="pm-msg-bubble-content">
                    <div class="pm-msg-bubble-name">${dispName}</div>
                    <div class="pm-msg-bubble-text">${pmRenderMessageBody(m.message, mine)}</div>
                    <div class="pm-msg-bubble-time">${pmFormatDate(m.created_at)}</div>
                </div>
            </div>`;
    }).join('');

    const replyReceiverId   = msg.sender_id == MY_ID ? msg.receiver_id : msg.sender_id;
    const replyReceiverName = msg.sender_id == MY_ID ? msg.receiver_name : msg.sender_name;
    const showReply         = !msg.is_system_sender || replyReceiverId;

    return `
        <div class="pm-main-header" style="justify-content:space-between; flex-shrink:0;">
            <span>${pmEscape(msg.subject || PM_STR.noSubject)}</span>
            <div style="display:flex;gap:0.5rem;">${backBtn} ${delBtn}</div>
        </div>
        <div class="pm-main-body" id="pm-body-scroll">
            <div class="pm-view">
                <div class="pm-view-meta" style="margin-bottom:1rem;">
                    <span>${PM_STR.from}: <strong>${msg.is_system_sender ? PM_STR.system : pmEscape(msg.sender_name || '–')}</strong></span>
                    <span>${PM_STR.to}: <strong>${pmEscape(msg.receiver_name || '–')}</strong></span>
                    <span>${pmFormatDate(msg.created_at)}</span>
                </div>
                ${displayMsgs.length ? bubbles : `<div class="pm-empty" style="padding:2rem;"><div class="pm-empty-icon">📭</div><div>${PM_STR.noEvents}</div></div>`}
            </div>
        </div>
        ${showReply && replyReceiverId ? `
        <div class="pm-reply-box">
            <div style="font-weight:700; font-size:0.9rem; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.4rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                ${PM_STR.replyTo} ${pmEscape(replyReceiverName || '–')}
            </div>
            <input type="hidden" id="pm-reply-receiver" value="${replyReceiverId}">
            <textarea id="pm-reply-text" class="form-control" rows="3" placeholder="${PM_STR.replyPh}" style="resize:vertical; margin-bottom:0.75rem;"></textarea>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <button class="btn btn-primary btn-sm" onclick="pmSend(${msg.id})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    ${PM_STR.replySend}
                </button>
                <span id="pm-reply-status" style="font-size:0.82rem; color:var(--danger); display:none;"></span>
            </div>
        </div>` : ''}`;
}

function pmSetNav(view) {
    pmCurrentView = view;
    document.getElementById('nav-inbox').classList.toggle('active', view === 'inbox');
    document.getElementById('nav-sent').classList.toggle('active',  view === 'sent');
    document.getElementById('nav-trash').classList.toggle('active', view === 'trash');
}

function pmRenderMessageBody(text, isMine) {
    if (!text) return '';
    const lines = text.split('\n');
    let bodyLines = [];
    let ticketUrl = null;

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        // URL-Zeile nach "TICKET ÖFFNEN:" erkennen
        if (/https?:\/\/[^\s]+\/support\/view-ticket\.php/i.test(line)) {
            ticketUrl = line.trim();
        } else if (/^🔗\s*(TICKET ÖFFNEN:?)?$/i.test(line.trim())) {
            // Überschrift weglassen, URL folgt in nächster Zeile
        } else {
            bodyLines.push(line);
        }
    }

    while (bodyLines.length && /^━+$/.test(bodyLines[bodyLines.length - 1].trim())) {
        bodyLines.pop();
    }

    const escaped = pmEscape(bodyLines.join('\n'));
    let html = `<div style="white-space:pre-wrap; word-break:break-word;">${escaped}</div>`;

    if (ticketUrl) {
        html += `<a href="${pmEscape(ticketUrl)}" target="_blank" class="pm-ticket-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            ${PM_STR.btnOpenTicket}
        </a>`;
    }
    return html;
}

function pmLoadingHtml(title) {
    return `<div class="pm-main-header">${title}</div><div class="pm-main-body"><div class="pm-empty"><div class="pm-empty-icon">⏳</div><div>${PM_STR.loading}</div></div></div>`;
}

function pmInitials(name) {
    return (name || '?').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
}

function pmEscape(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function pmFormatDate(dt) {
    if (!dt) return '–';
    const d = new Date(dt);
    const now = new Date();
    const diff = (now - d) / 1000;
    const locale = '<?= isset($_SESSION["lang"]) ? $_SESSION["lang"] : "de-DE" ?>';
    const timeStr = d.toLocaleTimeString(locale, {hour:'2-digit', minute:'2-digit'});
    const dateStr = d.toLocaleDateString(locale, {day:'2-digit', month:'2-digit', year:'numeric'});
    if (diff < 60)     return PM_STR.justNow;
    if (diff < 3600)   return Math.floor(diff/60) + ' min';
    if (diff < 86400)  return PM_STR.today + ' ' + timeStr;
    if (diff < 172800) return PM_STR.yesterday + ' ' + timeStr;
    return dateStr + ' ' + timeStr;
}

function pmTranslateRole(role) {
    return {'first_level':'First Level','second_level':'Second Level','third_level':'Third Level','admin':'Admin'}[role] || role;
}

function pmShowToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;padding:.5rem 1.25rem;border-radius:20px;font-size:.85rem;font-weight:600;z-index:9999;pointer-events:none;box-shadow:0 4px 12px rgba(0,0,0,.2);';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>

