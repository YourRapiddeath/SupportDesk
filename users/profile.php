<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';

requireLogin();

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if (empty($fullName)) {
        $error = $translator->translate('profile_error_name_empty');
    } else {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$fullName, $bio, $userId])) {
            $_SESSION['full_name'] = $fullName;
            $success = $translator->translate('profile_saved');
        } else {
            $error = $translator->translate('profile_error_update');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        $fileType = $_FILES['avatar']['type'];
        $fileSize = $_FILES['avatar']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = $translator->translate('profile_error_file_type');
        } elseif ($fileSize > $maxSize) {
            $error = $translator->translate('profile_error_file_size');
        } else {
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $newFilename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = __DIR__ . '/../uploads/avatars/' . $newFilename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldAvatar = $stmt->fetchColumn();
                if ($oldAvatar && file_exists(__DIR__ . '/../' . $oldAvatar)) {
                    unlink(__DIR__ . '/../' . $oldAvatar);
                }

                $avatarPath = 'uploads/avatars/' . $newFilename;
                $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                if ($stmt->execute([$avatarPath, $userId])) {
                    $success = $translator->translate('profile_avatar_uploaded');
                } else {
                    $error = $translator->translate('profile_error_save_avatar');
                }
            } else {
                $error = $translator->translate('profile_error_upload');
            }
        }
    } else {
        $error = $translator->translate('profile_error_no_file');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $avatar = $stmt->fetchColumn();

    if ($avatar && file_exists(__DIR__ . '/../' . $avatar)) {
        unlink(__DIR__ . '/../' . $avatar);
    }

    $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $success = $translator->translate('profile_avatar_removed');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = $translator->translate('error_fill_required_fields');
    } elseif ($newPassword !== $confirmPassword) {
        $error = $translator->translate('register_error_password_mismatch');
    } elseif (strlen($newPassword) < 6) {
        $error = $translator->translate('supporters_password_too_short');
    } else {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentHash = $stmt->fetchColumn();

        if (password_verify($currentPassword, $currentHash)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$newHash, $userId])) {
                $success = $translator->translate('profile_password_changed');
            } else {
                $error = $translator->translate('profile_error_change_password');
            }
        } else {
            $error = $translator->translate('profile_error_wrong_password');
        }
    }
}

if (isset($_GET['2fa_enabled'])) {
    $success = $translator->translate('profile_2fa_enabled_msg');
} elseif (isset($_GET['2fa_disabled'])) {
    $success = $translator->translate('profile_2fa_disabled_msg');
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

$isSupporter = in_array($userData['role'], ['first_level','second_level','third_level','admin']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $translator->translate('profile_page_title') ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .avatar-container { position: relative; }
        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
        }
        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            font-weight: bold;
        }
        .profile-info h1 { margin: 0 0 0.5rem 0; }
        .profile-stats { display: flex; gap: 2rem; margin-top: 1rem; flex-wrap: wrap; }
        .stat-item { font-size: 0.9rem; color: var(--text-light); }

        /* Tabs */
        .profile-tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1.5rem;
            gap: 0;
            flex-wrap: wrap;
        }
        .profile-tab-btn {
            padding: 0.65rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .15s, border-color .15s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .profile-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .profile-tab-btn:hover:not(.active) { color: var(--text); }
        .profile-tab-panel { display: none; }
        .profile-tab-panel.active { display: block; }

        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .section-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-tab-btn { padding: 0.5rem 0.75rem; font-size: 0.8rem; }
        }
        .two-fa-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .two-fa-enabled { background: #d1fae5; border-color: #10b981; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container profile-container">
        <div class="profile-header">
            <div class="avatar-container">
                <?php if ($userData['avatar']): ?>
                    <img src="<?= SITE_URL ?>/<?= escape($userData['avatar']) ?>" alt="<?= $translator->translate('profile_avatar_alt') ?>" class="avatar-large">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?= strtoupper(substr($userData['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?= escape($userData['full_name']) ?></h1>
                <p style="color: var(--text-light); margin: 0;">@<?= escape($userData['username']) ?></p>
                <span class="badge badge-<?= $userData['role'] ?>" style="margin-top: 0.5rem;">
                    <?= translateRole($userData['role']) ?>
                </span>
                <div class="profile-stats">
                    <div class="stat-item"><strong><?= $translator->translate('profile_member_since') ?>:</strong> <?= formatDate($userData['created_at']) ?></div>
                    <?php if ($userData['last_login']): ?>
                        <div class="stat-item"><strong><?= $translator->translate('profile_last_login') ?>:</strong> <?= formatDate($userData['last_login']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

        <div class="profile-tabs">
            <button class="profile-tab-btn active" onclick="switchProfileTab(event,'tab-profile')">
                👤 <?= $translator->translate('profile_tab_profile') ?>
            </button>
            <button class="profile-tab-btn" onclick="switchProfileTab(event,'tab-avatar')">
                🖼️ <?= $translator->translate('profile_tab_avatar') ?>
            </button>
            <button class="profile-tab-btn" onclick="switchProfileTab(event,'tab-password')">
                🔒 <?= $translator->translate('profile_tab_password') ?>
            </button>
            <button class="profile-tab-btn" onclick="switchProfileTab(event,'tab-2fa')">
                🛡️ <?= $translator->translate('profile_tab_2fa') ?>
                <?php if (!$userData['two_fa_enabled']): ?>
                    <span style="background:#ef4444;color:#fff;border-radius:10px;padding:0 5px;font-size:0.65rem;">!</span>
                <?php endif; ?>
            </button>
            <?php if ($isSupporter): ?>
            <button class="profile-tab-btn" onclick="switchProfileTab(event,'tab-templates')">
                ✏️ <?= $translator->translate('profile_tab_templates') ?>
            </button>
            <?php endif; ?>
        </div>

        <div id="tab-profile" class="profile-tab-panel active">
            <div class="card">
                <div class="card-header"><?= $translator->translate('profile_edit_header') ?></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('profile_label_name') ?>:</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= escape($userData['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('profile_label_bio') ?>:</label>
                            <textarea name="bio" class="form-control" rows="5"
                                      placeholder="<?= $translator->translate('profile_bio_placeholder') ?>"><?= escape($userData['bio'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary"><?= $translator->translate('profile_save_btn') ?></button>
                    </form>
                </div>
            </div>
            <?php if ($userData['bio']): ?>
                <div class="card" style="margin-top:1rem;">
                    <div class="card-header"><?= $translator->translate('profile_bio_preview') ?></div>
                    <div class="card-body">
                        <p style="white-space: pre-wrap; line-height: 1.6;"><?= escape($userData['bio']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-avatar" class="profile-tab-panel">
            <div class="card">
                <div class="card-header"><?= $translator->translate('profile_tab_avatar') ?></div>
                <div class="card-body">
                    <?php if ($userData['avatar']): ?>
                        <div style="margin-bottom:1rem; text-align:center;">
                            <img src="<?= SITE_URL ?>/<?= escape($userData['avatar']) ?>" alt="<?= $translator->translate('profile_avatar_alt') ?>"
                                 style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);">
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('profile_avatar_upload_label') ?>:</label>
                            <input type="file" name="avatar" class="form-control" accept="image/*">
                            <small style="color: var(--text-light); display: block; margin-top: 0.5rem;"><?= $translator->translate('profile_avatar_hint') ?></small>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" name="upload_avatar" class="btn btn-primary"><?= $translator->translate('profile_avatar_upload_btn') ?></button>
                            <?php if ($userData['avatar']): ?>
                                <button type="submit" name="remove_avatar" class="btn btn-danger"
                                        onclick="return confirm('<?= addslashes($translator->translate('profile_avatar_remove_confirm')) ?>')">
                                    <?= $translator->translate('profile_avatar_remove_btn') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="tab-password" class="profile-tab-panel">
            <div class="card">
                <div class="card-header"><?= $translator->translate('profile_password_header') ?></div>
                <div class="card-body">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('profile_password_current') ?>:</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('profile_password_new') ?>:</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('profile_password_confirm') ?>:</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary"><?= $translator->translate('profile_password_change') ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div id="tab-2fa" class="profile-tab-panel">
            <div class="card">
                <div class="card-header"><?= $translator->translate('profile_2fa_header') ?></div>
                <div class="card-body">
                    <div class="<?= $userData['two_fa_enabled'] ? 'two-fa-box two-fa-enabled' : 'two-fa-box' ?>">
                        <p>
                            <strong><?= $translator->translate('profile_2fa_status') ?>:</strong>
                            <?php if ($userData['two_fa_enabled']): ?>
                                <span style="color:#10b981;">✓ <?= $translator->translate('profile_2fa_active') ?></span>
                            <?php else: ?>
                                <span style="color:#ef4444;">✗ <?= $translator->translate('profile_2fa_inactive') ?></span>
                            <?php endif; ?>
                        </p>
                        <p style="font-size:0.9rem; color:var(--text-light); margin-top:0.5rem;">
                            <?= $translator->translate('profile_2fa_hint') ?>
                        </p>
                    </div>
                    <div style="margin-top:1rem;">
                        <?php if ($userData['two_fa_enabled']): ?>
                            <a href="<?= SITE_URL ?>/users/disable-2fa.php" class="btn btn-secondary"><?= $translator->translate('profile_2fa_manage') ?></a>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/users/setup-2fa.php" class="btn btn-primary"><?= $translator->translate('profile_2fa_setup') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isSupporter): ?>
        <div id="tab-templates" class="profile-tab-panel">
            <div class="card">
                <div class="card-header">✏️ <?= $translator->translate('profile_templates_header') ?></div>
                <div class="card-body" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <p style="margin:0 0 0.25rem 0; font-weight:600;"><?= $translator->translate('profile_templates_title') ?></p>
                        <p style="margin:0; font-size:0.875rem; color:var(--text-light);">
                            <?= $translator->translate('profile_templates_hint') ?>
                        </p>
                    </div>
                    <a href="<?= SITE_URL ?>/users/templates.php" class="btn btn-primary" style="white-space:nowrap;">
                        <?= $translator->translate('profile_templates_manage') ?> →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function switchProfileTab(e, id) {
        document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.profile-tab-panel').forEach(p => p.classList.remove('active'));
        e.currentTarget.classList.add('active');
        document.getElementById(id).classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const map = {
            'password': 'tab-password',
            '2fa':      'tab-2fa',
            'avatar':   'tab-avatar',
            'templates':'tab-templates',
        };
        const hash = window.location.hash.replace('#','');
        if (map[hash]) {
            document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.profile-tab-panel').forEach(p => p.classList.remove('active'));
            document.getElementById(map[hash]).classList.add('active');
            // Zugehörigen Button aktivieren
            document.querySelectorAll('.profile-tab-btn').forEach(b => {
                if (b.getAttribute('onclick') && b.getAttribute('onclick').includes(map[hash])) {
                    b.classList.add('active');
                }
            });
        }
    });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
