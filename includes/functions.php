<?php
function getCurrentTheme() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : 'modern-blue';
    } catch (Exception $e) {
        return 'modern-blue'; // Default theme if DB not available
    }
}

function loadTheme() {
    $theme = getCurrentTheme();
    return SITE_URL . "/assets/css/theme-{$theme}.css";
}

function getGtaBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gta_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function injectGtaBgStyle() {
    if (getCurrentTheme() !== 'gta-roleplay') return;
    $img = getGtaBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gta_bg_blur','gta_bg_brightness','gta_bg_pos_x','gta_bg_pos_y','gta_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['gta_bg_blur']       ?? 6)));
        $bri  = max(5,   min(100, (int)($vals['gta_bg_brightness'] ?? 35)));
        $posX = max(0,   min(100, (int)($vals['gta_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['gta_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['gta_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 6; $bri = 35; $posX = 50; $posY = 50; $size = 100; }
    $briFlt  = round($bri / 100, 2);
    $bgSize  = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #0C0C10 !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ') saturate(0.7);
    pointer-events: none;
}
</style>' . "\n";
}

function getRotlichtBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'rotlicht_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function injectRotlichtBgStyle() {
    if (getCurrentTheme() !== 'rotlicht') return;
    $img = getRotlichtBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('rotlicht_bg_blur','rotlicht_bg_brightness','rotlicht_bg_pos_x','rotlicht_bg_pos_y','rotlicht_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['rotlicht_bg_blur']       ?? 8)));
        $bri  = max(5,   min(100, (int)($vals['rotlicht_bg_brightness'] ?? 28)));
        $posX = max(0,   min(100, (int)($vals['rotlicht_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['rotlicht_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['rotlicht_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 8; $bri = 28; $posX = 50; $posY = 50; $size = 100; }
    $briFlt = round($bri / 100, 2);
    $bgSize = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #0D0508 !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ') saturate(0.6) hue-rotate(320deg);
    pointer-events: none;
}
</style>' . "\n";
}

function getDayzBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'dayz_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function injectDayzBgStyle() {
    if (getCurrentTheme() !== 'dayz') return;
    $img = getDayzBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('dayz_bg_blur','dayz_bg_brightness','dayz_bg_pos_x','dayz_bg_pos_y','dayz_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['dayz_bg_blur']       ?? 4)));
        $bri  = max(5,   min(100, (int)($vals['dayz_bg_brightness'] ?? 30)));
        $posX = max(0,   min(100, (int)($vals['dayz_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['dayz_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['dayz_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 4; $bri = 30; $posX = 50; $posY = 50; $size = 100; }
    $briFlt = round($bri / 100, 2);
    $bgSize = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #0A0C09 !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ') saturate(0.3) sepia(0.2);
    pointer-events: none;
}
</style>' . "\n";
}

function getBlackGoldBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blackgold_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function injectBlackGoldBgStyle() {
    if (getCurrentTheme() !== 'black-gold') return;
    $img = getBlackGoldBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('blackgold_bg_blur','blackgold_bg_brightness','blackgold_bg_pos_x','blackgold_bg_pos_y','blackgold_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['blackgold_bg_blur']       ?? 5)));
        $bri  = max(5,   min(100, (int)($vals['blackgold_bg_brightness'] ?? 30)));
        $posX = max(0,   min(100, (int)($vals['blackgold_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['blackgold_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['blackgold_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 5; $bri = 30; $posX = 50; $posY = 50; $size = 100; }
    $briFlt = round($bri / 100, 2);
    $bgSize = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #0A0A0A !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ') saturate(0.6) sepia(0.3);
    pointer-events: none;
}
</style>' . "\n";
}

function getWinXpBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'winxp_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) { return ''; }
}

function injectWinXpBgStyle() {
    if (getCurrentTheme() !== 'windows-xp') return;
    $img = getWinXpBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('winxp_bg_blur','winxp_bg_brightness','winxp_bg_pos_x','winxp_bg_pos_y','winxp_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['winxp_bg_blur']       ?? 3)));
        $bri  = max(5,   min(100, (int)($vals['winxp_bg_brightness'] ?? 70)));
        $posX = max(0,   min(100, (int)($vals['winxp_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['winxp_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['winxp_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 3; $bri = 70; $posX = 50; $posY = 50; $size = 100; }
    $briFlt = round($bri / 100, 2);
    $bgSize = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #3a6ea5 !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ');
    pointer-events: none;
}
</style>' . "\n";
}

function getYoutubeBgImage() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'youtube_bg_image'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) { return ''; }
}

function injectYoutubeBgStyle() {
    if (getCurrentTheme() !== 'youtube') return;
    $img = getYoutubeBgImage();
    if (empty($img)) return;
    $url = htmlspecialchars(SITE_URL . '/' . ltrim($img, '/'), ENT_QUOTES, 'UTF-8');
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('youtube_bg_blur','youtube_bg_brightness','youtube_bg_pos_x','youtube_bg_pos_y','youtube_bg_size')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        $blur = max(0,   min(20,  (int)($vals['youtube_bg_blur']       ?? 6)));
        $bri  = max(5,   min(100, (int)($vals['youtube_bg_brightness'] ?? 28)));
        $posX = max(0,   min(100, (int)($vals['youtube_bg_pos_x']      ?? 50)));
        $posY = max(0,   min(100, (int)($vals['youtube_bg_pos_y']      ?? 50)));
        $size = max(100, min(300, (int)($vals['youtube_bg_size']       ?? 100)));
    } catch (Exception $e) { $blur = 6; $bri = 28; $posX = 50; $posY = 50; $size = 100; }
    $briFlt = round($bri / 100, 2);
    $bgSize = $size === 100 ? 'cover' : $size . '%';
    echo '<style>
html {
    background-color: #0F0F0F !important;
    overflow-x: hidden;
}
body {
    margin: 0 !important;
    background-color: transparent !important;
    background-image: none !important;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: -40px; left: -40px; right: -40px; bottom: -40px;
    z-index: -1;
    background-image: url("' . $url . '");
    background-size: ' . $bgSize . ';
    background-position: ' . $posX . '% ' . $posY . '%;
    background-repeat: no-repeat;
    filter: blur(' . $blur . 'px) brightness(' . $briFlt . ') saturate(0.8);
    pointer-events: none;
}
</style>' . "\n";
}


/**
 * Gibt das gespeicherte Custom CSS als <style>-Tag aus.
 * Wird nur ausgegeben wenn custom_css_enabled=1 gesetzt ist.
 * Wird automatisch deaktiviert wenn ein Theme gewechselt wird.
 */
function injectCustomCss() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('custom_css', 'custom_css_enabled')");
        $stmt->execute();
        $vals = [];
        while ($r = $stmt->fetch()) $vals[$r['setting_key']] = $r['setting_value'];
        if (($vals['custom_css_enabled'] ?? '0') !== '1') return;
        $css = trim($vals['custom_css'] ?? '');
        if (!empty($css)) {
            echo '<style id="custom-css-global">' . $css . '</style>' . "\n";
        }
    } catch (Exception $e) {
        // silently ignore
    }
}

function requireLogin() {
    $user = new User();
    if (!$user->isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireRole($roles) {
    $user = new User();
    if (!$user->hasRole($roles)) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function translateStatus($status) {
    global $translator;
    if ($translator) {
        return $translator->translate('status_' . $status);
    }
    $fallback = [
        'open' => 'Open', 'in_progress' => 'In Progress',
        'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'
    ];
    return $fallback[$status] ?? $status;
}

function translatePriority($priority) {
    global $translator;
    if ($translator) {
        return $translator->translate('priority_' . $priority);
    }
    $fallback = [
        'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'
    ];
    return $fallback[$priority] ?? $priority;
}

function translateLevel($level) {
    global $translator;
    if ($translator) {
        return $translator->translate('level_' . $level);
    }
    $fallback = [
        'first_level' => 'First Level',
        'second_level' => 'Second Level',
        'third_level' => 'Third Level'
    ];
    return $fallback[$level] ?? $level;
}

function translateRole($role) {
    global $translator;
    if ($translator) {
        return $translator->translate('role_' . $role);
    }
    $fallback = [
        'user' => 'User', 'first_level' => 'First Level Support',
        'second_level' => 'Second Level Support',
        'third_level' => 'Third Level Support',
        'admin' => 'Administrator'
    ];
    return $fallback[$role] ?? $role;
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function safe_strimwidth($string, $start, $width, $trimmarker = '...') {
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($string, $start, $width, $trimmarker);
    }

    // Fallback ohne mbstring
    if (strlen($string) <= $width) {
        return $string;
    }

    $trimmarker_len = strlen($trimmarker);
    if ($width <= $trimmarker_len) {
        return substr($string, $start, $width);
    }

    return substr($string, $start, $width - $trimmarker_len) . $trimmarker;
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = 'UTF-8') {
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = 'UTF-8') {
        return strlen($string);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = 'UTF-8') {
        return strtoupper($string);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = 'UTF-8') {
        return strtolower($string);
    }
}

