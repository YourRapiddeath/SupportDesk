<?php
// ── Auto-Install Detection ────────────────────────────────────────────────────
// Wenn installed.lock nicht existiert, wird automatisch install.php aufgerufen.
if (!defined('INSTALL_RUNNING') && PHP_SAPI !== 'cli') {
    $lockFile = __DIR__ . '/installed.lock';
    if (!file_exists($lockFile)) {
        $currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($currentScript !== 'install.php') {
            header('Location: ' . (
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/install.php'
            ));
            exit;
        }
    }
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'database_name');
define('DB_USER', 'sup_user');
define('DB_PASS', 'sup_pass');

// Site Configuration
define('SITE_URL', 'localhost');;
define('SITE_NAME', 'Support System');

// Timezone
date_default_timezone_set('Europe/Berlin');

// Error Reporting (set to 0 in production)
error_reporting(0);
ini_set('display_errors', 0);
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
