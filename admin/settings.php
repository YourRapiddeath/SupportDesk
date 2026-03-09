<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/Email.php';

requireLogin();
requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_theme'])) {
        $theme = $_POST['theme'];
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'theme'");
        if ($stmt->execute([$theme])) {
            // Custom CSS deaktivieren wenn Theme gewechselt wird
            $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('custom_css_enabled','0') ON DUPLICATE KEY UPDATE setting_value='0'")->execute();
            $message = $translator->translate('settings_theme_updated');
        } else {
            $error = $translator->translate('settings_theme_updated_faild');
        }
    } elseif (isset($_POST['update_gta_bg'])) {
        // GTA Hintergrundbild Upload
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Blur-Wert immer speichern (auch ohne neues Bild)
        $gtaBlur       = max(0,   min(20,  (int)($_POST['gta_bg_blur']       ?? 6)));
        $gtaBrightness = max(5,   min(100, (int)($_POST['gta_bg_brightness'] ?? 35)));
        $gtaPosX       = max(0,   min(100, (int)($_POST['gta_bg_pos_x']      ?? 50)));
        $gtaPosY       = max(0,   min(100, (int)($_POST['gta_bg_pos_y']      ?? 50)));
        $gtaSize       = max(100, min(300, (int)($_POST['gta_bg_size']       ?? 100)));
        foreach ([
                         'gta_bg_blur'       => $gtaBlur,
                         'gta_bg_brightness' => $gtaBrightness,
                         'gta_bg_pos_x'      => $gtaPosX,
                         'gta_bg_pos_y'      => $gtaPosY,
                         'gta_bg_size'       => $gtaSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['gta_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024; // 8 MB
            if (!in_array($_FILES['gta_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['gta_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext = pathinfo($_FILES['gta_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'gta_bg_' . time() . '.' . strtolower($ext);
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['gta_bg_file']['tmp_name'], $dest)) {
                    // Altes Bild löschen
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gta_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) {
                        @unlink(__DIR__ . '/../' . $old);
                    }
                    $path = 'uploads/backgrounds/' . $filename;
                    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('gta_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                    $stmt->execute([$path]);
                    $message = 'GTA Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['gta_bg_remove'])) {
            // Bild entfernen
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gta_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) {
                @unlink(__DIR__ . '/../' . $old);
            }
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('gta_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''");
            $stmt->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            // Nur Blur geändert
            if (empty($error)) $message = 'Blur-Einstellung gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_rotlicht_bg'])) {
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        // Blur-Wert immer speichern
        $rlBlur       = max(0,   min(20,  (int)($_POST['rotlicht_bg_blur']       ?? 8)));
        $rlBrightness = max(5,   min(100, (int)($_POST['rotlicht_bg_brightness'] ?? 28)));
        $rlPosX       = max(0,   min(100, (int)($_POST['rotlicht_bg_pos_x']      ?? 50)));
        $rlPosY       = max(0,   min(100, (int)($_POST['rotlicht_bg_pos_y']      ?? 50)));
        $rlSize       = max(100, min(300, (int)($_POST['rotlicht_bg_size']       ?? 100)));
        foreach ([
                         'rotlicht_bg_blur'       => $rlBlur,
                         'rotlicht_bg_brightness' => $rlBrightness,
                         'rotlicht_bg_pos_x'      => $rlPosX,
                         'rotlicht_bg_pos_y'      => $rlPosY,
                         'rotlicht_bg_size'       => $rlSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['rotlicht_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024;
            if (!in_array($_FILES['rotlicht_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['rotlicht_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext      = pathinfo($_FILES['rotlicht_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'rotlicht_bg_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['rotlicht_bg_file']['tmp_name'], $dest)) {
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'rotlicht_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
                    $path  = 'uploads/backgrounds/' . $filename;
                    $stmt  = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('rotlicht_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                    $stmt->execute([$path]);
                    $message = 'Rotlicht Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['rotlicht_bg_remove'])) {
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'rotlicht_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('rotlicht_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''");
            $stmt->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            if (empty($error)) $message = 'Blur-Einstellung gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_dayz_bg'])) {
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $dayzBlur       = max(0,   min(20,  (int)($_POST['dayz_bg_blur']       ?? 4)));
        $dayzBrightness = max(5,   min(100, (int)($_POST['dayz_bg_brightness'] ?? 30)));
        $dayzPosX       = max(0,   min(100, (int)($_POST['dayz_bg_pos_x']      ?? 50)));
        $dayzPosY       = max(0,   min(100, (int)($_POST['dayz_bg_pos_y']      ?? 50)));
        $dayzSize       = max(100, min(300, (int)($_POST['dayz_bg_size']       ?? 100)));
        foreach ([
                         'dayz_bg_blur'       => $dayzBlur,
                         'dayz_bg_brightness' => $dayzBrightness,
                         'dayz_bg_pos_x'      => $dayzPosX,
                         'dayz_bg_pos_y'      => $dayzPosY,
                         'dayz_bg_size'       => $dayzSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['dayz_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024;
            if (!in_array($_FILES['dayz_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['dayz_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext      = pathinfo($_FILES['dayz_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'dayz_bg_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['dayz_bg_file']['tmp_name'], $dest)) {
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'dayz_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
                    $path = 'uploads/backgrounds/' . $filename;
                    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('dayz_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$path]);
                    $message = 'DayZ Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['dayz_bg_remove'])) {
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'dayz_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('dayz_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''")->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            if (empty($error)) $message = 'Einstellungen gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_blackgold_bg'])) {
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $bgBlur       = max(0,   min(20,  (int)($_POST['blackgold_bg_blur']       ?? 5)));
        $bgBrightness = max(5,   min(100, (int)($_POST['blackgold_bg_brightness'] ?? 30)));
        $bgPosX       = max(0,   min(100, (int)($_POST['blackgold_bg_pos_x']      ?? 50)));
        $bgPosY       = max(0,   min(100, (int)($_POST['blackgold_bg_pos_y']      ?? 50)));
        $bgSize       = max(100, min(300, (int)($_POST['blackgold_bg_size']       ?? 100)));
        foreach ([
                         'blackgold_bg_blur'       => $bgBlur,
                         'blackgold_bg_brightness' => $bgBrightness,
                         'blackgold_bg_pos_x'      => $bgPosX,
                         'blackgold_bg_pos_y'      => $bgPosY,
                         'blackgold_bg_size'       => $bgSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['blackgold_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024;
            if (!in_array($_FILES['blackgold_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['blackgold_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext      = pathinfo($_FILES['blackgold_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'blackgold_bg_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['blackgold_bg_file']['tmp_name'], $dest)) {
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blackgold_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
                    $path = 'uploads/backgrounds/' . $filename;
                    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('blackgold_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$path]);
                    $message = 'Black & Gold Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['blackgold_bg_remove'])) {
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blackgold_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('blackgold_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''")->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            if (empty($error)) $message = 'Einstellungen gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_winxp_bg'])) {
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $wxBlur       = max(0,   min(20,  (int)($_POST['winxp_bg_blur']       ?? 3)));
        $wxBrightness = max(5,   min(100, (int)($_POST['winxp_bg_brightness'] ?? 70)));
        $wxPosX       = max(0,   min(100, (int)($_POST['winxp_bg_pos_x']      ?? 50)));
        $wxPosY       = max(0,   min(100, (int)($_POST['winxp_bg_pos_y']      ?? 50)));
        $wxSize       = max(100, min(300, (int)($_POST['winxp_bg_size']       ?? 100)));
        foreach ([
                         'winxp_bg_blur'       => $wxBlur,
                         'winxp_bg_brightness' => $wxBrightness,
                         'winxp_bg_pos_x'      => $wxPosX,
                         'winxp_bg_pos_y'      => $wxPosY,
                         'winxp_bg_size'       => $wxSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['winxp_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024;
            if (!in_array($_FILES['winxp_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['winxp_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext      = pathinfo($_FILES['winxp_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'winxp_bg_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['winxp_bg_file']['tmp_name'], $dest)) {
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'winxp_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
                    $path = 'uploads/backgrounds/' . $filename;
                    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('winxp_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$path]);
                    $message = 'Windows XP Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['winxp_bg_remove'])) {
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'winxp_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('winxp_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''")->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            if (empty($error)) $message = 'Einstellungen gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_youtube_bg'])) {
        $uploadDir = __DIR__ . '/../uploads/backgrounds/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $ytBlur       = max(0,   min(20,  (int)($_POST['youtube_bg_blur']       ?? 6)));
        $ytBrightness = max(5,   min(100, (int)($_POST['youtube_bg_brightness'] ?? 28)));
        $ytPosX       = max(0,   min(100, (int)($_POST['youtube_bg_pos_x']      ?? 50)));
        $ytPosY       = max(0,   min(100, (int)($_POST['youtube_bg_pos_y']      ?? 50)));
        $ytSize       = max(100, min(300, (int)($_POST['youtube_bg_size']       ?? 100)));
        foreach ([
                         'youtube_bg_blur'       => $ytBlur,
                         'youtube_bg_brightness' => $ytBrightness,
                         'youtube_bg_pos_x'      => $ytPosX,
                         'youtube_bg_pos_y'      => $ytPosY,
                         'youtube_bg_size'       => $ytSize,
                 ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
        }

        if (!empty($_FILES['youtube_bg_file']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $maxSize = 8 * 1024 * 1024;
            if (!in_array($_FILES['youtube_bg_file']['type'], $allowed)) {
                $error = 'Nur JPG, PNG, WEBP und GIF sind erlaubt.';
            } elseif ($_FILES['youtube_bg_file']['size'] > $maxSize) {
                $error = 'Datei zu groß (max. 8 MB).';
            } else {
                $ext      = pathinfo($_FILES['youtube_bg_file']['name'], PATHINFO_EXTENSION);
                $filename = 'youtube_bg_' . time() . '.' . strtolower($ext);
                $dest     = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['youtube_bg_file']['tmp_name'], $dest)) {
                    $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'youtube_bg_image'");
                    $stmtOld->execute();
                    $old = $stmtOld->fetchColumn();
                    if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
                    $path = 'uploads/backgrounds/' . $filename;
                    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('youtube_bg_image',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$path]);
                    $message = 'YouTube Hintergrundbild gespeichert!';
                } else {
                    $error = 'Fehler beim Speichern der Datei.';
                }
            }
        } elseif (isset($_POST['youtube_bg_remove'])) {
            $stmtOld = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'youtube_bg_image'");
            $stmtOld->execute();
            $old = $stmtOld->fetchColumn();
            if ($old && file_exists(__DIR__ . '/../' . $old)) { @unlink(__DIR__ . '/../' . $old); }
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('youtube_bg_image','') ON DUPLICATE KEY UPDATE setting_value=''")->execute();
            $message = 'Hintergrundbild entfernt.';
        } else {
            if (empty($error)) $message = 'YouTube Einstellungen gespeichert!';
        }
        $activeTab = 'tab-theme';
    } elseif (isset($_POST['update_email'])) {
        $emailSettings = [
                'email_notifications' => $_POST['email_notifications'] ?? '0',
                'smtp_host'           => $_POST['smtp_host'] ?? '',
                'smtp_port'           => $_POST['smtp_port'] ?? '587',
                'smtp_user'           => $_POST['smtp_user'] ?? '',
                'smtp_from_email'     => $_POST['smtp_from_email'] ?? '',
                'smtp_from_name'      => $_POST['smtp_from_name'] ?? ''
        ];
        if (!empty($_POST['smtp_password'])) {
            $emailSettings['smtp_password'] = $_POST['smtp_password'];
        }
        foreach ($emailSettings as $key => $value) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $message = $translator->translate('settings_email_updated');
    } elseif (isset($_POST['update_site'])) {
        // ── DB: site_name speichern ────────────────────────────────────────
        $newSiteName = trim($_POST['site_name'] ?? '');
        if ($newSiteName) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='site_name'")->execute([$newSiteName]);
        }

        // ── config.php neu schreiben ───────────────────────────────────────
        $cfgPath = realpath(__DIR__ . '/../config.php');
        $cfgOk   = false;
        $cfgErr  = '';

        $isSuperAdmin = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

        if ($cfgPath && is_writable($cfgPath)) {
            $siteName  = trim($_POST['site_name']   ?? SITE_NAME);
            $siteUrl   = rtrim(trim($_POST['site_url']    ?? SITE_URL), '/');
            $timezone  = trim($_POST['timezone']    ?? 'Europe/Berlin');
            $errReport = isset($_POST['error_reporting']) ? 1 : 0;

            // DB-Zugangsdaten nur für Hauptadmin (ID=1)
            $dbHost = $isSuperAdmin ? trim($_POST['db_host'] ?? DB_HOST) : DB_HOST;
            $dbName = $isSuperAdmin ? trim($_POST['db_name'] ?? DB_NAME) : DB_NAME;
            $dbUser = $isSuperAdmin ? trim($_POST['db_user'] ?? DB_USER) : DB_USER;
            $dbPass = $isSuperAdmin ? (($_POST['db_pass'] ?? '') !== '' ? $_POST['db_pass'] : DB_PASS) : DB_PASS;

            // Bestehende config.php lesen und nur die definierten Zeilen ersetzen
            $cfg = file_get_contents($cfgPath);

            $cfg = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/",   "define('DB_HOST', " . var_export($dbHost, true) . ");",  $cfg);
            $cfg = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/",   "define('DB_NAME', " . var_export($dbName, true) . ");",  $cfg);
            $cfg = preg_replace("/define\('DB_USER',\s*'[^']*'\);/",   "define('DB_USER', " . var_export($dbUser, true) . ");",  $cfg);
            $cfg = preg_replace("/define\('DB_PASS',\s*'[^']*'\);/",   "define('DB_PASS', " . var_export($dbPass, true) . ");",  $cfg);
            $cfg = preg_replace("/define\('SITE_URL',\s*'[^']*'\);/",  "define('SITE_URL', " . var_export($siteUrl, true) . ");", $cfg);
            $cfg = preg_replace("/define\('SITE_NAME',\s*'[^']*'\);/", "define('SITE_NAME', " . var_export($siteName, true) . ");", $cfg);
            $cfg = preg_replace("/date_default_timezone_set\('[^']*'\);/", "date_default_timezone_set(" . var_export($timezone, true) . ");", $cfg);
            $cfg = preg_replace("/error_reporting\([^)]*\);/",          "error_reporting(" . ($errReport ? 'E_ALL' : '0') . ");",  $cfg);
            $cfg = preg_replace("/ini_set\('display_errors',\s*[01]\);/", "ini_set('display_errors', " . $errReport . ");",         $cfg);

            if (file_put_contents($cfgPath, $cfg) !== false) {
                $cfgOk   = true;
                $message = '✅ ' . $translator->translate('settings_config_saved');
            } else {
                $cfgErr = $translator->translate('settings_config_not_writable2');
                $error  = $cfgErr;
            }
        } else {
            $error = $translator->translate('settings_config_not_writable');
        }
        if ($cfgOk && !$message) $message = $translator->translate('settings_website_updated');
    } elseif (isset($_POST['save_custom_css'])) {
        $customCss = $_POST['custom_css'] ?? '';
        $enabled   = isset($_POST['custom_css_enabled']) ? '1' : '0';
        $stmtCss = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('custom_css', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmtEna = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('custom_css_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmtCss->execute([$customCss]) && $stmtEna->execute([$enabled])) {
            $message = $enabled === '1' ? '✅ Custom CSS gespeichert und aktiviert.' : '✅ Custom CSS gespeichert (deaktiviert).';
        } else {
            $error = 'Fehler beim Speichern des Custom CSS.';
        }
        $activeTab = 'tab-custom-css';
    } elseif (isset($_POST['save_footer'])) {
        // ── Footer-Einstellungen speichern ─────────────────────────────────────
        $activeTab = 'tab-footer';
        $ftKeys = ['footer_enabled','footer_bg_color','footer_text_color',
                'footer_imprint_show','footer_imprint_label','footer_imprint_content',
                'footer_privacy_show','footer_privacy_label','footer_privacy_content',
                'footer_terms_show','footer_terms_label','footer_terms_content',
                'footer_contact_show','footer_contact_label','footer_contact_content'];
        $upsert = $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach ($ftKeys as $k) {
            $v = '';
            if (in_array($k,['footer_enabled','footer_imprint_show','footer_privacy_show','footer_terms_show','footer_contact_show'])) {
                $v = isset($_POST[$k]) ? '1' : '0';
            } else {
                $v = $_POST[$k] ?? '';
            }
            $upsert->execute([$k, $v]);
        }
        // Custom Links speichern
        $labels  = $_POST['cl_label']   ?? [];
        $urls    = $_POST['cl_url']     ?? [];
        $newTabs = $_POST['cl_new_tab'] ?? [];
        $links   = [];
        foreach ($labels as $i => $lbl) {
            $lbl = trim($lbl); $url = trim($urls[$i] ?? '');
            if ($lbl && $url) {
                $links[] = ['label'=>$lbl,'url'=>$url,'new_tab'=>!empty($newTabs[$i])];
            }
        }
        $upsert->execute(['footer_custom_links', json_encode($links)]);
        $message = '✅ Footer-Einstellungen gespeichert.';
    } elseif (isset($_POST['lang_import'])) {
        // ── Sprach-Import ──────────────────────────────────────────────────────
        $activeTab  = 'tab-language';
        $langCode   = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['lang_code'] ?? ''));
        $overwrite  = !empty($_POST['lang_overwrite']);
        $file       = $_FILES['lang_file'] ?? null;

        if (!$langCode) {
            $error = $translator->translate('lang_import_error_code');
        } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = $translator->translate('lang_import_error_format');
        } else {
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $destPath = __DIR__ . '/../assets/lang/' . $langCode . '.php';

            if (!in_array($ext, ['php', 'json'])) {
                $error = $translator->translate('lang_import_error_format');
            } elseif (file_exists($destPath) && !$overwrite) {
                $error = $translator->translate('lang_import_error_exists');
            } else {
                $raw = file_get_contents($file['tmp_name']);
                if ($ext === 'json') {
                    $arr = json_decode($raw, true);
                    if (!is_array($arr)) {
                        $error = $translator->translate('lang_import_error_parse');
                    } else {
                        $php  = "<?php\nreturn " . var_export($arr, true) . ";\n";
                        if (file_put_contents($destPath, $php) !== false) {
                            $message = $translator->translate('lang_import_success');
                        } else {
                            $error = $translator->translate('lang_import_error_write');
                        }
                    }
                } else {
                    // PHP-Datei: temporär evaluieren und prüfen
                    $tmpPath = sys_get_temp_dir() . '/lang_import_' . uniqid() . '.php';
                    file_put_contents($tmpPath, $raw);
                    try {
                        $arr = include $tmpPath;
                        @unlink($tmpPath);
                        if (!is_array($arr)) {
                            $error = $translator->translate('lang_import_error_parse');
                        } else {
                            $php = "<?php\nreturn " . var_export($arr, true) . ";\n";
                            if (file_put_contents($destPath, $php) !== false) {
                                $message = $translator->translate('lang_import_success');
                            } else {
                                $error = $translator->translate('lang_import_error_write');
                            }
                        }
                    } catch (\Throwable $e) {
                        @unlink($tmpPath);
                        $error = $translator->translate('lang_import_error_parse');
                    }
                }
            }
        }
    } elseif (isset($_POST['lang_delete'])) {
        // ── Sprach-Löschen ─────────────────────────────────────────────────────
        $activeTab = 'tab-language';
        $builtIn   = ['DE-de', 'EN-en', 'FR-fr', 'ES-es', 'CH-ch', 'NDS-nds'];
        $langCode  = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['lang_code_delete'] ?? ''));
        $delPath   = __DIR__ . '/../assets/lang/' . $langCode . '.php';
        if (in_array($langCode, $builtIn)) {
            $error = $translator->translate('lang_delete_builtin_error');
        } elseif ($langCode && file_exists($delPath)) {
            @unlink($delPath);
            // Auch aus DB entfernen
            $db->prepare("DELETE FROM language_settings WHERE lang_code=?")->execute([$langCode]);
            $message = $translator->translate('lang_delete_success');
        }
    } elseif (isset($_POST['lang_toggle'])) {
        // ── Sprach-Aktivierung togglen ─────────────────────────────────────────
        $activeTab = 'tab-language';
        $langCode  = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['lang_code_toggle'] ?? ''));
        $newState  = (int)(bool)($_POST['lang_active'] ?? 0);
        // EN-en darf nicht deaktiviert werden (Fallback-Sprache)
        if ($langCode === 'EN-en') {
            $error = $translator->translate('lang_toggle_fallback_error');
        } elseif ($langCode) {
            $db->prepare(
                    "UPDATE language_settings SET is_active=? WHERE lang_code=?"
            )->execute([$newState, $langCode]);
            $message = $newState
                    ? sprintf($translator->translate('lang_toggle_activated'),   $langCode)
                    : sprintf($translator->translate('lang_toggle_deactivated'), $langCode);
        }
    } elseif (isset($_POST['lang_update_meta'])) {
        // ── Sprach-Metadaten (Label, Flag, Sortierung) speichern ──────────────
        $activeTab = 'tab-language';
        $langCode  = preg_replace('/[^A-Za-z0-9\-]/', '', trim($_POST['lang_code_meta'] ?? ''));
        $label     = mb_substr(trim($_POST['lang_label'] ?? ''), 0, 60);
        $flag      = mb_substr(trim($_POST['lang_flag']  ?? ''), 0, 10);
        $sort      = max(0, min(999, (int)($_POST['lang_sort'] ?? 99)));
        if ($langCode && $label) {
            $db->prepare(
                    "UPDATE language_settings SET label=?, flag=?, sort_order=? WHERE lang_code=?"
            )->execute([$label, $flag, $sort, $langCode]);
            $message = sprintf($translator->translate('lang_meta_saved'), $langCode);
        }
    }
}

// ── Sicherstellen dass language_settings Tabelle + Einträge existieren ────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS language_settings (
        lang_code   VARCHAR(20) NOT NULL PRIMARY KEY,
        is_active   TINYINT(1)  NOT NULL DEFAULT 1,
        label       VARCHAR(60) NOT NULL DEFAULT '',
        flag        VARCHAR(10) NOT NULL DEFAULT '',
        sort_order  INT         NOT NULL DEFAULT 99,
        is_builtin  TINYINT(1)  NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $builtinDefs = [
            ['DE-de', 1, 'Deutsch',         '🇩🇪', 1, 1],
            ['EN-en', 1, 'English',          '🇬🇧', 2, 1],
            ['FR-fr', 0, 'Français',         '🇫🇷', 3, 1],
            ['ES-es', 0, 'Español',          '🇪🇸', 4, 1],
            ['CH-ch',  1, 'Schwiizerdüütsch', '🇨🇭', 5, 1],
            ['NDS-nds',0,'Plattdüütsch',     '🌊',  6, 1],
    ];
    $insB = $db->prepare(
            "INSERT INTO language_settings (lang_code,is_active,label,flag,sort_order,is_builtin) VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
             label      = IF(label='' OR label=lang_code, VALUES(label), label),
             flag       = IF(flag='' OR flag='🌐',       VALUES(flag),  flag),
             sort_order = IF(sort_order=99,              VALUES(sort_order), sort_order),
             is_builtin = 1"
    );
    foreach ($builtinDefs as $bd) {
        if (file_exists(__DIR__ . '/../assets/lang/' . $bd[0] . '.php')) $insB->execute($bd);
    }
    // Auto-registrieren neu importierter Dateien
    $langDir2 = __DIR__ . '/../assets/lang/';
    $insNew2  = $db->prepare(
            "INSERT IGNORE INTO language_settings (lang_code,is_active,label,flag,sort_order,is_builtin) VALUES (?,0,?,?,99,0)"
    );
    foreach (glob($langDir2 . '*.php') ?: [] as $lf2) {
        $c2 = basename($lf2, '.php');
        if ($c2 !== 'translator') $insNew2->execute([$c2, $c2, '🌐']);
    }
} catch (\Throwable $e) { /* ignore */ }

// ── Sprach-Export (GET) ───────────────────────────────────────────────────────
if (isset($_GET['lang_export'])) {
    $langCode  = preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['lang_export']);
    $format    = $_GET['format'] ?? 'php';
    $filePath  = __DIR__ . '/../assets/lang/' . $langCode . '.php';
    if (file_exists($filePath)) {
        $arr = include $filePath;
        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $langCode . '.json"');
            echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $langCode . '.php"');
            echo "<?php\nreturn " . var_export($arr, true) . ";\n";
        }
        exit;
    }
}

// ── E-Mail-Vorlagen: Tabelle sicherstellen ────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Defaults-Array (wird sowohl für INSERT als auch für leere-Update genutzt)
$etpl_defaults = [
        'ticket_created' => [
                'name' => 'Ticket erstellt (Kunde)', 'desc' => 'Wird gesendet wenn ein neues Ticket erstellt wird.',
                'subject' => 'Dein Ticket {{ticket_code}} wurde erstellt – {{site_name}}',
                'html' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}.header{background:linear-gradient(135deg,#1d4ed8,#1e40af);padding:32px 36px;text-align:center}.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}.body{padding:32px 36px}.ticket-code{display:inline-block;background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#1d4ed8;letter-spacing:1px;margin-bottom:24px}.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}.btn{display:inline-block;background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}</style></head><body><div class="wrap"><div class="header"><h1>🎫 Ticket erstellt</h1><p>{{site_name}} – Support</p></div><div class="body"><p>Hallo <strong>{{customer_name}}</strong>,</p><p>dein Support-Ticket wurde erfolgreich erstellt.</p><div class="ticket-code">{{ticket_code}}</div><div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div><div class="info-row"><div class="lbl">Status</div>{{status}}</div><div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div><div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div><p style="margin-top:20px">Sobald unser Team antwortet, erhältst du eine Benachrichtigung.</p><a href="{{ticket_url}}" class="btn">Ticket ansehen →</a></div><div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div></div></body></html>',
                'text' => "Hallo {{customer_name}},\n\ndein Ticket wurde erstellt.\n\nTicket-Code : {{ticket_code}}\nBetreff     : {{subject}}\nStatus      : {{status}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
        ],
        'ticket_updated' => [
                'name' => 'Ticket aktualisiert (Kunde)', 'desc' => 'Wird gesendet wenn ein Supporter antwortet.',
                'subject' => 'Neue Antwort auf Ticket {{ticket_code}} – {{site_name}}',
                'html' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}.header{background:linear-gradient(135deg,#059669,#047857);padding:32px 36px;text-align:center}.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}.body{padding:32px 36px}.reply-box{background:#f0fdf4;border-left:4px solid #10b981;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}.btn{display:inline-block;background:#059669;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}</style></head><body><div class="wrap"><div class="header"><h1>💬 Neue Antwort</h1><p>Ticket {{ticket_code}}</p></div><div class="body"><p>Hallo <strong>{{customer_name}}</strong>,</p><p>es gibt eine neue Antwort auf dein Ticket.</p><div class="reply-box"><strong>Antwort von {{supporter_name}}:</strong><br><br>{{reply_message}}</div><div class="info-row"><div class="lbl">Status</div>{{status}}</div><a href="{{ticket_url}}" class="btn">Ticket ansehen →</a></div><div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div></div></body></html>',
                'text' => "Hallo {{customer_name}},\n\n{{supporter_name}} hat geantwortet.\n\nTicket : {{ticket_code}} – {{subject}}\nStatus : {{status}}\n\nAntwort:\n{{reply_message}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
        ],
        'ticket_assigned' => [
                'name' => 'Ticket zugewiesen (Supporter)', 'desc' => 'Wird an den Supporter gesendet bei Zuweisung.',
                'subject' => 'Neues Ticket zugewiesen: {{ticket_code}} – {{site_name}}',
                'html' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}.header{background:linear-gradient(135deg,#7c3aed,#5b21b6);padding:32px 36px;text-align:center}.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}.body{padding:32px 36px}.ticket-code{display:inline-block;background:#f5f3ff;border:2px solid #7c3aed;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#7c3aed;margin-bottom:24px}.info-row{background:#f8fafc;border-left:3px solid #7c3aed;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}.btn{display:inline-block;background:#7c3aed;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}</style></head><body><div class="wrap"><div class="header"><h1>📬 Neues Ticket zugewiesen</h1><p>{{site_name}}</p></div><div class="body"><p>Hallo <strong>{{supporter_name}}</strong>,</p><p>dir wurde ein neues Ticket zugewiesen:</p><div class="ticket-code">{{ticket_code}}</div><div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div><div class="info-row"><div class="lbl">Kundenname</div>{{customer_name}}</div><div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div><div class="info-row"><div class="lbl">Support-Level</div>{{support_level}}</div><div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div><a href="{{ticket_url}}" class="btn">Ticket bearbeiten →</a></div><div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div></div></body></html>',
                'text' => "Hallo {{supporter_name}},\n\ndir wurde ein neues Ticket zugewiesen.\n\nTicket-Code  : {{ticket_code}}\nBetreff      : {{subject}}\nKunde        : {{customer_name}}\nPriorität    : {{priority}}\nSupport-Level: {{support_level}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
        ],
        'ticket_new_message_supporter' => [
                'name' => 'Neue Kunden-Nachricht (Supporter)', 'desc' => 'Wird an den Supporter gesendet wenn der Kunde antwortet.',
                'subject' => 'Neue Nachricht vom Kunden: {{ticket_code}} – {{site_name}}',
                'html' => '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}.header{background:linear-gradient(135deg,#f59e0b,#d97706);padding:32px 36px;text-align:center}.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}.header p{color:rgba(255,255,255,.85);margin:6px 0 0;font-size:14px}.body{padding:32px 36px}.msg-box{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}.info-row{background:#f8fafc;border-left:3px solid #f59e0b;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}.btn{display:inline-block;background:#d97706;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}</style></head><body><div class="wrap"><div class="header"><h1>✉️ Neue Kunden-Nachricht</h1><p>Ticket {{ticket_code}}</p></div><div class="body"><p>Hallo <strong>{{supporter_name}}</strong>,</p><p>der Kunde <strong>{{customer_name}}</strong> hat geantwortet:</p><div style="font-size:16px;font-weight:700;color:#1d4ed8;margin-bottom:16px">🎫 {{ticket_code}} – {{subject}}</div><div class="msg-box">{{reply_message}}</div><div class="info-row"><div class="lbl">Status</div>{{status}}</div><a href="{{ticket_url}}" class="btn">Ticket öffnen →</a></div><div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div></div></body></html>',
                'text' => "Hallo {{supporter_name}},\n\n{{customer_name}} hat auf Ticket {{ticket_code}} geantwortet.\n\nBetreff: {{subject}}\nStatus : {{status}}\n\nNachricht:\n{{reply_message}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
        ],
];

// INSERT falls komplett leer
$etpl_count = (int)$db->query("SELECT COUNT(*) FROM email_templates")->fetchColumn();
if ($etpl_count === 0) {
    $ins = $db->prepare("INSERT IGNORE INTO email_templates (slug,name,description,subject,body_html,body_text) VALUES (?,?,?,?,?,?)");
    foreach ($etpl_defaults as $slug => $d) {
        $ins->execute([$slug, $d['name'], $d['desc'], $d['subject'], $d['html'], $d['text']]);
    }
}
// UPDATE falls body_html leer (Migration alter leerer Einträge)
$upd = $db->prepare("UPDATE email_templates SET subject=?,body_html=?,body_text=? WHERE slug=? AND (body_html='' OR body_html IS NULL)");
foreach ($etpl_defaults as $slug => $d) {
    $upd->execute([$d['subject'], $d['html'], $d['text'], $slug]);
}
// Fehlende Slugs einfügen
$upsertEtpl = $db->prepare("INSERT IGNORE INTO email_templates (slug,name,description,subject,body_html,body_text) VALUES (?,?,?,?,?,?)");
foreach ($etpl_defaults as $slug => $d) {
    $upsertEtpl->execute([$slug, $d['name'], $d['desc'], $d['subject'], $d['html'], $d['text']]);
}

// ── E-Mail-Vorlage speichern ──────────────────────────────────────────────────
$etpl_success = '';
$etpl_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $etpl_id      = (int)($_POST['tpl_id']       ?? 0);
    $etpl_subject = trim($_POST['tpl_subject']    ?? '');
    $etpl_html    = $_POST['tpl_body_html']       ?? '';
    $etpl_text    = $_POST['tpl_body_text']       ?? '';
    $etpl_active  = isset($_POST['tpl_active'])   ? 1 : 0;
    if (!$etpl_id || empty($etpl_subject)) {
        $etpl_error = 'Betreff darf nicht leer sein.';
    } else {
        $stmtEtpl = $db->prepare("UPDATE email_templates SET subject=?,body_html=?,body_text=?,is_active=?,updated_at=NOW() WHERE id=?");
        if ($stmtEtpl->execute([$etpl_subject, $etpl_html, $etpl_text, $etpl_active, $etpl_id])) {
            $etpl_success = 'E-Mail-Vorlage gespeichert.';
        } else {
            $etpl_error = 'Fehler beim Speichern.';
        }
    }
}

// Alle Templates laden
$etpl_all = $db->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$etpl_active_slug = $_GET['tpl'] ?? ($etpl_all[0]['slug'] ?? '');
// Nach POST: aktiven Slug aus tpl_id rekonstruieren
if (isset($_POST['tpl_id'])) {
    foreach ($etpl_all as $t) {
        if ($t['id'] == (int)$_POST['tpl_id']) { $etpl_active_slug = $t['slug']; break; }
    }
}
$etpl_current = null;
foreach ($etpl_all as $t) {
    if ($t['slug'] === $etpl_active_slug) { $etpl_current = $t; break; }
}
if (!$etpl_current && !empty($etpl_all)) { $etpl_current = $etpl_all[0]; $etpl_active_slug = $etpl_current['slug']; }
$etpl_placeholders = Email::getPlaceholders();

// Load current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Aktuelles Theme-CSS einlesen (als Vorlage für den Custom-CSS-Editor)
$currentThemeName = $settings['theme'] ?? 'modern-blue';
$themeCssPath = __DIR__ . '/../assets/css/theme-' . preg_replace('/[^a-z0-9\-]/', '', $currentThemeName) . '.css';
$currentThemeCss = '';
if (file_exists($themeCssPath)) {
    $currentThemeCss = file_get_contents($themeCssPath);
}

// Active tab aus POST ermitteln (damit nach Submit der richtige Tab aktiv bleibt)
$activeTab = 'tab-theme';
if (isset($_POST['update_email']))   $activeTab = 'tab-email';
if (isset($_POST['update_site']))    $activeTab = 'tab-site';
if (isset($_POST['update_theme']))   $activeTab = 'tab-theme';
if (isset($_POST['update_gta_bg']))          $activeTab = 'tab-theme';
if (isset($_POST['update_rotlicht_bg']))     $activeTab = 'tab-theme';
if (isset($_POST['update_dayz_bg']))         $activeTab = 'tab-theme';
if (isset($_POST['update_blackgold_bg']))    $activeTab = 'tab-theme';
if (isset($_POST['save_footer']))            $activeTab = 'tab-footer';
if (isset($_POST['save_custom_css']))        $activeTab = 'tab-custom-css';

// ── Footer-Einstellungen laden ────────────────────────────────────────────────
$ftKeys = ['footer_enabled','footer_bg_color','footer_text_color',
        'footer_imprint_show','footer_imprint_label','footer_imprint_content',
        'footer_privacy_show','footer_privacy_label','footer_privacy_content',
        'footer_terms_show','footer_terms_label','footer_terms_content',
        'footer_contact_show','footer_contact_label','footer_contact_content',
        'footer_custom_links'];
$ft = [];
$ftRows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'footer_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($ftKeys as $k) { $ft[$k] = $ftRows[$k] ?? ''; }
$ftEnabled   = $ft['footer_enabled']    ?: '1';
$ftBgColor   = $ft['footer_bg_color']   ?? '';
$ftTextColor = $ft['footer_text_color'] ?? '';
$ftBuiltin   = [
        'imprint' => ['label'=>'Impressum',    'icon'=>'⚖️'],
        'privacy'  => ['label'=>'Datenschutz', 'icon'=>'🔒'],
        'terms'    => ['label'=>'AGB',         'icon'=>'📄'],
        'contact'  => ['label'=>'Kontakt',     'icon'=>'✉️'],
];
$ftCustomLinks = json_decode($ft['footer_custom_links'] ?: '[]', true) ?: [];
if (isset($_POST['update_winxp_bg']))        $activeTab = 'tab-theme';
if (isset($_POST['update_youtube_bg']))      $activeTab = 'tab-theme';
if (isset($_POST['save_template']))          $activeTab = 'tab-email-tpl';
if (isset($_POST['save_custom_css']))        $activeTab = 'tab-custom-css';?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <?php if (!empty($settings['custom_css']) && ($settings['custom_css_enabled'] ?? '0') === '1'): ?>
        <style id="custom-css-live"><?= $settings['custom_css'] ?></style>
    <?php endif; ?>
    <style>
        .settings-container { max-width: 1200px; margin: 0 auto; }

        .settings-tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0;
        }
        .settings-tab-btn {
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
            white-space: nowrap;
        }
        .settings-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .settings-tab-btn:hover:not(.active) { color: var(--text); }
        .settings-tab-panel { display: none; }
        .settings-tab-panel.active { display: block; }

        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .theme-option {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem;
            cursor: pointer;
            text-align: center;
            transition: border-color .15s;
        }
        .theme-option.selected { border-color: var(--primary); }
        .theme-option input { display: none; }
        .theme-swatch {
            width: 100%;
            height: 40px;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container settings-container">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem;">
        <h1 style="margin:0;"><?= $translator->translate('settings_title') ?></h1>
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-secondary"><?= $translator->translate('back_to_dahboard') ?></a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= escape($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>

    <!-- Tab-Navigation -->
    <div class="settings-tabs">
        <button class="settings-tab-btn" data-tab="tab-site"    onclick="switchSettingsTab(event,'tab-site')">
            🌐 <?= $translator->translate('settings_website') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-theme"   onclick="switchSettingsTab(event,'tab-theme')">
            🎨 <?= $translator->translate('settings_theme') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-custom-css" onclick="switchSettingsTab(event,'tab-custom-css')">
            <?= $translator->translate('settings_tab_custom_css') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-language" onclick="switchSettingsTab(event,'tab-language')">
            <?= $translator->translate('settings_tab_language') ?>
        </button>
        <a href="ticket-fields.php" class="settings-tab-btn" style="text-decoration:none;">
            🗂️ Ticket-Felder
        </a>
        <button class="settings-tab-btn" data-tab="tab-footer" onclick="switchSettingsTab(event,'tab-footer')">
            🦶 Footer
        </button>
        <button class="settings-tab-btn" data-tab="tab-email"   onclick="switchSettingsTab(event,'tab-email')">
            📧 <?= $translator->translate('settings_email_notify') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-email-tpl" onclick="switchSettingsTab(event,'tab-email-tpl')">
            <?= $translator->translate('settings_tab_email_templates') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-templates" onclick="switchSettingsTab(event,'tab-templates')">
            <?= $translator->translate('settings_tab_global_templates') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-chat" onclick="switchSettingsTab(event,'tab-chat')">
            <?= $translator->translate('settings_tab_team_chat') ?>
        </button>
        <button class="settings-tab-btn" data-tab="tab-discord" onclick="switchSettingsTab(event,'tab-discord')">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.031.053a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
            <?= $translator->translate('settings_tab_discord') ?>
        </button>
        <a href="git-integration.php" class="settings-tab-btn" style="text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
            <?= $translator->translate('settings_tab_git') ?>
        </a>
        <a href="youtrack-integration.php" class="settings-tab-btn" style="text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="#0f4fff" style="flex-shrink:0"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1.5 14.5L5 11l1.5-1.5 4 4 7-7L19 8l-8.5 8.5z"/></svg>
            <?= $translator->translate('settings_tab_youtrack') ?>
        </a>
    </div>

    <!-- Tab: Theme -->
    <div id="tab-theme" class="settings-tab-panel">
        <div class="card">
            <div class="card-header"><?= $translator->translate('settings_theme') ?></div>
            <div class="card-body">
                <form method="POST">
                    <div class="theme-grid">
                        <?php
                        $themes = [
                                'modern-blue'       => ['label' => 'Modern Blue',            'colors' => ['#1e40af','#eff6ff','#dbeafe']],
                                'ios'               => ['label' => 'iOS',                    'colors' => ['#007AFF','#F2F2F7','#ffffff']],
                                'dark-purple'       => ['label' => 'Dark Purple',            'colors' => ['#7c3aed','#1e1b2e','#2d2940']],
                                'green-minimal'     => ['label' => 'Green Minimal',          'colors' => ['#16a34a','#f0fdf4','#dcfce7']],
                                'bordeaux-red'      => ['label' => 'Bordeaux Red',           'colors' => ['#9b1c1c','#fff5f5','#fee2e2']],
                                'bordeaux-black'    => ['label' => 'Bordeaux Black Metallic','colors' => ['#9b1c1c','#0a0a0a','#1c0a0a']],
                                'blue-dark'         => ['label' => 'Blue Dark Metallic',     'colors' => ['#1d4ed8','#060a14','#0d1526']],
                                'minecraft'         => ['label' => 'Minecraft Dark',         'colors' => ['#5d9b34','#1a1a1a','#242424']],
                                'minecraft-light'   => ['label' => 'Minecraft',              'colors' => ['#5d9b34','#c6b896','#3d6e1f']],
                                'windows95'         => ['label' => 'Windows 95',             'colors' => ['#000080','#c0c0c0','#008080']],
                                'windows-xp'        => ['label' => 'Windows XP<br>(Image Upload)', 'colors' => ['#1f5fa6','#ece9d8','#3a6ea5']],
                                'windows-vista'     => ['label' => 'Windows Vista Aero',     'colors' => ['#1e6bbf','#c8dcff','#1a3a6b']],
                                'tiktok'            => ['label' => 'TikTok<br>(Animated)',                 'colors' => ['#FE2C55','#000000','#25F4EE']],
                                'whatsapp'          => ['label' => 'WhatsApp',               'colors' => ['#25D366','#111B21','#1F2C34']],
                                'ark'               => ['label' => 'ARK: Survival Ascended', 'colors' => ['#E8700A','#0D0F0A','#C8A84B']],
                                'dayz'              => ['label' => 'DayZ<br>(Image Upload)',                   'colors' => ['#4A5240','#0A0C09','#A0522D']],
                                'unicorn'           => ['label' => 'Unicorn Magic',           'colors' => ['#C084FC','#FAF5FF','#F472B6']],
                                'animal-crossing'   => ['label' => 'Animal Crossing',         'colors' => ['#69C14E','#EEF8E0','#F7C948']],
                                'mario-kart'        => ['label' => 'Mario Kart<br>(Animated)',               'colors' => ['#E8001A','#1A1A2E','#F7C300']],
                                'neon-cyber'        => ['label' => 'Neon Cyber<br>(Animated)<br><small><small>by SA KÜ</small></small>',               'colors' => ['#000000','#00F5FF','#FF00FF']],
                                'black-gold'        => ['label' => 'Black &amp; Gold<br>(Animated)<br>(Image Upload)<br><small><small>by SA KÜ</small></small>',         'colors' => ['#0A0A0A','#C9A84C','#FFD700']],
                                'black-silver'      => ['label' => 'Black &amp; Silver (Animated)<br><small><small>by SA KÜ</small></small>',       'colors' => ['#060608','#C8C8C8','#F0F0F0']],
                                'roblox'            => ['label' => 'ROBLOX',                   'colors' => ['#E8392A','#191919','#FFD700']],
                                'gta-roleplay'      => ['label' => 'GTA Roleplay<br>(Animated)<br>(Image Upload)',             'colors' => ['#0C0C10','#F4C818','#1A8FD1']],
                                'rotlicht'          => ['label' => 'Rotlicht<br>(Animated)<br>(Image Upload)',                 'colors' => ['#0D0508','#E8386A','#6A0080']],
                                'youtube'           => ['label' => 'YouTube<br>(Image Upload)',                                    'colors' => ['#FF0000','#0F0F0F','#282828']],
                        ];
                        foreach ($themes as $val => $t):
                            $sel = ($settings['theme'] ?? '') === $val;
                            ?>
                            <label class="theme-option <?= $sel ? 'selected' : '' ?>" onclick="this.querySelector('input').checked=true;document.querySelectorAll('.theme-option').forEach(o=>o.classList.remove('selected'));this.classList.add('selected');">
                                <input type="radio" name="theme" value="<?= $val ?>" <?= $sel ? 'checked' : '' ?>>
                                <div class="theme-swatch" style="background:linear-gradient(135deg,<?= $t['colors'][0] ?> 40%,<?= $t['colors'][1] ?> 40%);"></div>
                                <div style="font-size:0.85rem; font-weight:600;"><?= $t['label'] ?></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <button type="submit" name="update_theme" class="btn btn-primary"><?= $translator->translate('settings_theme_save') ?></button>
                        <?php if (($settings['custom_css_enabled'] ?? '0') === '1'): ?>
                            <span style="font-size:0.82rem;color:var(--danger);display:flex;align-items:center;gap:0.3rem;">
                            <?= $translator->translate('settings_theme_custom_css_active_warning') ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $currentTheme      = $settings['theme'] ?? 'modern-blue';
        $gtaBg             = $settings['gta_bg_image']      ?? '';
        $gtaBlur           = (int)($settings['gta_bg_blur']        ?? 6);
        $gtaBrightness     = (int)($settings['gta_bg_brightness']  ?? 35);
        $gtaBriFloat       = round($gtaBrightness / 100, 2);
        $gtaPosX           = (int)($settings['gta_bg_pos_x']       ?? 50);
        $gtaPosY           = (int)($settings['gta_bg_pos_y']       ?? 50);
        $gtaSize           = (int)($settings['gta_bg_size']        ?? 100);
        ?>

        <!-- GTA background image box -->
        <div id="gta-bg-box" class="card" style="<?= $currentTheme !== 'gta-roleplay' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid rgba(244,200,24,0.35); background:linear-gradient(180deg,#1E1E28,#111118);">
            <div class="card-header" style="color:#F4C818; font-family:'Barlow Condensed',sans-serif; letter-spacing:0.12em; text-transform:uppercase; border-bottom:1px solid rgba(244,200,24,0.12); background:rgba(244,200,24,0.04);">
                🌆 GTA Roleplay – <?= $translator->translate('settings_bg_image') ?>
            </div>
            <div class="card-body">
                <p style="font-size:13px; color:#A0A098; margin-bottom:1rem;">
                    <?= $translator->translate('settings_bg_hint_gta') ?>
                </p>

                <!-- Preview -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:6px; overflow:hidden; border:2px solid rgba(244,200,24,0.3); position:relative; background:#111118;">
                    <?php if ($gtaBg): ?>
                        <div id="gta-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $gtaBg) ?>'); background-size:<?= $gtaSize ?>%; background-position:<?= $gtaPosX ?>% <?= $gtaPosY ?>%; filter:brightness(<?= $gtaBriFloat ?>) saturate(0.65) blur(<?= $gtaBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="gta-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#404048; font-size:13px;">🌆 <?= $translator->translate('settings_bg_no_image') ?></div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.6);color:#F4C818;font-family:'Barlow Condensed',sans-serif;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;font-size:11px;padding:4px 12px;border-radius:3px;"><?= $translator->translate('settings_bg_preview') ?></span>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <!-- Blur -->
                        <div>
                            <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_blur') ?>: <strong id="gta-blur-val" style="color:#F4C818;"><?= $gtaBlur ?>px</strong></label>
                            <input type="range" name="gta_bg_blur" id="gta-blur-slider" min="0" max="20" step="1" value="<?= $gtaBlur ?>"
                                   style="width:100%; accent-color:#F4C818; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#505058;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <!-- Brightness -->
                        <div>
                            <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_darkness') ?>: <strong id="gta-bri-val" style="color:#F4C818;"><?= $gtaBrightness ?>%</strong></label>
                            <input type="range" name="gta_bg_brightness" id="gta-bri-slider" min="5" max="100" step="5" value="<?= $gtaBrightness ?>"
                                   style="width:100%; accent-color:#F4C818; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#505058;margin-top:2px;"><span>5% (<?= $translator->translate('settings_bg_dark') ?>)</span><span>100%</span></div>
                        </div>
                        <!-- Size -->
                        <div>
                            <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_size') ?>: <strong id="gta-size-val" style="color:#F4C818;"><?= $gtaSize ?>%</strong></label>
                            <input type="range" name="gta_bg_size" id="gta-size-slider" min="100" max="300" step="5" value="<?= $gtaSize ?>"
                                   style="width:100%; accent-color:#F4C818; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#505058;margin-top:2px;"><span>100% (cover)</span><span>300%</span></div>
                        </div>
                        <!-- Position X -->
                        <div>
                            <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_pos_x') ?>: <strong id="gta-posx-val" style="color:#F4C818;"><?= $gtaPosX ?>%</strong></label>
                            <input type="range" name="gta_bg_pos_x" id="gta-posx-slider" min="0" max="100" step="1" value="<?= $gtaPosX ?>"
                                   style="width:100%; accent-color:#F4C818; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#505058;margin-top:2px;"><span>← <?= $translator->translate('settings_bg_left') ?></span><span><?= $translator->translate('settings_bg_right') ?> →</span></div>
                        </div>
                        <!-- Position Y -->
                        <div>
                            <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_pos_y') ?>: <strong id="gta-posy-val" style="color:#F4C818;"><?= $gtaPosY ?>%</strong></label>
                            <input type="range" name="gta_bg_pos_y" id="gta-posy-slider" min="0" max="100" step="1" value="<?= $gtaPosY ?>"
                                   style="width:100%; accent-color:#F4C818; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#505058;margin-top:2px;"><span>↑ <?= $translator->translate('settings_bg_top') ?></span><span><?= $translator->translate('settings_bg_bottom') ?> ↓</span></div>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label class="form-label" style="color:#A0A098;"><?= $translator->translate('settings_bg_upload_new') ?></label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="gta_bg_file" id="gta-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:7px 10px; background:rgba(244,200,24,0.05); border:1px solid rgba(244,200,24,0.3); border-radius:3px; color:#E8E8E0; font-size:13px; cursor:pointer;">
                            <button type="submit" name="update_gta_bg"
                                    style="padding:9px 20px; background:transparent; color:#F4C818; border:1px solid rgba(244,200,24,0.5); border-radius:3px; font-family:'Barlow Condensed',sans-serif; font-weight:800; font-size:12px; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; white-space:nowrap; transition:all .15s;"
                                    onmouseover="this.style.background='rgba(244,200,24,0.12)'" onmouseout="this.style.background='transparent'">
                                📤 Speichern
                            </button>
                        </div>
                        <div style="margin-top:5px; font-size:11px; color:#606058;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>

                    <?php if ($gtaBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#A0A098;">
                                <input type="checkbox" name="gta_bg_remove" value="1" style="width:15px;height:15px; accent-color:#C0201A; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Rotlicht Hintergrundbild -->
        <?php
        $rotlichtBg         = $settings['rotlicht_bg_image']      ?? '';
        $rotlichtBlur       = (int)($settings['rotlicht_bg_blur']        ?? 8);
        $rotlichtBrightness = (int)($settings['rotlicht_bg_brightness']  ?? 28);
        $rotlichtBriFloat   = round($rotlichtBrightness / 100, 2);
        $rotlichtPosX       = (int)($settings['rotlicht_bg_pos_x']       ?? 50);
        $rotlichtPosY       = (int)($settings['rotlicht_bg_pos_y']       ?? 50);
        $rotlichtSize       = (int)($settings['rotlicht_bg_size']        ?? 100);
        ?>
        <div id="rotlicht-bg-box" class="card" style="<?= $currentTheme !== 'rotlicht' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid rgba(232,56,106,0.35); background:linear-gradient(180deg,#200C14,#100406);">
            <div class="card-header" style="color:#FF6090; font-family:'Playfair Display',serif; font-style:italic; letter-spacing:0.08em; border-bottom:1px solid rgba(232,56,106,0.12); background:rgba(232,56,106,0.04);">
                ♥ Rotlicht – Hintergrundbild
            </div>
            <div class="card-body">
                <p style="font-size:13px; color:#B08090; margin-bottom:1rem;">
                    Bild wird als Vollbild-Hintergrund mit Blur, Abdunklung, Rotstich, Größe und einstellbarer Position angezeigt.
                </p>
                <!-- Vorschau -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:6px; overflow:hidden; border:2px solid rgba(232,56,106,0.3); position:relative; background:#100406;">
                    <?php if ($rotlichtBg): ?>
                        <div id="rl-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $rotlichtBg) ?>'); background-size:<?= $rotlichtSize ?>%; background-position:<?= $rotlichtPosX ?>% <?= $rotlichtPosY ?>%; filter:brightness(<?= $rotlichtBriFloat ?>) saturate(0.5) hue-rotate(320deg) blur(<?= $rotlichtBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="rl-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#503040; font-size:13px;">♥ Kein Bild – Datei auswählen für Vorschau</div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.65);color:#FF6090;font-family:'Raleway',sans-serif;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;font-size:11px;padding:4px 12px;border-radius:4px;border:1px solid rgba(232,56,106,0.3);">Vorschau</span>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <!-- 3×2 Slider-Grid -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <div>
                            <label class="form-label" style="color:#B08090;">Unschärfe: <strong id="rl-blur-val" style="color:#FF6090;"><?= $rotlichtBlur ?>px</strong></label>
                            <input type="range" name="rotlicht_bg_blur" id="rl-blur-slider" min="0" max="20" step="1" value="<?= $rotlichtBlur ?>"
                                   style="width:100%; accent-color:#E8386A; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#503040;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#B08090;">Dunkelheit: <strong id="rl-bri-val" style="color:#FF6090;"><?= $rotlichtBrightness ?>%</strong></label>
                            <input type="range" name="rotlicht_bg_brightness" id="rl-bri-slider" min="5" max="100" step="5" value="<?= $rotlichtBrightness ?>"
                                   style="width:100%; accent-color:#E8386A; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#503040;margin-top:2px;"><span>5% (dunkel)</span><span>100%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#B08090;">Größe: <strong id="rl-size-val" style="color:#FF6090;"><?= $rotlichtSize ?>%</strong></label>
                            <input type="range" name="rotlicht_bg_size" id="rl-size-slider" min="100" max="300" step="5" value="<?= $rotlichtSize ?>"
                                   style="width:100%; accent-color:#E8386A; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#503040;margin-top:2px;"><span>100% (cover)</span><span>300%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#B08090;">Position Horizontal: <strong id="rl-posx-val" style="color:#FF6090;"><?= $rotlichtPosX ?>%</strong></label>
                            <input type="range" name="rotlicht_bg_pos_x" id="rl-posx-slider" min="0" max="100" step="1" value="<?= $rotlichtPosX ?>"
                                   style="width:100%; accent-color:#E8386A; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#503040;margin-top:2px;"><span>← Links</span><span>Rechts →</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#B08090;">Position Vertikal: <strong id="rl-posy-val" style="color:#FF6090;"><?= $rotlichtPosY ?>%</strong></label>
                            <input type="range" name="rotlicht_bg_pos_y" id="rl-posy-slider" min="0" max="100" step="1" value="<?= $rotlichtPosY ?>"
                                   style="width:100%; accent-color:#E8386A; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#503040;margin-top:2px;"><span>↑ Oben</span><span>Unten ↓</span></div>
                        </div>
                    </div>
                    <!-- Datei-Upload -->
                    <div class="form-group">
                        <label class="form-label" style="color:#B08090;">Neues Bild hochladen</label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="rotlicht_bg_file" id="rl-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:7px 10px; background:rgba(232,56,106,0.04); border:1px solid rgba(232,56,106,0.3); border-radius:4px; color:#F0D8E0; font-size:13px; cursor:pointer;">
                            <button type="submit" name="update_rotlicht_bg"
                                    style="padding:9px 20px; background:transparent; color:#FF6090; border:1px solid rgba(232,56,106,0.5); border-radius:4px; font-family:'Raleway',sans-serif; font-weight:700; font-size:12px; letter-spacing:0.08em; text-transform:uppercase; cursor:pointer; white-space:nowrap; transition:all .15s;"
                                    onmouseover="this.style.background='rgba(232,56,106,0.10)'" onmouseout="this.style.background='transparent'">
                                ♥ Speichern
                            </button>
                        </div>
                        <div style="margin-top:5px; font-size:11px; color:#704050;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>

                    <?php if ($rotlichtBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#B08090;">
                                <input type="checkbox" name="rotlicht_bg_remove" value="1" style="width:15px;height:15px; accent-color:#C8002A; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- DayZ Hintergrundbild -->
        <?php
        $dayzBg         = $settings['dayz_bg_image']      ?? '';
        $dayzBlur       = (int)($settings['dayz_bg_blur']        ?? 4);
        $dayzBrightness = (int)($settings['dayz_bg_brightness']  ?? 30);
        $dayzBriFloat   = round($dayzBrightness / 100, 2);
        $dayzPosX       = (int)($settings['dayz_bg_pos_x']       ?? 50);
        $dayzPosY       = (int)($settings['dayz_bg_pos_y']       ?? 50);
        $dayzSize       = (int)($settings['dayz_bg_size']        ?? 100);
        ?>
        <div id="dayz-bg-box" class="card" style="<?= $currentTheme !== 'dayz' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid rgba(139,105,20,0.35); background:linear-gradient(180deg,#111410,#0A0C09);">
            <div class="card-header" style="color:#8B6914; font-family:'Oswald',sans-serif; letter-spacing:0.1em; text-transform:uppercase; border-bottom:1px solid rgba(139,105,20,0.15); background:rgba(139,105,20,0.04);">
                🪖 DayZ – Hintergrundbild
            </div>
            <div class="card-body">
                <p style="font-size:13px; color:#8A8070; margin-bottom:1rem;">
                    Bild wird als Vollbild-Hintergrund mit Blur, Abdunklung, Desaturierung, Größe und einstellbarer Position angezeigt.<br>
                    Empfohlen: Wald, verlassene Stadt, Chernarus-Landschaft. Mindestens 1920×1080 px.
                </p>

                <!-- Vorschau -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:4px; overflow:hidden; border:2px solid rgba(139,105,20,0.3); position:relative; background:#0A0C09;">
                    <?php if ($dayzBg): ?>
                        <div id="dayz-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $dayzBg) ?>'); background-size:<?= $dayzSize ?>%; background-position:<?= $dayzPosX ?>% <?= $dayzPosY ?>%; filter:brightness(<?= $dayzBriFloat ?>) saturate(0.3) sepia(0.2) blur(<?= $dayzBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="dayz-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#35322A; font-size:13px;">🪖 Kein Bild – Datei auswählen für Vorschau</div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.7);color:#8B6914;font-family:'Oswald',sans-serif;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;font-size:11px;padding:4px 12px;border-radius:3px;border:1px solid rgba(139,105,20,0.3);">Vorschau</span>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <!-- 3×2 Slider-Grid -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <div>
                            <label class="form-label" style="color:#8A8070;">Unschärfe: <strong id="dayz-blur-val" style="color:#8B6914;"><?= $dayzBlur ?>px</strong></label>
                            <input type="range" name="dayz_bg_blur" id="dayz-blur-slider" min="0" max="20" step="1" value="<?= $dayzBlur ?>"
                                   style="width:100%; accent-color:#8B6914; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#5A5448;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#8A8070;">Dunkelheit: <strong id="dayz-bri-val" style="color:#8B6914;"><?= $dayzBrightness ?>%</strong></label>
                            <input type="range" name="dayz_bg_brightness" id="dayz-bri-slider" min="5" max="100" step="5" value="<?= $dayzBrightness ?>"
                                   style="width:100%; accent-color:#8B6914; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#5A5448;margin-top:2px;"><span>5% (dunkel)</span><span>100%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#8A8070;">Größe: <strong id="dayz-size-val" style="color:#8B6914;"><?= $dayzSize ?>%</strong></label>
                            <input type="range" name="dayz_bg_size" id="dayz-size-slider" min="100" max="300" step="5" value="<?= $dayzSize ?>"
                                   style="width:100%; accent-color:#8B6914; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#5A5448;margin-top:2px;"><span>100%</span><span>300%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#8A8070;">Position Horizontal: <strong id="dayz-posx-val" style="color:#8B6914;"><?= $dayzPosX ?>%</strong></label>
                            <input type="range" name="dayz_bg_pos_x" id="dayz-posx-slider" min="0" max="100" step="1" value="<?= $dayzPosX ?>"
                                   style="width:100%; accent-color:#8B6914; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#5A5448;margin-top:2px;"><span>← Links</span><span>Rechts →</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#8A8070;">Position Vertikal: <strong id="dayz-posy-val" style="color:#8B6914;"><?= $dayzPosY ?>%</strong></label>
                            <input type="range" name="dayz_bg_pos_y" id="dayz-posy-slider" min="0" max="100" step="1" value="<?= $dayzPosY ?>"
                                   style="width:100%; accent-color:#8B6914; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#5A5448;margin-top:2px;"><span>↑ Oben</span><span>Unten ↓</span></div>
                        </div>
                    </div>

                    <!-- Datei-Upload -->
                    <div class="form-group">
                        <label class="form-label" style="color:#8A8070;">Neues Bild hochladen</label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="dayz_bg_file" id="dayz-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:7px 10px; background:rgba(139,105,20,0.05); border:1px solid rgba(139,105,20,0.3); border-radius:3px; color:#C8BEA0; font-size:13px; cursor:pointer;">
                            <button type="submit" name="update_dayz_bg"
                                    style="padding:9px 20px; background:transparent; color:#8B6914; border:1px solid rgba(139,105,20,0.5); border-radius:3px; font-family:'Oswald',sans-serif; font-weight:600; font-size:12px; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; white-space:nowrap; transition:all .15s;"
                                    onmouseover="this.style.background='rgba(139,105,20,0.12)'" onmouseout="this.style.background='transparent'">
                                📤 Speichern
                            </button>
                        </div>
                        <div style="margin-top:5px; font-size:11px; color:#5A5448;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>

                    <?php if ($dayzBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#8A8070;">
                                <input type="checkbox" name="dayz_bg_remove" value="1" style="width:15px;height:15px; accent-color:#C0392B; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Black & Gold Hintergrundbild -->
        <?php
        $bgoldBg         = $settings['blackgold_bg_image']      ?? '';
        $bgoldBlur       = (int)($settings['blackgold_bg_blur']        ?? 5);
        $bgoldBrightness = (int)($settings['blackgold_bg_brightness']  ?? 30);
        $bgoldBriFloat   = round($bgoldBrightness / 100, 2);
        $bgoldPosX       = (int)($settings['blackgold_bg_pos_x']       ?? 50);
        $bgoldPosY       = (int)($settings['blackgold_bg_pos_y']       ?? 50);
        $bgoldSize       = (int)($settings['blackgold_bg_size']        ?? 100);
        ?>
        <div id="blackgold-bg-box" class="card" style="<?= $currentTheme !== 'black-gold' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid rgba(201,168,76,0.35); background:linear-gradient(180deg,#141414,#0A0A0A);">
            <div class="card-header" style="color:#C9A84C; font-family:'Cinzel',serif; letter-spacing:0.12em; border-bottom:1px solid rgba(201,168,76,0.15); background:rgba(201,168,76,0.04);">
                ✦ Black &amp; Gold – Hintergrundbild
            </div>
            <div class="card-body">
                <p style="font-size:13px; color:#705A28; margin-bottom:1rem;">
                    Bild wird als Vollbild-Hintergrund mit Blur, Abdunklung, Goldtönung, Größe und einstellbarer Position angezeigt.<br>
                    Empfohlen: Marmor, Luxus-Architektur, Goldtexturen. Mindestens 1920×1080 px.
                </p>
                <!-- Vorschau -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:4px; overflow:hidden; border:2px solid rgba(201,168,76,0.3); position:relative; background:#0A0A0A;">
                    <?php if ($bgoldBg): ?>
                        <div id="bgold-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $bgoldBg) ?>'); background-size:<?= $bgoldSize ?>%; background-position:<?= $bgoldPosX ?>% <?= $bgoldPosY ?>%; filter:brightness(<?= $bgoldBriFloat ?>) saturate(0.6) sepia(0.3) blur(<?= $bgoldBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="bgold-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#2E2510; font-size:13px;">✦ Kein Bild – Datei auswählen für Vorschau</div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.7);color:#C9A84C;font-family:'Cinzel',serif;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;font-size:11px;padding:4px 12px;border-radius:3px;border:1px solid rgba(201,168,76,0.3);">Vorschau</span>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <!-- 3×2 Slider-Grid -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <div>
                            <label class="form-label" style="color:#705A28;">Unschärfe: <strong id="bgold-blur-val" style="color:#C9A84C;"><?= $bgoldBlur ?>px</strong></label>
                            <input type="range" name="blackgold_bg_blur" id="bgold-blur-slider" min="0" max="20" step="1" value="<?= $bgoldBlur ?>"
                                   style="width:100%; accent-color:#C9A84C; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#3A2E14;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#705A28;">Dunkelheit: <strong id="bgold-bri-val" style="color:#C9A84C;"><?= $bgoldBrightness ?>%</strong></label>
                            <input type="range" name="blackgold_bg_brightness" id="bgold-bri-slider" min="5" max="100" step="5" value="<?= $bgoldBrightness ?>"
                                   style="width:100%; accent-color:#C9A84C; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#3A2E14;margin-top:2px;"><span>5% (dunkel)</span><span>100%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#705A28;">Größe: <strong id="bgold-size-val" style="color:#C9A84C;"><?= $bgoldSize ?>%</strong></label>
                            <input type="range" name="blackgold_bg_size" id="bgold-size-slider" min="100" max="300" step="5" value="<?= $bgoldSize ?>"
                                   style="width:100%; accent-color:#C9A84C; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#3A2E14;margin-top:2px;"><span>100%</span><span>300%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#705A28;">Position Horizontal: <strong id="bgold-posx-val" style="color:#C9A84C;"><?= $bgoldPosX ?>%</strong></label>
                            <input type="range" name="blackgold_bg_pos_x" id="bgold-posx-slider" min="0" max="100" step="1" value="<?= $bgoldPosX ?>"
                                   style="width:100%; accent-color:#C9A84C; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#3A2E14;margin-top:2px;"><span>← Links</span><span>Rechts →</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#705A28;">Position Vertikal: <strong id="bgold-posy-val" style="color:#C9A84C;"><?= $bgoldPosY ?>%</strong></label>
                            <input type="range" name="blackgold_bg_pos_y" id="bgold-posy-slider" min="0" max="100" step="1" value="<?= $bgoldPosY ?>"
                                   style="width:100%; accent-color:#C9A84C; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#3A2E14;margin-top:2px;"><span>↑ Oben</span><span>Unten ↓</span></div>
                        </div>
                    </div>
                    <!-- Datei-Upload -->
                    <div class="form-group">
                        <label class="form-label" style="color:#705A28;">Neues Bild hochladen</label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="blackgold_bg_file" id="bgold-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:7px 10px; background:rgba(201,168,76,0.05); border:1px solid rgba(201,168,76,0.3); border-radius:3px; color:#E8D8A0; font-size:13px; cursor:pointer;">
                            <button type="submit" name="update_blackgold_bg"
                                    style="padding:9px 20px; background:transparent; color:#C9A84C; border:1px solid rgba(201,168,76,0.5); border-radius:3px; font-family:'Cinzel',serif; font-weight:600; font-size:12px; letter-spacing:0.1em; text-transform:uppercase; cursor:pointer; white-space:nowrap; transition:all .15s;"
                                    onmouseover="this.style.background='rgba(201,168,76,0.12)'" onmouseout="this.style.background='transparent'">
                                ✦ Speichern
                            </button>
                        </div>
                        <div style="margin-top:5px; font-size:11px; color:#3A2E14;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>
                    <?php if ($bgoldBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#705A28;">
                                <input type="checkbox" name="blackgold_bg_remove" value="1" style="width:15px;height:15px; accent-color:#C9A84C; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Windows XP Hintergrundbild -->
        <?php
        $winxpBg         = $settings['winxp_bg_image']      ?? '';
        $winxpBlur       = (int)($settings['winxp_bg_blur']        ?? 3);
        $winxpBrightness = (int)($settings['winxp_bg_brightness']  ?? 70);
        $winxpBriFloat   = round($winxpBrightness / 100, 2);
        $winxpPosX       = (int)($settings['winxp_bg_pos_x']       ?? 50);
        $winxpPosY       = (int)($settings['winxp_bg_pos_y']       ?? 50);
        $winxpSize       = (int)($settings['winxp_bg_size']        ?? 100);
        ?>
        <div id="winxp-bg-box" class="card" style="<?= $currentTheme !== 'windows-xp' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid #6090c0; background:linear-gradient(180deg,#dff0ff,#ece9d8);">
            <div class="card-header" style="background:linear-gradient(180deg,#0a246a 0%,#3a6ea5 35%,#1a3e8a 100%); color:#fff; font-family:Tahoma,Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.03em; border-bottom:1px solid #6090c0;">
                🪟 Windows XP – Hintergrundbild (Bliss)
            </div>
            <div class="card-body" style="background:#ece9d8;">
                <p style="font-size:11px; color:#444; margin-bottom:1rem; font-family:Tahoma,Arial,sans-serif;">
                    Bild wird als Desktop-Hintergrund angezeigt (wie das klassische XP Bliss-Bild). Blur, Helligkeit, Größe und Position einstellbar.<br>
                    Tipp: Grüne Hügel, Wolken oder Naturfotos passen perfekt zum XP-Stil.
                </p>
                <!-- Vorschau -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:2px; overflow:hidden; border:2px solid #7090b0; position:relative; background:#3a6ea5;">
                    <?php if ($winxpBg): ?>
                        <div id="winxp-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $winxpBg) ?>'); background-size:<?= $winxpSize ?>%; background-position:<?= $winxpPosX ?>% <?= $winxpPosY ?>%; filter:brightness(<?= $winxpBriFloat ?>) blur(<?= $winxpBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="winxp-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#a0b8d0; font-size:11px; font-family:Tahoma,Arial,sans-serif;">🪟 Kein Bild – Datei auswählen für Vorschau</div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.5);color:#fff;font-family:Tahoma,Arial,sans-serif;font-size:10px;font-weight:700;padding:3px 10px;border:1px solid rgba(255,255,255,0.4);">Vorschau</span>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <!-- 3×2 Slider-Grid -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Unschärfe: <strong id="winxp-blur-val" style="color:#1f5fa6;"><?= $winxpBlur ?>px</strong></label>
                            <input type="range" name="winxp_bg_blur" id="winxp-blur-slider" min="0" max="20" step="1" value="<?= $winxpBlur ?>"
                                   style="width:100%; accent-color:#1f5fa6; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#666;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Helligkeit: <strong id="winxp-bri-val" style="color:#1f5fa6;"><?= $winxpBrightness ?>%</strong></label>
                            <input type="range" name="winxp_bg_brightness" id="winxp-bri-slider" min="5" max="100" step="5" value="<?= $winxpBrightness ?>"
                                   style="width:100%; accent-color:#1f5fa6; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#666;margin-top:2px;"><span>5%</span><span>100%</span></div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Größe: <strong id="winxp-size-val" style="color:#1f5fa6;"><?= $winxpSize ?>%</strong></label>
                            <input type="range" name="winxp_bg_size" id="winxp-size-slider" min="100" max="300" step="5" value="<?= $winxpSize ?>"
                                   style="width:100%; accent-color:#1f5fa6; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#666;margin-top:2px;"><span>100%</span><span>300%</span></div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Position Horizontal: <strong id="winxp-posx-val" style="color:#1f5fa6;"><?= $winxpPosX ?>%</strong></label>
                            <input type="range" name="winxp_bg_pos_x" id="winxp-posx-slider" min="0" max="100" step="1" value="<?= $winxpPosX ?>"
                                   style="width:100%; accent-color:#1f5fa6; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#666;margin-top:2px;"><span>← Links</span><span>Rechts →</span></div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Position Vertikal: <strong id="winxp-posy-val" style="color:#1f5fa6;"><?= $winxpPosY ?>%</strong></label>
                            <input type="range" name="winxp_bg_pos_y" id="winxp-posy-slider" min="0" max="100" step="1" value="<?= $winxpPosY ?>"
                                   style="width:100%; accent-color:#1f5fa6; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#666;margin-top:2px;"><span>↑ Oben</span><span>Unten ↓</span></div>
                        </div>
                    </div>
                    <!-- Datei-Upload -->
                    <div style="margin-bottom:0.8rem;">
                        <label style="display:block; font-size:11px; font-weight:700; color:#000; margin-bottom:3px; font-family:Tahoma,Arial,sans-serif;">Neues Bild hochladen</label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="winxp_bg_file" id="winxp-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:4px 6px; background:#fff; border:2px solid; border-color:#7a7a7a #fff #fff #7a7a7a; font-size:11px; font-family:Tahoma,Arial,sans-serif; cursor:pointer;">
                            <button type="submit" name="update_winxp_bg"
                                    style="padding:4px 14px; background:linear-gradient(180deg,#f0f8ff 0%,#d4ecff 45%,#aad0ff 55%,#c8e4ff 100%); border:1px solid; border-color:#6090c0 #2a5090 #2a5090 #6090c0; font-family:Tahoma,Arial,sans-serif; font-size:11px; cursor:pointer; white-space:nowrap; color:#000; border-radius:3px;"
                                    onmouseover="this.style.background='linear-gradient(180deg,#fff 0%,#ddf0ff 45%,#b8daff 55%,#d8eeff 100%)'" onmouseout="this.style.background='linear-gradient(180deg,#f0f8ff 0%,#d4ecff 45%,#aad0ff 55%,#c8e4ff 100%)'">
                                💾 Speichern
                            </button>
                        </div>
                        <div style="margin-top:4px; font-size:10px; color:#666; font-family:Tahoma,Arial,sans-serif;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>
                    <?php if ($winxpBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:11px; color:#444; font-family:Tahoma,Arial,sans-serif;">
                                <input type="checkbox" name="winxp_bg_remove" value="1" style="width:13px;height:13px; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- YouTube Hintergrundbild -->
        <?php
        $ytBg         = $settings['youtube_bg_image']      ?? '';
        $ytBlur       = (int)($settings['youtube_bg_blur']        ?? 6);
        $ytBrightness = (int)($settings['youtube_bg_brightness']  ?? 28);
        $ytBriFloat   = round($ytBrightness / 100, 2);
        $ytPosX       = (int)($settings['youtube_bg_pos_x']       ?? 50);
        $ytPosY       = (int)($settings['youtube_bg_pos_y']       ?? 50);
        $ytSize       = (int)($settings['youtube_bg_size']        ?? 100);
        ?>
        <div id="youtube-bg-box" class="card" style="<?= $currentTheme !== 'youtube' ? 'display:none;' : '' ?> margin-top:1.2rem; border:2px solid rgba(255,0,0,0.4); background:linear-gradient(180deg,#1A1A1A,#0F0F0F);">
            <div class="card-header" style="background:linear-gradient(135deg,#1A0000,#2A0A0A); color:#FF4444; font-family:'Roboto',sans-serif; font-weight:800; letter-spacing:0.06em; border-bottom:1px solid rgba(255,0,0,0.2); display:flex; align-items:center; gap:0.6rem;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:18px;background:#FF0000;border-radius:4px;position:relative;">
                    <span style="position:absolute;left:50%;top:50%;transform:translate(-38%,-50%);width:0;height:0;border-style:solid;border-width:4px 0 4px 7px;border-color:transparent transparent transparent #fff;"></span>
                </span>
                YouTube – Hintergrundbild
            </div>
            <div class="card-body" style="background:#111111;">
                <p style="font-size:13px; color:#888; margin-bottom:1rem;">
                    Bild wird als Vollbild-Hintergrund mit Unschärfe, Abdunklung, Größe und Position angezeigt.<br>
                    Empfohlen: Studio-Aufnahmen, Bühnen, Creator-Setup. Mindestens 1920×1080 px.
                </p>
                <!-- Vorschau -->
                <div style="margin-bottom:1.2rem; width:100%; height:180px; border-radius:8px; overflow:hidden; border:2px solid rgba(255,0,0,0.3); position:relative; background:#0F0F0F;">
                    <?php if ($ytBg): ?>
                        <div id="yt-preview-img"
                             style="width:100%; height:100%; background-image:url('<?= escape(SITE_URL . '/' . $ytBg) ?>'); background-size:<?= $ytSize ?>%; background-position:<?= $ytPosX ?>% <?= $ytPosY ?>%; filter:brightness(<?= $ytBriFloat ?>) blur(<?= $ytBlur ?>px); transform-origin:center;"></div>
                    <?php else: ?>
                        <div id="yt-preview-img" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#333; font-size:13px; font-family:'Roboto',sans-serif; flex-direction:column; gap:8px;">
                            <span style="font-size:36px;">▶</span>
                            <span>Kein Bild – Datei auswählen für Vorschau</span>
                        </div>
                    <?php endif; ?>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <span style="background:rgba(0,0,0,0.7);color:#FF4444;font-family:'Roboto',sans-serif;font-weight:700;font-size:11px;padding:4px 12px;border-radius:20px;border:1px solid rgba(255,0,0,0.4);">▶ Vorschau</span>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.9rem; margin-bottom:1.1rem;">
                        <div>
                            <label class="form-label" style="color:#888;">Unschärfe: <strong id="yt-blur-val" style="color:#FF4444;"><?= $ytBlur ?>px</strong></label>
                            <input type="range" name="youtube_bg_blur" id="yt-blur-slider" min="0" max="20" step="1" value="<?= $ytBlur ?>"
                                   style="width:100%; accent-color:#FF0000; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#555;margin-top:2px;"><span>0px</span><span>20px</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#888;">Dunkelheit: <strong id="yt-bri-val" style="color:#FF4444;"><?= $ytBrightness ?>%</strong></label>
                            <input type="range" name="youtube_bg_brightness" id="yt-bri-slider" min="5" max="100" step="5" value="<?= $ytBrightness ?>"
                                   style="width:100%; accent-color:#FF0000; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#555;margin-top:2px;"><span>5% (dunkel)</span><span>100%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#888;">Größe: <strong id="yt-size-val" style="color:#FF4444;"><?= $ytSize ?>%</strong></label>
                            <input type="range" name="youtube_bg_size" id="yt-size-slider" min="100" max="300" step="5" value="<?= $ytSize ?>"
                                   style="width:100%; accent-color:#FF0000; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#555;margin-top:2px;"><span>100%</span><span>300%</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#888;">Position Horizontal: <strong id="yt-posx-val" style="color:#FF4444;"><?= $ytPosX ?>%</strong></label>
                            <input type="range" name="youtube_bg_pos_x" id="yt-posx-slider" min="0" max="100" step="1" value="<?= $ytPosX ?>"
                                   style="width:100%; accent-color:#FF0000; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#555;margin-top:2px;"><span>← Links</span><span>Rechts →</span></div>
                        </div>
                        <div>
                            <label class="form-label" style="color:#888;">Position Vertikal: <strong id="yt-posy-val" style="color:#FF4444;"><?= $ytPosY ?>%</strong></label>
                            <input type="range" name="youtube_bg_pos_y" id="yt-posy-slider" min="0" max="100" step="1" value="<?= $ytPosY ?>"
                                   style="width:100%; accent-color:#FF0000; cursor:pointer; height:5px;">
                            <div style="display:flex;justify-content:space-between;font-size:9px;color:#555;margin-top:2px;"><span>↑ Oben</span><span>Unten ↓</span></div>
                        </div>
                    </div>
                    <!-- Datei-Upload -->
                    <div class="form-group">
                        <label class="form-label" style="color:#888;">Neues Bild hochladen</label>
                        <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                            <input type="file" name="youtube_bg_file" id="yt-file-input" accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="flex:1; min-width:0; padding:7px 10px; background:rgba(255,0,0,0.05); border:1px solid rgba(255,0,0,0.25); border-radius:6px; color:#AAA; font-size:13px; cursor:pointer;">
                            <button type="submit" name="update_youtube_bg"
                                    style="padding:9px 20px; background:#FF0000; color:#fff; border:none; border-radius:20px; font-family:'Roboto',sans-serif; font-weight:700; font-size:12px; letter-spacing:0.04em; cursor:pointer; white-space:nowrap; transition:all .15s; box-shadow:0 2px 8px rgba(255,0,0,0.4);"
                                    onmouseover="this.style.background='#CC0000'" onmouseout="this.style.background='#FF0000'">
                                ▶ Speichern
                            </button>
                        </div>
                        <div style="margin-top:5px; font-size:11px; color:#555;">JPG, PNG, WEBP oder GIF · max. 8 MB · Empfehlung: 1920×1080 px</div>
                    </div>
                    <?php if ($ytBg): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#888;">
                                <input type="checkbox" name="youtube_bg_remove" value="1" style="width:15px;height:15px; accent-color:#FF0000; cursor:pointer;">
                                Aktuelles Bild entfernen
                            </label>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div id="tab-site" class="settings-tab-panel">
        <?php
        // Timezone-Liste (häufige Zeitzonen)
        $timezones = [
                'Europa'  => ['Europe/Berlin','Europe/Vienna','Europe/Zurich','Europe/London','Europe/Paris','Europe/Rome','Europe/Madrid','Europe/Amsterdam','Europe/Brussels','Europe/Warsaw','Europe/Budapest','Europe/Prague','Europe/Stockholm','Europe/Oslo','Europe/Copenhagen','Europe/Helsinki','Europe/Lisbon','Europe/Athens','Europe/Bucharest','Europe/Sofia','Europe/Kiev','Europe/Moscow'],
                'Amerika' => ['America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Toronto','America/Vancouver','America/Sao_Paulo','America/Argentina/Buenos_Aires','America/Mexico_City'],
                'Asien'   => ['Asia/Tokyo','Asia/Shanghai','Asia/Singapore','Asia/Kolkata','Asia/Dubai','Asia/Bangkok','Asia/Seoul','Asia/Jakarta','Asia/Karachi'],
                'Andere'  => ['UTC','Pacific/Auckland','Pacific/Sydney','Africa/Cairo','Africa/Johannesburg'],
        ];
        $currentTz  = date_default_timezone_get();
        $currentErr = (bool)ini_get('display_errors');
        ?>
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start;">

                <!-- ── Linke Spalte: Website & Datenbank ── -->
                <div>
                    <!-- Website -->
                    <div class="card" style="margin-bottom:1.25rem;">
                        <div class="card-header">🌐 <?= $translator->translate('settings_site_website_header') ?></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label"><?= $translator->translate('settings_website_name') ?></label>
                                <input type="text" name="site_name" class="form-control"
                                       value="<?= escape(SITE_NAME) ?>" required
                                       placeholder="Mein Support-System">
                                <small style="color:var(--text-light);font-size:.8rem;"><?= $translator->translate('settings_site_website_name_hint') ?></small>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label"><?= $translator->translate('settings_site_website_url_label') ?></label>
                                <input type="url" name="site_url" class="form-control"
                                       value="<?= escape(SITE_URL) ?>"
                                       placeholder="https://support.example.com">
                                <small style="color:var(--text-light);font-size:.8rem;"><?= $translator->translate('settings_site_website_url_hint') ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Datenbank -->
                    <div class="card">
                        <div class="card-header">🗄️ <?= $translator->translate('settings_site_db_header') ?></div>
                        <div class="card-body">
                            <?php $isSuperAdminView = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1); ?>
                            <?php if (!$isSuperAdminView): ?>
                                <div style="background:rgba(239,68,68,.1);border:1.5px solid #ef4444;border-radius:8px;padding:.85rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.65rem;font-size:.88rem;color:#dc2626;">
                                    <span style="font-size:1.2rem;">🔒</span>
                                    <div><?= $translator->translate('settings_site_db_no_access') ?></div>
                                </div>
                            <?php else: ?>
                                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:.65rem .9rem;margin-bottom:1rem;font-size:.82rem;color:#d97706;">
                                    <?= $translator->translate('settings_site_db_warning') ?>
                                </div>
                            <?php endif; ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                                <div class="form-group">
                                    <label class="form-label"><?= $translator->translate('settings_site_db_host') ?></label>
                                    <input type="text" name="db_host" class="form-control"
                                           value="<?= escape(DB_HOST) ?>" placeholder="localhost"
                                            <?= !$isSuperAdminView ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= $translator->translate('settings_site_db_name') ?></label>
                                    <input type="text" name="db_name" class="form-control"
                                           value="<?= escape(DB_NAME) ?>" placeholder="support"
                                            <?= !$isSuperAdminView ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><?= $translator->translate('settings_site_db_user') ?></label>
                                    <input type="text" name="db_user" class="form-control"
                                           value="<?= escape(DB_USER) ?>" placeholder="root"
                                            <?= !$isSuperAdminView ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label"><?= $translator->translate('settings_site_db_pass') ?></label>
                                    <input type="password" name="db_pass" class="form-control"
                                           placeholder="<?= $isSuperAdminView ? $translator->translate('settings_site_db_pass_placeholder') : '––––––––' ?>"
                                           autocomplete="new-password"
                                            <?= !$isSuperAdminView ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                                    <?php if ($isSuperAdminView): ?>
                                        <small style="color:var(--text-light);font-size:.8rem;"><?= $translator->translate('settings_site_db_pass_hint') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Rechte Spalte: System ── -->
                <div>
                    <!-- Timezone -->
                    <div class="card" style="margin-bottom:1.25rem;">
                        <div class="card-header">🕐 <?= $translator->translate('settings_site_timezone_header') ?></div>
                        <div class="card-body">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label"><?= $translator->translate('settings_site_timezone_label') ?></label>
                                <select name="timezone" class="form-control">
                                    <?php foreach ($timezones as $group => $tzList): ?>
                                        <optgroup label="── <?= $group ?> ──">
                                            <?php foreach ($tzList as $tz): ?>
                                                <option value="<?= $tz ?>" <?= $tz === $currentTz ? 'selected' : '' ?>>
                                                    <?= $tz ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:var(--text-light);font-size:.8rem;"><?= $translator->translate('settings_site_timezone_current') ?> <strong><?= $currentTz ?></strong> · <?= $translator->translate('settings_site_timezone_server') ?> <?= date('d.m.Y H:i:s') ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Entwickler -->
                    <div class="card" style="margin-bottom:1.25rem;">
                        <div class="card-header">🛠️ <?= $translator->translate('settings_site_dev_header') ?></div>
                        <div class="card-body">
                            <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;padding:.75rem;background:var(--background);border-radius:8px;border:1px solid var(--border);">
                                <input type="checkbox" name="error_reporting" value="1"
                                        <?= $currentErr ? 'checked' : '' ?>
                                       style="width:18px;height:18px;margin-top:2px;flex-shrink:0;">
                                <div>
                                    <div style="font-weight:600;"><?= $translator->translate('settings_site_dev_display_errors') ?></div>
                                    <div style="font-size:.8rem;color:var(--text-light);margin-top:.2rem;"><?= $translator->translate('settings_site_dev_display_errors_hint') ?></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Config-Datei Status -->
                    <div class="card">
                        <div class="card-header">📄 <?= $translator->translate('settings_site_config_header') ?></div>
                        <div class="card-body">
                            <?php
                            $cfgPathCheck = realpath(__DIR__ . '/../config.php');
                            $writable     = $cfgPathCheck && is_writable($cfgPathCheck);
                            ?>
                            <div style="display:flex;flex-direction:column;gap:.55rem;font-size:.85rem;">
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                <span style="color:<?= $writable ? '#10b981' : '#ef4444' ?>;">
                                    <?= $writable ? '✅' : '❌' ?>
                                </span>
                                    <span><?= $writable ? $translator->translate('settings_site_config_writable') : $translator->translate('settings_site_config_not_writable') ?></span>
                                </div>
                                <div style="color:var(--text-light);font-size:.78rem;">
                                    <?= $translator->translate('settings_site_config_path') ?> <code><?= escape($cfgPathCheck ?: $translator->translate('settings_site_config_not_found')) ?></code>
                                </div>
                                <?php if (!$writable): ?>
                                    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:.5rem .75rem;font-size:.8rem;color:#ef4444;">
                                        <?= $translator->translate('settings_site_config_chmod_hint') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div style="margin-top:1.25rem;display:flex;align-items:center;gap:.75rem;">
                <button type="submit" name="update_site" class="btn btn-primary">
                    <?= $translator->translate('settings_site_save_btn') ?>
                </button>
                <small style="color:var(--text-light);"><?= $translator->translate('settings_site_save_hint') ?></small>
            </div>
        </form>
    </div>

    <!-- Tab: E-Mail -->
    <div id="tab-email" class="settings-tab-panel">
        <div class="card">
            <div class="card-header"><?= $translator->translate('settings_email_notify') ?></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" name="email_notifications" value="1"
                                    <?= ($settings['email_notifications'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <span><?= $translator->translate('settings_email_notifications') ?></span>
                        </label>
                    </div>
                    <hr style="border:none; border-top:1px solid var(--border); margin:1rem 0;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_host') ?></label>
                            <input type="text" name="smtp_host" class="form-control"
                                   value="<?= escape($settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_port') ?></label>
                            <input type="number" name="smtp_port" class="form-control"
                                   value="<?= escape($settings['smtp_port'] ?? '587') ?>" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_user') ?></label>
                            <input type="text" name="smtp_user" class="form-control"
                                   value="<?= escape($settings['smtp_user'] ?? '') ?>" placeholder="user@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_password') ?></label>
                            <input type="password" name="smtp_password" class="form-control"
                                   placeholder="<?= $translator->translate('settings_smtp_pw_placeholder') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_from_email') ?></label>
                            <input type="email" name="smtp_from_email" class="form-control"
                                   value="<?= escape($settings['smtp_from_email'] ?? '') ?>" placeholder="noreply@support.local">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('settings_email_smtp_from_name') ?></label>
                            <input type="text" name="smtp_from_name" class="form-control"
                                   value="<?= escape($settings['smtp_from_name'] ?? '') ?>" placeholder="Support System">
                        </div>
                    </div>
                    <button type="submit" name="update_email" class="btn btn-primary"><?= $translator->translate('settings_email_save') ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab: E-Mail-Vorlagen -->
    <div id="tab-email-tpl" class="settings-tab-panel">
        <style>
            .etpl-layout { display: flex; gap: 1.25rem; align-items: flex-start; }
            .etpl-sidebar-inner { width: 260px; flex-shrink: 0; }
            .etpl-main-inner { flex: 1; min-width: 0; }
            .tpl-nav-card2 { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:1rem; }
            .tpl-nav-hd2 { padding:.6rem .9rem; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); border-bottom:1px solid var(--border); background:var(--background); }
            .tpl-nav-item2 { display:block; padding:.75rem .9rem; text-decoration:none; color:var(--text); border-bottom:1px solid var(--border); font-size:.85rem; cursor:pointer; transition:background .15s; }
            .tpl-nav-item2:last-child { border-bottom:none; }
            .tpl-nav-item2:hover { background:var(--background); }
            .tpl-nav-item2.active { background:var(--primary); color:#fff; }
            .tpl-nav-item2.active .tpl-sub2 { color:rgba(255,255,255,.7); }
            .tpl-title2 { font-weight:700; display:block; margin-bottom:.1rem; display:flex; align-items:center; gap:.35rem; }
            .tpl-sub2 { font-size:.73rem; color:var(--text-light); display:block; }
            .tpl-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }
            .tpl-dot-on { background:#10b981; } .tpl-dot-off { background:#ef4444; }
            .ph-card2 { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; overflow:hidden; }
            .ph-hd2 { padding:.6rem .9rem; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); border-bottom:1px solid var(--border); background:var(--background); }
            .ph-item2 { padding:.4rem .9rem; border-bottom:1px solid var(--border); font-size:.8rem; cursor:pointer; transition:background .15s; }
            .ph-item2:last-child { border-bottom:none; }
            .ph-item2:hover { background:var(--background); }
            .ph-code2 { font-family:monospace; background:var(--background); border:1px solid var(--border); border-radius:4px; padding:.05rem .35rem; font-size:.76rem; color:var(--primary); }
            .ph-desc2 { color:var(--text-light); font-size:.72rem; margin-top:.1rem; }
            .ecard { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; overflow:hidden; }
            .ecard-hd { padding:.9rem 1.1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
            .ecard-title { font-size:1rem; font-weight:700; }
            .ecard-desc { font-size:.8rem; color:var(--text-light); margin-top:.15rem; }
            .etabs { display:flex; border-bottom:1px solid var(--border); background:var(--background); }
            .etab { padding:.55rem 1.1rem; font-size:.83rem; font-weight:600; color:var(--text-light); cursor:pointer; border-bottom:2px solid transparent; transition:color .15s,border-color .15s; user-select:none; }
            .etab:hover { color:var(--text); }
            .etab.active { color:var(--primary); border-bottom-color:var(--primary); }
            .egroup { padding:1rem 1.1rem; border-bottom:1px solid var(--border); }
            .egroup:last-child { border-bottom:none; }
            .elabel { display:block; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-light); margin-bottom:.4rem; }
            .einput { width:100%; box-sizing:border-box; padding:.55rem .8rem; border:1.5px solid var(--border); border-radius:8px; background:var(--background); color:var(--text); font-size:.88rem; font-family:inherit; transition:border-color .2s; }
            .einput:focus { outline:none; border-color:var(--primary); }
            .etextarea { width:100%; box-sizing:border-box; padding:.55rem .8rem; border:1.5px solid var(--border); border-radius:8px; background:var(--background); color:var(--text); font-size:.8rem; font-family:'Consolas','Monaco',monospace; line-height:1.5; resize:vertical; min-height:300px; transition:border-color .2s; }
            .etextarea:focus { outline:none; border-color:var(--primary); }
            .etextarea-prose { font-family:inherit; font-size:.88rem; min-height:200px; }
            .etoolbar { display:flex; flex-wrap:wrap; gap:.25rem; padding:.45rem .8rem; background:var(--background); border-bottom:1px solid var(--border); }
            .etoolbar button { background:var(--surface); border:1px solid var(--border); border-radius:5px; padding:.18rem .45rem; font-size:.76rem; cursor:pointer; color:var(--text); transition:background .15s; }
            .etoolbar button:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
            .etoolbar-sep { width:1px; background:var(--border); margin:0 .2rem; align-self:stretch; }
            .efooter { padding:.85rem 1.1rem; border-top:1px solid var(--border); background:var(--background); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.65rem; }
            .etoggle-wrap { display:flex; align-items:center; gap:.55rem; font-size:.85rem; cursor:pointer; }
            .etoggle { position:relative; width:38px; height:20px; }
            .etoggle input { opacity:0; width:0; height:0; }
            .etoggle-track { position:absolute; inset:0; background:#6b7280; border-radius:20px; transition:.2s; cursor:pointer; }
            .etoggle-track::before { content:''; position:absolute; width:14px; height:14px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
            input:checked + .etoggle-track { background:var(--primary); }
            input:checked + .etoggle-track::before { transform:translateX(18px); }
            #etpl-preview-frame { width:100%; min-height:450px; border:none; background:#fff; }
            .last-upd { font-size:.73rem; color:var(--text-light); }
            @media(max-width:860px){ .etpl-layout{flex-direction:column;} .etpl-sidebar-inner{width:100%;} }
        </style>

        <?php if ($etpl_success): ?><div class="alert alert-success" style="margin-bottom:1rem;">✅ <?= escape($etpl_success) ?></div><?php endif; ?>
        <?php if ($etpl_error):   ?><div class="alert alert-error"   style="margin-bottom:1rem;">❌ <?= escape($etpl_error) ?></div><?php endif; ?>

        <div class="etpl-layout">
            <!-- Sidebar -->
            <div class="etpl-sidebar-inner">
                <div class="tpl-nav-card2">
                    <div class="tpl-nav-hd2">📧 <?= $translator->translate('settings_email_templates_nav') ?></div>
                    <?php foreach ($etpl_all as $t): ?>
                        <div class="tpl-nav-item2 <?= $t['slug'] === $etpl_active_slug ? 'active' : '' ?>"
                             onclick="etplSwitch('<?= escape($t['slug']) ?>', <?= (int)$t['id'] ?>, <?= $t['is_active'] ? 'true' : 'false' ?>)">
                            <span class="tpl-title2">
                                <span class="tpl-dot <?= $t['is_active'] ? 'tpl-dot-on' : 'tpl-dot-off' ?>"></span>
                                <?= escape($t['name']) ?>
                            </span>
                            <span class="tpl-sub2"><?= escape(safe_strimwidth($t['description'], 0, 52, '…')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="ph-card2">
                    <div class="ph-hd2">🔖 <?= $translator->translate('settings_email_placeholders_header') ?></div>
                    <?php foreach ($etpl_placeholders as $ph => $desc): ?>
                        <div class="ph-item2" onclick="etplInsertPh('<?= escape($ph) ?>')">
                            <span class="ph-code2"><?= escape($ph) ?></span>
                            <div class="ph-desc2"><?= escape($desc) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Editor -->
            <div class="etpl-main-inner">
                <?php if ($etpl_current): ?>
                    <form method="post" action="settings.php?tab=email-tpl&tpl=<?= urlencode($etpl_active_slug) ?>" id="etpl-form">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="tpl_id" value="<?= (int)$etpl_current['id'] ?>" id="etpl-hidden-id">
                        <div class="ecard">
                            <div class="ecard-hd">
                                <div>
                                    <div class="ecard-title" id="etpl-card-title"><?= escape($etpl_current['name']) ?></div>
                                    <div class="ecard-desc"  id="etpl-card-desc"><?= escape($etpl_current['description']) ?></div>
                                </div>
                                <?php if ($etpl_current['updated_at']): ?>
                                    <span class="last-upd" id="etpl-last-upd"><?= $translator->translate('settings_tpl_last_changed') ?>: <?= date('d.m.Y H:i', strtotime($etpl_current['updated_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Subject -->
                            <div class="egroup">
                                <label class="elabel" for="etpl-subject"><?= $translator->translate('ticket_create_subject') ?></label>
                                <input class="einput" type="text" id="etpl-subject" name="tpl_subject"
                                       value="<?= escape($etpl_current['subject']) ?>"
                                       placeholder="<?= $translator->translate('settings_tpl_subject_ph') ?>">
                            </div>
                            <!-- Tabs -->
                            <div class="etabs" id="etpl-tabs">
                                <div class="etab active" data-etab="html"    onclick="etplSwitchTab('html')">🖥️ HTML</div>
                                <div class="etab"        data-etab="text"    onclick="etplSwitchTab('text')">📄 Plaintext</div>
                                <div class="etab"        data-etab="preview" onclick="etplSwitchTab('preview')">👁 <?= $translator->translate('settings_tpl_preview') ?></div>
                            </div>
                            <!-- HTML Editor -->
                            <div id="etpl-tab-html">
                                <div class="etoolbar">
                                    <button type="button" onclick="etplWrap('<strong>','</strong>')"><b>B</b></button>
                                    <button type="button" onclick="etplWrap('<em>','</em>')"><i>I</i></button>
                                    <button type="button" onclick="etplWrap('<u>','</u>')"><u>U</u></button>
                                    <div class="etoolbar-sep"></div>
                                    <button type="button" onclick="etplWrap('<h2>','</h2>')">H2</button>
                                    <button type="button" onclick="etplWrap('<p>','</p>')">¶</button>
                                    <button type="button" onclick="etplInsert('<br>')">BR</button>
                                    <button type="button" onclick="etplInsert('<hr style=\'border:none;border-top:1px solid #e5e7eb;margin:16px 0\'>')">HR</button>
                                    <div class="etoolbar-sep"></div>
                                    <button type="button" onclick="etplWrap('<div style=\'background:#f9fafb;border-left:3px solid #3b82f6;padding:10px 14px;margin:8px 0\'>','</div>')">📦 Box</button>
                                    <button type="button" onclick="etplInsert('{{ticket_code}}')">🎫 Code</button>
                                    <button type="button" onclick="etplInsert('{{ticket_url}}')">🔗 URL</button>
                                    <button type="button" onclick="etplInsert('{{customer_name}}')">👤 Kunde</button>
                                    <button type="button" onclick="etplInsert('{{supporter_name}}')">🧑‍💼 Supporter</button>
                                </div>
                                <div class="egroup" style="padding-top:.75rem;">
                                    <textarea class="etextarea" id="etpl-html" name="tpl_body_html" rows="18"><?= escape($etpl_current['body_html']) ?></textarea>
                                </div>
                            </div>
                            <!-- Plaintext Editor -->
                            <div id="etpl-tab-text" style="display:none;">
                                <div class="egroup">
                                    <label class="elabel">Plaintext <small style="font-weight:400;text-transform:none;">– <?= $translator->translate('settings_tpl_plaintext_hint') ?></small></label>
                                    <textarea class="etextarea etextarea-prose" id="etpl-text" name="tpl_body_text" rows="16"><?= escape($etpl_current['body_text']) ?></textarea>
                                </div>
                            </div>
                            <!-- Preview -->
                            <div id="etpl-tab-preview" style="display:none;">
                                <div style="padding:.65rem 1.1rem;background:var(--background);border-bottom:1px solid var(--border);font-size:.8rem;color:var(--text-light);">
                                    ℹ️ <?= $translator->translate('settings_tpl_preview_info') ?>
                                </div>
                                <iframe id="etpl-preview-frame" title="<?= $translator->translate('settings_tpl_preview') ?>" style="width:100%;min-height:450px;border:none;background:#fff;"></iframe>
                            </div>
                            <!-- Footer -->
                            <div class="efooter">
                                <label class="etoggle-wrap">
                                <span class="etoggle">
                                    <input type="checkbox" id="etpl-active" name="tpl_active" <?= $etpl_current['is_active'] ? 'checked' : '' ?>>
                                    <span class="etoggle-track"></span>
                                </span>
                                    <span><?= $translator->translate('settings_tpl_active') ?> <small style="color:var(--text-light);">(<?= $translator->translate('settings_tpl_inactive_hint') ?>)</small></span>
                                </label>
                                <div style="display:flex;gap:.5rem;align-items:center;">
                                    <button type="button" class="btn btn-secondary" style="padding:.35rem .75rem;font-size:.8rem;" onclick="etplRefreshPreview()">👁 <?= $translator->translate('settings_tpl_preview') ?></button>
                                    <button type="submit" class="btn btn-primary">💾 <?= $translator->translate('save') ?></button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Daten aller Templates als JSON für JS -->
                    <script id="etpl-data" type="application/json">
                <?= json_encode(array_map(function($t) {
                            return ['id'=>$t['id'],'slug'=>$t['slug'],'name'=>$t['name'],'description'=>$t['description'],
                                    'subject'=>$t['subject'],'body_html'=>$t['body_html'],'body_text'=>$t['body_text'],
                                    'is_active'=>(bool)$t['is_active'],'updated_at'=>$t['updated_at']];
                        }, $etpl_all), JSON_UNESCAPED_UNICODE) ?>
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Global Templates -->
    <div id="tab-templates" class="settings-tab-panel">
        <div class="card">
            <div class="card-header">📄 <?= $translator->translate('settings_gtpl_header') ?></div>
            <div class="card-body" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                <div>
                    <p style="margin:0 0 0.25rem 0; font-weight:600;"><?= $translator->translate('settings_gtpl_manage') ?></p>
                    <p style="margin:0; font-size:0.875rem; color:var(--text-light);">
                        <?= $translator->translate('settings_gtpl_hint') ?>
                    </p>
                </div>
                <a href="<?= SITE_URL ?>/admin/global-templates.php" class="btn btn-primary" style="white-space:nowrap;">
                    Vorlagen verwalten →
                </a>
            </div>
        </div>
    </div>

    <!-- Tab: Team-Chat -->
    <div id="tab-chat" class="settings-tab-panel">

        <!-- Chat ein-/ausschalten + Einstellungen -->
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">💬 Team-Chat Einstellungen</div>
            <div class="card-body">
                <form id="chatSettingsForm">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">

                        <!-- Chat enable -->
                        <div style="grid-column:1/-1;">
                            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer; padding:0.75rem; background:var(--background); border-radius:8px; border:1px solid var(--border);">
                                <input type="checkbox" id="chat_enabled" style="width:18px;height:18px;">
                                <div>
                                    <div style="font-weight:600;"><?= $translator->translate('settings_chat_enable') ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-light);"><?= $translator->translate('settings_chat_enable_hint') ?></div>
                                </div>
                            </label>
                        </div>

                        <!-- Max length -->
                        <div class="form-group" style="margin:0;">
                            <label class="form-label"><?= $translator->translate('settings_chat_max_length') ?></label>
                            <input type="number" id="chat_max_length" class="form-control" min="50" max="5000" step="50">
                            <small style="color:var(--text-light);">50–5000 <?= $translator->translate('settings_chat_chars') ?></small>
                        </div>

                        <!-- Emojis/GIFs -->
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer; padding:0.75rem; background:var(--background); border-radius:8px; border:1px solid var(--border);">
                                <input type="checkbox" id="chat_emojis" style="width:18px;height:18px;">
                                <div>
                                    <div style="font-weight:600;">😊 <?= $translator->translate('settings_chat_allow_emojis') ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-light);"><?= $translator->translate('settings_chat_allow_emojis_hint') ?></div>
                                </div>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer; padding:0.75rem; background:var(--background); border-radius:8px; border:1px solid var(--border);">
                                <input type="checkbox" id="chat_gifs" style="width:18px;height:18px;">
                                <div>
                                    <div style="font-weight:600;">🎞️ <?= $translator->translate('settings_chat_allow_gifs') ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-light);"><?= $translator->translate('settings_chat_allow_gifs_hint') ?></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <button type="button" onclick="saveChatSettings()" class="btn btn-primary"><?= $translator->translate('settings_save') ?></button>
                        <span id="chat-settings-msg" style="font-size:0.85rem; color:var(--success); display:none;">✓ <?= $translator->translate('saved') ?></span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Broadcast message -->
        <div class="card">
            <div class="card-header">📢 <?= $translator->translate('settings_broadcast_header') ?></div>
            <div class="card-body">
                <p style="font-size:0.875rem; color:var(--text-light); margin-bottom:1rem;">
                    <?= $translator->translate('settings_broadcast_hint') ?>
                </p>
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('settings_broadcast_label') ?></label>
                    <textarea id="broadcast_msg" class="form-control" rows="3"
                              placeholder="⚠️ <?= $translator->translate('settings_broadcast_ph') ?>"></textarea>
                </div>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <button type="button" onclick="sendBroadcast()" class="btn btn-primary">📢 <?= $translator->translate('settings_broadcast_send') ?></button>
                    <span id="broadcast-msg" style="font-size:0.85rem; color:var(--success); display:none;">✓ <?= $translator->translate('settings_broadcast_sent') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Discord Integration -->
    <div id="tab-discord" class="settings-tab-panel">

        <!-- Header -->
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; padding:1.25rem 1.5rem; background:linear-gradient(135deg,#5865F2 0%,#4752C4 100%); border-radius:12px; color:#fff;">
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="white"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.031.053a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
            </div>
            <div>
                <div style="font-size:1.25rem;font-weight:800;letter-spacing:-0.01em;">Discord Integration</div>
                <div style="font-size:0.85rem;opacity:0.85;margin-top:2px;"><?= $translator->translate('discord_header_subtitle') ?></div>
            </div>
            <div style="margin-left:auto;">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;background:rgba(255,255,255,0.15);border-radius:8px;padding:0.5rem 0.9rem;font-weight:700;font-size:0.9rem;">
                    <input type="checkbox" id="discord_enabled_toggle" style="width:18px;height:18px;accent-color:#fff;cursor:pointer;">
                    <?= $translator->translate('discord_enabled_label') ?>
                </label>
            </div>
        </div>

        <!-- Status-Meldung -->
        <div id="discord-save-msg" style="display:none;" class="alert alert-success"><?= $translator->translate('discord_save_success') ?></div>
        <div id="discord-error-msg" style="display:none;" class="alert alert-danger"></div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start;">

            <!-- ── Linke Spalte ── -->
            <div>

                <!-- Webhook-Konfiguration -->
                <div class="card" style="margin-bottom:1.25rem;">
                    <div class="card-header"><?= $translator->translate('discord_webhook_card') ?></div>
                    <div class="card-body">
                        <div style="background:rgba(88,101,242,0.08);border:1px solid rgba(88,101,242,0.25);border-radius:8px;padding:0.85rem 1rem;margin-bottom:1rem;font-size:0.82rem;color:var(--text-secondary);line-height:1.55;">
                            <?= $translator->translate('discord_webhook_hint') ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('discord_webhook_url_label') ?> <span style="color:var(--danger);">*</span></label>
                            <div style="display:flex;gap:0.5rem;">
                                <input type="url" id="discord_webhook_url" class="form-control"
                                       placeholder="https://discord.com/api/webhooks/..." style="flex:1;">
                                <button type="button" onclick="discordTestWebhook()" class="btn btn-secondary" style="white-space:nowrap;flex-shrink:0;">
                                    <?= $translator->translate('discord_webhook_test_btn') ?>
                                </button>
                            </div>
                            <div id="discord-test-result" style="margin-top:0.4rem;font-size:0.82rem;display:none;"></div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label"><?= $translator->translate('discord_bot_name_label') ?></label>
                                <input type="text" id="discord_bot_name" class="form-control" placeholder="SupportBot" maxlength="80">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label"><?= $translator->translate('discord_mention_role_label') ?></label>
                                <input type="text" id="discord_mention_role_id" class="form-control" placeholder="123456789012345678" maxlength="25">
                                <small style="color:var(--text-light);"><?= $translator->translate('discord_mention_role_hint') ?></small>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:0.85rem;">
                            <label class="form-label"><?= $translator->translate('discord_avatar_url_label') ?></label>
                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                <input type="url" id="discord_bot_avatar_url" class="form-control" placeholder="https://example.com/avatar.png">
                                <img id="discord-avatar-preview" src="" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border);display:none;flex-shrink:0;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Benachrichtigungs-Trigger -->
                <div class="card" style="margin-bottom:1.25rem;">
                    <div class="card-header"><?= $translator->translate('discord_triggers_card') ?></div>
                    <div class="card-body">
                        <p style="font-size:0.82rem;color:var(--text-light);margin-bottom:0.85rem;"><?= $translator->translate('discord_triggers_hint') ?></p>
                        <?php
                        $triggers = [
                                'discord_notify_new_ticket'    => ['label' => $translator->translate('discord_trigger_new_ticket_label'), 'desc' => $translator->translate('discord_trigger_new_ticket_desc')],
                                'discord_notify_new_reply'     => ['label' => $translator->translate('discord_trigger_new_reply_label'),  'desc' => $translator->translate('discord_trigger_new_reply_desc')],
                                'discord_notify_status_change' => ['label' => $translator->translate('discord_trigger_status_label'),     'desc' => $translator->translate('discord_trigger_status_desc')],
                                'discord_notify_closed'        => ['label' => $translator->translate('discord_trigger_closed_label'),     'desc' => $translator->translate('discord_trigger_closed_desc')],
                        ];
                        foreach ($triggers as $key => $t):
                            ?>
                            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;padding:0.65rem 0.75rem;background:var(--background);border-radius:8px;border:1px solid var(--border);margin-bottom:0.5rem;transition:border-color .15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                                <input type="checkbox" id="<?= $key ?>" style="width:17px;height:17px;margin-top:2px;flex-shrink:0;accent-color:var(--primary);cursor:pointer;">
                                <div>
                                    <div style="font-weight:600;font-size:0.88rem;"><?= $t['label'] ?></div>
                                    <div style="font-size:0.78rem;color:var(--text-light);margin-top:2px;"><?= $t['desc'] ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Benutzerdefinierte Felder -->
                <div class="card">
                    <div class="card-header"><?= $translator->translate('discord_custom_keys_card') ?></div>
                    <div class="card-body">
                        <p style="font-size:0.82rem;color:var(--text-light);margin-bottom:0.85rem;">
                            <?= $translator->translate('discord_custom_keys_hint') ?>
                        </p>
                        <div style="font-size:0.78rem;color:var(--text-secondary);background:var(--background);border-radius:6px;padding:0.6rem 0.8rem;margin-bottom:0.85rem;border:1px solid var(--border);">
                            <strong><?= $translator->translate('discord_placeholders_label') ?></strong><br>
                            <code style="color:var(--primary);">{{ticket_code}}</code> · <code style="color:var(--primary);">{{subject}}</code> · <code style="color:var(--primary);">{{username}}</code> · <code style="color:var(--primary);">{{priority}}</code> · <code style="color:var(--primary);">{{category}}</code> · <code style="color:var(--primary);">{{status}}</code> · <code style="color:var(--primary);">{{date}}</code> · <code style="color:var(--primary);">{{site_name}}</code>
                        </div>
                        <div id="discord-custom-keys-list" style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:0.75rem;"></div>
                        <button type="button" onclick="discordAddCustomKey()" class="btn btn-secondary btn-sm">
                            <?= $translator->translate('discord_add_field_btn') ?>
                        </button>
                    </div>
                </div>

            </div>

            <!-- ── Rechte Spalte ── -->
            <div>

                <!-- Embed-Aussehen -->
                <div class="card" style="margin-bottom:1.25rem;">
                    <div class="card-header"><?= $translator->translate('discord_embed_card') ?></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('discord_embed_title_label') ?></label>
                            <input type="text" id="discord_embed_title" class="form-control" placeholder="<?= $translator->translate('discord_embed_title_ph') ?>" maxlength="256">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('discord_embed_desc_label') ?></label>
                            <textarea id="discord_embed_description" class="form-control" rows="2" placeholder="<?= $translator->translate('discord_embed_desc_ph') ?>" maxlength="4096" style="resize:vertical;"></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label"><?= $translator->translate('discord_embed_color_label') ?></label>
                                <div style="display:flex;gap:0.5rem;align-items:center;">
                                    <input type="color" id="discord_embed_color_picker" value="#5865F2"
                                           style="width:44px;height:38px;border:none;background:none;cursor:pointer;padding:0;border-radius:6px;border:2px solid var(--border);"
                                           oninput="document.getElementById('discord_embed_color_hex').value=this.value;discordUpdateColorPreview();">
                                    <input type="text" id="discord_embed_color_hex" class="form-control" value="#5865F2" maxlength="7" placeholder="#5865F2"
                                           style="flex:1;" oninput="discordSyncColorFromHex();">
                                    <input type="hidden" id="discord_embed_color">
                                </div>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label"><?= $translator->translate('discord_footer_text_label') ?></label>
                                <input type="text" id="discord_footer_text" class="form-control" placeholder="<?= $translator->translate('discord_footer_text_ph') ?>" maxlength="2048">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Angezeigte Felder -->
                <div class="card" style="margin-bottom:1.25rem;">
                    <div class="card-header"><?= $translator->translate('discord_show_fields_card') ?></div>
                    <div class="card-body">
                        <p style="font-size:0.82rem;color:var(--text-light);margin-bottom:0.85rem;"><?= $translator->translate('discord_show_fields_hint') ?></p>
                        <?php
                        $showFields = [
                                'discord_show_subject'     => $translator->translate('discord_show_subject'),
                                'discord_show_description' => $translator->translate('discord_show_description'),
                                'discord_show_priority'    => $translator->translate('discord_show_priority'),
                                'discord_show_category'    => $translator->translate('discord_show_category'),
                                'discord_show_username'    => $translator->translate('discord_show_username'),
                                'discord_show_ticket_url'  => $translator->translate('discord_show_ticket_url'),
                        ];
                        foreach ($showFields as $key => $label):
                            ?>
                            <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;padding:0.45rem 0;border-bottom:1px solid var(--separator);">
                                <input type="checkbox" id="<?= $key ?>" style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;">
                                <span style="font-size:0.88rem;"><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live-Vorschau -->
                <div class="card">
                    <div class="card-header"><?= $translator->translate('discord_preview_card') ?></div>
                    <div class="card-body" style="background:#313338;border-radius:8px;">
                        <div style="font-size:0.78rem;color:#949CF7;font-weight:700;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.4rem;">
                            <img id="discord-preview-avatar" src="" alt="" style="width:22px;height:22px;border-radius:50%;background:#5865F2;display:none;">
                            <span id="discord-preview-botname">SupportBot</span>
                            <span style="font-size:0.68rem;background:#5865F2;color:#fff;padding:1px 4px;border-radius:3px;font-weight:700;">BOT</span>
                        </div>
                        <div id="discord-embed-preview" style="border-left:4px solid #5865F2;background:#2B2D31;border-radius:0 6px 6px 0;padding:0.75rem 0.85rem;">
                            <div id="discord-preview-title" style="font-weight:700;color:#fff;font-size:0.95rem;margin-bottom:4px;"><?= $translator->translate('discord_preview_title_default') ?></div>
                            <div id="discord-preview-desc" style="font-size:0.82rem;color:#DBDEE1;margin-bottom:8px;"><?= $translator->translate('discord_preview_desc_default') ?></div>
                            <div id="discord-preview-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;margin-bottom:6px;">
                                <div style="background:rgba(255,255,255,0.04);border-radius:4px;padding:4px 6px;">
                                    <div style="font-size:0.72rem;color:#B5BAC1;font-weight:700;"><?= $translator->translate('discord_preview_subject_label') ?></div>
                                    <div style="font-size:0.82rem;color:#DBDEE1;"><?= $translator->translate('discord_preview_subject_value') ?></div>
                                </div>
                                <div style="background:rgba(255,255,255,0.04);border-radius:4px;padding:4px 6px;">
                                    <div style="font-size:0.72rem;color:#B5BAC1;font-weight:700;"><?= $translator->translate('discord_preview_prio_label') ?></div>
                                    <div style="font-size:0.82rem;color:#DBDEE1;"><?= $translator->translate('discord_preview_prio_value') ?></div>
                                </div>
                            </div>
                            <div id="discord-preview-footer" style="font-size:0.72rem;color:#B5BAC1;border-top:1px solid rgba(255,255,255,0.08);padding-top:5px;margin-top:4px;"><?= SITE_NAME ?> Support-System</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Speichern-Button -->
        <div style="display:flex;align-items:center;gap:1rem;margin-top:1.5rem;padding:1.25rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;">
            <button type="button" onclick="discordSaveSettings()" class="btn btn-primary" style="min-width:160px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $translator->translate('discord_save_btn') ?>
            </button>
            <button type="button" onclick="discordTestWebhook()" class="btn btn-secondary">
                <?= $translator->translate('discord_test_btn') ?>
            </button>
            <span style="font-size:0.82rem;color:var(--text-light);"><?= $translator->translate('discord_save_hint') ?></span>
        </div>

    </div><!-- /tab-discord -->

    <!-- Tab: Custom CSS -->
    <div id="tab-custom-css" class="settings-tab-panel">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
                🖌️ Custom CSS
                <span style="font-size:0.78rem;font-weight:400;color:var(--text-light);margin-left:auto;">Eigenes CSS wird auf allen Seiten des Systems geladen.</span>
            </div>
            <div class="card-body">
                <form method="POST" id="custom-css-form">
                    <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;">
                        <?= $translator->translate('settings_css_description') ?>
                    </p>

                    <!-- Toolbar -->
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;flex-wrap:wrap;">
                        <span style="font-size:0.8rem;color:var(--text-light);font-weight:600;"><?= $translator->translate('settings_css_quickinsert') ?></span>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccInsert(':root {\n    --primary: #1e40af;\n    --background: #f8fafc;\n}')">:root Variablen</button>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccInsert('.navbar {\n    \n}')">Navbar</button>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccInsert('.card {\n    \n}')">Card</button>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccInsert('.btn {\n    \n}')">Button</button>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccInsert('body {\n    \n}')">Body</button>
                        <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;color:var(--primary);font-weight:700;" onclick="ccLoadTheme()" title="Aktuelles Theme-CSS als Ausgangsbasis in den Editor laden">📂 <?= $translator->translate('settings_css_load_theme_btn') ?> (<?= htmlspecialchars($currentThemeName) ?>)</button>
                        <span style="margin-left:auto;display:flex;gap:0.4rem;">
                            <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="ccFormatCss()" title="CSS formatieren"><?= $translator->translate('settings_css_format_btn') ?></button>
                            <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" id="cc-preview-btn" onclick="ccTogglePreview()" title="Live-Vorschau ein/ausschalten"><?= $translator->translate('settings_css_preview_btn') ?></button>
                            <button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;color:var(--danger);" onclick="ccClearAll()" title="Alles löschen"><?= $translator->translate('settings_css_clear_btn') ?></button>
                        </span>
                    </div>

                    <!-- Editor -->
                    <div style="position:relative;">
                        <textarea
                                id="custom-css-editor"
                                name="custom_css"
                                rows="24"
                                spellcheck="false"
                                autocomplete="off"
                                style="font-family:'Fira Code','Cascadia Code','Consolas',monospace;font-size:0.85rem;line-height:1.6;width:100%;padding:1rem;background:var(--surface);border:1px solid var(--border);border-radius:8px;resize:vertical;color:var(--text);tab-size:4;"
                                placeholder="/* Dein eigenes CSS hier… */&#10;&#10;:root {&#10;    /* CSS-Variablen überschreiben */&#10;}&#10;&#10;.navbar {&#10;    /* Navbar anpassen */&#10;}"
                        ><?= htmlspecialchars($settings['custom_css'] ?? '') ?></textarea>

                        <!-- Zeilen-Zähler -->
                        <div id="cc-line-count" style="position:absolute;bottom:0.5rem;right:0.75rem;font-size:0.72rem;color:var(--text-light);pointer-events:none;background:var(--surface);padding:1px 6px;border-radius:4px;border:1px solid var(--border);">0 Zeilen · 0 Zeichen</div>
                    </div>

                    <!-- Validierungs-Hinweis -->
                    <div id="cc-validation" style="margin-top:0.5rem;font-size:0.82rem;min-height:1.2rem;"></div>

                    <!-- Info-Box -->
                    <div style="margin-top:1rem;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:0.85rem 1rem;font-size:0.82rem;color:var(--text-light);">
                        <strong style="color:var(--text);"><?= $translator->translate('settings_css_tips_title') ?></strong>
                        <ul style="margin:0.4rem 0 0 1.2rem;padding:0;line-height:1.8;">
                            <li><?= $translator->translate('settings_css_tip1') ?></li>
                            <li><?= $translator->translate('settings_css_tip2') ?></li>
                            <li><?= $translator->translate('settings_css_tip3') ?></li>
                            <li><?= $translator->translate('settings_css_tip4') ?></li>
                            <li><?= $translator->translate('settings_css_tip5') ?></li>
                        </ul>
                    </div>

                    <div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <!-- Aktiviert-Toggle -->
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.9rem;font-weight:600;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:0.55rem 0.9rem;">
                            <input type="checkbox" name="custom_css_enabled" id="cc-enabled-toggle"
                                    <?= ($settings['custom_css_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                                   style="width:1.1rem;height:1.1rem;accent-color:var(--primary);cursor:pointer;">
                            <?= $translator->translate('settings_css_enable_toggle') ?>
                        </label>
                        <button type="submit" name="save_custom_css" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <?= $translator->translate('settings_css_save_btn') ?>
                        </button>
                        <?php if (($settings['custom_css_enabled'] ?? '0') !== '1'): ?>
                            <span style="font-size:0.82rem;color:var(--danger);display:flex;align-items:center;gap:0.3rem;">
                            <?= $translator->translate('settings_css_status_inactive') ?>
                            <?php if (!empty($settings['custom_css'])): ?>
                                <?= $translator->translate('settings_css_status_inactive_has_css') ?>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                            <span style="font-size:0.82rem;color:#16a34a;"><?= $translator->translate('settings_css_status_active') ?></span>
                        <?php endif; ?>
                        <span style="font-size:0.78rem;color:var(--text-light);margin-left:auto;">
                            <?= $translator->translate('settings_css_theme_switch_hint') ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /tab-custom-css -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         Tab: Sprachen
    ════════════════════════════════════════════════════════════════════════ -->
    <div id="tab-language" class="settings-tab-panel">

        <!-- Header -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1.25rem 1.5rem;
                    background:linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%);border-radius:12px;color:#fff;">
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:12px;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.8rem;">🌍</div>
            <div>
                <div style="font-size:1.25rem;font-weight:800;letter-spacing:-0.01em;"><?= $translator->translate('lang_page_title') ?></div>
                <div style="font-size:0.85rem;opacity:0.85;margin-top:2px;"><?= $translator->translate('lang_page_subtitle') ?></div>
            </div>
        </div>

        <?php
        // Alle Sprachen aus DB laden (mit Status)
        $langDir   = __DIR__ . '/../assets/lang/';
        $langFiles = array_filter(glob($langDir . '*.php') ?: [], fn($f) => basename($f) !== 'translator.php');
        $dbLangs   = [];
        try {
            $rows = $db->query("SELECT * FROM language_settings ORDER BY sort_order, lang_code")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $dbLangs[$r['lang_code']] = $r;
        } catch (\Throwable $e) {}

        // Bekannte Built-in Flags/Labels als Fallback
        $builtinFlagMap  = [
                'DE-de'   => '🇩🇪', 'EN-en'   => '🇬🇧', 'FR-fr'  => '🇫🇷',
                'ES-es'   => '🇪🇸', 'CH-ch'   => '🇨🇭', 'NDS-nds'=> '🌊',
        ];
        $builtinLabelMap = [
                'DE-de'   => 'Deutsch',         'EN-en'   => 'English',
                'FR-fr'   => 'Français',        'ES-es'   => 'Español',
                'CH-ch'   => 'Schweizerdeutsch','NDS-nds' => 'Plattdüütsch',
        ];
        ?>

        <!-- ── Sprachen aktivieren/deaktivieren ─────────────────────────────── -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <span>🌐 <?= $translator->translate('lang_toggle_card_title') ?></span>
                <span style="font-size:0.8rem;color:var(--text-light);"><?= count($langFiles) ?> <?= $translator->translate('lang_files_found') ?></span>
            </div>
            <div class="card-body" style="padding:0;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                    <tr style="border-bottom:2px solid var(--border);background:var(--background);">
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;width:48px;"><?= $translator->translate('lang_col_active') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_col_flag') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_available_col_code') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_col_label') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_available_col_keys') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_available_col_modified') ?></th>
                        <th style="padding:0.7rem 1rem;text-align:left;font-weight:700;"><?= $translator->translate('lang_available_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($langFiles as $lf):
                        $code    = basename($lf, '.php');
                        $arr     = @include $lf;
                        $cnt     = is_array($arr) ? count($arr) : 0;
                        $mtime   = date('d.m.Y H:i', filemtime($lf));
                        $dbRow   = $dbLangs[$code] ?? ['is_active'=>0,'label'=>'','flag'=>'','sort_order'=>99,'is_builtin'=>0];
                        $active  = (bool)($dbRow['is_active'] ?? 0);
                        $isBuilt = (bool)($dbRow['is_builtin'] ?? 0);
                        // Meta aus Sprachdatei lesen (_meta-Block)
                        $fileMeta = is_array($arr) ? ($arr['_meta'] ?? []) : [];
                        // Label: DB > Sprachdatei > Fallback
                        $label = (!empty($dbRow['label']) && $dbRow['label'] !== $code)
                                ? $dbRow['label']
                                : (!empty($fileMeta['label']) ? $fileMeta['label'] : ($builtinLabelMap[$code] ?? $code));
                        // Flag-Emoji: DB > Sprachdatei > Fallback
                        $flag  = (!empty($dbRow['flag']) && $dbRow['flag'] !== '🌐')
                                ? $dbRow['flag']
                                : (!empty($fileMeta['flag_emoji']) ? $fileMeta['flag_emoji'] : ($builtinFlagMap[$code] ?? '🌐'));
                        // Flag-Bild: aus Sprachdatei
                        $flagImage = $fileMeta['flag_image'] ?? '';
                        $rowBg   = $active ? '' : 'background:var(--background);opacity:0.65;';
                        $isFallback = ($code === 'EN-en');
                        ?>
                        <tr style="border-bottom:1px solid var(--border);<?= $rowBg ?>transition:opacity .2s;">
                            <!-- Toggle -->
                            <td style="padding:0.6rem 1rem;">
                                <?php if (!$isFallback): ?>
                                    <form method="POST" action="settings.php#language" style="margin:0;">
                                        <input type="hidden" name="lang_code_toggle" value="<?= htmlspecialchars($code) ?>">
                                        <input type="hidden" name="lang_active" value="<?= $active ? 0 : 1 ?>">
                                        <button type="submit" name="lang_toggle"
                                                title="<?= $active ? $translator->translate('lang_btn_deactivate') : $translator->translate('lang_btn_activate') ?>"
                                                style="background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;">
                                            <svg width="38" height="22" viewBox="0 0 38 22">
                                                <rect x="0" y="1" width="38" height="20" rx="10"
                                                      fill="<?= $active ? 'var(--primary)' : '#ccc' ?>"/>
                                                <circle cx="<?= $active ? 27 : 11 ?>" cy="11" r="8"
                                                        fill="#fff" filter="drop-shadow(0 1px 2px rgba(0,0,0,.25))"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <svg width="38" height="22" viewBox="0 0 38 22" title="<?= $translator->translate('lang_fallback_tooltip') ?>">
                                        <rect x="0" y="1" width="38" height="20" rx="10" fill="var(--primary)"/>
                                        <circle cx="27" cy="11" r="8" fill="#fff" filter="drop-shadow(0 1px 2px rgba(0,0,0,.25))"/>
                                    </svg>
                                <?php endif; ?>
                            </td>
                            <!-- Flag -->
                            <td style="padding:0.6rem 1rem;">
                                <?php if (!empty($flagImage)): ?>
                                    <img src="<?= htmlspecialchars($flagImage) ?>"
                                         alt="<?= htmlspecialchars($flag) ?>"
                                         width="32" height="32"
                                         style="border-radius:50%;object-fit:cover;box-shadow:0 1px 4px rgba(0,0,0,.25);vertical-align:middle;">
                                <?php else: ?>
                                    <span style="font-size:1.6rem;line-height:1;"><?= htmlspecialchars($flag) ?></span>
                                <?php endif; ?>
                            </td>
                            <!-- Code -->
                            <td style="padding:0.6rem 1rem;">
                                <strong><?= htmlspecialchars($code) ?></strong>
                                <?php if ($isBuilt): ?>
                                    <span style="font-size:0.68rem;background:var(--primary);color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px;vertical-align:middle;">built-in</span>
                                <?php endif; ?>
                                <?php if ($isFallback): ?>
                                    <span style="font-size:0.68rem;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px;vertical-align:middle;"><?= $translator->translate('lang_fallback_badge') ?></span>
                                <?php endif; ?>
                            </td>
                            <!-- Label -->
                            <td style="padding:0.6rem 1rem;color:var(--text-light);"><?= htmlspecialchars($label) ?></td>
                            <!-- Keys -->
                            <td style="padding:0.6rem 1rem;"><span style="font-weight:600;"><?= $cnt ?></span></td>
                            <!-- Geändert -->
                            <td style="padding:0.6rem 1rem;font-size:0.8rem;color:var(--text-light);"><?= $mtime ?></td>
                            <!-- Aktionen -->
                            <td style="padding:0.6rem 1rem;">
                                <div style="display:flex;gap:0.35rem;flex-wrap:wrap;align-items:center;">
                                    <a href="?lang_export=<?= urlencode($code) ?>&format=php"
                                       class="btn btn-secondary btn-sm" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        ⬇ PHP
                                    </a>
                                    <a href="?lang_export=<?= urlencode($code) ?>&format=json"
                                       class="btn btn-secondary btn-sm" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        ⬇ JSON
                                    </a>
                                    <button type="button"
                                            onclick="openLangMeta('<?= htmlspecialchars($code) ?>','<?= htmlspecialchars(addslashes($label)) ?>','<?= htmlspecialchars(addslashes($flag)) ?>','<?= (int)($dbRow['sort_order'] ?? 99) ?>','<?= htmlspecialchars(addslashes($flagImage)) ?>')"
                                            class="btn btn-secondary btn-sm" style="font-size:0.75rem;padding:0.2rem 0.55rem;">
                                        ✏️ <?= $translator->translate('lang_btn_meta') ?>
                                    </button>
                                    <?php if (!$isBuilt): ?>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('<?= addslashes($translator->translate('lang_delete_confirm')) ?>')">
                                            <input type="hidden" name="lang_code_delete" value="<?= htmlspecialchars($code) ?>">
                                            <button type="submit" name="lang_delete"
                                                    class="btn btn-secondary btn-sm"
                                                    style="font-size:0.75rem;padding:0.2rem 0.55rem;color:var(--danger);">
                                                🗑
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="padding:0.75rem 1rem;background:var(--background);border-top:1px solid var(--border);font-size:0.8rem;color:var(--text-light);">
                    💡 <?= $translator->translate('lang_toggle_hint') ?>
                </div>
            </div>
        </div>

        <!-- ── Export / Import ──────────────────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;">

            <!-- Export -->
            <div class="card">
                <div class="card-header"><?= $translator->translate('lang_export_card') ?></div>
                <div class="card-body">
                    <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;">
                        <?= $translator->translate('lang_export_hint') ?>
                    </p>
                    <form method="GET" action="settings.php" id="lang-export-form">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('lang_export_select_label') ?></label>
                            <select name="lang_export" id="lang-export-select" class="form-control" onchange="updateExportInfo()">
                                <?php foreach ($langFiles as $lf):
                                    $code = basename($lf, '.php');
                                    $arr  = @include $lf;
                                    $cnt  = is_array($arr) ? count($arr) : 0;
                                    ?>
                                    <option value="<?= htmlspecialchars($code) ?>"
                                            data-keys="<?= $cnt ?>"
                                            <?= ($code === ($_SESSION['lang'] ?? 'DE-de')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($code) ?> (<?= $cnt ?> Keys)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('lang_export_format_label') ?></label>
                            <div style="display:flex;gap:0.75rem;">
                                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.9rem;">
                                    <input type="radio" name="format" value="php" checked> <?= $translator->translate('lang_export_format_php') ?>
                                </label>
                                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.9rem;">
                                    <input type="radio" name="format" value="json"> <?= $translator->translate('lang_export_format_json') ?>
                                </label>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                            <button type="submit" class="btn btn-primary"><?= $translator->translate('lang_export_btn') ?></button>
                            <span style="font-size:0.82rem;color:var(--text-light);" id="lang-export-info">
                                <?= $translator->translate('lang_export_keys_label') ?>
                                <strong id="lang-export-keys">–</strong>
                            </span>
                        </div>
                    </form>
                    <div style="margin-top:1.25rem;padding:0.85rem 1rem;background:var(--background);border:1px solid var(--border);border-radius:8px;font-size:0.82rem;color:var(--text-light);">
                        <?= $translator->translate('lang_hint_format_php') ?><br>
                        <?= $translator->translate('lang_hint_format_json') ?>
                    </div>
                </div>
            </div>

            <!-- Import -->
            <div class="card">
                <div class="card-header"><?= $translator->translate('lang_import_card') ?></div>
                <div class="card-body">
                    <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;">
                        <?= $translator->translate('lang_import_hint') ?>
                    </p>
                    <form method="POST" enctype="multipart/form-data" action="settings.php">
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('lang_import_file_label') ?></label>
                            <input type="file" name="lang_file" class="form-control"
                                   accept=".php,.json" required style="padding:0.4rem 0.6rem;"
                                   onchange="autoFillLangCode(this)">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $translator->translate('lang_import_code_label') ?></label>
                            <input type="text" name="lang_code" id="lang-import-code" class="form-control"
                                   placeholder="DE-de" maxlength="20" required pattern="[A-Za-z0-9\-]+">
                            <small style="color:var(--text-light);font-size:0.78rem;"><?= $translator->translate('lang_import_code_hint') ?></small>
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                            <input type="checkbox" name="lang_overwrite" id="lang-overwrite"
                                   style="width:1rem;height:1rem;accent-color:var(--primary);">
                            <label for="lang-overwrite" style="margin:0;cursor:pointer;font-size:0.9rem;font-weight:600;">
                                <?= $translator->translate('lang_import_overwrite_label') ?>
                            </label>
                        </div>
                        <button type="submit" name="lang_import" class="btn btn-primary">
                            <?= $translator->translate('lang_import_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /tab-language -->

    <!-- ── Modal: Sprach-Metadaten bearbeiten ─────────────────────────────── -->
    <div id="lang-meta-modal" style="position:fixed;inset:0;z-index:99999;
         background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;
                    padding:1.75rem;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.35);">
            <h3 style="margin:0 0 1.25rem;font-size:1.1rem;font-weight:700;">✏️ <?= $translator->translate('lang_meta_modal_title') ?></h3>
            <form method="POST" action="settings.php#language" id="lang-meta-form">
                <input type="hidden" name="lang_code_meta" id="lmm-code">

                <!-- Code (readonly) -->
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('lang_meta_code_label') ?></label>
                    <input type="text" id="lmm-code-display" class="form-control" disabled
                           style="opacity:0.6;background:var(--background);">
                </div>

                <!-- Label -->
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('lang_meta_label_label') ?></label>
                    <input type="text" name="lang_label" id="lmm-label" class="form-control"
                           maxlength="60" required placeholder="<?= $translator->translate('lang_meta_label_placeholder') ?>">
                </div>

                <!-- Flag-Bild aus Sprachdatei (readonly Info) -->
                <div class="form-group">
                    <label class="form-label">🖼 Flaggen-Bild</label>
                    <div id="lmm-flag-img-wrap" style="display:flex;align-items:center;gap:0.75rem;
                         padding:0.6rem 0.85rem;background:var(--background);border:1px solid var(--border);
                         border-radius:8px;">
                        <img id="lmm-flag-img" src="" alt="" width="40" height="40"
                             style="border-radius:50%;object-fit:cover;box-shadow:0 1px 4px rgba(0,0,0,.25);display:none;">
                        <span id="lmm-flag-img-none" style="font-size:0.8rem;color:var(--text-light);">
                            Kein Bild in der Sprachdatei definiert
                        </span>
                        <div id="lmm-flag-img-info" style="font-size:0.78rem;color:var(--text-light);display:none;">
                            Bild aus <code>_meta['flag_image']</code> der Sprachdatei
                        </div>
                    </div>
                    <small style="color:var(--text-light);font-size:0.75rem;margin-top:0.35rem;display:block;">
                        Das Flaggen-Bild wird direkt in der Sprachdatei unter <code>_meta['flag_image']</code> als Data-URI definiert.
                    </small>
                </div>

                <!-- Flag-Emoji (Fallback) -->
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('lang_meta_flag_label') ?> <span style="font-size:0.75rem;color:var(--text-light);">(Fallback-Emoji)</span></label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="text" name="lang_flag" id="lmm-flag" class="form-control"
                               maxlength="10" placeholder="🇩🇪" style="flex:1;">
                        <span id="lmm-flag-preview" style="font-size:1.8rem;min-width:2rem;text-align:center;line-height:1;"></span>
                    </div>
                    <small style="color:var(--text-light);font-size:0.78rem;"><?= $translator->translate('lang_meta_flag_hint') ?></small>
                    <!-- Schnellauswahl -->
                    <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-top:0.5rem;">
                        <?php
                        $quickFlags = [
                                '🇩🇪'=>'Deutsch','🇬🇧'=>'English','🇺🇸'=>'English (US)','🇫🇷'=>'Français',
                                '🇪🇸'=>'Español','🇮🇹'=>'Italiano','🇳🇱'=>'Nederlands','🇵🇱'=>'Polski',
                                '🇵🇹'=>'Português','🇷🇺'=>'Русский','🇹🇷'=>'Türkçe','🇯🇵'=>'日本語',
                                '🇨🇳'=>'中文','🇰🇷'=>'한국어','🇨🇭'=>'Schweizerdeutsch',
                                '🇦🇹'=>'Österreich','🌊'=>'Plattdüütsch','🌐'=>'Global',
                        ];
                        foreach ($quickFlags as $emoji => $title): ?>
                            <button type="button" title="<?= htmlspecialchars($title) ?>"
                                    onclick="setLmmFlag('<?= $emoji ?>')"
                                    style="background:var(--background);border:1px solid var(--border);border-radius:6px;
                                       padding:0.15rem 0.35rem;font-size:1.2rem;cursor:pointer;line-height:1;
                                       transition:border-color .15s;"
                                    onmouseenter="this.style.borderColor='var(--primary)'"
                                    onmouseleave="this.style.borderColor='var(--border)'">
                                <?= $emoji ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sortierung -->
                <div class="form-group">
                    <label class="form-label"><?= $translator->translate('lang_meta_sort_label') ?></label>
                    <input type="number" name="lang_sort" id="lmm-sort" class="form-control"
                           min="0" max="999" value="99">
                    <small style="color:var(--text-light);font-size:0.78rem;"><?= $translator->translate('lang_meta_sort_hint') ?></small>
                </div>

                <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.25rem;">
                    <button type="button" onclick="closeLangMeta()"
                            class="btn btn-secondary"><?= $translator->translate('btn_cancel') ?></button>
                    <button type="submit" name="lang_update_meta"
                            class="btn btn-primary">💾 <?= $translator->translate('btn_save') ?></button>
                </div>
            </form>
        </div>
    </div>
    <!-- Tab: Footer -->
    <div id="tab-footer" class="settings-tab-panel">
        <form method="POST" enctype="multipart/form-data">

            <!-- ① Allgemein -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">⚙️ <?= $translator->translate('footer_tab_general') ?></div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">

                    <!-- Footer aktivieren -->
                    <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;padding:0.75rem;
                               background:var(--background);border-radius:8px;border:1px solid var(--border);">
                        <input type="checkbox" name="footer_enabled" value="1"
                                <?= $ftEnabled === '1' ? 'checked' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary);">
                        <div>
                            <div style="font-weight:600;"><?= $translator->translate('footer_enable_label') ?></div>
                            <div style="font-size:0.8rem;color:var(--text-light);"><?= $translator->translate('footer_enable_hint') ?></div>
                        </div>
                    </label>

                    <!-- Copyright – fest -->
                    <div style="padding:0.85rem 1rem;background:var(--background);
                             border-radius:8px;border:1px dashed var(--border);
                             display:flex;align-items:center;gap:0.75rem;">
                        <span style="font-size:1.3rem;flex-shrink:0;">©</span>
                        <div>
                            <div style="font-weight:600;font-size:0.88rem;color:var(--text-light);margin-bottom:2px;"><?= $translator->translate('footer_copyright_fixed') ?></div>
                            <div style="font-size:0.95rem;font-weight:600;">Made with ❤️ from YourRapiddeath</div>
                        </div>
                    </div>

                    <!-- Farben -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:0.85rem;"><?= $translator->translate('footer_bg_color_label') ?> <small style="font-weight:400;">(<?= $translator->translate('footer_color_empty_hint') ?>)</small></label>
                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                <input type="color" id="ft-bg-picker" value="<?= $ftBgColor ?: '#f8fafc' ?>"
                                       style="width:40px;height:36px;border:2px solid var(--border);border-radius:6px;cursor:pointer;padding:2px;flex-shrink:0;"
                                       oninput="document.getElementById('ft-bg-hex').value=this.value">
                                <input type="text" name="footer_bg_color" id="ft-bg-hex" class="form-control"
                                       value="<?= htmlspecialchars($ftBgColor) ?>" placeholder="<?= $translator->translate('footer_color_empty_hint') ?>" maxlength="20">
                                <button type="button" onclick="document.getElementById('ft-bg-hex').value='';document.getElementById('ft-bg-picker').value='#f8fafc';"
                                        class="btn btn-secondary" style="padding:0.3rem 0.6rem;font-size:0.8rem;flex-shrink:0;">✕</button>
                            </div>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:0.85rem;"><?= $translator->translate('footer_text_color_label') ?> <small style="font-weight:400;">(<?= $translator->translate('footer_color_empty_hint') ?>)</small></label>
                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                <input type="color" id="ft-txt-picker" value="<?= $ftTextColor ?: '#6b7280' ?>"
                                       style="width:40px;height:36px;border:2px solid var(--border);border-radius:6px;cursor:pointer;padding:2px;flex-shrink:0;"
                                       oninput="document.getElementById('ft-txt-hex').value=this.value">
                                <input type="text" name="footer_text_color" id="ft-txt-hex" class="form-control"
                                       value="<?= htmlspecialchars($ftTextColor) ?>" placeholder="<?= $translator->translate('footer_color_empty_hint') ?>" maxlength="20">
                                <button type="button" onclick="document.getElementById('ft-txt-hex').value='';document.getElementById('ft-txt-picker').value='#6b7280';"
                                        class="btn btn-secondary" style="padding:0.3rem 0.6rem;font-size:0.8rem;flex-shrink:0;">✕</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ② Rechtliche Seiten -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">📋 <?= $translator->translate('footer_legal_pages') ?></div>
                <div class="card-body" style="padding:0;">
                    <?php foreach ($ftBuiltin as $slug => $cfg):
                        $isShow  = ($ft['footer_'.$slug.'_show']   ?? '1') === '1';
                        $label   = $ft['footer_'.$slug.'_label']   ?? $cfg['label'];
                        $content = $ft['footer_'.$slug.'_content'] ?? '';
                        ?>
                        <details style="border-bottom:1px solid var(--border);" <?= $content ? 'open' : '' ?>>
                            <summary style="display:flex;align-items:center;gap:0.75rem;padding:0.85rem 1.25rem;cursor:pointer;list-style:none;">
                                <span style="font-size:1.2rem;flex-shrink:0;"><?= $cfg['icon'] ?></span>
                                <span style="font-weight:700;flex:1;"><?= htmlspecialchars($label) ?></span>
                                <?= $content
                                    ? '<span style="font-size:0.75rem;padding:2px 8px;border-radius:12px;background:#dcfce7;color:#16a34a;font-weight:600;">✓ ' . $translator->translate('footer_content_present') . '</span>'
                                    : '<span style="font-size:0.75rem;padding:2px 8px;border-radius:12px;background:#fef9c3;color:#854d0e;font-weight:600;">⚠ ' . $translator->translate('footer_no_content') . '</span>' ?>
                                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.82rem;font-weight:600;flex-shrink:0;" onclick="event.stopPropagation()">
                                    <input type="checkbox" name="footer_<?= $slug ?>_show" value="1" <?= $isShow ? 'checked' : '' ?> style="accent-color:var(--primary);"> <?= $translator->translate('footer_show_in_footer') ?>
                                </label>
                            </summary>
                            <div style="padding:0 1.25rem 1rem;border-top:1px solid var(--border);background:var(--background);">
                                <div style="display:flex;align-items:center;gap:0.5rem;margin:0.75rem 0 0.5rem;">
                                    <label style="font-size:0.82rem;color:var(--text-light);white-space:nowrap;min-width:80px;"><?= $translator->translate('footer_link_text') ?>:</label>
                                    <input type="text" name="footer_<?= $slug ?>_label" class="form-control"
                                           value="<?= htmlspecialchars($label) ?>" placeholder="<?= htmlspecialchars($cfg['label']) ?>"
                                           style="font-size:0.85rem;max-width:280px;">
                                </div>
                                <textarea name="footer_<?= $slug ?>_content" id="ft-<?= $slug ?>" rows="6"
                                          placeholder="<?= $translator->translate('footer_content_placeholder') ?> <?= htmlspecialchars($cfg['label']) ?> (HTML <?= $translator->translate('footer_html_allowed') ?>)…"
                                          style="width:100%;box-sizing:border-box;padding:0.75rem;border:1.5px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:monospace;font-size:0.82rem;resize:vertical;"><?= htmlspecialchars($content) ?></textarea>
                                <small style="color:var(--text-light);font-size:0.75rem;">URL: <code><?= SITE_URL ?>/legal.php?page=<?= $slug ?></code></small>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ③ Eigene Links -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <span>🔗 <?= $translator->translate('footer_custom_links') ?></span>
                    <button type="button" onclick="ftAddLink()" class="btn btn-secondary" style="font-size:0.82rem;padding:0.3rem 0.75rem;">+ <?= $translator->translate('footer_add_link') ?></button>
                </div>
                <div class="card-body">
                    <div id="ft-custom-links" style="display:flex;flex-direction:column;gap:0.5rem;">
                        <?php foreach ($ftCustomLinks as $i => $lnk): ?>
                            <div class="ft-link-row" style="display:grid;grid-template-columns:1fr 1.5fr auto auto;gap:0.5rem;align-items:center;padding:0.6rem 0.75rem;background:var(--background);border:1px solid var(--border);border-radius:8px;">
                                <input type="text" name="cl_label[]" class="form-control" value="<?= htmlspecialchars($lnk['label'] ?? '') ?>" placeholder="<?= $translator->translate('footer_link_caption') ?>" style="font-size:0.85rem;">
                                <input type="url"  name="cl_url[]"   class="form-control" value="<?= htmlspecialchars($lnk['url']   ?? '') ?>" placeholder="https://…" style="font-size:0.85rem;">
                                <label style="display:flex;align-items:center;gap:0.35rem;font-size:0.82rem;white-space:nowrap;cursor:pointer;">
                                    <input type="checkbox" name="cl_new_tab[<?= $i ?>]" value="1" <?= !empty($lnk['new_tab']) ? 'checked' : '' ?> style="accent-color:var(--primary);"> <?= $translator->translate('footer_new_tab') ?>
                                </label>
                                <button type="button" onclick="this.closest('.ft-link-row').remove();" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:1.1rem;padding:0 4px;">✕</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Speichern -->
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:2rem;">
                <button type="submit" name="save_footer" class="btn btn-primary">💾 <?= $translator->translate('footer_save_btn') ?></button>
            </div>

        </form>

        <script>
            (function() {
                const newTabLabel = <?= json_encode($translator->translate('footer_new_tab')) ?>;
                const captionPh   = <?= json_encode($translator->translate('footer_link_caption')) ?>;
                window.ftAddLink = function() {
                    const idx = Date.now();
                    const row = document.createElement('div');
                    row.className = 'ft-link-row';
                    row.style.cssText = 'display:grid;grid-template-columns:1fr 1.5fr auto auto;gap:0.5rem;align-items:center;padding:0.6rem 0.75rem;background:var(--background);border:1px solid var(--border);border-radius:8px;';
                    row.innerHTML = `
                    <input type="text" name="cl_label[]" class="form-control" placeholder="${captionPh}" style="font-size:0.85rem;">
                    <input type="url"  name="cl_url[]"   class="form-control" placeholder="https://…" style="font-size:0.85rem;">
                    <label style="display:flex;align-items:center;gap:0.35rem;font-size:0.82rem;white-space:nowrap;cursor:pointer;">
                        <input type="checkbox" name="cl_new_tab[${idx}]" value="1" style="accent-color:var(--primary);"> ${newTabLabel}
                    </label>
                    <button type="button" onclick="this.closest('.ft-link-row').remove();" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:1.1rem;padding:0 4px;">✕</button>
                `;
                    document.getElementById('ft-custom-links').appendChild(row);
                };
                window.ftInsert = function(id, before, after) {
                    const ta = document.getElementById(id);
                    if (!ta) return;
                    const s = ta.selectionStart, e = ta.selectionEnd;
                    const sel = ta.value.substring(s, e) || 'Text';
                    ta.value = ta.value.substring(0, s) + before + sel + after + ta.value.substring(e);
                    ta.selectionStart = s + before.length;
                    ta.selectionEnd   = s + before.length + sel.length;
                    ta.focus();
                };
            })();
        </script>

    </div><!-- /tab-footer -->

</div><!-- /.settings-container -->


<script>
    function switchSettingsTab(e, id) {
        document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
        if (e && e.currentTarget) e.currentTarget.classList.add('active');
        var panel = document.getElementById(id);
        if (panel) panel.classList.add('active');
        // Button aktiv setzen auch bei programmatischem Aufruf
        document.querySelectorAll('.settings-tab-btn').forEach(function(b) {
            if (b.dataset.tab === id) b.classList.add('active');
        });
        // Hash setzen für Reload-Persistenz
        history.replaceState(null, '', '#' + id.replace('tab-', ''));
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Aktiven Tab aus PHP (nach Submit) oder Hash ermitteln
        const fromPhp  = '<?= $activeTab ?>';
        const hash     = window.location.hash.replace('#', '');
        const hashMap  = { theme: 'tab-theme', site: 'tab-site', email: 'tab-email', 'email-tpl': 'tab-email-tpl', templates: 'tab-templates', chat: 'tab-chat', discord: 'tab-discord', 'custom-css': 'tab-custom-css', language: 'tab-language', footer: 'tab-footer' };
        const activeId = (hash && hashMap[hash]) ? hashMap[hash] : fromPhp;

        document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById(activeId);
        if (panel) panel.classList.add('active');
        document.querySelectorAll('.settings-tab-btn').forEach(b => {
            if (b.dataset.tab === activeId) b.classList.add('active');
        });

        // Chat-Einstellungen laden
        loadChatSettings();
    });

    async function loadChatSettings() {
        try {
            const r = await fetch('../includes/chat.php?action=settings');
            const d = await r.json();
            document.getElementById('chat_enabled').checked    = d.enabled;
            document.getElementById('chat_max_length').value   = d.max_length;
            document.getElementById('chat_emojis').checked     = d.emojis;
            document.getElementById('chat_gifs').checked       = d.gifs;
        } catch(e) {}
    }

    async function saveChatSettings() {
        const fd = new FormData();
        fd.append('action',     'update_settings');
        fd.append('enabled',    document.getElementById('chat_enabled').checked    ? 1 : 0);
        fd.append('max_length', document.getElementById('chat_max_length').value);
        fd.append('emojis',     document.getElementById('chat_emojis').checked     ? 1 : 0);
        fd.append('gifs',       document.getElementById('chat_gifs').checked       ? 1 : 0);
        try {
            const r = await fetch('../includes/chat.php', {method:'POST', body:fd});
            const d = await r.json();
            if (d.ok) {
                const msg = document.getElementById('chat-settings-msg');
                if (msg) { msg.style.display = 'inline'; setTimeout(() => msg.style.display = 'none', 2500); }
            }
        } catch(e) { console.error('saveChatSettings error:', e); }
    }

    async function sendBroadcast() {
        const text = document.getElementById('broadcast_msg').value.trim();
        if (!text) return;
        const fd = new FormData();
        fd.append('action',  'broadcast');
        fd.append('message', text);
        const r = await fetch('../includes/chat.php', {method:'POST', body:fd});
        const d = await r.json();
        if (d.ok) {
            document.getElementById('broadcast_msg').value = '';
            const msg = document.getElementById('broadcast-msg');
            msg.style.display = 'inline';
            setTimeout(() => msg.style.display = 'none', 2500);
        }
    }

    /* ── Blur + Brightness Slider Live-Vorschau ── */
    (function() {

        function initSliders(blurId, blurValId, briId, briValId, sizeId, sizeValId, posxId, posxValId, posyId, posyValId, previewId, filterFn) {
            const blurSlider = document.getElementById(blurId);
            const briSlider  = document.getElementById(briId);
            const sizeSlider = document.getElementById(sizeId);
            const posxSlider = document.getElementById(posxId);
            const posySlider = document.getElementById(posyId);
            const blurVal    = document.getElementById(blurValId);
            const briVal     = document.getElementById(briValId);
            const sizeVal    = document.getElementById(sizeValId);
            const posxVal    = document.getElementById(posxValId);
            const posyVal    = document.getElementById(posyValId);
            const preview    = document.getElementById(previewId);
            if (!blurSlider || !briSlider) return;

            function update() {
                const blur = parseInt(blurSlider.value, 10);
                const bri  = parseInt(briSlider.value,  10);
                const size = sizeSlider ? parseInt(sizeSlider.value, 10) : 100;
                const posX = posxSlider ? parseInt(posxSlider.value, 10) : 50;
                const posY = posySlider ? parseInt(posySlider.value, 10) : 50;
                if (blurVal) blurVal.textContent = blur + 'px';
                if (briVal)  briVal.textContent  = bri  + '%';
                if (sizeVal) sizeVal.textContent = size + '%';
                if (posxVal) posxVal.textContent = posX + '%';
                if (posyVal) posyVal.textContent = posY + '%';
                if (preview) {
                    preview.style.filter             = filterFn(blur, bri / 100);
                    preview.style.backgroundSize     = size + '%';
                    preview.style.backgroundPosition = posX + '% ' + posY + '%';
                }
            }

            [blurSlider, briSlider, sizeSlider, posxSlider, posySlider].forEach(function(s) {
                if (s) s.addEventListener('input', update);
            });
            update();
        }

        function initFilePreview(fileInputId, previewId, blurId, briId, sizeId, posxId, posyId, filterFn) {
            const fileInput = document.getElementById(fileInputId);
            if (!fileInput) return;

            fileInput.addEventListener('change', function() {
                if (!this.files || !this.files[0]) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    let el = document.getElementById(previewId);
                    if (!el) return;
                    if (!el.style.backgroundImage || el.style.backgroundImage === 'none') {
                        el.style.display         = '';
                        el.style.alignItems      = '';
                        el.style.justifyContent  = '';
                        el.style.color           = '';
                        el.style.transformOrigin = 'center';
                        el.textContent           = '';
                    }
                    el.style.backgroundImage = 'url("' + e.target.result + '")';
                    const blur = parseInt(document.getElementById(blurId)?.value  ?? 6,   10);
                    const bri  = parseInt(document.getElementById(briId)?.value   ?? 35,  10) / 100;
                    const size = parseInt(document.getElementById(sizeId)?.value  ?? 100, 10);
                    const posX = parseInt(document.getElementById(posxId)?.value  ?? 50,  10);
                    const posY = parseInt(document.getElementById(posyId)?.value  ?? 50,  10);
                    el.style.filter             = filterFn(blur, bri);
                    el.style.backgroundSize     = size + '%';
                    el.style.backgroundPosition = posX + '% ' + posY + '%';
                };
                reader.readAsDataURL(this.files[0]);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // GTA
            initSliders(
                'gta-blur-slider', 'gta-blur-val',
                'gta-bri-slider',  'gta-bri-val',
                'gta-size-slider', 'gta-size-val',
                'gta-posx-slider', 'gta-posx-val',
                'gta-posy-slider', 'gta-posy-val',
                'gta-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.65) blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'gta-file-input', 'gta-preview-img',
                'gta-blur-slider', 'gta-bri-slider',
                'gta-size-slider',
                'gta-posx-slider', 'gta-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.65) blur(' + blur + 'px)'; }
            );

            // Rotlicht
            initSliders(
                'rl-blur-slider', 'rl-blur-val',
                'rl-bri-slider',  'rl-bri-val',
                'rl-size-slider', 'rl-size-val',
                'rl-posx-slider', 'rl-posx-val',
                'rl-posy-slider', 'rl-posy-val',
                'rl-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.5) hue-rotate(320deg) blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'rl-file-input', 'rl-preview-img',
                'rl-blur-slider', 'rl-bri-slider',
                'rl-size-slider',
                'rl-posx-slider', 'rl-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.5) hue-rotate(320deg) blur(' + blur + 'px)'; }
            );

            // DayZ
            initSliders(
                'dayz-blur-slider', 'dayz-blur-val',
                'dayz-bri-slider',  'dayz-bri-val',
                'dayz-size-slider', 'dayz-size-val',
                'dayz-posx-slider', 'dayz-posx-val',
                'dayz-posy-slider', 'dayz-posy-val',
                'dayz-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.3) sepia(0.2) blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'dayz-file-input', 'dayz-preview-img',
                'dayz-blur-slider', 'dayz-bri-slider',
                'dayz-size-slider',
                'dayz-posx-slider', 'dayz-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.3) sepia(0.2) blur(' + blur + 'px)'; }
            );

            // Black & Gold
            initSliders(
                'bgold-blur-slider', 'bgold-blur-val',
                'bgold-bri-slider',  'bgold-bri-val',
                'bgold-size-slider', 'bgold-size-val',
                'bgold-posx-slider', 'bgold-posx-val',
                'bgold-posy-slider', 'bgold-posy-val',
                'bgold-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.6) sepia(0.3) blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'bgold-file-input', 'bgold-preview-img',
                'bgold-blur-slider', 'bgold-bri-slider',
                'bgold-size-slider',
                'bgold-posx-slider', 'bgold-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.6) sepia(0.3) blur(' + blur + 'px)'; }
            );

            // Windows XP
            initSliders(
                'winxp-blur-slider', 'winxp-blur-val',
                'winxp-bri-slider',  'winxp-bri-val',
                'winxp-size-slider', 'winxp-size-val',
                'winxp-posx-slider', 'winxp-posx-val',
                'winxp-posy-slider', 'winxp-posy-val',
                'winxp-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'winxp-file-input', 'winxp-preview-img',
                'winxp-blur-slider', 'winxp-bri-slider',
                'winxp-size-slider',
                'winxp-posx-slider', 'winxp-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') blur(' + blur + 'px)'; }
            );

            // ── YouTube Sliders ──
            initSliders(
                'yt-blur-slider', 'yt-blur-val',
                'yt-bri-slider',  'yt-bri-val',
                'yt-size-slider', 'yt-size-val',
                'yt-posx-slider', 'yt-posx-val',
                'yt-posy-slider', 'yt-posy-val',
                'yt-preview-img',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.8) blur(' + blur + 'px)'; }
            );
            initFilePreview(
                'yt-file-input', 'yt-preview-img',
                'yt-blur-slider', 'yt-bri-slider',
                'yt-size-slider',
                'yt-posx-slider', 'yt-posy-slider',
                function(blur, bri) { return 'brightness(' + bri + ') saturate(0.8) blur(' + blur + 'px)'; }
            );

            // ── Hintergrundbild-Boxen ein-/ausblenden je nach gewähltem Theme ──
            function toggleBgBoxes() {
                const checked   = document.querySelector('input[name="theme"]:checked');
                const val       = checked ? checked.value : '<?= escape($currentTheme) ?>';
                const gtaBox    = document.getElementById('gta-bg-box');
                const rlBox     = document.getElementById('rotlicht-bg-box');
                const dayzBox   = document.getElementById('dayz-bg-box');
                const bgoldBox  = document.getElementById('blackgold-bg-box');
                const winxpBox  = document.getElementById('winxp-bg-box');
                const ytBox     = document.getElementById('youtube-bg-box');
                if (gtaBox)   gtaBox.style.display   = (val === 'gta-roleplay') ? '' : 'none';
                if (rlBox)    rlBox.style.display    = (val === 'rotlicht')     ? '' : 'none';
                if (dayzBox)  dayzBox.style.display  = (val === 'dayz')         ? '' : 'none';
                if (bgoldBox) bgoldBox.style.display = (val === 'black-gold')   ? '' : 'none';
                if (winxpBox) winxpBox.style.display = (val === 'windows-xp')   ? '' : 'none';
                if (ytBox)    ytBox.style.display    = (val === 'youtube')       ? '' : 'none';
            }
            toggleBgBoxes();
            document.querySelectorAll('input[name="theme"]').forEach(function(r) {
                r.addEventListener('change', toggleBgBoxes);
            });
            document.querySelectorAll('.theme-option').forEach(function(lbl) {
                lbl.addEventListener('click', function() { setTimeout(toggleBgBoxes, 10); });
            });
        });
    })();

    /* ══ E-Mail-Vorlagen Tab ══════════════════════════════════════════════ */
    const ETPL_DATA = JSON.parse(document.getElementById('etpl-data')?.textContent || '[]');
    const ETPL_DEMO = {
        '{{site_name}}'      : '<?= escape(SITE_NAME) ?>',
        '{{year}}'           : '<?= date('Y') ?>',
        '{{ticket_code}}'    : 'TKT-20260001',
        '{{ticket_url}}'     : '#',
        '{{subject}}'        : 'Probleme beim Login',
        '{{description}}'    : 'Ich kann mich nicht mehr einloggen.',
        '{{status}}'         : 'Offen',
        '{{priority}}'       : 'Mittel',
        '{{support_level}}'  : 'First Level',
        '{{customer_name}}'  : 'Max Mustermann',
        '{{customer_email}}' : 'max@example.com',
        '{{supporter_name}}' : 'Anna Support',
        '{{reply_message}}'  : 'Bitte setze dein Passwort zurück.',
    };

    function etplSwitch(slug, id, isActive) {
        const tpl = ETPL_DATA.find(t => t.slug === slug);
        if (!tpl) return;
        // Felder füllen
        document.getElementById('etpl-hidden-id').value  = id;
        document.getElementById('etpl-subject').value    = tpl.subject;
        document.getElementById('etpl-html').value       = tpl.body_html;
        document.getElementById('etpl-text').value       = tpl.body_text;
        document.getElementById('etpl-active').checked   = tpl.is_active;
        document.getElementById('etpl-card-title').textContent = tpl.name;
        document.getElementById('etpl-card-desc').textContent  = tpl.description;
        const lu = document.getElementById('etpl-last-upd');
        if (lu) lu.textContent = tpl.updated_at ? 'Zuletzt geändert: ' + tpl.updated_at.substring(0,16).replace('T',' ') : '';
        // Form-Action aktualisieren
        document.getElementById('etpl-form').action = 'settings.php?tab=email-tpl&tpl=' + encodeURIComponent(slug);
        // Sidebar-Auswahl
        document.querySelectorAll('.tpl-nav-item2').forEach(el => el.classList.remove('active'));
        event && event.currentTarget && event.currentTarget.classList.add('active');
        // Tab zurücksetzen
        etplSwitchTab('html');
    }

    function etplSwitchTab(tab) {
        document.getElementById('etpl-tab-html').style.display    = tab === 'html'    ? '' : 'none';
        document.getElementById('etpl-tab-text').style.display    = tab === 'text'    ? '' : 'none';
        document.getElementById('etpl-tab-preview').style.display = tab === 'preview' ? '' : 'none';
        document.querySelectorAll('.etab').forEach(el => el.classList.toggle('active', el.dataset.etab === tab));
        if (tab === 'preview') etplRefreshPreview();
    }

    function etplRefreshPreview() {
        let html = document.getElementById('etpl-html')?.value || '';
        for (const [k,v] of Object.entries(ETPL_DEMO)) html = html.replaceAll(k, v);
        const frame = document.getElementById('etpl-preview-frame');
        if (!frame) return;
        const doc = frame.contentDocument || frame.contentWindow.document;
        doc.open(); doc.write(html); doc.close();
    }

    function etplWrap(before, after) {
        const ta = document.getElementById('etpl-html');
        if (!ta) return;
        const s = ta.selectionStart, e = ta.selectionEnd;
        const sel = ta.value.substring(s,e) || 'Text';
        ta.value = ta.value.substring(0,s) + before + sel + after + ta.value.substring(e);
        ta.selectionStart = s + before.length; ta.selectionEnd = s + before.length + sel.length;
        ta.focus();
    }

    function etplInsert(str) {
        const ta = document.getElementById('etpl-html');
        if (!ta) return;
        const s = ta.selectionStart;
        ta.value = ta.value.substring(0,s) + str + ta.value.substring(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + str.length; ta.focus();
    }

    function etplInsertPh(ph) {
        const htmlVisible = document.getElementById('etpl-tab-html')?.style.display !== 'none';
        const textVisible = document.getElementById('etpl-tab-text')?.style.display !== 'none';
        let ta = htmlVisible ? document.getElementById('etpl-html')
            : textVisible ? document.getElementById('etpl-text')
                : document.getElementById('etpl-subject');
        if (!ta) return;
        const s = ta.selectionStart || ta.value.length;
        ta.value = ta.value.substring(0,s) + ph + ta.value.substring(ta.selectionEnd || s);
        ta.selectionStart = ta.selectionEnd = s + ph.length; ta.focus();
        const tip = document.createElement('div');
        tip.textContent = '✅ ' + ph + ' eingefügt';
        tip.style.cssText = 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;padding:.4rem 1rem;border-radius:20px;font-size:.8rem;font-weight:600;z-index:9999;pointer-events:none;';
        document.body.appendChild(tip); setTimeout(() => tip.remove(), 1600);
    }

    // hashMap für URL-Hash Tab-Restore erweitern
    document.addEventListener('DOMContentLoaded', function() {
        const origHashMap = { theme: 'tab-theme', site: 'tab-site', email: 'tab-email', 'email-tpl': 'tab-email-tpl', templates: 'tab-templates', chat: 'tab-chat', discord: 'tab-discord', 'custom-css': 'tab-custom-css' };
        const hash = location.hash.replace('#','');
        if (origHashMap[hash]) switchSettingsTab(null, origHashMap[hash]);
        // GET-Parameter ?tab=
        const urlParams = new URLSearchParams(location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam && origHashMap[tabParam]) switchSettingsTab(null, origHashMap[tabParam]);

        // Discord-Einstellungen laden
        discordLoadSettings();
    });

    /* ══ Discord Integration JavaScript ══════════════════════════════════════ */

    const DISCORD_HANDLER = 'discord-handler.php';

    // ── Einstellungen laden ──────────────────────────────────────────────────
    async function discordLoadSettings() {
        try {
            const r = await fetch(DISCORD_HANDLER + '?action=get_settings');
            const d = await r.json();
            if (!d.ok) return;
            const s = d.settings;

            // Aktiviert
            document.getElementById('discord_enabled_toggle').checked = s.discord_enabled === '1';

            // Webhook
            document.getElementById('discord_webhook_url').value       = s.discord_webhook_url    || '';
            document.getElementById('discord_bot_name').value          = s.discord_bot_name        || 'SupportBot';
            document.getElementById('discord_bot_avatar_url').value    = s.discord_bot_avatar_url  || '';
            document.getElementById('discord_mention_role_id').value   = s.discord_mention_role_id || '';

            // Embed
            document.getElementById('discord_embed_title').value       = s.discord_embed_title       || '🎫 Neues Support-Ticket';
            document.getElementById('discord_embed_description').value = s.discord_embed_description || 'Ein neues Ticket wurde erstellt.';
            document.getElementById('discord_footer_text').value       = s.discord_footer_text       || '{{site_name}} Support-System';

            // Farbe
            const colorInt = parseInt(s.discord_embed_color || '5793266');
            const colorHex = '#' + colorInt.toString(16).padStart(6, '0');
            document.getElementById('discord_embed_color_picker').value = colorHex;
            document.getElementById('discord_embed_color_hex').value    = colorHex;
            document.getElementById('discord_embed_color').value        = colorInt;
            document.getElementById('discord-embed-preview').style.borderLeftColor = colorHex;

            // Trigger-Checkboxen
            ['discord_notify_new_ticket','discord_notify_new_reply','discord_notify_status_change','discord_notify_closed'].forEach(k => {
                const el = document.getElementById(k);
                if (el) el.checked = s[k] === '1';
            });

            // Show-Felder
            ['discord_show_subject','discord_show_description','discord_show_priority','discord_show_category','discord_show_username','discord_show_ticket_url'].forEach(k => {
                const el = document.getElementById(k);
                if (el) el.checked = s[k] === '1';
            });

            // Avatar-Vorschau
            discordUpdateAvatarPreview();

            // Bot-Name Vorschau
            document.getElementById('discord-preview-botname').textContent = s.discord_bot_name || 'SupportBot';

            // Custom Keys
            if (s.discord_custom_keys) {
                try {
                    const keys = JSON.parse(s.discord_custom_keys);
                    keys.forEach(k => discordAddCustomKey(k.name, k.value, k.inline));
                } catch(e) {}
            }

        } catch(e) { console.error('Discord settings load error:', e); }
    }

    // ── Einstellungen speichern ──────────────────────────────────────────────
    async function discordSaveSettings() {
        // Farbe aus Hex berechnen
        const hexColor = document.getElementById('discord_embed_color_hex').value.trim();
        const colorInt = parseInt(hexColor.replace('#',''), 16) || 5793266;
        document.getElementById('discord_embed_color').value = colorInt;

        // Custom Keys sammeln
        const customKeys = [];
        document.querySelectorAll('.discord-custom-key-row').forEach(row => {
            const name  = row.querySelector('.ck-name')?.value.trim()  || '';
            const value = row.querySelector('.ck-value')?.value.trim() || '';
            const inline = row.querySelector('.ck-inline')?.checked    || false;
            if (name && value) customKeys.push({name, value, inline});
        });

        const fd = new FormData();
        fd.append('action', 'save_settings');

        // Basis-Felder
        const fields = {
            discord_enabled:            document.getElementById('discord_enabled_toggle').checked ? '1' : '0',
            discord_webhook_url:        document.getElementById('discord_webhook_url').value.trim(),
            discord_bot_name:           document.getElementById('discord_bot_name').value.trim(),
            discord_bot_avatar_url:     document.getElementById('discord_bot_avatar_url').value.trim(),
            discord_mention_role_id:    document.getElementById('discord_mention_role_id').value.trim(),
            discord_embed_title:        document.getElementById('discord_embed_title').value.trim(),
            discord_embed_description:  document.getElementById('discord_embed_description').value.trim(),
            discord_embed_color:        String(colorInt),
            discord_footer_text:        document.getElementById('discord_footer_text').value.trim(),
            discord_notify_new_ticket:  document.getElementById('discord_notify_new_ticket').checked  ? '1' : '0',
            discord_notify_new_reply:   document.getElementById('discord_notify_new_reply').checked   ? '1' : '0',
            discord_notify_status_change: document.getElementById('discord_notify_status_change').checked ? '1' : '0',
            discord_notify_closed:      document.getElementById('discord_notify_closed').checked      ? '1' : '0',
            discord_show_subject:       document.getElementById('discord_show_subject').checked       ? '1' : '0',
            discord_show_description:   document.getElementById('discord_show_description').checked   ? '1' : '0',
            discord_show_priority:      document.getElementById('discord_show_priority').checked      ? '1' : '0',
            discord_show_category:      document.getElementById('discord_show_category').checked      ? '1' : '0',
            discord_show_username:      document.getElementById('discord_show_username').checked      ? '1' : '0',
            discord_show_ticket_url:    document.getElementById('discord_show_ticket_url').checked    ? '1' : '0',
            discord_custom_keys:        JSON.stringify(customKeys),
        };

        for (const [k, v] of Object.entries(fields)) fd.append(k, v);

        try {
            const r = await fetch(DISCORD_HANDLER, {method: 'POST', body: fd});
            const d = await r.json();
            const okMsg  = document.getElementById('discord-save-msg');
            const errMsg = document.getElementById('discord-error-msg');
            if (d.ok) {
                okMsg.style.display = 'flex';
                errMsg.style.display = 'none';
                setTimeout(() => okMsg.style.display = 'none', 3000);
            } else {
                errMsg.textContent = '❌ Fehler: ' + (d.error || 'Unbekannt');
                errMsg.style.display = 'flex';
            }
        } catch(e) {
            document.getElementById('discord-error-msg').textContent = '❌ Netzwerkfehler: ' + e.message;
            document.getElementById('discord-error-msg').style.display = 'flex';
        }
    }

    // ── Test-Webhook ─────────────────────────────────────────────────────────
    async function discordTestWebhook() {
        const url    = document.getElementById('discord_webhook_url').value.trim();
        const name   = document.getElementById('discord_bot_name').value.trim()       || 'SupportBot';
        const avatar = document.getElementById('discord_bot_avatar_url').value.trim() || '';
        const result = document.getElementById('discord-test-result');

        if (!url) {
            result.style.cssText = 'margin-top:0.4rem;font-size:0.82rem;display:block;color:var(--danger);';
            result.textContent   = '⚠️ Bitte zuerst eine Webhook-URL eingeben.';
            return;
        }

        result.style.cssText = 'margin-top:0.4rem;font-size:0.82rem;display:block;color:var(--text-secondary);';
        result.textContent   = '⏳ Sende Test-Nachricht…';

        const fd = new FormData();
        fd.append('action',      'test_webhook');
        fd.append('webhook_url', url);
        fd.append('bot_name',    name);
        fd.append('avatar_url',  avatar);

        try {
            const r = await fetch(DISCORD_HANDLER, {method:'POST', body:fd});
            const d = await r.json();
            if (d.ok) {
                result.style.color = 'var(--success)';
                result.textContent = '✅ Test-Nachricht erfolgreich gesendet! Schau in deinen Discord-Kanal.';
            } else {
                result.style.color = 'var(--danger)';
                result.textContent = '❌ Fehler: ' + (d.error || 'Unbekannt');
            }
        } catch(e) {
            result.style.color = 'var(--danger)';
            result.textContent = '❌ Netzwerkfehler: ' + e.message;
        }
    }

    // ── Avatar-Vorschau ───────────────────────────────────────────────────────
    function discordUpdateAvatarPreview() {
        const url     = document.getElementById('discord_bot_avatar_url').value.trim();
        const preview = document.getElementById('discord-avatar-preview');
        const previewSmall = document.getElementById('discord-preview-avatar');
        if (url) {
            preview.src = url; preview.style.display = 'block';
            previewSmall.src = url; previewSmall.style.display = 'block';
        } else {
            preview.style.display = 'none';
            previewSmall.style.display = 'none';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('discord_bot_avatar_url')?.addEventListener('input', discordUpdateAvatarPreview);
        document.getElementById('discord_bot_name')?.addEventListener('input', function() {
            document.getElementById('discord-preview-botname').textContent = this.value || 'SupportBot';
        });
        document.getElementById('discord_embed_title')?.addEventListener('input', function() {
            document.getElementById('discord-preview-title').textContent = this.value || '🎫 Neues Support-Ticket';
        });
        document.getElementById('discord_embed_description')?.addEventListener('input', function() {
            document.getElementById('discord-preview-desc').textContent = this.value || 'Ein neues Ticket wurde erstellt.';
        });
        document.getElementById('discord_footer_text')?.addEventListener('input', function() {
            document.getElementById('discord-preview-footer').textContent = this.value || '<?= SITE_NAME ?> Support-System';
        });
    });

    // ── Farb-Sync ─────────────────────────────────────────────────────────────
    function discordUpdateColorPreview() {
        const hex = document.getElementById('discord_embed_color_picker').value;
        document.getElementById('discord-embed-preview').style.borderLeftColor = hex;
        const colorInt = parseInt(hex.replace('#',''), 16);
        document.getElementById('discord_embed_color').value = colorInt;
    }
    function discordSyncColorFromHex() {
        let hex = document.getElementById('discord_embed_color_hex').value.trim();
        if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
        document.getElementById('discord_embed_color_picker').value = hex;
        document.getElementById('discord-embed-preview').style.borderLeftColor = hex;
        document.getElementById('discord_embed_color').value = parseInt(hex.replace('#',''), 16);
    }

    // ── Custom Key Row hinzufügen ─────────────────────────────────────────────
    function discordAddCustomKey(name = '', value = '', inline = false) {
        const list = document.getElementById('discord-custom-keys-list');
        const row  = document.createElement('div');
        row.className = 'discord-custom-key-row';
        row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto auto;gap:0.4rem;align-items:center;background:var(--background);border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.65rem;';
        row.innerHTML = `
            <input type="text" class="form-control ck-name" placeholder="Feld-Name" value="${escapeHtml(name)}" maxlength="256" style="font-size:0.82rem;padding:0.35rem 0.6rem;">
            <input type="text" class="form-control ck-value" placeholder="Wert / Platzhalter" value="${escapeHtml(value)}" maxlength="1024" style="font-size:0.82rem;padding:0.35rem 0.6rem;">
            <label title="Inline anzeigen" style="display:flex;align-items:center;gap:3px;font-size:0.75rem;cursor:pointer;white-space:nowrap;color:var(--text-secondary);">
                <input type="checkbox" class="ck-inline" ${inline ? 'checked' : ''} style="accent-color:var(--primary);"> Inline
            </label>
            <button type="button" onclick="this.closest('.discord-custom-key-row').remove()" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:1.1rem;padding:0 4px;line-height:1;" title="Entfernen">×</button>
        `;
        list.appendChild(row);
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ══ Custom CSS Editor ═══════════════════════════════════════════════════ */
    (function() {
        const ta   = document.getElementById('custom-css-editor');
        const info = document.getElementById('cc-line-count');
        const val  = document.getElementById('cc-validation');
        let previewActive = false;
        let previewStyle  = null;

        // Aktuelles Theme-CSS aus PHP (für "Theme-CSS laden"-Button)
        const themeCssContent = <?= json_encode($currentThemeCss) ?>;

        function updateStats() {
            if (!ta || !info) return;
            const lines = ta.value.split('\n').length;
            const chars = ta.value.length;
            info.textContent = lines + ' Zeilen · ' + chars + ' Zeichen';
        }

        function validateCss() {
            if (!ta || !val) return;
            const css = ta.value.trim();
            if (!css) { val.textContent = ''; return; }
            // Einfache Brace-Balance-Prüfung
            let open = 0;
            for (let i = 0; i < css.length; i++) {
                if (css[i] === '{') open++;
                else if (css[i] === '}') open--;
                if (open < 0) break;
            }
            if (open !== 0) {
                val.innerHTML = '<span style="color:var(--danger);">⚠️ <?= addslashes($translator->translate('settings_css_brace_warning')) ?> ('+open+(open>0?' <?= addslashes($translator->translate('settings_css_too_many_open')) ?>':' <?= addslashes($translator->translate('settings_css_too_many_close')) ?>')+').</span>';
            } else {
                val.innerHTML = '<span style="color:#16a34a;">✅ <?= addslashes($translator->translate('settings_css_syntax_ok')) ?></span>';
            }
        }

        function applyPreview() {
            if (!previewStyle) {
                previewStyle = document.createElement('style');
                previewStyle.id = 'custom-css-preview-live';
                document.head.appendChild(previewStyle);
            }
            previewStyle.textContent = ta ? ta.value : '';
        }

        function removePreview() {
            if (previewStyle) { previewStyle.textContent = ''; }
        }

        window.ccTogglePreview = function() {
            previewActive = !previewActive;
            const btn = document.getElementById('cc-preview-btn');
            if (previewActive) {
                applyPreview();
                if (btn) { btn.textContent = '👁️ Vorschau aktiv'; btn.style.color = 'var(--primary)'; }
            } else {
                removePreview();
                if (btn) { btn.textContent = '👁️ Vorschau'; btn.style.color = ''; }
            }
        };

        window.ccInsert = function(snippet) {
            if (!ta) return;
            const s = ta.selectionStart;
            const e = ta.selectionEnd;
            ta.value = ta.value.substring(0, s) + snippet + ta.value.substring(e);
            ta.selectionStart = ta.selectionEnd = s + snippet.length;
            ta.focus();
            updateStats();
            if (previewActive) applyPreview();
        };

        window.ccClearAll = function() {
            if (!ta) return;
            if (!confirm('<?= addslashes($translator->translate('settings_css_clear_confirm')) ?>')) return;
            ta.value = '';
            updateStats();
            if (previewActive) applyPreview();
            if (val) val.textContent = '';
        };

        window.ccLoadTheme = function() {
            if (!ta) return;
            const hasContent = ta.value.trim().length > 0;
            if (hasContent && !confirm('<?= addslashes($translator->translate('settings_css_load_confirm')) ?>')) return;
            ta.value = themeCssContent;
            updateStats();
            validateCss();
            if (previewActive) applyPreview();
            // Zum Anfang scrollen
            ta.scrollTop = 0;
            ta.focus();
        };

        window.ccFormatCss = function() {
            if (!ta) return;
            let css = ta.value;
            // Einfacher Formatter: Leerzeilen normalisieren, Einrückung
            css = css.replace(/\s*\{\s*/g, ' {\n    ')
                .replace(/;\s*/g, ';\n    ')
                .replace(/\s*\}\s*/g, '\n}\n\n')
                .replace(/\n {4}(\s*\})/g, '\n$1')
                .replace(/\n\n\n+/g, '\n\n')
                .trim();
            ta.value = css;
            updateStats();
            validateCss();
            if (previewActive) applyPreview();
        };

        // Tab-Taste im Editor
        if (ta) {
            ta.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const s = this.selectionStart;
                    this.value = this.value.substring(0, s) + '    ' + this.value.substring(this.selectionEnd);
                    this.selectionStart = this.selectionEnd = s + 4;
                }
            });
            ta.addEventListener('input', function() {
                updateStats();
                validateCss();
                if (previewActive) applyPreview();
            });
            updateStats();
            validateCss();
        }

        // ── Sprach-Tab Hilfsfunktionen ────────────────────────────────────────────
        function updateExportInfo() {
            const sel  = document.getElementById('lang-export-select');
            const keys = document.getElementById('lang-export-keys');
            if (sel && keys) {
                const opt = sel.options[sel.selectedIndex];
                keys.textContent = opt ? opt.dataset.keys : '–';
            }
        }
        // Beim Laden initial befüllen
        updateExportInfo();

        function autoFillLangCode(input) {
            const codeEl = document.getElementById('lang-import-code');
            if (!codeEl || !input.files[0]) return;
            const name = input.files[0].name;
            // Dateiname ohne Extension als Sprachcode vorschlagen
            const base = name.replace(/\.(php|json)$/i, '');
            if (/^[A-Za-z0-9\-]+$/.test(base)) codeEl.value = base;
        }

        // ── Sprach-Metadaten Modal ───────────────────────────────────────────────
        const langMetaModal = document.getElementById('lang-meta-modal');

        function setLmmFlag(emoji) {
            document.getElementById('lmm-flag').value = emoji;
            const preview = document.getElementById('lmm-flag-preview');
            if (preview) preview.textContent = emoji;
        }

        function openLangMeta(code, label, flag, sort, flagImage) {
            if (!langMetaModal) return;
            document.getElementById('lmm-code').value         = code;
            document.getElementById('lmm-code-display').value = code;
            document.getElementById('lmm-label').value        = label;
            document.getElementById('lmm-flag').value         = flag;
            document.getElementById('lmm-sort').value         = sort;

            // Emoji-Vorschau
            const preview = document.getElementById('lmm-flag-preview');
            if (preview) preview.textContent = flag;

            // Bild-Vorschau aus Sprachdatei
            const imgEl   = document.getElementById('lmm-flag-img');
            const imgNone = document.getElementById('lmm-flag-img-none');
            const imgInfo = document.getElementById('lmm-flag-img-info');
            if (imgEl) {
                if (flagImage && flagImage.length > 10) {
                    imgEl.src = flagImage;
                    imgEl.style.display = 'block';
                    if (imgNone) imgNone.style.display = 'none';
                    if (imgInfo) imgInfo.style.display = 'block';
                } else {
                    imgEl.src = '';
                    imgEl.style.display = 'none';
                    if (imgNone) imgNone.style.display = 'inline';
                    if (imgInfo) imgInfo.style.display = 'none';
                }
            }

            langMetaModal.style.display = 'flex';
            setTimeout(() => document.getElementById('lmm-label')?.focus(), 100);
        }

        function closeLangMeta() {
            if (langMetaModal) langMetaModal.style.display = 'none';
        }

        // Modal schließen beim Klick außerhalb
        if (langMetaModal) {
            langMetaModal.addEventListener('click', function(e) {
                if (e.target === langMetaModal) closeLangMeta();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeLangMeta();
            });
        }

        // Flag-Input: Live-Preview
        const lmmFlagInput = document.getElementById('lmm-flag');
        if (lmmFlagInput) {
            lmmFlagInput.addEventListener('input', function() {
                const preview = document.getElementById('lmm-flag-preview');
                if (preview) preview.textContent = this.value;
            });
        }

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const panel = document.getElementById('tab-custom-css');
                if (panel && panel.classList.contains('active')) {
                    e.preventDefault();
                    document.getElementById('custom-css-form')?.submit();
                }
            }
        });
    })();

</script>

</body>
</html>