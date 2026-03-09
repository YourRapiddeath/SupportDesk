<?php
define('INSTALL_RUNNING', true);
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/YouTrackIntegration.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['first_level','second_level','third_level','admin'])) {
    http_response_code(403); exit;
}

header('Content-Type: application/json; charset=utf-8');
$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$yt     = new YouTrackIntegration();

if ($action === 'list') {
    echo json_encode($yt->getAll(true)); exit;
}

// Issue erstellen
if ($action === 'create') {
    $integrationId = (int)($_POST['integration_id'] ?? 0);
    $ticketId      = (int)($_POST['ticket_id']      ?? 0);
    $summary       = trim($_POST['summary']          ?? '');
    $description   = trim($_POST['description']      ?? '');
    $type          = trim($_POST['type']             ?? '');
    $priority      = trim($_POST['priority']         ?? '');
    $assignee      = trim($_POST['assignee']         ?? '');
    $tags          = trim($_POST['tags']             ?? '');

    if (!$integrationId || !$ticketId || !$summary) {
        echo json_encode(['ok' => false, 'error' => 'Pflichtfelder fehlen.']); exit;
    }
    $result = $yt->createIssue($integrationId, [
        'ticket_id'   => $ticketId,
        'summary'     => $summary,
        'description' => $description,
        'type'        => $type,
        'priority'    => $priority,
        'assignee'    => $assignee,
        'tags'        => $tags,
    ], $userId);
    echo json_encode($result); exit;
}

// Issues für Ticket laden
if ($action === 'ticket_issues') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    echo json_encode($yt->getIssuesForTicket($ticketId)); exit;
}

echo json_encode(['error' => 'unknown_action']);

