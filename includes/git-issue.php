<?php
define('INSTALL_RUNNING', true);
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/GitIntegration.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['first_level','second_level','third_level','admin'])) {
    http_response_code(403); exit;
}

header('Content-Type: application/json; charset=utf-8');
$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$git    = new GitIntegration();

// Alle aktiven Integrationen
if ($action === 'list') {
    echo json_encode($git->getAll(true)); exit;
}

// Issue erstellen
if ($action === 'create') {
    $integrationId = (int)($_POST['integration_id'] ?? 0);
    $ticketId      = (int)($_POST['ticket_id']      ?? 0);
    $title         = trim($_POST['title']            ?? '');
    $body          = trim($_POST['body']             ?? '');
    $labels        = trim($_POST['labels']           ?? '');
    $assignees     = trim($_POST['assignees']        ?? '');
    if (!$integrationId || !$ticketId || !$title) {
        echo json_encode(['ok' => false, 'error' => 'Pflichtfelder fehlen.']); exit;
    }
    $result = $git->createIssue($integrationId, [
        'ticket_id' => $ticketId, 'title' => $title, 'body'  => $body,
        'labels'    => $labels,   'assignees' => $assignees,
    ], $userId);
    echo json_encode($result); exit;
}

// Issues für Ticket
if ($action === 'ticket_issues') {
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    echo json_encode($git->getIssuesForTicket($ticketId)); exit;
}

echo json_encode(['error' => 'unknown_action']);

