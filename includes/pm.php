<?php
/**
 * Private Messages API – Supporter Postfach
 */


define('INSTALL_RUNNING', true);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['first_level','second_level','third_level','admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error', 'message' => $e->getMessage()]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function pmEscape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($action === 'unread_count') {
    // Zählt Root-Threads mit mindestens einer ungelesenen Nachricht
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT COALESCE(pm.parent_id, pm.id))
        FROM private_messages pm
        JOIN private_messages root
          ON root.id = COALESCE(pm.parent_id, pm.id)
        WHERE pm.receiver_id = ?
          AND pm.is_read = 0
          AND pm.deleted_receiver = 0
          AND pm.trashed_receiver = 0
          AND root.deleted_receiver = 0
          AND root.trashed_receiver = 0
          AND (pm.sender_id != ? OR pm.sender_id IS NULL)
    ");
    $stmt->execute([$userId, $userId]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit;
}

if ($action === 'inbox') {
    $stmt = $db->prepare("
        SELECT
            root.id,
            root.sender_id,
            root.receiver_id,
            root.subject,
            root.deleted_receiver,
            root.created_at,
            root.ticket_id,
            COALESCE(last_msg.message, root.message, '') AS message,
            COALESCE(last_msg.created_at, root.created_at) AS last_activity,
            COALESCE(unread.cnt, 0) AS unread_cnt,
            sender.full_name AS sender_name,
            sender.avatar AS sender_avatar
        FROM private_messages root
        LEFT JOIN users sender ON sender.id = root.sender_id
        LEFT JOIN (
            SELECT pm2.parent_id, pm2.message, pm2.created_at
            FROM private_messages pm2
            INNER JOIN (
                SELECT parent_id, MAX(id) AS max_id
                FROM private_messages
                WHERE parent_id IS NOT NULL
                GROUP BY parent_id
            ) newest ON newest.max_id = pm2.id
        ) last_msg ON last_msg.parent_id = root.id
        LEFT JOIN (
            SELECT COALESCE(parent_id, id) AS thread_root, COUNT(*) AS cnt
            FROM private_messages
            WHERE receiver_id = ? AND is_read = 0 AND (sender_id IS NULL OR sender_id != ?)
            GROUP BY thread_root
        ) unread ON unread.thread_root = root.id
        WHERE root.parent_id IS NULL
          AND root.deleted_receiver = 0
          AND root.trashed_receiver = 0
          AND root.receiver_id = ?
        GROUP BY root.id
        ORDER BY last_activity DESC
        LIMIT 100
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['is_read']          = ((int)$row['unread_cnt'] === 0) ? 1 : 0;
        $row['is_system_sender'] = ($row['sender_id'] === null) ? 1 : 0;
        if ($row['is_system_sender']) {
            $row['sender_name']   = 'System';
            $row['sender_avatar'] = null;
        }
    }
    unset($row);
    echo json_encode($rows);
    exit;
}

if ($action === 'sent') {
    $stmt = $db->prepare("
        SELECT
            root.id,
            root.sender_id,
            root.receiver_id,
            root.subject,
            root.deleted_sender,
            root.created_at,
            COALESCE(last_msg.message, root.message) AS message,
            COALESCE(last_msg.created_at, root.created_at) AS last_activity,
            1 AS is_read,
            recv.full_name AS receiver_name,
            recv.avatar AS receiver_avatar
        FROM private_messages root
        JOIN users recv ON recv.id = root.receiver_id
        LEFT JOIN (
            SELECT parent_id, message, created_at
            FROM private_messages
            WHERE parent_id IS NOT NULL
            ORDER BY created_at DESC
        ) last_msg ON last_msg.parent_id = root.id
        WHERE root.parent_id IS NULL
          AND root.deleted_sender = 0
          AND root.trashed_sender  = 0
          AND (
              root.sender_id = ?
              OR EXISTS (
                  SELECT 1 FROM private_messages replies
                  WHERE replies.parent_id = root.id
                    AND replies.sender_id = ?
              )
          )
        GROUP BY root.id
        ORDER BY last_activity DESC
        LIMIT 100
    ");
    $stmt->execute([$userId, $userId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
if ($action === 'view') {
    $msgId = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT pm.*,
               s.full_name AS sender_name, s.avatar AS sender_avatar,
               r.full_name AS receiver_name, r.avatar AS receiver_avatar
        FROM private_messages pm
        LEFT JOIN users s ON s.id = pm.sender_id
        JOIN  users r ON r.id = pm.receiver_id
        WHERE pm.id = ?
          AND (pm.receiver_id = ? OR pm.sender_id = ?)
    ");
    $stmt->execute([$msgId, $userId, $userId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$msg) { echo json_encode(['error' => 'not_found']); exit; }

    $msg['is_system_sender'] = ($msg['sender_id'] === null) ? 1 : 0;
    if ($msg['is_system_sender']) {
        $msg['sender_name']   = 'System';
        $msg['sender_avatar'] = null;
    }

    if ($msg['receiver_id'] == $userId && !$msg['is_read']) {
        $db->prepare("UPDATE private_messages SET is_read = 1 WHERE id = ?")->execute([$msgId]);
        $msg['is_read'] = 1;
    }

    $tStmt = $db->prepare("
        SELECT pm.*,
               s.full_name AS sender_name, s.avatar AS sender_avatar
        FROM private_messages pm
        LEFT JOIN users s ON s.id = pm.sender_id
        WHERE pm.parent_id = ?
        ORDER BY pm.created_at ASC
    ");
    $tStmt->execute([$msgId]);
    $thread = $tStmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadIds = array_column(
        array_filter($thread, function($t) use ($userId) {
            return $t['receiver_id'] == $userId && !$t['is_read'];
        }),
        'id'
    );
    if ($unreadIds) {
        $ph = implode(',', array_fill(0, count($unreadIds), '?'));
        $db->prepare("UPDATE private_messages SET is_read = 1 WHERE id IN ($ph)")
           ->execute($unreadIds);
    }

    foreach ($thread as &$t) {
        $t['is_system_sender'] = ($t['sender_id'] === null) ? 1 : 0;
        if ($t['is_system_sender']) {
            $t['sender_name']   = 'System';
            $t['sender_avatar'] = null;
        }
    }
    unset($t);

    echo json_encode(['message' => $msg, 'thread' => $thread]);
    exit;
}

if ($action === 'send') {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $subject    = trim($_POST['subject']      ?? '');
    $message    = trim($_POST['message']      ?? '');
    $parentId   = (int)($_POST['parent_id']   ?? 0) ?: null;

    if (!$receiverId || empty($message)) {
        echo json_encode(['ok' => false, 'error' => 'required_fields_missing']); exit;
    }
    if ($receiverId === $userId) {
        echo json_encode(['ok' => false, 'error' => 'cannot_message_self']); exit;
    }

    // Check receiver is a supporter
    $rStmt = $db->prepare("SELECT id, full_name FROM users WHERE id = ? AND role IN ('first_level','second_level','third_level','admin')");
    $rStmt->execute([$receiverId]);
    $receiver = $rStmt->fetch();
    if (!$receiver) { echo json_encode(['ok' => false, 'error' => 'receiver_not_found']); exit; }

    // Bei Antwort: subject vom Parent übernehmen
    if ($parentId) {
        $pStmt = $db->prepare("SELECT subject, receiver_id, sender_id FROM private_messages WHERE id = ?");
        $pStmt->execute([$parentId]);
        $parent = $pStmt->fetch();
        if ($parent) {
            $subject = 'Re: ' . ltrim(preg_replace('/^(Re:\s*)+/i', '', $parent['subject']));
        }
    }

    $subject = mb_substr($subject ?: '(no subject)', 0, 255);
    $message = mb_substr($message, 0, 10000);

    $stmt = $db->prepare("INSERT INTO private_messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, $receiverId, $subject, $message, $parentId]);
    $newId = $db->lastInsertId();

    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

if ($action === 'delete') {
    $msgId = (int)($_POST['id'] ?? 0);
    $stmt  = $db->prepare("SELECT sender_id, receiver_id, parent_id FROM private_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    if (!$msg) { echo json_encode(['ok' => false]); exit; }

    if ($msg['receiver_id'] == $userId) {
        // Root-PM + alle Replies in Papierkorb verschieben
        $db->prepare("UPDATE private_messages SET trashed_receiver = 1, deleted_receiver = 0 WHERE id = ?")->execute([$msgId]);
        $db->prepare("UPDATE private_messages SET trashed_receiver = 1, deleted_receiver = 0 WHERE parent_id = ?")->execute([$msgId]);
    } elseif ($msg['sender_id'] == $userId) {
        $db->prepare("UPDATE private_messages SET trashed_sender = 1, deleted_sender = 0 WHERE id = ?")->execute([$msgId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'trash') {
    $stmt = $db->prepare("
        SELECT
            root.id,
            root.sender_id,
            root.receiver_id,
            root.subject,
            root.created_at,
            root.ticket_id,
            COALESCE(last_msg.message, root.message, '') AS message,
            COALESCE(last_msg.created_at, root.created_at) AS last_activity,
            1 AS is_read,
            sender.full_name AS sender_name,
            sender.avatar    AS sender_avatar,
            recv.full_name   AS receiver_name
        FROM private_messages root
        LEFT JOIN users sender ON sender.id = root.sender_id
        LEFT JOIN users recv   ON recv.id   = root.receiver_id
        LEFT JOIN (
            SELECT pm2.parent_id, pm2.message, pm2.created_at
            FROM private_messages pm2
            INNER JOIN (
                SELECT parent_id, MAX(id) AS max_id
                FROM private_messages WHERE parent_id IS NOT NULL GROUP BY parent_id
            ) newest ON newest.max_id = pm2.id
        ) last_msg ON last_msg.parent_id = root.id
        WHERE root.parent_id IS NULL
          AND (
              (root.receiver_id = ? AND root.trashed_receiver = 1)
              OR (root.sender_id   = ? AND root.trashed_sender   = 1)
          )
        GROUP BY root.id
        ORDER BY last_activity DESC
        LIMIT 100
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['is_system_sender'] = ($row['sender_id'] === null) ? 1 : 0;
        if ($row['is_system_sender']) { $row['sender_name'] = 'System'; $row['sender_avatar'] = null; }
        // Welche Seite hat gelöscht?
        $row['trashed_as'] = ($row['receiver_id'] == $userId) ? 'receiver' : 'sender';
    }
    unset($row);
    echo json_encode($rows);
    exit;
}

if ($action === 'restore') {
    $msgId = (int)($_POST['id'] ?? 0);
    $stmt  = $db->prepare("SELECT sender_id, receiver_id FROM private_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    if (!$msg) { echo json_encode(['ok' => false]); exit; }

    if ($msg['receiver_id'] == $userId) {
        $db->prepare("UPDATE private_messages SET trashed_receiver = 0 WHERE id = ?")->execute([$msgId]);
        $db->prepare("UPDATE private_messages SET trashed_receiver = 0 WHERE parent_id = ?")->execute([$msgId]);
    } elseif ($msg['sender_id'] == $userId) {
        $db->prepare("UPDATE private_messages SET trashed_sender = 0 WHERE id = ?")->execute([$msgId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete_permanent') {
    $msgId = (int)($_POST['id'] ?? 0);
    $stmt  = $db->prepare("SELECT sender_id, receiver_id FROM private_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    if (!$msg) { echo json_encode(['ok' => false]); exit; }

    if ($msg['receiver_id'] == $userId) {
        $db->prepare("UPDATE private_messages SET deleted_receiver = 1, trashed_receiver = 0 WHERE id = ?")->execute([$msgId]);
        $db->prepare("UPDATE private_messages SET deleted_receiver = 1, trashed_receiver = 0 WHERE parent_id = ?")->execute([$msgId]);
    } elseif ($msg['sender_id'] == $userId) {
        $db->prepare("UPDATE private_messages SET deleted_sender = 1, trashed_sender = 0 WHERE id = ?")->execute([$msgId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'empty_trash') {
    // Alle im Papierkorb des Users endgültig als gelöscht markieren
    $db->prepare("UPDATE private_messages SET deleted_receiver = 1, trashed_receiver = 0
                  WHERE receiver_id = ? AND trashed_receiver = 1 AND parent_id IS NULL")->execute([$userId]);
    $db->prepare("UPDATE private_messages SET deleted_receiver = 1, trashed_receiver = 0
                  WHERE receiver_id = ? AND trashed_receiver = 1 AND parent_id IS NOT NULL")->execute([$userId]);
    $db->prepare("UPDATE private_messages SET deleted_sender = 1, trashed_sender = 0
                  WHERE sender_id = ? AND trashed_sender = 1")->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'supporters') {
    $stmt = $db->prepare("
        SELECT id, full_name, avatar, role
        FROM users
        WHERE role IN ('first_level','second_level','third_level','admin')
          AND id != ?
        ORDER BY full_name ASC
    ");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'mark_read') {
    $msgId = (int)($_POST['id'] ?? 0);
    $db->prepare("UPDATE private_messages SET is_read = 1 WHERE id = ? AND receiver_id = ?")->execute([$msgId, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'unknown_action']);

