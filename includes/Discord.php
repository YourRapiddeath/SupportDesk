<?php
class Discord {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Einstellungen laden ─────────────────────────────────────────────────
    public function getSettings(): array {
        try {
            $stmt = $this->db->query(
                "SELECT setting_key, setting_value FROM settings
                 WHERE setting_key LIKE 'discord_%'"
            );
            $raw = [];
            while ($r = $stmt->fetch()) {
                $raw[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $raw);
    }

    // ── Standard-Einstellungen ──────────────────────────────────────────────
    public static function defaults(): array {
        return [
            'discord_enabled'            => '0',
            'discord_webhook_url'        => '',
            'discord_bot_name'           => 'SupportBot',
            'discord_bot_avatar_url'     => '',
            'discord_embed_color'        => '5793266',
            'discord_mention_role_id'    => '',
            'discord_notify_new_ticket'  => '1',
            'discord_notify_new_reply'   => '0',
            'discord_notify_status_change' => '0',
            'discord_notify_closed'      => '0',
            'discord_embed_title'        => '🎫 Neues Support-Ticket',
            'discord_embed_description'  => 'Ein neues Ticket wurde erstellt.',
            'discord_show_subject'       => '1',
            'discord_show_description'   => '1',
            'discord_show_priority'      => '1',
            'discord_show_category'      => '1',
            'discord_show_username'      => '1',
            'discord_show_ticket_url'    => '1',
            'discord_footer_text'        => '{{site_name}} Support-System',
            'discord_custom_keys'        => '',   // JSON: [{name,value,inline}]
        ];
    }

    // ── Einstellung speichern ───────────────────────────────────────────────
    public function saveSetting(string $key, string $value) {
        $this->db->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([$key, $value]);
    }

    // ── Farbe als Integer oder Hex umwandeln ────────────────────────────────
    public static function hexToInt(string $hex): int {
        $hex = ltrim($hex, '#');
        return hexdec($hex);
    }

    public static function intToHex(int $color): string {
        return '#' . str_pad(dechex($color), 6, '0', STR_PAD_LEFT);
    }

    // ── Platzhalter ersetzen ────────────────────────────────────────────────
    private function replacePlaceholders(string $text, array $data): string {
        $map = [
            '{{ticket_code}}'    => $data['ticket_code']   ?? '',
            '{{subject}}'        => $data['subject']       ?? '',
            '{{description}}'    => mb_substr($data['description'] ?? '', 0, 200) . (mb_strlen($data['description'] ?? '') > 200 ? '…' : ''),
            '{{priority}}'       => $this->translatePriority($data['priority'] ?? ''),
            '{{status}}'         => $this->translateStatus($data['status']   ?? ''),
            '{{category}}'       => $data['category_name'] ?? 'Keine Kategorie',
            '{{username}}'       => $data['user_name']     ?? '',
            '{{email}}'          => $data['user_email']    ?? '',
            '{{ticket_url}}'     => SITE_URL . '/support/view-ticket.php?id=' . ($data['id'] ?? ''),
            '{{site_name}}'      => defined('SITE_NAME') ? SITE_NAME : 'Support',
            '{{date}}'           => date('d.m.Y H:i'),
        ];
        return str_replace(array_keys($map), array_values($map), $text);
    }

    // ── Neues Ticket – Benachrichtigung ─────────────────────────────────────
    public function notifyNewTicket(array $ticket): bool {
        $cfg = $this->getSettings();
        if (empty($cfg['discord_enabled']) || $cfg['discord_enabled'] !== '1') return false;
        if (empty($cfg['discord_webhook_url'])) return false;
        if (empty($cfg['discord_notify_new_ticket']) || $cfg['discord_notify_new_ticket'] !== '1') return false;

        $fields = [];

        if (!empty($cfg['discord_show_subject']) && $cfg['discord_show_subject'] === '1') {
            $fields[] = ['name' => '📋 Betreff', 'value' => escape_discord($ticket['subject'] ?? '-'), 'inline' => false];
        }
        if (!empty($cfg['discord_show_description']) && $cfg['discord_show_description'] === '1') {
            $desc = mb_substr($ticket['description'] ?? '-', 0, 300);
            if (mb_strlen($ticket['description'] ?? '') > 300) $desc .= '…';
            $fields[] = ['name' => '📝 Beschreibung', 'value' => escape_discord($desc), 'inline' => false];
        }
        if (!empty($cfg['discord_show_priority']) && $cfg['discord_show_priority'] === '1') {
            $pMap = ['low' => '🟢 Niedrig', 'medium' => '🟡 Mittel', 'high' => '🔴 Hoch', 'urgent' => '🚨 Dringend'];
            $fields[] = ['name' => '⚡ Priorität', 'value' => $pMap[$ticket['priority'] ?? 'medium'] ?? 'Mittel', 'inline' => true];
        }
        if (!empty($cfg['discord_show_category']) && $cfg['discord_show_category'] === '1') {
            $fields[] = ['name' => '🏷️ Kategorie', 'value' => escape_discord($ticket['category_name'] ?? 'Keine'), 'inline' => true];
        }
        if (!empty($cfg['discord_show_username']) && $cfg['discord_show_username'] === '1') {
            $fields[] = ['name' => '👤 Erstellt von', 'value' => escape_discord($ticket['user_name'] ?? '-'), 'inline' => true];
        }

        // Custom Keys
        if (!empty($cfg['discord_custom_keys'])) {
            $customKeys = json_decode($cfg['discord_custom_keys'], true) ?? [];
            foreach ($customKeys as $ck) {
                if (!empty($ck['name']) && !empty($ck['value'])) {
                    $fields[] = [
                        'name'   => escape_discord($this->replacePlaceholders($ck['name'], $ticket)),
                        'value'  => escape_discord($this->replacePlaceholders($ck['value'], $ticket)),
                        'inline' => !empty($ck['inline']),
                    ];
                }
            }
        }

        $embed = [
            'title'       => $this->replacePlaceholders($cfg['discord_embed_title'], $ticket),
            'description' => $this->replacePlaceholders($cfg['discord_embed_description'], $ticket),
            'color'       => (int)($cfg['discord_embed_color'] ?? 5793266),
            'fields'      => $fields,
            'footer'      => ['text' => $this->replacePlaceholders($cfg['discord_footer_text'], $ticket)],
            'timestamp'   => date('c'),
        ];

        if (!empty($cfg['discord_show_ticket_url']) && $cfg['discord_show_ticket_url'] === '1') {
            $embed['url'] = SITE_URL . '/support/view-ticket.php?id=' . $ticket['id'];
        }

        $content = '';
        if (!empty($cfg['discord_mention_role_id'])) {
            $roleId = preg_replace('/\D/', '', $cfg['discord_mention_role_id']);
            if ($roleId) $content = '<@&' . $roleId . '>';
        }

        return $this->sendWebhook($cfg['discord_webhook_url'], $content, [$embed], $cfg);
    }

    public function notifyNewReply(array $ticket, string $replyMessage, string $replierName): bool {
        $cfg = $this->getSettings();
        if (empty($cfg['discord_enabled']) || $cfg['discord_enabled'] !== '1') return false;
        if (empty($cfg['discord_webhook_url'])) return false;
        if (empty($cfg['discord_notify_new_reply']) || $cfg['discord_notify_new_reply'] !== '1') return false;

        $fields = [
            ['name' => '📋 Ticket', 'value' => escape_discord(($ticket['ticket_code'] ?? '') . ' – ' . ($ticket['subject'] ?? '')), 'inline' => false],
            ['name' => '💬 Nachricht', 'value' => escape_discord(mb_substr($replyMessage, 0, 300) . (mb_strlen($replyMessage) > 300 ? '…' : '')), 'inline' => false],
            ['name' => '👤 Von', 'value' => escape_discord($replierName), 'inline' => true],
            ['name' => '📊 Status', 'value' => $this->translateStatus($ticket['status'] ?? 'open'), 'inline' => true],
        ];

        $embed = [
            'title'       => '💬 Neue Antwort auf Ticket ' . ($ticket['ticket_code'] ?? ''),
            'color'       => 3066993,  // Grün
            'fields'      => $fields,
            'footer'      => ['text' => $this->replacePlaceholders($cfg['discord_footer_text'], $ticket)],
            'timestamp'   => date('c'),
            'url'         => SITE_URL . '/support/view-ticket.php?id=' . ($ticket['id'] ?? ''),
        ];

        return $this->sendWebhook($cfg['discord_webhook_url'], '', [$embed], $cfg);
    }

    public function notifyStatusChange(array $ticket, string $oldStatus, string $newStatus): bool {
        $cfg = $this->getSettings();
        if (empty($cfg['discord_enabled']) || $cfg['discord_enabled'] !== '1') return false;
        if (empty($cfg['discord_webhook_url'])) return false;
        if (empty($cfg['discord_notify_status_change']) || $cfg['discord_notify_status_change'] !== '1') return false;

        // Bei geschlossen extra prüfen
        if ($newStatus === 'closed' && ($cfg['discord_notify_closed'] !== '1')) return false;

        $colorMap = [
            'open'        => 15158332,  // Rot
            'in_progress' => 15844367,  // Orange
            'pending'     => 16776960,  // Gelb
            'resolved'    => 3066993,   // Grün
            'closed'      => 9807270,   // Grau
        ];

        $embed = [
            'title'   => '🔄 Ticket-Status geändert',
            'color'   => $colorMap[$newStatus] ?? 9807270,
            'fields'  => [
                ['name' => '📋 Ticket', 'value' => escape_discord(($ticket['ticket_code'] ?? '') . ' – ' . ($ticket['subject'] ?? '')), 'inline' => false],
                ['name' => '↩️ Vorher', 'value' => $this->translateStatus($oldStatus), 'inline' => true],
                ['name' => '✅ Jetzt',  'value' => $this->translateStatus($newStatus),  'inline' => true],
                ['name' => '👤 Ersteller', 'value' => escape_discord($ticket['user_name'] ?? '-'), 'inline' => true],
            ],
            'footer'    => ['text' => $this->replacePlaceholders($cfg['discord_footer_text'], $ticket)],
            'timestamp' => date('c'),
            'url'       => SITE_URL . '/support/view-ticket.php?id=' . ($ticket['id'] ?? ''),
        ];

        return $this->sendWebhook($cfg['discord_webhook_url'], '', [$embed], $cfg);
    }

    // ── Webhook senden ──────────────────────────────────────────────────────
    private function sendWebhook(string $url, string $content, array $embeds, array $cfg): bool {
        if (empty($url)) return false;

        $payload = [
            'username'   => $cfg['discord_bot_name'] ?: 'SupportBot',
            'avatar_url' => $cfg['discord_bot_avatar_url'] ?: null,
            'content'    => $content,
            'embeds'     => $embeds,
        ];

        $payload = array_filter($payload, function($v) { return $v !== null && $v !== ''; });

        $json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError || ($httpCode < 200 || $httpCode >= 300)) {
            error_log('[Discord Webhook] Fehler: HTTP ' . $httpCode . ' | ' . $curlError . ' | Response: ' . $response);
            return false;
        }
        return true;
    }

    public function sendTestMessage(string $webhookUrl, string $botName, string $avatarUrl): array {
        $payload = [
            'username'   => $botName ?: 'SupportBot',
            'avatar_url' => $avatarUrl ?: null,
            'embeds'     => [[
                'title'       => '✅ Verbindungstest erfolgreich!',
                'description' => 'Der Discord-Webhook wurde erfolgreich konfiguriert. Dieser Bot wird euch über neue Support-Tickets benachrichtigen.',
                'color'       => 5793266,
                'fields'      => [
                    ['name' => '🌐 System', 'value' => defined('SITE_NAME') ? SITE_NAME : 'Support', 'inline' => true],
                    ['name' => '📅 Datum',  'value' => date('d.m.Y H:i'), 'inline' => true],
                ],
                'footer'    => ['text' => 'Testbenachrichtigung · ' . (defined('SITE_NAME') ? SITE_NAME : 'Support')],
                'timestamp' => date('c'),
            ]],
        ];

        $payload = array_filter($payload, function($v) { return $v !== null && $v !== ''; });
        $json = json_encode($payload);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) return ['ok' => false, 'error' => 'cURL-Fehler: ' . $curlError];
        if ($httpCode < 200 || $httpCode >= 300) {
            $resp = json_decode($response, true);
            return ['ok' => false, 'error' => 'HTTP ' . $httpCode . ': ' . ($resp['message'] ?? $response)];
        }
        return ['ok' => true];
    }


    private function translateStatus(string $status): string {
        return ['open' => 'Offen', 'in_progress' => 'In Bearbeitung', 'pending' => 'Ausstehend',
                'resolved' => 'Gelöst', 'closed' => 'Geschlossen'][$status] ?? $status;
    }

    private function translatePriority(string $priority): string {
        return ['low' => 'Niedrig', 'medium' => 'Mittel', 'high' => 'Hoch',
                'urgent' => 'Dringend'][$priority] ?? $priority;
    }
}

function escape_discord(string $text): string {
    $text = str_replace(['`', '*', '_', '~', '|', '>'], ['\\`', '\\*', '\\_', '\\~', '\\|', '\\>'], $text);
    return mb_substr($text, 0, 1024);
}

