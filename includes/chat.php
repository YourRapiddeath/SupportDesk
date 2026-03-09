<?php
/**
 * Internal Supporter Chat – API endpoint
 */
define('INSTALL_RUNNING', true); // API-Endpunkt – kein Install-Redirect
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error'=>'not_logged_in']); exit; }
$role    = $_SESSION['role'] ?? '';
$isAdmin = $role === 'admin';
if (!in_array($role, ['first_level','second_level','third_level','admin'])) {
    http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
}

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

try {
    $db     = Database::getInstance()->getConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'db_error: ' . $e->getMessage()]); exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Chat settings helper ──────────────────────────────────────────────────────
function getChatSettings(PDO $db): array {
    try {
        $keys = ['chat_enabled','chat_max_length','chat_emojis','chat_gifs'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $rows = [];
    }
    return [
        'enabled'    => ($rows['chat_enabled']    ?? '1') === '1',
        'max_length' => (int)($rows['chat_max_length'] ?? 2000),
        'emojis'     => ($rows['chat_emojis']     ?? '1') === '1',
        'gifs'       => ($rows['chat_gifs']       ?? '0') === '1',
    ];
}

function saveChatSettings(PDO $db, array $map): bool {
    try {
        $stmt = $db->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($map as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }
        return true;
    } catch (Exception $e) {
        error_log('[Chat] saveChatSettings error: ' . $e->getMessage());
        return false;
    }
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Chat settings (public read) ───────────────────────────────────────────────
if ($action === 'settings') {
    echo json_encode(getChatSettings($db));
    exit;
}

// ── Admin: update chat settings ───────────────────────────────────────────────
if ($action === 'update_settings') {
    if (!$isAdmin) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
    $map = [
        'chat_enabled'    => (int)($_POST['enabled']    ?? 1),
        'chat_max_length' => max(50, min(5000, (int)($_POST['max_length'] ?? 2000))),
        'chat_emojis'     => (int)($_POST['emojis']     ?? 1),
        'chat_gifs'       => (int)($_POST['gifs']       ?? 0),
    ];
    $ok = saveChatSettings($db, $map);
    echo json_encode(['ok' => $ok]);
    exit;
}

// ── Admin: send global/system message ────────────────────────────────────────
if ($action === 'broadcast') {
    if (!$isAdmin) { http_response_code(403); exit; }
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) { echo json_encode(['ok' => false]); exit; }
    $msg = mb_substr($msg, 0, 1000);
    $stmt = $db->prepare("INSERT INTO supporter_chat (user_id, message, is_system) VALUES (?,?,1)");
    $stmt->execute([$userId, $msg]);
    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    exit;
}

// ── Check if chat is enabled ──────────────────────────────────────────────────
$chatSettings = getChatSettings($db);
if (!$chatSettings['enabled'] && $action !== 'get_sound') {
    echo json_encode(['error' => 'chat_disabled', 'settings' => $chatSettings]);
    exit;
}

// ── GET messages ──────────────────────────────────────────────────────────────
if ($action === 'messages') {
    $sinceInt = (int)($_GET['since'] ?? 0);
    $stmt = $db->prepare("
        SELECT c.id, c.user_id, c.message, c.is_system, c.created_at,
               COALESCE(u.full_name, 'System') AS full_name,
               u.avatar
        FROM supporter_chat c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.id > ?
        ORDER BY c.id ASC
        LIMIT 100
    ");
    $stmt->execute([$sinceInt]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lr = $db->prepare("SELECT last_read FROM supporter_chat_read WHERE user_id = ?");
    $lr->execute([$userId]);
    $lastRead = $lr->fetchColumn() ?: '1970-01-01 00:00:00';

    $uc = $db->prepare("SELECT COUNT(*) FROM supporter_chat WHERE user_id != ? AND created_at > ?");
    $uc->execute([$userId, $lastRead]);
    $unread = (int)$uc->fetchColumn();

    echo json_encode(['messages' => $rows, 'unread' => $unread, 'settings' => $chatSettings]);
    exit;
}

// ── POST message ──────────────────────────────────────────────────────────────
if ($action === 'send') {
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) { echo json_encode(['ok' => false]); exit; }
    $msg = mb_substr($msg, 0, $chatSettings['max_length']);
    // Strip emojis if disabled
    if (!$chatSettings['emojis']) {
        $msg = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $msg);
    }
    $stmt = $db->prepare("INSERT INTO supporter_chat (user_id, message) VALUES (?,?)");
    $stmt->execute([$userId, $msg]);
    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    exit;
}

// ── Mark as read ──────────────────────────────────────────────────────────────
if ($action === 'read') {
    $stmt = $db->prepare("INSERT INTO supporter_chat_read (user_id, last_read) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_read = NOW()");
    $stmt->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Sound preference ──────────────────────────────────────────────────────────
if ($action === 'sound') {
    $val = (int)($_POST['enabled'] ?? 1);
    $stmt = $db->prepare("UPDATE users SET chat_sound = ? WHERE id = ?");
    $stmt->execute([$val, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'get_sound') {
    $stmt = $db->prepare("SELECT chat_sound FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    echo json_encode(['sound' => (int)$stmt->fetchColumn()]);
    exit;
}

echo json_encode(['error' => 'unknown action']);

