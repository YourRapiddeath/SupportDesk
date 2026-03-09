<?php
/**
 * Support System - Einrichtungsassistent
 *
 * Dieser Assistent führt Sie durch die Installation des Support-Systems
 * und konfiguriert automatisch Datenbank und grundlegende Einstellungen.
 */

define('INSTALL_RUNNING', true);

$lockFile = __DIR__ . '/installed.lock';
$isInstalled = file_exists($lockFile);

if ($isInstalled && !isset($_GET['force'])) {
    die('
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bereits installiert</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                       background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                       display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .box { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; text-align:center; }
                h1 { color: #333; margin-top: 0; }
                p { color: #666; line-height: 1.6; }
                a { color: #667eea; text-decoration: none; }
                .btn { display: inline-block; background: #667eea; color: white; padding: 0.75rem 1.5rem;
                       border-radius: 4px; text-decoration: none; margin: 0.5rem; }
                .btn-danger { background: #e53e3e; }
                .btn:hover { opacity: 0.9; text-decoration: none; }
                .warn { background:#fff3cd; border:1px solid #ffc107; border-radius:6px; padding:1rem; margin:1rem 0; color:#856404; font-size:.9rem; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>✓ System bereits installiert</h1>
                <p>Das Support-System wurde bereits eingerichtet.</p>
                <div class="warn">
                    ⚠️ <strong>Warnung:</strong> Eine Neuinstallation mit <code>?force=1</code> löscht alle vorhandenen Daten!
                </div>
                <a href="index.php" class="btn">Zum Support-System</a>
                <a href="install.php?force=1" class="btn btn-danger">Neu installieren</a>
            </div>
        </body>
        </html>
    ');
}

if (isset($_GET['force']) && $_GET['force'] == '1') {
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }

    session_start();
    session_unset();
    session_destroy();
    header('Location: install.php');
    exit;
}


if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 3600);
    session_start();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    $step = (int)$_POST['step'];
} elseif (isset($_GET['step'])) {
    $step = (int)$_GET['step'];
} else {
    $step = 1;
}


$errors = [];
$success = [];

$debug_mode = false;

if ($debug_mode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = "DEBUG: Step = $step, POST keys = " . implode(', ', array_keys($_POST));
}


function checkPHPVersion() {
    return version_compare(PHP_VERSION, '7.4.0', '>=');
}

function checkPDO() {
    return class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers());
}

function checkMySQLi() {
    return extension_loaded('mysqli');
}


function checkWritePermissions(): array {
    $base = __DIR__;
    $checks = [
        ['path' => $base,                        'label' => '/ (Stammverzeichnis)',          'required' => true],
        ['path' => $base . '/config.php',        'label' => 'config.php',                    'required' => true,  'isFile' => true],
        ['path' => $base . '/uploads',           'label' => 'uploads/',                      'required' => true],
        ['path' => $base . '/uploads/avatars',   'label' => 'uploads/avatars/',              'required' => true],
        ['path' => $base . '/uploads/backgrounds','label' => 'uploads/backgrounds/',         'required' => true],
        ['path' => $base . '/assets/lang',       'label' => 'assets/lang/',                  'required' => false],
        ['path' => $base . '/assets/css',        'label' => 'assets/css/',                   'required' => false],
    ];

    $results = [];
    foreach ($checks as $check) {
        $path     = $check['path'];
        $isFile   = $check['isFile'] ?? false;
        $required = $check['required'];

        if ($isFile) {

            if (file_exists($path)) {
                $writable = is_writable($path);
            } else {
                $writable = is_writable(dirname($path));
            }
        } else {
            if (!is_dir($path)) {

                $writable = is_writable(dirname($path));
                $check['label'] .= ' (wird angelegt)';
            } else {
                $writable = is_writable($path);
            }
        }

        $results[] = [
            'path'     => $path,
            'label'    => $check['label'],
            'writable' => $writable,
            'required' => $required,
        ];
    }
    return $results;
}

function allRequiredWritable(): bool {
    foreach (checkWritePermissions() as $check) {
        if ($check['required'] && !$check['writable']) return false;
    }
    return true;
}

function testDatabaseConnection($host, $user, $pass, $dbname = null) {
    try {
        $dsn = $dbname
            ? "mysql:host=$host;dbname=$dbname;charset=utf8mb4"
            : "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT            => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
        return ['success' => true, 'connection' => $pdo];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


function createTablesViaMySQL(string $host, string $user, string $pass, string $dbname): array {
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        return ['success' => false, 'fatal' => 'database.sql nicht gefunden: ' . $sqlFile, 'log' => []];
    }
    $raw = file_get_contents($sqlFile);
    if (!$raw || strlen($raw) < 100) {
        return ['success' => false, 'fatal' => 'database.sql leer oder nicht lesbar.', 'log' => []];
    }

    if (!extension_loaded('mysqli')) {
        // Fallback auf PDO wenn mysqli nicht verfügbar
        $r = testDatabaseConnection($host, $user, $pass, $dbname);
        if (!$r['success']) return ['success' => false, 'fatal' => $r['error'], 'log' => []];
        return createTables($r['connection']);
    }

    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        return ['success' => false, 'fatal' => 'mysqli Verbindungsfehler: ' . $mysqli->connect_error, 'log' => []];
    }
    $mysqli->set_charset('utf8mb4');

    $log            = [];
    $criticalErrors = [];

    // multi_query führt die gesamte SQL-Datei auf einmal aus
    if ($mysqli->multi_query($raw)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
            if ($mysqli->errno && $mysqli->errno !== 0) {
                $msg = $mysqli->error;
                // Harmlose Fehler ignorieren
                if ($mysqli->errno == 1050 // Table already exists
                    || $mysqli->errno == 1062 // Duplicate entry
                    || $mysqli->errno == 1061 // Duplicate key name
                    || stripos($msg, 'already exists') !== false
                    || stripos($msg, 'Duplicate') !== false) {
                    $log[] = ['status' => 'skip', 'label' => 'Statement', 'msg' => $msg];
                } else {
                    $log[]            = ['status' => 'err', 'label' => 'Statement', 'msg' => "[{$mysqli->errno}] $msg"];
                    $criticalErrors[] = "[{$mysqli->errno}] $msg";
                }
            }
        } while ($mysqli->next_result());
    } else {
        $msg = $mysqli->error;
        if ($mysqli->errno && $mysqli->errno !== 1050 && $mysqli->errno !== 1062) {
            $criticalErrors[] = "[{$mysqli->errno}] $msg";
            $log[] = ['status' => 'err', 'label' => 'multi_query', 'msg' => "[{$mysqli->errno}] $msg"];
        }
    }

    $tablesResult = $mysqli->query("SHOW TABLES");
    if ($tablesResult) {
        while ($row = $tablesResult->fetch_row()) {
            $log[] = ['status' => 'ok', 'label' => 'Tabelle vorhanden: ' . $row[0], 'msg' => ''];
        }
    }

    $mysqli->close();

    return [
        'success' => empty($criticalErrors),
        'fatal'   => implode("\n", $criticalErrors),
        'log'     => $log,
    ];
}

function createDatabase($pdo, $dbname) {
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createTables($pdo) {
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        return ['success' => false, 'fatal' => 'database.sql nicht gefunden unter: ' . $sqlFile, 'log' => []];
    }

    $raw = file_get_contents($sqlFile);
    if ($raw === false || strlen($raw) < 100) {
        return ['success' => false, 'fatal' => 'database.sql ist leer oder nicht lesbar.', 'log' => []];
    }
    $statements = [];
    $current    = '';
    $inString   = false;
    $stringChar = '';
    $inComment  = false;
    $len        = strlen($raw);

    for ($i = 0; $i < $len; $i++) {
        $c = $raw[$i];
        if ($inComment) {
            if ($c === '*' && isset($raw[$i+1]) && $raw[$i+1] === '/') { $i++; $inComment = false; }
            continue;
        }
        if ($inString) {
            $current .= $c;
            if ($c === '\\') { if ($i+1 < $len) $current .= $raw[++$i]; }
            elseif ($c === $stringChar) {
                if (isset($raw[$i+1]) && $raw[$i+1] === $stringChar) $current .= $raw[++$i];
                else $inString = false;
            }
            continue;
        }
        if ($c === '/' && isset($raw[$i+1]) && $raw[$i+1] === '*') { $i++; $inComment = true; continue; }
        if ($c === '-' && isset($raw[$i+1]) && $raw[$i+1] === '-') {
            while ($i < $len && $raw[$i] !== "\n") $i++;
            $current .= ' '; continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $inString = true; $stringChar = $c; $current .= $c; continue; }
        if ($c === ';') {
            $s = trim($current);
            if ($s !== '') $statements[] = $s;
            $current = ''; continue;
        }
        $current .= $c;
    }
    $s = trim($current);
    if ($s !== '') $statements[] = $s;


    $log            = [];
    $criticalErrors = [];

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET NAMES utf8mb4");

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            if (preg_match('/^\s*SET\s+(NAMES|FOREIGN_KEY_CHECKS)\b/i', $statement)) continue;

            // Label
            preg_match('/^\s*(\w+(?:\s+\w+){0,3})\s+`?(\w+)`?/i', $statement, $m);
            $label = isset($m[2]) ? trim($m[1]) . ' ' . $m[2] : mb_substr($statement, 0, 60);

            try {
                $pdo->exec($statement);
                $log[] = ['status' => 'ok', 'label' => $label, 'msg' => ''];
            } catch (PDOException $e) {
                $code = $e->getCode();
                $msg  = $e->getMessage();
                if ($code === '23000' || $code === '42S01'
                    || stripos($msg, 'already exists') !== false
                    || stripos($msg, 'Duplicate key name') !== false
                    || stripos($msg, 'Duplicate entry') !== false) {
                    $log[] = ['status' => 'skip', 'label' => $label, 'msg' => $msg];
                } else {
                    $log[]            = ['status' => 'err', 'label' => $label, 'msg' => "[$code] $msg", 'sql' => mb_substr($statement, 0, 400)];
                    $criticalErrors[] = "[$code] $msg";
                }
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    } catch (PDOException $e) {
        return ['success' => false, 'fatal' => 'Kritischer DB-Fehler: ' . $e->getMessage(), 'log' => $log];
    }

    return [
        'success' => empty($criticalErrors),
        'fatal'   => implode("\n", $criticalErrors),
        'log'     => $log,
    ];
}

function insertDefaultSettings($pdo, $siteName, $siteUrl) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute(['site_name', $siteName]);
        $stmt->execute(['site_url',  $siteUrl]);
        $stmt->execute(['smtp_from_name', $siteName]);
        return true;
    } catch (PDOException $e) {
        error_log('[Install] insertDefaultSettings: ' . $e->getMessage());
        return false;
    }
}

function createAdminUser($pdo, $username, $email, $password, $fullName) {
    try {

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return ['success' => true, 'exists' => true];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$username, $email, $hashedPassword, $fullName]);
        return ['success' => true, 'exists' => false];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function saveConfig($host, $dbname, $user, $pass, $siteUrl, $siteName, $timezone) {

    $esc = function($v) { return str_replace("'", "\\'", $v); };

    $config = <<<PHP
<?php

if (!defined('INSTALL_RUNNING') && PHP_SAPI !== 'cli') {
    \$lockFile = __DIR__ . '/installed.lock';
    if (!file_exists(\$lockFile)) {
        \$currentScript = basename(\$_SERVER['SCRIPT_FILENAME'] ?? '');
        if (\$currentScript !== 'install.php') {
            header('Location: ' . (
                (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost')
                . rtrim(dirname(\$_SERVER['SCRIPT_NAME'] ?? ''), '/\\\\') . '/install.php'
            ));
            exit;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    session_start();
}

define('DB_HOST', '{$esc($host)}');
define('DB_NAME', '{$esc($dbname)}');
define('DB_USER', '{$esc($user)}');
define('DB_PASS', '{$esc($pass)}');

define('SITE_URL', '{$esc($siteUrl)}');
define('SITE_NAME', '{$esc($siteName)}');

date_default_timezone_set('{$esc($timezone)}');

error_reporting(E_ALL);
ini_set('display_errors', 1);

PHP;

    $langFunctions = <<<'LANGFUNC'

// ── Dynamic Language Configuration ───────────────────────────────────────────
function getSupportedLanguages(): array {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        // Tabelle via database.sql angelegt
        $builtins = [
            ['DE-de', 1, 'Deutsch',          '🇩🇪', 1, 1],
            ['EN-en', 1, 'English',           '🇬🇧', 2, 1],
            ['FR-fr', 0, 'Français',          '🇫🇷', 3, 1],
            ['CH-ch', 1, 'Schwiizerdüütsch',  '🇨🇭', 4, 1],
            ['NDS-nds', 0, 'Plattdüütsch',    '🌊',  5, 1],
        ];
        $upsert = $pdo->prepare(
            "INSERT INTO language_settings (lang_code, is_active, label, flag, sort_order, is_builtin)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                 label      = IF(label='' OR label=lang_code, VALUES(label), label),
                 flag       = IF(flag='' OR flag='🌐', VALUES(flag), flag),
                 sort_order = IF(sort_order=99, VALUES(sort_order), sort_order),
                 is_builtin = 1"
        );
        foreach ($builtins as $row) {
            $file = __DIR__ . '/assets/lang/' . $row[0] . '.php';
            if (file_exists($file)) $upsert->execute($row);
        }
        $dir = __DIR__ . '/assets/lang/';
        $files = glob($dir . '*.php');
        $insNew = $pdo->prepare(
            "INSERT IGNORE INTO language_settings (lang_code,is_active,label,flag,sort_order,is_builtin) VALUES (?,0,?,?,99,0)"
        );
        foreach ($files as $f) {
            $code = basename($f, '.php');
            if ($code === 'translator') continue;
            $insNew->execute([$code, $code, '🌐']);
        }
        $rows = $pdo->query(
            "SELECT lang_code FROM language_settings WHERE is_active = 1 ORDER BY sort_order, lang_code"
        )->fetchAll(PDO::FETCH_COLUMN);
        $cached = !empty($rows) ? $rows : ['DE-de', 'EN-en'];
        return $cached;
    } catch (\Throwable $e) {
        $dir = __DIR__ . '/assets/lang/';
        $files = glob($dir . '*.php') ?: [];
        $langs = [];
        foreach ($files as $f) {
            $code = basename($f, '.php');
            if ($code !== 'translator') $langs[] = $code;
        }
        $cached = $langs ?: ['DE-de', 'EN-en'];
        return $cached;
    }
}

$supportedLanguages = getSupportedLanguages();

if (isset($_GET['lang']) && PHP_SAPI !== 'cli') {
    $requestedLang = $_GET['lang'];
    if (in_array($requestedLang, $supportedLanguages, true)) {
        $_SESSION['lang'] = $requestedLang;
    }
    if (isset($_SERVER['REQUEST_URI'])) {
        $urlParts = parse_url($_SERVER['REQUEST_URI']);
        $path     = $urlParts['path'] ?? '/';
        $query    = [];
        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $query);
            unset($query['lang']);
        }
        $newQuery    = http_build_query($query);
        $redirectUrl = $path . ($newQuery ? '?' . $newQuery : '');
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$currentLang = $_SESSION['lang'] ?? 'EN-en';
if (!in_array($currentLang, $supportedLanguages, true)) {
    if (in_array('EN-en', $supportedLanguages, true)) {
        $currentLang = 'EN-en';
    } elseif (in_array('DE-de', $supportedLanguages, true)) {
        $currentLang = 'DE-de';
    } else {
        $currentLang = $supportedLanguages[0] ?? 'DE-de';
    }
}

require __DIR__ . '/assets/lang/translator.php';
$translator = new Translator($currentLang);

function getLangMeta(string $langCode): array {
    static $cache = [];
    if (isset($cache[$langCode])) return $cache[$langCode];
    $file = __DIR__ . '/assets/lang/' . $langCode . '.php';
    $defaults = ['label' => $langCode, 'flag_emoji' => '🌐', 'flag_image' => ''];
    if (!file_exists($file)) { $cache[$langCode] = $defaults; return $defaults; }
    $arr = @include $file;
    if (!is_array($arr)) { $cache[$langCode] = $defaults; return $defaults; }
    $meta = array_merge($defaults, $arr['_meta'] ?? []);
    $cache[$langCode] = $meta;
    return $meta;
}

function getLangFlagHtml(string $langCode, int $size = 22): string {
    $meta = getLangMeta($langCode);
    if (!empty($meta['flag_image'])) {
        return '<img src="' . htmlspecialchars($meta['flag_image']) . '"'
             . ' alt="' . htmlspecialchars($meta['flag_emoji'] ?: $langCode) . '"'
             . ' width="' . $size . '" height="' . $size . '"'
             . ' style="border-radius:50%;object-fit:cover;vertical-align:middle;display:inline-block;box-shadow:0 1px 3px rgba(0,0,0,.25);">';
    }
    return '<span style="font-size:' . round($size * 0.9) . 'px;line-height:1;vertical-align:middle;">'
         . htmlspecialchars($meta['flag_emoji'] ?: '🌐') . '</span>';
}
LANGFUNC;

    $fullConfig = $config . $langFunctions . "\n";

    $configWritten = file_put_contents(__DIR__ . '/config.php', $fullConfig) !== false;
    if (!$configWritten) return false;

    return true;
}


if (!empty($_SESSION['install_success'])) {
    $success = array_merge($success, $_SESSION['install_success']);
    unset($_SESSION['install_success']);
}
if (!empty($_SESSION['install_errors'])) {
    $errors = array_merge($errors, $_SESSION['install_errors']);
    unset($_SESSION['install_errors']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 2 && isset($_POST['check_requirements'])) {
        $step = 2;
    } elseif ($step == 2 && isset($_POST['configure_database'])) {
        $installData = [
            'db_host'   => trim($_POST['db_host']),
            'db_name'   => trim($_POST['db_name']),
            'db_user'   => trim($_POST['db_user']),
            'db_pass'   => $_POST['db_pass'],
            'site_url'  => rtrim(trim($_POST['site_url']), '/'),
            'site_name' => trim($_POST['site_name']),
            'timezone'  => $_POST['timezone'],
        ];
        $_SESSION['install_data'] = $installData;

        saveConfig(
            $installData['db_host'],
            $installData['db_name'],
            $installData['db_user'],
            $installData['db_pass'],
            $installData['site_url'],
            $installData['site_name'],
            $installData['timezone']
        );

        $step = 3;
    } elseif ($step == 3 && isset($_POST['test_connection'])) {
        if (!isset($_SESSION['install_data'])) {
            $errors[] = "Keine Konfigurationsdaten gefunden. Bitte beginnen Sie bei Schritt 2.";
            $step = 2;
        } else {
            $data = $_SESSION['install_data'];
            $_SESSION['db_log']       = [];
            $_SESSION['db_connection_ok'] = false;
            $_SESSION['tables_created']   = false;

            $testResult = testDatabaseConnection($data['db_host'], $data['db_user'], $data['db_pass']);
            if (!$testResult['success']) {
                $_SESSION['db_log'][] = ['status'=>'err',
                    'label' => 'Server-Verbindung zu ' . $data['db_host'],
                    'msg'   => $testResult['error']];
            } else {
                $_SESSION['db_log'][] = ['status'=>'ok',
                    'label' => 'Server-Verbindung zu ' . $data['db_host'] . ' hergestellt',
                    'msg'   => ''];

                try {
                    $testResult['connection']->exec(
                        "CREATE DATABASE IF NOT EXISTS `{$data['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                    $_SESSION['db_log'][] = ['status'=>'ok',
                        'label' => 'Datenbank \'' . $data['db_name'] . '\' angelegt / vorhanden',
                        'msg'   => ''];
                } catch (PDOException $e) {
                    $_SESSION['db_log'][] = ['status'=>'skip',
                        'label' => 'Datenbank anlegen nicht möglich (kein CREATE-Recht)',
                        'msg'   => $e->getMessage() . ' – Die Datenbank muss bereits existieren.'];
                }

                $dbResult = testDatabaseConnection($data['db_host'], $data['db_user'], $data['db_pass'], $data['db_name']);
                if (!$dbResult['success']) {
                    $_SESSION['db_log'][] = ['status'=>'err',
                        'label' => 'Zugriff auf Datenbank \'' . $data['db_name'] . '\' fehlgeschlagen',
                        'msg'   => $dbResult['error'] . ' – Bitte sicherstellen dass die Datenbank existiert und der Benutzer Zugriffsrecht hat.'];
                } else {
                    $_SESSION['db_log'][] = ['status'=>'ok',
                        'label' => 'Zugriff auf Datenbank \'' . $data['db_name'] . '\' erfolgreich',
                        'msg'   => ''];

                    try {
                        $ver = $dbResult['connection']->query("SELECT VERSION()")->fetchColumn();
                        $cnt = $dbResult['connection']->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchColumn();
                        $_SESSION['db_log'][] = ['status'=>'ok',
                            'label' => 'MySQL/MariaDB Version: ' . $ver,
                            'msg'   => 'Vorhandene Tabellen in dieser Datenbank: ' . (int)$cnt];
                        $_SESSION['db_connection_ok'] = true;
                    } catch (PDOException $e) {
                        $_SESSION['db_log'][] = ['status'=>'err', 'label'=>'Versionsabfrage fehlgeschlagen', 'msg'=>$e->getMessage()];
                    }
                }
            }
            $step = 3;
        }

    } elseif ($step == 3 && isset($_POST['create_database'])) {
        if (!isset($_SESSION['install_data']) || empty($_SESSION['db_connection_ok'])) {
            $errors[] = "Bitte zuerst die Verbindung testen.";
            $step = 3;
        } else {
            $data = $_SESSION['install_data'];
            $prevLog = array_filter($_SESSION['db_log'] ?? [], function($l){ return $l['status'] !== 'err' || strpos($l['label'],'Verbindung') !== false; });
            $_SESSION['db_log'] = array_values($prevLog);

            $tableResult = createTablesViaMySQL(
                $data['db_host'], $data['db_user'], $data['db_pass'], $data['db_name']
            );
            $_SESSION['db_log'] = array_merge($_SESSION['db_log'], $tableResult['log']);

            if ($tableResult['success']) {
                $ok   = count(array_filter($tableResult['log'], function($l){ return $l['status']==='ok'; }));
                $skip = count(array_filter($tableResult['log'], function($l){ return $l['status']==='skip'; }));
                $_SESSION['tables_created']  = true;
                $_SESSION['install_success'] = ["✅ Alle Tabellen importiert: {$ok} OK, {$skip} übersprungen."];
                header('Location: install.php?step=4');
                exit;
            } else {
                $errors[] = "❌ Fehler beim Anlegen der Tabellen – Details im Protokoll.";
                if ($tableResult['fatal']) {
                    $_SESSION['db_log'][] = ['status'=>'err', 'label'=>'Kritischer Fehler', 'msg'=>$tableResult['fatal']];
                }
                $step = 3;
            }
        }
    } elseif ($step == 4 && isset($_POST['create_admin'])) {
        $data = $_SESSION['install_data'];
        $adminUsername = $_POST['admin_username'];
        $adminEmail = $_POST['admin_email'];
        $adminPassword = $_POST['admin_password'];
        $adminFullName = $_POST['admin_fullname'];

        if (strlen($adminPassword) < 8) {
            $errors[] = "Das Passwort muss mindestens 8 Zeichen lang sein.";
            $step = 4;
        } elseif ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
            $errors[] = "Die Passwörter stimmen nicht überein.";
            $step = 4;
        } else {
            $dbResult = testDatabaseConnection($data['db_host'], $data['db_user'], $data['db_pass'], $data['db_name']);
            if ($dbResult['success']) {
                $adminResult = createAdminUser($dbResult['connection'], $adminUsername, $adminEmail, $adminPassword, $adminFullName);

                if ($adminResult['success']) {
                    $success[] = "Administrator-Konto erfolgreich erstellt!";
                    insertDefaultSettings($dbResult['connection'], $data['site_name'], $data['site_url']);
                    $success[] = "Standard-Einstellungen erfolgreich gespeichert!";

                    if (saveConfig($data['db_host'], $data['db_name'], $data['db_user'], $data['db_pass'],
                                   $data['site_url'], $data['site_name'], $data['timezone'])) {
                        // ✅ Erst JETZT Lock-Datei schreiben – Installation vollständig
                        $lockContent = "Installed: " . date('Y-m-d H:i:s') . "\n"
                            . "Host: " . $data['db_host'] . "\n"
                            . "DB: "   . $data['db_name'] . "\n"
                            . "Note: Diese Datei verhindert, dass der Installations-Assistent beim ersten Aufruf automatisch gestartet wird.\n"
                            . "      Löschen Sie diese Datei NUR wenn Sie eine Neuinstallation durchführen möchten.\n"
                            . "      WARNUNG: Eine Neuinstallation löscht alle vorhandenen Daten!\n";
                        file_put_contents(__DIR__ . '/installed.lock', $lockContent);

                        $_SESSION['installation_complete'] = true;
                        header('Location: install.php?step=5');
                        exit;
                    } else {
                        $errors[] = "Fehler beim Erstellen der Konfigurationsdatei. Prüfen Sie die Schreibrechte.";
                        $step = 4;
                    }
                } else {
                    $errors[] = "Fehler beim Erstellen des Administrator-Kontos: " . $adminResult['error'];
                    $step = 4;
                }
            } else {
                $errors[] = "Datenbankverbindung fehlgeschlagen: " . $dbResult['error'];
                $step = 4;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support System - Einrichtungsassistent</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .progress-bar {
            display: flex;
            background: #f5f5f5;
            padding: 1rem 2rem;
            justify-content: space-between;
            border-bottom: 1px solid #e0e0e0;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 0.5rem;
        }

        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 1.25rem;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }

        .progress-step.active .step-number {
            background: #667eea;
            color: white;
        }

        .progress-step.completed .step-number {
            background: #4CAF50;
            color: white;
        }

        .progress-step.completed:not(:last-child)::after {
            background: #4CAF50;
        }

        .step-number {
            display: inline-block;
            width: 2.5rem;
            height: 2.5rem;
            line-height: 2.5rem;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            font-weight: bold;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .step-label {
            font-size: 0.85rem;
            color: #666;
        }

        .progress-step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }

        .content {
            padding: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="url"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .help-text {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .requirements-list {
            list-style: none;
        }

        .requirements-list li {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 6px;
            background: #f8f9fa;
        }

        .requirements-list li.success {
            background: #d4edda;
            color: #155724;
        }

        .requirements-list li.error {
            background: #f8d7da;
            color: #721c24;
        }

        .check-icon {
            margin-right: 0.5rem;
        }

        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #667eea;
        }

        .stat-card h4 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }

        .completion-icon {
            font-size: 4rem;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Support System</h1>
            <p>Einrichtungsassistent</p>
        </div>

        <div class="progress-bar">
            <div class="progress-step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
                <div class="step-number">1</div>
                <div class="step-label">Willkommen</div>
            </div>
            <div class="progress-step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
                <div class="step-number">2</div>
                <div class="step-label">Konfiguration</div>
            </div>
            <div class="progress-step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">
                <div class="step-number">3</div>
                <div class="step-label">Datenbank</div>
            </div>
            <div class="progress-step <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">
                <div class="step-number">4</div>
                <div class="step-label">Administrator</div>
            </div>
            <div class="progress-step <?= $step >= 5 ? 'active' : '' ?> <?= $step > 5 ? 'completed' : '' ?>">
                <div class="step-number">5</div>
                <div class="step-label">Fertig</div>
            </div>
        </div>

        <div class="content">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error">
                    <strong>❌ Fehler:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($success as $msg): ?>
                <div class="alert alert-success">
                    <strong>✅ Erfolg:</strong> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endforeach; ?>

            <?php if ($step == 1): ?>
                <h2>Willkommen beim Einrichtungsassistenten</h2>
                <p style="margin: 1rem 0; color: #666; line-height: 1.6;">
                    Dieser Assistent führt Sie durch die Installation des Support-Systems.
                    Der Prozess dauert nur wenige Minuten und umfasst folgende Schritte:
                </p>

                <div class="card">
                    <h3>Was wird installiert?</h3>
                    <ul style="line-height: 2; color: #666;">
                        <li>✓ Systemvoraussetzungen prüfen</li>
                        <li>✓ Datenbankverbindung konfigurieren</li>
                        <li>✓ Datenbank und Tabellen erstellen</li>
                        <li>✓ Administrator-Konto anlegen</li>
                        <li>✓ Grundeinstellungen speichern</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <strong>📋 Voraussetzungen:</strong><br>
                    Stellen Sie sicher, dass Sie Zugriff auf einen MySQL-Server haben und die
                    Zugangsdaten (Host, Benutzername, Passwort) zur Hand haben.
                </div>

                <form method="GET">
                    <input type="hidden" name="step" value="2">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Installation starten →</button>
                    </div>
                </form>

            <?php elseif ($step == 2): ?>
                <h2>Systemvoraussetzungen & Konfiguration</h2>

                <?php
                $writeChecks   = checkWritePermissions();
                $allWritable   = allRequiredWritable();
                $phpOk         = checkPHPVersion();
                $pdoOk         = checkPDO();
                $mysqliOk      = checkMySQLi();
                $dbExtOk       = $pdoOk || $mysqliOk;
                $canProceed    = $phpOk && $dbExtOk && $allWritable;
                ?>

                <!-- PHP & Extensions -->
                <div class="card">
                    <h3>🖥️ PHP & Erweiterungen</h3>
                    <ul class="requirements-list">
                        <li class="<?= $phpOk ? 'success' : 'error' ?>">
                            <span class="check-icon"><?= $phpOk ? '✅' : '❌' ?></span>
                            PHP Version 7.4+ &nbsp;<small style="opacity:.7">(Aktuell: <?= PHP_VERSION ?>)</small>
                        </li>
                        <li class="<?= $pdoOk ? 'success' : 'error' ?>">
                            <span class="check-icon"><?= $pdoOk ? '✅' : '❌' ?></span>
                            PDO MySQL Extension
                            <?php if (!$pdoOk): ?><small style="opacity:.7"> – php_pdo_mysql aktivieren</small><?php endif; ?>
                        </li>
                        <li class="<?= $mysqliOk ? 'success' : ($pdoOk ? 'success' : 'error') ?>">
                            <span class="check-icon"><?= $mysqliOk ? '✅' : ($pdoOk ? '✅' : '❌') ?></span>
                            MySQLi Extension
                            <?php if (!$mysqliOk && $pdoOk): ?><small style="opacity:.7"> – nicht nötig, PDO vorhanden</small><?php endif; ?>
                        </li>
                        <li class="<?= extension_loaded('mbstring') ? 'success' : 'error' ?>">
                            <span class="check-icon"><?= extension_loaded('mbstring') ? '✅' : '❌' ?></span>
                            mbstring Extension
                        </li>
                        <li class="<?= extension_loaded('json') ? 'success' : 'error' ?>">
                            <span class="check-icon"><?= extension_loaded('json') ? '✅' : '❌' ?></span>
                            JSON Extension
                        </li>
                        <li class="<?= extension_loaded('openssl') ? 'success' : 'error' ?>">
                            <span class="check-icon"><?= extension_loaded('openssl') ? '✅' : '⚠️' ?></span>
                            OpenSSL Extension
                            <?php if (!extension_loaded('openssl')): ?><small style="opacity:.7"> – empfohlen für 2FA & HTTPS</small><?php endif; ?>
                        </li>
                    </ul>
                </div>

                <div class="card">
                    <h3>📁 Schreibrechte</h3>
                    <?php if (!$allWritable): ?>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#856404;">
                        ⚠️ Einige Pflichtverzeichnisse sind <strong>nicht beschreibbar</strong>.
                        Bitte passe die Rechte an (z.B. <code>chmod 755</code> oder <code>chown www-data</code>).
                    </div>
                    <?php endif; ?>
                    <ul class="requirements-list">
                        <?php foreach ($writeChecks as $wc):
                            $cls  = $wc['writable'] ? 'success' : ($wc['required'] ? 'error' : 'error');
                            $icon = $wc['writable'] ? '✅' : ($wc['required'] ? '❌' : '⚠️');
                            $hint = $wc['required'] ? ' <small style="opacity:.6">(Pflicht)</small>' : ' <small style="opacity:.6">(optional)</small>';
                        ?>
                        <li class="<?= $cls ?>">
                            <span class="check-icon"><?= $icon ?></span>
                            <code style="font-size:.85rem"><?= htmlspecialchars($wc['label']) ?></code>
                            <?= $hint ?>
                            <?php if (!$wc['writable']): ?>
                            <br><small style="opacity:.7;margin-left:1.5rem">Pfad: <?= htmlspecialchars($wc['path']) ?></small>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (!$canProceed): ?>
                    <div class="alert alert-error">
                        <strong>❌ Voraussetzungen nicht erfüllt.</strong>
                        Bitte behebe die oben markierten Probleme und lade die Seite neu.
                    </div>
                    <form method="GET">
                        <input type="hidden" name="step" value="2">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-secondary">🔄 Erneut prüfen</button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php if (!$allWritable): ?><!-- dieser Block wird nie erreicht wenn !$canProceed, aber sicher ist sicher --><?php endif; ?>
                    <form method="POST">
                        <h3 style="margin: 2rem 0 1rem;">🗄️ Datenbank-Konfiguration</h3>

                        <div class="form-group">
                            <label for="db_host">Datenbank-Host</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                            <div class="help-text">In der Regel "localhost" oder eine IP-Adresse</div>
                        </div>

                        <div class="form-group">
                            <label for="db_name">Datenbankname</label>
                            <input type="text" id="db_name" name="db_name" value="support_system" required>
                            <div class="help-text">Name der zu erstellenden Datenbank</div>
                        </div>

                        <div class="form-group">
                            <label for="db_user">Datenbank-Benutzername</label>
                            <input type="text" id="db_user" name="db_user" required>
                        </div>

                        <div class="form-group">
                            <label for="db_pass">Datenbank-Passwort</label>
                            <input type="password" id="db_pass" name="db_pass">
                        </div>

                        <h3 style="margin: 2rem 0 1rem;">Website-Konfiguration</h3>

                        <div class="form-group">
                            <label for="site_url">Website-URL</label>
                            <input type="url" id="site_url" name="site_url"
                                   value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>" required>
                            <div class="help-text">Die vollständige URL Ihrer Installation (ohne abschließenden Slash)</div>
                        </div>

                        <div class="form-group">
                            <label for="site_name">Website-Name</label>
                            <input type="text" id="site_name" name="site_name" value="Support System" required>
                        </div>

                        <div class="form-group">
                            <label for="timezone">Zeitzone</label>
                            <select id="timezone" name="timezone" required>
                                <option value="Europe/Berlin" selected>Europe/Berlin (Deutschland)</option>
                                <option value="Europe/Vienna">Europe/Vienna (Österreich)</option>
                                <option value="Europe/Zurich">Europe/Zurich (Schweiz)</option>
                                <option value="Europe/London">Europe/London</option>
                                <option value="America/New_York">America/New_York</option>
                                <option value="America/Los_Angeles">America/Los_Angeles</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>

                        <input type="hidden" name="step" value="2">
                        <div class="btn-group">
                            <button type="submit" name="configure_database" class="btn btn-primary">Weiter →</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php elseif ($step == 3): ?>
                <h2>🗄️ Datenbank einrichten</h2>

                <?php if (!isset($_SESSION['install_data'])): ?>
                    <div class="alert alert-error">
                        <strong>❌ Fehler:</strong> Keine Konfigurationsdaten gefunden.
                    </div>
                    <a href="?step=2" class="btn btn-primary">← Zurück zu Schritt 2</a>
                <?php else:
                    $data          = $_SESSION['install_data'];
                    $dbLog         = $_SESSION['db_log'] ?? [];
                    $connOk        = !empty($_SESSION['db_connection_ok']);
                    $tablesCreated = !empty($_SESSION['tables_created']);

                    // Zähler für das Log
                    $errCount = $okCount = $skipCount = 0;
                    foreach ($dbLog as $e) {
                        if ($e['status']==='ok')   $okCount++;
                        if ($e['status']==='skip') $skipCount++;
                        if ($e['status']==='err')  $errCount++;
                    }
                ?>

                <div class="card">
                    <h3>📋 Konfiguration</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                        <tr><td style="padding:.3rem .5rem;color:#666;width:140px">Host</td>      <td style="padding:.3rem .5rem;font-weight:600"><?= htmlspecialchars($data['db_host']) ?></td></tr>
                        <tr><td style="padding:.3rem .5rem;color:#666">Datenbank</td>             <td style="padding:.3rem .5rem;font-weight:600"><?= htmlspecialchars($data['db_name']) ?></td></tr>
                        <tr><td style="padding:.3rem .5rem;color:#666">Benutzer</td>              <td style="padding:.3rem .5rem;font-weight:600"><?= htmlspecialchars($data['db_user']) ?></td></tr>
                        <tr><td style="padding:.3rem .5rem;color:#666">Website</td>               <td style="padding:.3rem .5rem;font-weight:600"><?= htmlspecialchars($data['site_url']) ?></td></tr>
                    </table>
                </div>

                <!-- ── Phase 1: Verbindungscheck ── -->
                <div class="card" style="padding:0;overflow:hidden;">
                    <div style="padding:1rem 1.5rem;background:#f8f9fa;border-bottom:1px solid #dee2e6;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                        <h3 style="margin:0">
                            <?php if (!$dbLog): ?>
                                🔌 Schritt 1: Verbindung prüfen
                            <?php elseif ($connOk): ?>
                                ✅ Schritt 1: Verbindung erfolgreich
                            <?php else: ?>
                                ❌ Schritt 1: Verbindung fehlgeschlagen
                            <?php endif; ?>
                        </h3>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="step" value="3">
                            <button type="submit" name="test_connection"
                                class="btn <?= $connOk ? 'btn-secondary' : 'btn-primary' ?>"
                                style="padding:.45rem 1rem;font-size:.875rem">
                                <?= $connOk ? '🔄 Erneut prüfen' : '🔌 Verbindung testen' ?>
                            </button>
                        </form>
                    </div>

                    <?php if (!empty($dbLog)): ?>
                    <div>
                        <?php foreach ($dbLog as $entry):
                            if ($entry['status'] === 'err')  { $bg='#f8d7da'; $col='#721c24'; $ic='❌'; }
                            elseif ($entry['status']==='skip'){ $bg='#fff3cd'; $col='#856404'; $ic='⚠️'; }
                            else                              { $bg='#d4edda'; $col='#155724'; $ic='✅'; }
                        ?>
                        <div style="padding:.55rem 1rem;border-bottom:1px solid rgba(0,0,0,.05);background:<?= $bg ?>;display:flex;gap:.75rem;align-items:flex-start;">
                            <span style="flex-shrink:0"><?= $ic ?></span>
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;color:<?= $col ?>;font-size:.85rem"><?= htmlspecialchars($entry['label']) ?></div>
                                <?php if (!empty($entry['msg'])): ?>
                                <div style="font-size:.78rem;color:<?= $col ?>;opacity:.85;margin-top:2px;word-break:break-all"><?= htmlspecialchars($entry['msg']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($entry['sql'])): ?>
                                <pre style="font-size:.72rem;background:rgba(0,0,0,.08);padding:.4rem;border-radius:4px;margin-top:4px;overflow:auto;white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars(mb_substr($entry['sql'],0,300)) ?></pre>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:.6rem 1rem;background:#f8f9fa;border-top:1px solid #dee2e6;font-size:.82rem;display:flex;gap:1.5rem">
                        <span style="color:#155724">✅ OK: <strong><?= $okCount ?></strong></span>
                        <span style="color:#856404">⚠️ Hinweise: <strong><?= $skipCount ?></strong></span>
                        <span style="color:#721c24">❌ Fehler: <strong><?= $errCount ?></strong></span>
                    </div>
                    <?php else: ?>
                    <div style="padding:1.25rem 1rem;color:#666;font-size:.9rem;text-align:center">
                        Noch kein Test durchgeführt. Klicke auf „🔌 Verbindung testen".
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($connOk && !$tablesCreated): ?>
                <div class="card" style="padding:0;overflow:hidden;border:2px solid #28a745;">
                    <div style="padding:1rem 1.5rem;background:#d4edda;border-bottom:1px solid #c3e6cb;">
                        <h3 style="margin:0;color:#155724">🚀 Schritt 2: Tabellen aus database.sql importieren</h3>
                    </div>
                    <div style="padding:1rem 1.5rem">
                        <p style="margin:0 0 1rem;color:#555;font-size:.9rem">
                            Die Verbindung ist erfolgreich. Klicke jetzt auf den Button um alle Tabellen
                            aus <code>database.sql</code> in die Datenbank <strong><?= htmlspecialchars($data['db_name']) ?></strong> zu importieren.
                            Bereits vorhandene Tabellen werden <strong>nicht überschrieben</strong>.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="step" value="3">
                            <div class="btn-group" style="margin-top:0">
                                <button type="submit" name="create_database" class="btn btn-success">
                                    🗄️ Tabellen jetzt anlegen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif ($tablesCreated): ?>
                <div class="alert alert-success">
                    <strong>✅ Datenbank vollständig eingerichtet!</strong>
                    Alle Tabellen aus <code>database.sql</code> wurden importiert.
                </div>
                <form method="GET">
                    <input type="hidden" name="step" value="4">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Weiter → Administrator anlegen</button>
                    </div>
                </form>
                <?php endif; ?>

                <div style="margin-top:1rem">
                    <a href="?step=2" style="color:#667eea;font-size:.875rem">← Zurück zur Konfiguration</a>
                </div>

                <?php endif; ?>

            <?php elseif ($step == 4): ?>
                <h2>Administrator-Konto erstellen</h2>

                <div class="alert alert-info">
                    <strong>ℹ️ Administrator-Konto:</strong> Dieses Konto hat vollen Zugriff auf alle Funktionen
                    des Support-Systems, einschließlich Benutzerverwaltung und Systemeinstellungen.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="admin_username">Benutzername</label>
                        <input type="text" id="admin_username" name="admin_username" value="admin" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">E-Mail-Adresse</label>
                        <input type="email" id="admin_email" name="admin_email" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_fullname">Vollständiger Name</label>
                        <input type="text" id="admin_fullname" name="admin_fullname" value="System Administrator" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Passwort</label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="8">
                        <div class="help-text">Mindestens 8 Zeichen</div>
                    </div>

                    <div class="form-group">
                        <label for="admin_password_confirm">Passwort bestätigen</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="8">
                    </div>

                    <input type="hidden" name="step" value="4">
                    <div class="btn-group">
                        <button type="submit" name="create_admin" class="btn btn-success">Administrator erstellen</button>
                    </div>
                </form>

            <?php elseif ($step == 5): ?>
                <div class="completion-icon">🎉</div>
                <h2 style="text-align: center; color: #4CAF50;">Installation erfolgreich abgeschlossen!</h2>

                <div class="alert alert-success">
                    <strong>✅ Glückwunsch!</strong> Das Support-System wurde erfolgreich installiert und ist einsatzbereit.
                </div>

                <div class="grid">
                    <div class="stat-card">
                        <h4>✓ Datenbank</h4>
                        <p>Alle Tabellen wurden erfolgreich erstellt</p>
                    </div>
                    <div class="stat-card">
                        <h4>✓ Konfiguration</h4>
                        <p>config.php wurde erstellt</p>
                    </div>
                    <div class="stat-card">
                        <h4>✓ Administrator</h4>
                        <p>Admin-Konto wurde angelegt</p>
                    </div>
                    <div class="stat-card">
                        <h4>✓ Einstellungen</h4>
                        <p>Standard-Einstellungen gespeichert</p>
                    </div>
                </div>

                <?php if (file_exists(__DIR__ . '/installed.lock')): ?>
                <div class="alert alert-success" style="margin-top:1rem;">
                    <strong>🔒 Installations-Schutz aktiv:</strong>
                    Die Datei <code>installed.lock</code> wurde erstellt.
                    Das System erkennt nun beim Start automatisch, dass es bereits installiert ist
                    und leitet nicht mehr zum Installer weiter.
                </div>
                <?php else: ?>
                <div class="alert alert-warning" style="margin-top:1rem;">
                    <strong>⚠️ installed.lock fehlt!</strong>
                    Bitte prüfen Sie die Schreibrechte im Verzeichnis.
                    Erstellen Sie die Datei manuell: <code>touch installed.lock</code>
                </div>
                <?php endif; ?>

                <div class="card">
                    <h3>Nächste Schritte</h3>
                    <ol style="line-height: 2; color: #666;">
                        <li><strong>Sicherheit:</strong> Löschen oder verschieben Sie die Datei <code>install.php</code> aus Sicherheitsgründen.</li>
                        <li><strong>Anmelden:</strong> Melden Sie sich mit Ihren Administrator-Zugangsdaten an.</li>
                        <li><strong>Konfiguration:</strong> Passen Sie die Systemeinstellungen in der Administration an.</li>
                        <li><strong>E-Mail:</strong> Konfigurieren Sie SMTP-Einstellungen für E-Mail-Benachrichtigungen.</li>
                        <li><strong>Benutzer:</strong> Legen Sie Support-Mitarbeiter und Benutzer an.</li>
                        <li><strong>Kategorien:</strong> Erstellen Sie Ticket-Kategorien für verschiedene Support-Level.</li>
                    </ol>
                </div>

                <div class="alert alert-warning">
                    <strong>⚠️ Sicherheitshinweis:</strong> Aus Sicherheitsgründen sollten Sie die Datei
                    <code>install.php</code> jetzt löschen oder aus dem Web-Verzeichnis verschieben,
                    um unbefugten Zugriff zu verhindern.
                </div>

                <div class="btn-group">
                    <a href="index.php" class="btn btn-success">Zum Support-System →</a>
                    <a href="login.php" class="btn btn-primary">Anmelden</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($debug_mode): ?>
    <div class="container" style="margin-top: 20px;">
        <div class="content">
            <h3>Debug-Informationen</h3>
            <div class="card">
                <h4>Session-Daten:</h4>
                <pre><?php print_r($_SESSION); ?></pre>
            </div>
            <div class="card">
                <h4>POST-Daten:</h4>
                <pre><?php print_r($_POST); ?></pre>
            </div>
            <div class="card">
                <h4>GET-Daten:</h4>
                <pre><?php print_r($_GET); ?></pre>
            </div>
            <div class="card">
                <h4>Aktueller Step:</h4>
                <p><?php echo $step; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>

