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

$stmt = $db->prepare("SELECT two_fa_enabled, two_fa_secret, backup_codes FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData['two_fa_enabled']) {
    header('Location: ' . SITE_URL . 'users/setup-2fa.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    $password = $_POST['password'] ?? '';

    if (empty($password)) {
        $error = 'Bitte geben Sie Ihr Passwort ein.';
    } else {
        // Verify password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $passwordHash = $stmt->fetchColumn();

        if (password_verify($password, $passwordHash)) {
            $newSecret      = TOTP::generateSecret(16);
            $newBackupCodes = [];
            for ($i = 0; $i < 10; $i++) {
                $newBackupCodes[] = strtoupper(bin2hex(random_bytes(4)));
            }
            $newBackupCodesJson = json_encode($newBackupCodes);

            // 2FA deaktivieren, neuen Schlüssel + Codes speichern
            $stmt = $db->prepare("
                UPDATE users
                SET two_fa_enabled = 0,
                    two_fa_secret  = ?,
                    backup_codes   = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$newSecret, $newBackupCodesJson, $userId])) {
                header('Location: ' . SITE_URL . '/users/profile.php?2fa_disabled=1');
                exit;
            } else {
                $error = 'Fehler beim Deaktivieren von 2FA.';
            }
        } else {
            $error = 'Falsches Passwort.';
        }
    }
}

$backupCodes = json_decode($userData['backup_codes'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verwalten - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .manage-container {
            max-width: 700px;
            margin: 2rem auto;
        }
        .status-box {
            background: #d1fae5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
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
        .danger-zone {
            color: #0f172a;
            background: rgba(239, 68, 68, 0.47);
            border: 2px solid #ef4444;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container manage-container">
        <h1 style="text-align: center; margin-bottom: 2rem;">🔐 Zwei-Faktor-Authentifizierung verwalten</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>

        <div class="status-box">
            <h2 style="margin: 0 0 0.5rem 0; color: #10b981;">✓ 2FA ist aktiviert</h2>
            <p style="margin: 0; color: var(--text-light);">
                Ihr Account ist durch Zwei-Faktor-Authentifizierung geschützt.
            </p>
        </div>
        <div class="card">
            <div class="card-header">Ihr 2FA-Schlüssel</div>
            <div class="card-body">
                <p style="margin-bottom: 1rem;">
                    Dies ist Ihr aktueller 2FA-Schlüssel. Bewahren Sie ihn sicher auf:
                </p>
                <div style="background: var(--surface); padding: 1rem; border-radius: 8px; text-align: center;">
                    <code style="font-size: 1.3rem; font-weight: bold; letter-spacing: 2px;">
                        <?= escape($userData['two_fa_secret']) ?>
                    </code>
                </div>
            </div>
        </div>

        <?php if (!empty($backupCodes)): ?>
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">Backup-Codes</div>
            <div class="card-body">
                <p style="margin-bottom: 1rem;">
                    Ihre Backup-Codes zur Wiederherstellung des Zugangs:
                </p>
                <div class="backup-codes">
                    <?php foreach ($backupCodes as $code): ?>
                        <div class="backup-code"><?= escape($code) ?></div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 1rem; text-align: center;">
                    <button onclick="copyBackupCodes()" class="btn btn-secondary">
                        📋 Codes kopieren
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="danger-zone">
            <h3 style="margin: 0 0 1rem 0; color: #ef4444;">⚠️ Gefahrenzone</h3>
            <p style="margin-bottom: 1.5rem;">
                Das Deaktivieren von 2FA reduziert die Sicherheit Ihres Accounts erheblich.
            </p>

            <form method="POST" onsubmit="return confirm('Sind Sie sicher, dass Sie 2FA deaktivieren möchten?');">
                <div class="form-group">
                    <label class="form-label">Passwort zur Bestätigung:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" name="disable_2fa" class="btn btn-danger">
                    2FA deaktivieren
                </button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="<?= SITE_URL ?>/users/profile.php" class="btn btn-secondary">
                ← Zurück zum Profil
            </a>
        </div>
    </div>

    <script>
        function copyBackupCodes() {
            const codes = <?= json_encode($backupCodes) ?>;
            const text = '<?= SITE_NAME ?> - 2FA Backup Codes\n\n' + codes.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                alert('Backup-Codes in die Zwischenablage kopiert!');
            });
        }
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
