<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

$lockoutActive = false;
$lockoutUntil  = 0;
if (!empty($_SESSION['lockout_until'])) {
    if ($_SESSION['lockout_until'] > time()) {
        $lockoutActive = true;
        $lockoutUntil  = $_SESSION['lockout_until'];
    } else {
        unset($_SESSION['lockout_username'], $_SESSION['lockout_until']);
    }
}

$error = '';
$success = '';
$ticketCode = '';

$_pubActiveLangs = function_exists('getSupportedLanguages') ? getSupportedLanguages() : ['DE-de','EN-en'];
$currentLang = $_SESSION['lang'] ?? 'DE-de';
if (!in_array($currentLang, $_pubActiveLangs, true)) {
    $currentLang = 'DE-de';
}

if (!function_exists('buildLangUrl')) {
    function buildLangUrl($lang) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['lang'] = $lang;
        return $path . '?' . http_build_query($query);
    }
}

$_pubBuiltinFlags  = ['DE-de'=>'🇩🇪','EN-en'=>'🇬🇧','FR-fr'=>'🇫🇷','CH-ch'=>'🇨🇭','NDS-nds'=>'🌊'];
$_pubBuiltinLabels = ['DE-de'=>'Deutsch','EN-en'=>'English','FR-fr'=>'Français','CH-ch'=>'Schwiizerdüütsch','NDS-nds'=>'Plattdüütsch'];


function getPubLangFlag(string $code): string {
    global $_pubBuiltinFlags;
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
    return $_pubBuiltinFlags[$code] ?? '🌐';
}
function getPubActiveLangs(): array {
    global $_pubBuiltinFlags, $_pubBuiltinLabels;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        $rows = $pdo->query(
            "SELECT lang_code, label, flag FROM language_settings WHERE is_active=1 ORDER BY sort_order, lang_code"
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($r) use ($_pubBuiltinFlags, $_pubBuiltinLabels) {
            $c = $r['lang_code'];
            return [
                'lang_code' => $c,
                'label' => (!empty($r['label']) && $r['label'] !== $c) ? $r['label'] : ($_pubBuiltinLabels[$c] ?? $c),
                'flag'  => (!empty($r['flag'])  && $r['flag']  !== '🌐') ? $r['flag']  : ($_pubBuiltinFlags[$c]  ?? '🌐'),
            ];
        }, $rows);
    } catch (\Throwable $e) {
        return [['lang_code'=>'DE-de','label'=>'Deutsch','flag'=>'🇩🇪']];
    }
}

$langFlag = getPubLangFlag($currentLang);

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM ticket_categories ORDER BY name");
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../includes/CustomFields.php';
$customFields = new CustomFields();
$publicFields = $customFields->getActiveFields(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $categoryId = $_POST['category_id'] ?? null;
    $dsgvo = isset($_POST['dsgvo_consent']);

    $cfErrors = $customFields->validatePost($_POST, true);

    if (empty($name)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $translator->translate('register_error_invalid_email') ?: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (empty($subject)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif (empty($description)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif (!$dsgvo) {
        $error = $translator->translate('public_ticket_dsgvo_title');
    } elseif (!empty($cfErrors)) {
        $error = implode('<br>', $cfErrors);
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // Check if user exists by email, if not create anonymous user
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // GASTUSER ANLEGEN
                $username = 'guest_' . substr(md5($email . time()), 0, 10);
                $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->execute([$username, $email, $password, $name]);
                $userId = $db->lastInsertId();
            } else {
                $userId = $user['id'];
            }


            $ticketCode = 'T-' . strtoupper(substr(uniqid(), -8));

            $stmt = $db->prepare("
                INSERT INTO tickets (ticket_code, user_id, subject, description, priority, category_id, status, support_level, dsgvo_consent)
                VALUES (?, ?, ?, ?, ?, ?, 'open', 'first_level', 1)
            ");
            $stmt->execute([$ticketCode, $userId, $subject, $description, $priority, $categoryId]);
            $newTicketId = $db->lastInsertId();

            // Custom-Field-Werte speichern
            $customFields->saveFromPost($newTicketId, $_POST, true);

            $success = true;

        } catch (Exception $e) {
            $error = $translator->translate('error_fill_required_fields') . ': ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(strtolower(substr($currentLang,0,2))) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= $translator->translate('public_ticket_page_title') ?></title>
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
        <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="public-navbar-brand">
            <?= SITE_NAME ?>
        </a>
        <div class="public-navbar-actions">
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-secondary"><?= $translator->translate('public_ticket_nav_login') ?></a>
            <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary"><?= $translator->translate('public_ticket_nav_register') ?></a>

            <!-- Sprachauswahl -->
            <div class="dropdown">
                <a href="#" class="dropdown-toggle lang-toggle" title="Sprache ändern" style="display:flex;align-items:center;gap:4px;">
                    <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($currentLang, 24) : '<span class="lang-flag-circular">' . htmlspecialchars($langFlag) . '</span>' ?>
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:4px;">
                        <path d="M6 9L1 4h10z"/>
                    </svg>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php foreach (getPubActiveLangs() as $_pl): ?>
                    <a href="<?= escape(buildLangUrl($_pl['lang_code'])) ?>"
                       style="display:flex;align-items:center;gap:0.6rem;<?= $currentLang === $_pl['lang_code'] ? 'font-weight:700;color:var(--primary);' : '' ?>">
                        <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($_pl['lang_code'], 20) : htmlspecialchars($_pl['flag']) ?>
                        <?= htmlspecialchars($_pl['label']) ?>
                        <?php if ($currentLang === $_pl['lang_code']): ?>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:auto;color:var(--primary);"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </nav>
    <script>(function(){function s(){var n=document.querySelector('.public-navbar');if(!n)return;var h=n.getBoundingClientRect().height;if(h<=0)return;document.documentElement.style.setProperty('--navbar-h',h+'px');document.documentElement.classList.add('navbar-ready');document.body.classList.add('navbar-ready');}s();document.addEventListener('DOMContentLoaded',s);window.addEventListener('load',s);window.addEventListener('resize',s);})();</script>

    <div class="public-container">
        <?php if ($lockoutActive): ?>
            <!-- ════ SPERRSEITE ════ -->
            <style>
                .lockout-wrap { display:flex; align-items:center; justify-content:center; min-height:60vh; }
                .lockout-card {padding:2.5rem 2rem; max-width:480px; width:100%; text-align:center; }
                .lockout-img { width:300px; height:300px; object-fit:cover; margin:0 auto 1.25rem; display:block;}
                .lockout-title { font-size:1.4rem; font-weight:800; color:#ef4444; margin-bottom:.5rem; }
                .lockout-caption { font-size:.95rem; color:var(--text-light); line-height:1.6; margin-bottom:1.75rem; font-style:italic; }
                .lockout-countdown { display:inline-flex; align-items:center; gap:.65rem; background:rgba(239,68,68,.08); border:1.5px solid rgba(239,68,68,.3); border-radius:12px; padding:.75rem 1.5rem; font-size:1.1rem; font-weight:700; color:#dc2626; margin-bottom:1.5rem; }
                .countdown-num { font-size:2rem; font-weight:900; font-variant-numeric:tabular-nums; min-width:3.5ch; }
                .lockout-hint { font-size:.8rem; color:var(--text-light); }
            </style>
            <div class="lockout-wrap">
                <div class="lockout-card">
                    <img src="<?= SITE_URL ?>/assets/images/hacker2.webp" alt="Gesperrt" class="lockout-img"
                         onerror="this.style.display='none'">
                    <div class="lockout-title"><?= $translator->translate('public_ticket_lockout_title') ?></div>
                    <p class="lockout-caption">
                        <?= $translator->translate('public_ticket_lockout_caption') ?>
                    </p>
                    <div class="lockout-countdown">
                        <span>⏱</span>
                        <div>
                            <div class="countdown-num" id="cd-time">5:00</div>
                            <div style="font-size:.7rem;font-weight:500;opacity:.7;"><?= $translator->translate('login_lockout_remaining') ?></div>
                        </div>
                    </div>
                    <div class="lockout-hint">
                        <?= $translator->translate('login_lockout_hint') ?>
                    </div>
                </div>
            </div>
            <script>
                const until = <?= (int)$lockoutUntil ?> * 1000;
                const cdEl  = document.getElementById('cd-time');
                function tick() {
                    const rem = Math.max(0, Math.floor((until - Date.now()) / 1000));
                    cdEl.textContent = Math.floor(rem/60) + ':' + String(rem%60).padStart(2,'0');
                    if (rem <= 0) location.reload(); else setTimeout(tick, 500);
                }
                tick();
            </script>

        <?php elseif ($success): ?>
            <div class="success-message">
                <h2><?= $translator->translate('public_ticket_success_title') ?></h2>
                <div class="ticket-code-display">
                    <p style="margin-bottom: 0.5rem;"><?= $translator->translate('public_ticket_success_code_label') ?></p>
                    <div class="code"><?= escape($ticketCode) ?></div>
                </div>
                <p><?= $translator->translate('public_ticket_success_text') ?></p>
                <p><?= $translator->translate('public_ticket_success_email') ?> <strong><?= escape($_POST['email']) ?></strong></p>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-light);">
                    <strong><?= $translator->translate('public_ticket_important') ?></strong> <?= $translator->translate('public_ticket_success_note') ?>
                </p>
                <p style="margin-top: 1.5rem;">
                    <a href="<?= SITE_URL ?>/tickets/view_public_ticket.php?code=<?= urlencode($ticketCode) ?>" class="btn btn-primary"><?= $translator->translate('public_ticket_success_view') ?></a>
                    <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="btn btn-secondary"><?= $translator->translate('public_ticket_success_new') ?></a>
                </p>
            </div>
        <?php else: ?>
            <div class="public-header">
                <h1><?= SITE_NAME ?></h1>
                <p><?= $translator->translate('public_ticket_subtitle') ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <div class="two-col-layout">

                <!-- Linke Spalte: Ticket erstellen -->
                <div class="card-left-ticket">
                    <div class="card-header"><?= $translator->translate('public_ticket_create_header') ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('public_ticket_name_label') ?></label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?= escape($_POST['name'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('public_ticket_email_create_label') ?></label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?= escape($_POST['email'] ?? '') ?>">
                                <small style="color: var(--text-light);"><?= $translator->translate('public_ticket_email_hint') ?></small>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('public_ticket_subject_label') ?></label>
                                <input type="text" name="subject" class="form-control" required
                                       value="<?= escape($_POST['subject'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('public_ticket_desc_label') ?></label>
                                <textarea name="description" class="form-control" rows="6" required><?= escape($_POST['description'] ?? '') ?></textarea>
                                <small style="color: var(--text-light);"><?= $translator->translate('public_ticket_desc_hint') ?></small>
                            </div>

                            <?php if (!empty($categories)): ?>
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('public_ticket_cat_label') ?></label>
                                <select name="category_id" class="form-control">
                                    <option value=""><?= $translator->translate('public_ticket_cat_placeholder') ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                                <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                            <?= escape($cat['name']) ?><?= $cat['description'] ? ' - ' . escape($cat['description']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($publicFields)): ?>
                            <hr style="margin:1rem 0; border-color:var(--border-color,#e5e7eb);">
                            <div style="font-size:.88rem; font-weight:600; margin-bottom:.75rem; color:var(--text-light);">Weitere Informationen</div>
                            <?= CustomFields::renderFields($publicFields, [], $_POST) ?>
                            <?php endif; ?>

                            <div class="dsgvo-box">
                                <div class="dsgvo-checkbox">
                                    <input type="checkbox" name="dsgvo_consent" id="dsgvo_consent" required>
                                    <label for="dsgvo_consent">
                                        <strong><?= $translator->translate('public_ticket_dsgvo_title') ?></strong><br>
                                        <?= $translator->translate('public_ticket_dsgvo_text') ?>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $translator->translate('public_ticket_submit_btn') ?></button>
                        </form>
                    </div>
                </div>

                <!-- Rechte Spalte: Ticket aufrufen + FAQ -->
                <div class="right-col">

                    <div class="card">
                        <div class="card-header"><?= $translator->translate('public_ticket_lookup_header') ?></div>
                        <div class="card-body">
                            <p style="margin-bottom: 1rem; color: var(--text-light); font-size: 0.9rem;">
                                <?= $translator->translate('public_ticket_lookup_hint') ?>
                            </p>
                            <form method="GET" action="<?= SITE_URL ?>/tickets/view_public_ticket.php">
                                <div class="form-group">
                                    <input type="text" name="code" class="form-control"
                                           placeholder="<?= htmlspecialchars($translator->translate('public_ticket_lookup_ph')) ?>" required
                                           pattern="T-[A-Z0-9]{8}"
                                           title="<?= htmlspecialchars($translator->translate('public_ticket_form_hint')) ?>"
                                           value="">
                                </div>
                                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $translator->translate('public_ticket_lookup_btn') ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><?= $translator->translate('public_ticket_faq_header') ?></div>
                        <div class="card-body">
                            <div class="faq-item">
                                <strong><?= $translator->translate('public_ticket_faq_1_q') ?></strong>
                                <p><?= $translator->translate('public_ticket_faq_1_a') ?></p>
                            </div>
                            <div class="faq-item">
                                <strong><?= $translator->translate('public_ticket_faq_2_q') ?></strong>
                                <p><?= $translator->translate('public_ticket_faq_2_a') ?></p>
                            </div>
                            <div class="faq-item">
                                <strong><?= $translator->translate('public_ticket_faq_3_q') ?></strong>
                                <p><?= $translator->translate('public_ticket_faq_3_a') ?></p>
                            </div>
                            <div class="faq-item">
                                <strong><?= $translator->translate('public_ticket_faq_4_q') ?></strong>
                                <p><?= $translator->translate('public_ticket_faq_4_a') ?></p>
                            </div>
                        </div>
                    </div>

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
                dropdowns.forEach(function (other) {
                    if (other !== dropdown) other.classList.remove('active');
                });
                dropdown.classList.toggle('active');
            });
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.dropdown')) {
                dropdowns.forEach(function (d) { d.classList.remove('active'); });
            }
        });
    });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
