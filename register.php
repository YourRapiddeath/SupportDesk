<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/User.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$success = '';

try {
    $user = new User();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $fullName = $_POST['full_name'] ?? '';

        if ($password !== $passwordConfirm) {
            $error = $translator->translate('register_error_password_mismatch');
        } elseif (strlen($password) < 6) {
            $error = $translator->translate('supporters_password_too_short');
        } elseif ($user->register($username, $email, $password, $fullName)) {
            $success = $translator->translate('register_success');
        } else {
            $error = $translator->translate('supporters_user_added_mailfail');
        }
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
    <title>Registrierung - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2><?= $translator->translate('register_title') ?></h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= escape($success) ?>
                    <a href="<?= SITE_URL ?>/login.php" style="color: inherit; text-decoration: underline;"><?= $translator->translate('btn_login') ?></a>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('register_name') ?></label>
                    <input type="text" name="full_name" class="form-control" required autofocus
                           value="<?= escape($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('register_username') ?></label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= escape($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('register_email') ?></label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= escape($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('register_password') ?></label>
                    <input type="password" name="password" class="form-control" required>
                    <small style="color: var(--text-light);"><?= $translator->translate('supporters_password_rules') ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('register_password_confirm') ?></label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $translator->translate('register_submit') ?></button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem;">
                <?= $translator->translate('register_already_member') ?>
                <a href="<?= SITE_URL ?>/login.php" style="color: var(--primary); font-weight: 500;"><?= $translator->translate('btn_login') ?></a>
            </p>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
