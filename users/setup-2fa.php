<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/TOTP.php';

requireLogin();

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $db->prepare("SELECT two_fa_enabled, two_fa_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if ($userData['two_fa_enabled']) {
    header('Location: ' . SITE_URL . '/users/disable-2fa.php');
    exit;
}

if (!$userData['two_fa_secret']) {
    $secret = TOTP::generateSecret(16); // Base32 encoded secret
    $stmt = $db->prepare("UPDATE users SET two_fa_secret = ? WHERE id = ?");
    $stmt->execute([$secret, $userId]);
    $userData['two_fa_secret'] = $secret;
}

$stmt = $db->prepare("SELECT backup_codes FROM users WHERE id = ?");
$stmt->execute([$userId]);
$existingCodes = $stmt->fetchColumn();

if (empty($existingCodes)) {
    $backupCodes = [];
    for ($i = 0; $i < 10; $i++) {
        $backupCodes[] = strtoupper(bin2hex(random_bytes(4))); // 8 chars each
    }
    $backupCodesJson = json_encode($backupCodes);
    $stmt = $db->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
    $stmt->execute([$backupCodesJson, $userId]);
} else {
    $backupCodes = json_decode($existingCodes, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code = trim($_POST['code'] ?? '');

    if (empty($code)) {
        $error = $translator->translate('2fa_setup_error_empty');
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = $translator->translate('2fa_setup_error_format');
    } else {
        $secret = $userData['two_fa_secret'];
        if (TOTP::verifyCode($secret, $code, 2)) {
            $stmt = $db->prepare("UPDATE users SET two_fa_enabled = 1 WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $_SESSION['2fa_setup_complete'] = true;
                header('Location: ' . SITE_URL . '/users/profile.php?2fa_enabled=1');
                exit;
            } else {
                $error = $translator->translate('2fa_setup_error_activate');
            }
        } else {
            $error = $translator->translate('2fa_setup_error_invalid');
        }
    }
}

$stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

$issuer = SITE_NAME;
$accountName = $userInfo['username'];
$secret = $userData['two_fa_secret'];
$qrData = "otpauth://totp/" . urlencode($issuer) . ":" . urlencode($accountName) . "?secret={$secret}&issuer=" . urlencode($issuer);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('2fa_setup_page_title') ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .setup-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        .setup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .setup-grid {
                grid-template-columns: 1fr;
            }
        }
        .qr-section {
            text-align: center;
        }
        .qr-code {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            display: inline-block;
            margin: 1rem 0;
        }
        .secret-box {
            background: var(--surface);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            word-break: break-all;
        }
        .secret-code {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            letter-spacing: 2px;
        }
        .backup-codes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .backup-code {
            background: var(--surface);
            padding: 0.75rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            text-align: center;
            border: 1px solid var(--border);
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .info-box {
            color: #0f172a;
            background: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-box strong {
            color: #1e40af;
        }
        .warning-box {
            color: #0f172a;
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container setup-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1>🔐 <?= $translator->translate('2fa_setup_heading') ?></h1>
            <p style="color: var(--text-light); max-width: 600px; margin: 1rem auto;">
                <?= $translator->translate('2fa_setup_subheading') ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>ℹ️ <?= $translator->translate('2fa_setup_required_label') ?>:</strong>
            <?= $translator->translate('2fa_setup_required_text') ?>
        </div>

        <div class="setup-grid">
            <div class="card">
                <div class="card-header">
                    <span class="step-number">1</span>
                    <?= $translator->translate('2fa_setup_step1_title') ?>
                </div>
                <div class="card-body qr-section">
                    <p style="text-align: left; margin-bottom: 1rem;">
                        <?= $translator->translate('2fa_setup_step1_desc') ?>
                    </p>

                    <div class="qr-code">
                        <div id="qrcode"></div>
                    </div>

                    <div style="margin-top: 1.5rem; text-align: left;">
                        <strong><?= $translator->translate('2fa_setup_manual_entry') ?></strong>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin: 0.5rem 0;">
                            <?= $translator->translate('2fa_setup_manual_desc') ?>
                        </p>
                        <div class="secret-box">
                            <div class="secret-code"><?= escape($secret) ?></div>
                        </div>
                        <small style="color: var(--text-light);">
                            <?= $translator->translate('2fa_setup_account_label') ?>: <?= escape($userInfo['username']) ?><br>
                            <?= $translator->translate('2fa_setup_type_label') ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="step-number">2</span>
                    <?= $translator->translate('2fa_setup_step2_title') ?>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">
                        <?= $translator->translate('2fa_setup_step2_desc') ?>
                    </p>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('2fa_setup_code_label') ?>:</label>
                            <input type="text"
                                   name="code"
                                   class="form-control"
                                   placeholder="123456"
                                   pattern="[0-9]{6}"
                                   maxlength="6"
                                   required
                                   autofocus
                                   style="font-size: 1.5rem; text-align: center; letter-spacing: 0.5rem;">
                        </div>

                        <button type="submit" name="verify_code" class="btn btn-primary" style="width: 100%;">
                            <?= $translator->translate('2fa_setup_verify_btn') ?>
                        </button>
                    </form>

                    <div class="warning-box" style="margin-top: 2rem;">
                        <strong>⚠️ <?= $translator->translate('2fa_setup_warning_label') ?>:</strong>
                        <?= $translator->translate('2fa_setup_warning_text') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <span class="step-number">3</span>
                <?= $translator->translate('2fa_setup_step3_title') ?>
            </div>
            <div class="card-body">
                <p style="margin-bottom: 1rem;">
                    <strong><?= $translator->translate('2fa_setup_backup_intro') ?></strong>
                    <?= $translator->translate('2fa_setup_backup_desc') ?>
                </p>

                <div class="backup-codes">
                    <?php foreach ($backupCodes as $code): ?>
                        <div class="backup-code"><?= escape($code) ?></div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 1.5rem; text-align: center;">
                    <button onclick="printBackupCodes()" class="btn btn-secondary">
                        🖨️ <?= $translator->translate('2fa_setup_print_btn') ?>
                    </button>
                    <button onclick="copyBackupCodes()" class="btn btn-secondary">
                        📋 <?= $translator->translate('2fa_setup_copy_btn') ?>
                    </button>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="<?= SITE_URL ?>/users/profile.php" class="btn btn-secondary">
                ← <?= $translator->translate('2fa_setup_back_btn') ?>
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const qrData = <?= json_encode($qrData) ?>;
        new QRCode(document.getElementById("qrcode"), {
            text: qrData,
            width: 250,
            height: 250,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });

        function printBackupCodes() {
            const codes = <?= json_encode($backupCodes) ?>;
            const printWindow = window.open('', '', 'width=600,height=400');
            printWindow.document.write('<html><head><title><?= addslashes(SITE_NAME) ?> - <?= addslashes($translator->translate('2fa_setup_backup_print_title')) ?></title>');
            printWindow.document.write('<style>body { font-family: Arial, sans-serif; padding: 2rem; }');
            printWindow.document.write('.code { font-family: monospace; font-size: 1.2rem; margin: 0.5rem 0; }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h1><?= addslashes(SITE_NAME) ?> - <?= addslashes($translator->translate('2fa_setup_backup_print_title')) ?></h1>');
            printWindow.document.write('<p><strong><?= addslashes($translator->translate('2fa_setup_account_label')) ?>:</strong> <?= escape($userInfo['username']) ?></p>');
            printWindow.document.write('<p><strong><?= addslashes($translator->translate('2fa_setup_date_label')) ?>:</strong> ' + new Date().toLocaleDateString() + '</p>');
            printWindow.document.write('<hr>');
            codes.forEach(code => {
                printWindow.document.write('<div class="code">' + code + '</div>');
            });
            printWindow.document.write('<hr><p style="font-size: 0.9rem; color: #666;"><?= addslashes($translator->translate('2fa_setup_backup_keep_safe')) ?></p>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        function copyBackupCodes() {
            const codes = <?= json_encode($backupCodes) ?>;
            const text = '<?= addslashes(SITE_NAME) ?> - <?= addslashes($translator->translate('2fa_setup_backup_print_title')) ?>\n\n' + codes.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                alert('<?= addslashes($translator->translate('2fa_setup_copy_success')) ?>');
            });
        }
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
