<?php
/**
 * Discord AJAX-Handler
 */
ob_start(); // Alle unerwarteten Ausgaben (Warnings, Notices) abfangen

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/Discord.php';

header('Content-Type: application/json; charset=utf-8');

// Nicht-JSON-Ausgaben vor dem Header abfangen
if (ob_get_length()) ob_clean();

requireLogin();
requireRole('admin');

$db      = Database::getInstance()->getConnection();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $discord = new Discord();
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB-Fehler: ' . $e->getMessage()]);
    exit;
}

switch ($action) {

    // ── Test-Nachricht senden ────────────────────────────────────────────────
    case 'test_webhook':
        $url    = trim($_POST['webhook_url'] ?? '');
        $name   = trim($_POST['bot_name']    ?? 'SupportBot');
        $avatar = trim($_POST['avatar_url']  ?? '');

        if (empty($url)) {
            echo json_encode(['ok' => false, 'error' => 'Webhook-URL ist leer.']);
            exit;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'discord.com/api/webhooks') === false) {
            echo json_encode(['ok' => false, 'error' => 'Ungültige Discord-Webhook-URL.']);
            exit;
        }

        try {
            $result = $discord->sendTestMessage($url, $name, $avatar);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;

    // ── Einstellungen speichern ─────────────────────────────────────────────
    case 'save_settings':
        try {
            $allowed = array_keys(Discord::defaults());
            $saved   = 0;
            foreach ($allowed as $key) {
                if (array_key_exists($key, $_POST)) {
                    $discord->saveSetting($key, (string)$_POST[$key]);
                    $saved++;
                }
            }
            if (array_key_exists('discord_custom_keys', $_POST)) {
                $discord->saveSetting('discord_custom_keys', (string)$_POST['discord_custom_keys']);
                $saved++;
            }
            echo json_encode(['ok' => true, 'saved' => $saved]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Speicherfehler: ' . $e->getMessage()]);
        }
        exit;

    // ── Einstellungen laden ─────────────────────────────────────────────────
    case 'get_settings':
        try {
            echo json_encode(['ok' => true, 'settings' => $discord->getSettings()]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'settings' => Discord::defaults()]);
        }
        exit;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);
        exit;
}
