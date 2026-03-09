<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';
require_once '../includes/Ticket.php';
require_once '../includes/Email.php';
require_once '../includes/Discord.php';
require_once '../includes/CategoryHelper.php';

$_vpActiveLangs = function_exists('getSupportedLanguages') ? getSupportedLanguages() : ['DE-de','EN-en'];
$currentLang = $_SESSION['lang'] ?? 'DE-de';
if (!in_array($currentLang, $_vpActiveLangs, true)) {
    $currentLang = 'DE-de';
}
if (!function_exists('buildLangUrl')) {
    function buildLangUrl($lang) {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path  = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $query);
        $query['lang'] = $lang;
        return $path . '?' . http_build_query($query);
    }
}
if (!function_exists('getVpActiveLangs')) {
    $_vpBuiltinFlags  = ['DE-de'=>'🇩🇪','EN-en'=>'🇬🇧','FR-fr'=>'🇫🇷','ES-es'=>'🇪🇸','CH-ch'=>'🇨🇭','NDS-nds'=>'🌊'];
    $_vpBuiltinLabels = ['DE-de'=>'Deutsch','EN-en'=>'English','FR-fr'=>'Français','ES-es'=>'Español','CH-ch'=>'Schwiizerdüütsch','NDS-nds'=>'Plattdüütsch'];

    function getVpActiveLangs(): array {
        global $_vpBuiltinFlags, $_vpBuiltinLabels;
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $rows = $pdo->query(
                "SELECT lang_code, label, flag FROM language_settings WHERE is_active=1 ORDER BY sort_order, lang_code"
            )->fetchAll(PDO::FETCH_ASSOC);
            return array_map(function($r) use ($_vpBuiltinFlags, $_vpBuiltinLabels) {
                $c = $r['lang_code'];
                return [
                    'lang_code' => $c,
                    'label' => (!empty($r['label']) && $r['label'] !== $c) ? $r['label'] : ($_vpBuiltinLabels[$c] ?? $c),
                    'flag'  => (!empty($r['flag'])  && $r['flag']  !== '🌐') ? $r['flag']  : ($_vpBuiltinFlags[$c]  ?? '🌐'),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [['lang_code'=>'DE-de','label'=>'Deutsch','flag'=>'🇩🇪']];
        }
    }
    function getVpLangFlag(string $code): string {
        global $_vpBuiltinFlags;
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $r = $pdo->prepare("SELECT flag FROM language_settings WHERE lang_code=?");
            $r->execute([$code]);
            $f = $r->fetchColumn();
            if ($f && $f !== '🌐') return $f;
        } catch (\Throwable $e) {}
        return $_vpBuiltinFlags[$code] ?? '🌐';
    }
}
$langFlag = getVpLangFlag($currentLang);

$db       = Database::getInstance()->getConnection();
global $translator;
$error    = '';
$success  = '';
$ticket   = null;
$messages = [];
$verified = false;

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Ihre Nachricht wurde erfolgreich gesendet.';
}

$ticketCode    = trim($_POST['ticket_code'] ?? $_GET['code']  ?? '');
$verifiedEmail = trim($_POST['email']       ?? $_GET['email'] ?? '');

if (!empty($ticketCode) && !empty($verifiedEmail) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT t.*, u.email, u.full_name, u.id as user_id_fk
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.ticket_code = ?
    ");
    $stmt->execute([$ticketCode]);
    $ticket = $stmt->fetch();

    if ($ticket && strtolower($ticket['email']) === strtolower($verifiedEmail)) {
        $verified = true;
        $msgs = $db->prepare("
            SELECT tm.*, u.full_name, u.role, u.avatar, u.bio
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ? AND tm.is_internal = 0
            ORDER BY tm.created_at ASC
        ");
        $msgs->execute([$ticket['id']]);
        $messages = $msgs->fetchAll();
    } else {
        $ticket = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $ticketCode    = trim($_POST['ticket_code'] ?? '');
    $verifiedEmail = trim($_POST['email']       ?? '');

    if (empty($ticketCode) || empty($verifiedEmail)) {
        $error = 'Bitte Ticket-Nummer und E-Mail-Adresse eingeben.';
    } else {
        $stmt = $db->prepare("
            SELECT t.*, u.email, u.full_name, u.id as user_id_fk
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.ticket_code = ?
        ");
        $stmt->execute([$ticketCode]);
        $ticket = $stmt->fetch();

        if (!$ticket || strtolower($ticket['email']) !== strtolower($verifiedEmail)) {
            $error  = $translator->translate('public_ticket_not_found');
            $ticket = null;
        } else {
            $verified = true;
            // Nachrichten laden (keine internen)
            $msgs = $db->prepare("
                SELECT tm.*, u.full_name, u.role, u.avatar, u.bio
                FROM ticket_messages tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.ticket_id = ? AND tm.is_internal = 0
                ORDER BY tm.created_at ASC
            ");
            $msgs->execute([$ticket['id']]);
            $messages = $msgs->fetchAll();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $ticketCode    = trim($_POST['ticket_code'] ?? '');
    $verifiedEmail = trim($_POST['email']       ?? '');
    $messageText   = trim($_POST['message']     ?? '');

    $stmt = $db->prepare("
        SELECT t.*, u.email, u.full_name, u.id as user_id_fk
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.ticket_code = ?
    ");
    $stmt->execute([$ticketCode]);
    $ticket = $stmt->fetch();

    if (!$ticket || strtolower($ticket['email']) !== strtolower($verifiedEmail)) {
        $error  = $translator->translate('public_ticket_session_invalid');
        $ticket = null;
    } elseif (empty($messageText)) {
        $error    = $translator->translate('public_ticket_empty_message');
        $verified = true;
    } elseif ($ticket['status'] === 'closed') {
        $error    = $translator->translate('public_ticket_closed_error');
        $verified = true;
    } else {
        $ticketObj = new Ticket();
        $ticketObj->addMessage((int)$ticket['id'], (int)$ticket['user_id_fk'], $messageText, false);

        $params = http_build_query([
            'code'    => $ticketCode,
            'email'   => $verifiedEmail,
            'success' => '1',
        ]);
        header('Location: ' . SITE_URL . '/tickets/view_public_ticket.php?' . $params);
        exit;
    }

    if ($ticket) {
        $msgs = $db->prepare("
            SELECT tm.*, u.full_name, u.role, u.avatar, u.bio
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ? AND tm.is_internal = 0
            ORDER BY tm.created_at ASC
        ");
        $msgs->execute([$ticket['id']]);
        $messages = $msgs->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ticket ? 'Ticket ' . escape($ticket['ticket_code']) : 'Ticket aufrufen' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .public-navbar { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; z-index: 9000 !important; width: 100% !important; margin-bottom: 0 !important; }
        html { scroll-padding-top: 64px; }
        body { padding-top: 64px; }
        body.navbar-ready { padding-top: var(--navbar-h) !important; }
        html.navbar-ready { scroll-padding-top: var(--navbar-h) !important; }
    </style>
    <?php injectGtaBgStyle(); ?>
    <?php injectRotlichtBgStyle(); ?>
    <?php injectDayzBgStyle(); ?>
    <?php injectBlackGoldBgStyle(); ?>
    <?php injectWinXpBgStyle(); ?>
    <?php injectYoutubeBgStyle(); ?>
</head>
<body>

<nav class="public-navbar">
    <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="public-navbar-brand"><?= SITE_NAME ?></a>
    <div class="public-navbar-actions">
        <a href="<?= SITE_URL ?>/login.php"    class="btn btn-secondary">Anmelden</a>
        <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary">Registrieren</a>
        <div class="dropdown">
            <a href="#" class="dropdown-toggle lang-toggle" title="Sprache ändern" style="display:flex;align-items:center;gap:4px;">
                <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($currentLang, 24) : '<span class="lang-flag-circular">' . htmlspecialchars($langFlag) . '</span>' ?>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:4px;">
                    <path d="M6 9L1 4h10z"/>
                </svg>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <?php foreach (getVpActiveLangs() as $_vl): ?>
                <a href="<?= escape(buildLangUrl($_vl['lang_code'])) ?>"
                   style="display:flex;align-items:center;gap:0.6rem;<?= $currentLang === $_vl['lang_code'] ? 'font-weight:700;color:var(--primary);' : '' ?>">
                    <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($_vl['lang_code'], 20) : htmlspecialchars($_vl['flag']) ?>
                    <?= htmlspecialchars($_vl['label']) ?>
                    <?php if ($currentLang === $_vl['lang_code']): ?>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:auto;color:var(--primary);"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>
<script>(function(){function s(){var n=document.querySelector('.public-navbar');if(!n)return;var h=n.getBoundingClientRect().height;if(h<=0)return;document.documentElement.style.setProperty('--navbar-h',h+'px');document.documentElement.classList.add('navbar-ready');document.body.classList.add('navbar-ready');}s();document.addEventListener('DOMContentLoaded',s);window.addEventListener('load',s);window.addEventListener('resize',s);})();</script>

<div class="public-container" style="max-width: 860px;">

    <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="btn btn-secondary btn-sm" style="margin-bottom:1.5rem;">
        ← <?= $translator->translate('back_to_overview') ?>
    </a>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= escape($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= escape($success) ?></div>
    <?php endif; ?>

    <?php if (!$verified || !$ticket): ?>
        <!-- Verify form -->
        <div class="card">
            <div class="card-header">🔍 <?= $translator->translate('public_ticket_title') ?></div>
            <div class="card-body">
                <p style="color:var(--text-light); margin-bottom:1.5rem;">
                    <?= $translator->translate('public_ticket_form_hint') ?>
                </p>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?= $translator->translate('public_ticket_id_label') ?> *</label>
                        <input type="text" name="ticket_code" class="form-control"
                               placeholder="z.B. TKT-2026-ABC123" required
                               value="<?= escape($ticketCode) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $translator->translate('public_ticket_email_label') ?> *</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="<?= $translator->translate('public_ticket_email_ph') ?>" required>
                    </div>
                    <button type="submit" name="verify" class="btn btn-primary" style="width:100%;">
                        <?= $translator->translate('public_ticket_submit') ?>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Ticket view -->
        <div class="card">
            <div class="card-header">
                <span style="color:var(--primary); font-size:1.3rem; font-weight:700;"><?= escape($ticket['ticket_code']) ?></span>
                &nbsp;·&nbsp;
                <?= escape($ticket['subject']) ?>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns: repeat(4,1fr); margin-bottom:0;">
                    <div class="info-item">
                        <div style="font-size:.8rem; color:var(--text-light); margin-bottom:.25rem;"><?= $translator->translate('tickets_table_status') ?></div>
                        <span class="badge badge-<?= $ticket['status'] ?>"><?= translateStatus($ticket['status']) ?></span>
                    </div>
                    <div class="info-item">
                        <div style="font-size:.8rem; color:var(--text-light); margin-bottom:.25rem;"><?= $translator->translate('tickets_priority') ?></div>
                        <span class="badge badge-<?= $ticket['priority'] ?>"><?= translatePriority($ticket['priority']) ?></span>
                    </div>
                    <div class="info-item">
                        <div style="font-size:.8rem; color:var(--text-light); margin-bottom:.25rem;"><?= $translator->translate('tickets_created_at') ?></div>
                        <strong><?= formatDate($ticket['created_at']) ?></strong>
                    </div>
                    <div class="info-item">
                        <div style="font-size:.8rem; color:var(--text-light); margin-bottom:.25rem;"><?= $translator->translate('ticketview_support_level') ?></div>
                        <strong><?= translateLevel($ticket['support_level']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= $translator->translate('ticketview_description') ?></div>
            <div class="card-body">
                <p style="white-space:pre-wrap; line-height:1.6;"><?= escape($ticket['description']) ?></p>
            </div>
        </div>

        <!-- Messages -->
        <div class="card">
            <div class="card-header"><?= $translator->translate('ticketview_public_messages') ?> (<?= count($messages) ?>)</div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <p style="color:var(--text-light); text-align:center; padding:1rem;">
                        <?= $translator->translate('ticketview_no_public_messages') ?>
                    </p>
                <?php else: ?>
                    <div class="message-list" style="margin-bottom:1.5rem;">
                        <?php foreach ($messages as $msg): ?>
                            <?php $isSupport = in_array($msg['role'], ['first_level','second_level','third_level','admin']); ?>
                            <div class="message" style="border-left-color: <?= $isSupport ? 'var(--primary)' : 'var(--border)' ?>">
                                <div class="message-header">
                                    <div class="msg-author-row">
                                        <?php if (!empty($msg['avatar'])): ?>
                                            <img src="<?= SITE_URL ?>/<?= escape($msg['avatar']) ?>"
                                                 alt="" class="msg-avatar">
                                        <?php else: ?>
                                            <div class="msg-avatar msg-avatar-placeholder">
                                                <?= mb_strtoupper(mb_substr($msg['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="msg-author-info">
                                            <span class="message-author">
                                                <?= escape($msg['full_name']) ?>
                                                <?php if ($isSupport): ?>
                                                    <span class="badge" style="font-size:.7rem; padding:.15rem .5rem; margin-left:.4rem;">Support</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!empty($msg['bio'])): ?>
                                                <span class="msg-bio"><?= escape($msg['bio']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="msg-date"><?= formatDate($msg['created_at']) ?></span>
                                </div>
                                <div class="message-content" style="white-space:pre-wrap;"><?= escape($msg['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ticket['status'] !== 'closed'): ?>
                    <hr style="border:none; border-top:1px solid var(--border); margin-bottom:1.25rem;">
                    <form method="POST">
                        <input type="hidden" name="ticket_code" value="<?= escape($ticket['ticket_code']) ?>">
                        <input type="hidden" name="email"       value="<?= escape($verifiedEmail) ?>">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('public_ticket_reply_submit') ?>:</label>
                            <textarea name="message" class="form-control" rows="4"
                                      placeholder="<?= $translator->translate('public_ticket_reply_ph') ?>" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <?= $translator->translate('public_ticket_reply_submit') ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top:1rem;">
                        <?= $translator->translate('public_ticket_closed_info') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function (dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            dropdowns.forEach(function (o) { if (o !== dropdown) o.classList.remove('active'); });
            dropdown.classList.toggle('active');
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(function (d) { d.classList.remove('active'); });
        }
    });

    const list = document.querySelector('.message-list');
    if (list) list.scrollTop = list.scrollHeight;
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
