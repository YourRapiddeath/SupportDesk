<?php
global $translator;
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/User.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/index.php');
}

$error      = '';
$show2FA    = isset($_SESSION['2fa_pending_user_id']);
$locked     = false;
$lockedUntil = 0;
$failedAttempts = 0;

try {
    $user = new User();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['login'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $result   = $user->login($username, $password);

            if ($result === true) {
                redirect(SITE_URL . '/index.php');
            } elseif ($result === '2fa_required') {
                $show2FA = true;
            } elseif (is_array($result) && !empty($result['locked'])) {
                $locked      = true;
                $lockedUntil = $result['until'];
                $_SESSION['lockout_username'] = htmlspecialchars($username, ENT_QUOTES);
                $_SESSION['lockout_until']    = $lockedUntil;
                redirect(SITE_URL . '/tickets/public_ticket.php');
            } else {
                // Fehlversuche aus DB laden um Warnung anzuzeigen
                $db   = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT failed_login_attempts FROM users WHERE username=? OR email=?");
                $stmt->execute([$username, $username]);
                $row  = $stmt->fetch();
                $failedAttempts = (int)($row['failed_login_attempts'] ?? 0);
                $error = $translator->translate('login_error_invalid_credentials');
            }
        }

        if (isset($_POST['verify_2fa'])) {
            $code = trim($_POST['totp_code'] ?? '');
            if (empty($code)) {
                $error = $translator->translate('login_error_2fa_required');
                $show2FA = true;
            } elseif ($user->verify2FA($code)) {
                redirect(SITE_URL . '/index.php');
            } else {
                $error = $translator->translate('login_error_2fa_invalid');
                $show2FA = true;
            }
        }

        if (isset($_POST['cancel_2fa'])) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_username'],
                  $_SESSION['2fa_pending_role'], $_SESSION['2fa_pending_name']);
            redirect(SITE_URL . '/login.php');
        }
    }

    if (!$locked && !empty($_SESSION['lockout_until']) && $_SESSION['lockout_until'] > time()) {
        redirect(SITE_URL . '/tickets/public_ticket.php');
    } elseif (!empty($_SESSION['lockout_until']) && $_SESSION['lockout_until'] <= time()) {
        unset($_SESSION['lockout_username'], $_SESSION['lockout_until']);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $locked ? 'Zugang gesperrt' : 'Login' ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .totp-input { text-align:center; font-size:2rem; font-weight:700; letter-spacing:.5rem; padding:.75rem 1rem; }
        .totp-hint  { text-align:center; color:var(--text-light); font-size:.875rem; margin-top:.5rem; }
        .two-fa-icon { text-align:center; font-size:2.5rem; margin-bottom:.75rem; }

        /* ── Sperrseite ── */
        .lockout-card {
            background: var(--surface);
            border: 2px solid #ef4444;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 8px 40px rgba(239,68,68,.15);
            margin: 0 auto;
        }
        .lockout-img {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 16px;
            margin: 0 auto 1.25rem;
            display: block;
            border: 3px solid #ef4444;
            box-shadow: 0 4px 20px rgba(239,68,68,.25);
        }
        .lockout-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #ef4444;
            margin-bottom: .5rem;
        }
        .lockout-caption {
            font-size: .95rem;
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 1.75rem;
            font-style: italic;
        }
        .lockout-countdown {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            background: rgba(239,68,68,.08);
            border: 1.5px solid rgba(239,68,68,.3);
            border-radius: 12px;
            padding: .75rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 1.5rem;
        }
        .countdown-num {
            font-size: 2rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            min-width: 3.5ch;
        }
        .lockout-hint {
            font-size: .8rem;
            color: var(--text-light);
        }
        .attempts-warning {
            background: rgba(245,158,11,.1);
            border: 1px solid rgba(245,158,11,.4);
            border-radius: 8px;
            padding: .6rem .9rem;
            font-size: .85rem;
            color: #d97706;
            margin-top: .75rem;
            text-align: center;
        }
    </style>
</head>
<body>

<?php if ($locked): ?>
    <?php require_once 'includes/navbar.php'; ?>
    <div class="container" style="display:flex; align-items:center; justify-content:center; min-height:70vh;">
        <div class="lockout-card">
            <img src="<?= SITE_URL ?>/assets/images/hacker.webp"
                 alt="Gesperrt"
                 class="lockout-img"
                 onerror="this.style.display='none'">

            <div class="lockout-title">🔒 <?= $translator->translate('login_lockout_title') ?></div>

            <p class="lockout-caption">
                „<?= $translator->translate('login_lockout_caption') ?>"
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
        const until = <?= (int)$lockedUntil ?> * 1000;
        const cdEl  = document.getElementById('cd-time');
        function tick() {
            const rem = Math.max(0, Math.floor((until - Date.now()) / 1000));
            const m   = Math.floor(rem / 60);
            const s   = rem % 60;
            cdEl.textContent = m + ':' + String(s).padStart(2, '0');
            if (rem <= 0) {
                location.reload();
            } else {
                setTimeout(tick, 500);
            }
        }
        tick();
    </script>

<?php else: ?>
    <div class="auth-container">
        <div class="auth-card">

            <?php if ($show2FA): ?>
                <div class="two-fa-icon">🔐</div>
                <h2 style="text-align:center;margin-bottom:.25rem;"><?= $translator->translate('login_2fa_title') ?></h2>
                <p style="text-align:center;color:var(--text-light);font-size:.9rem;margin-bottom:1.5rem;">
                    <?= $translator->translate('login_pending_info', ['pending_name' => escape($_SESSION['2fa_pending_name'] ?? '')]) ?>
                </p>
                <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label" style="text-align:center;display:block;"><?= $translator->translate('login_2fa_code_label') ?></label>
                        <input type="text" name="totp_code" class="form-control totp-input"
                               placeholder="000000" maxlength="8" inputmode="numeric" autofocus required>
                        <p class="totp-hint"><?= $translator->translate('totp-hint') ?></p>
                    </div>
                    <button type="submit" name="verify_2fa" class="btn btn-primary" style="width:100%;margin-bottom:.75rem;">
                        <?= $translator->translate('login_confirm_2fa') ?>
                    </button>
                    <button type="submit" name="cancel_2fa" class="btn btn-secondary" style="width:100%;">
                        <?= $translator->translate('login_back_to_login') ?>
                    </button>
                </form>

            <?php else: ?>
                <h2><?= $translator->translate('login_welcome') ?></h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= escape($error) ?></div>
                    <?php if ($failedAttempts > 0 && $failedAttempts < 3): ?>
                        <div class="attempts-warning">
                            ⚠️ <?= $translator->translate('login_attempts_remaining', ['n' => (3 - $failedAttempts)]) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?= $translator->translate('login_username_label') ?></label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= $translator->translate('login_password_label') ?></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary" style="width:100%;">
                        <?= $translator->translate('btn_login') ?>
                    </button>
                </form>

                <p style="text-align:center;margin-top:1.5rem;">
                    <?= $translator->translate('login_no_member') ?>
                    <a href="<?= SITE_URL ?>/register.php" style="color:var(--primary);font-weight:500;">
                        <?= $translator->translate('btn_register') ?>
                    </a>
                </p>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
</body>
</html>
