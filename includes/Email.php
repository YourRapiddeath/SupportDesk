<?php
class Email {
    private $db;
    private $settings = [];
    public static function getPlaceholders(): array {
        return [
            '{{site_name}}'       => 'Name der Website (aus Einstellungen)',
            '{{year}}'            => 'Aktuelles Jahr (z.B. 2026)',
            '{{ticket_code}}'     => 'Ticket-Code (z.B. TKT-20260001)',
            '{{ticket_url}}'      => 'Direkter Link zum Ticket',
            '{{subject}}'         => 'Betreff des Tickets',
            '{{description}}'     => 'Beschreibung des Tickets',
            '{{status}}'          => 'Aktueller Ticket-Status (übersetzt)',
            '{{priority}}'        => 'Priorität des Tickets (übersetzt)',
            '{{support_level}}'   => 'Support-Level (übersetzt)',
            '{{customer_name}}'   => 'Vollständiger Name des Kunden',
            '{{customer_email}}'  => 'E-Mail-Adresse des Kunden',
            '{{supporter_name}}'  => 'Vollständiger Name des Supporters',
            '{{reply_message}}'   => 'Nachrichtentext (bei Antwort-Mails)',
        ];
    }

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadSettings();
    }

    // Tabelle via database.sql angelegt – keine DDL im PHP-Code

    // ── Settings laden ───────────────────────────────────────────────────────
    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // ── Template aus DB holen ────────────────────────────────────────────────
    private function getTemplate($slug) {
        $stmt = $this->db->prepare(
            "SELECT * FROM email_templates WHERE slug = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Platzhalter ersetzen ─────────────────────────────────────────────────
    private function replacePlaceholders(string $text, array $vars): string {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string)$value, $text);
        }
        return $text;
    }

    private function baseVars(array $ticket): array {
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        return [
            'site_name'     => $this->settings['site_name']   ?? 'Support System',
            'year'          => date('Y'),
            'ticket_code'   => $ticket['ticket_code']          ?? '',
            'ticket_url'    => $siteUrl . '/tickets/view-ticket.php?code=' . ($ticket['ticket_code'] ?? ''),
            'subject'       => $ticket['subject']              ?? '',
            'description'   => $ticket['description']          ?? '',
            'status'        => $this->translateStatus($ticket['status']               ?? ''),
            'priority'      => $this->translatePriority($ticket['priority']           ?? ''),
            'support_level' => $this->translateLevel($ticket['support_level']         ?? ''),
            'customer_name' => $ticket['customer_name']        ?? ($ticket['full_name'] ?? ''),
            'customer_email'=> $ticket['customer_email']       ?? ($ticket['email']    ?? ''),
            'supporter_name'=> $ticket['supporter_name']       ?? '',
            'reply_message' => $ticket['reply_message']        ?? '',
        ];
    }

    // ── Mailer ───────────────────────────────────────────────────────────────
    private function sendMail(string $to, string $subject, string $bodyHtml, string $bodyText): bool {
        $from     = $this->settings['smtp_from_email'] ?? 'noreply@localhost';
        $fromName = $this->settings['smtp_from_name']  ?? 'Support';
        $boundary = '----=_Part_' . md5(uniqid());

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyText)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyHtml)) . "\r\n";
        $body .= "--{$boundary}--";

        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return mail($to, $encSubject, $body, $headers);
    }

    // ── Öffentliche Send-Methoden ────────────────────────────────────────────
    public function sendTicketCreatedEmail(array $ticketData, string $userEmail): bool {
        if (!$this->notificationsEnabled()) return false;
        $tpl = $this->getTemplate('ticket_created');
        if (!$tpl) return false;

        $vars    = $this->baseVars($ticketData);
        $subject = $this->replacePlaceholders($tpl['subject'],   $vars);
        $html    = $this->replacePlaceholders($tpl['body_html'], $vars);
        $text    = $this->replacePlaceholders($tpl['body_text'], $vars);

        return $this->sendMail($userEmail, $subject, $html, $text);
    }

    public function sendTicketUpdatedEmail(array $ticketData, string $userEmail, string $updateMessage): bool {
        if (!$this->notificationsEnabled()) return false;
        $tpl = $this->getTemplate('ticket_updated');
        if (!$tpl) return false;

        $vars = $this->baseVars($ticketData);
        $vars['reply_message'] = $updateMessage;

        $subject = $this->replacePlaceholders($tpl['subject'],   $vars);
        $html    = $this->replacePlaceholders($tpl['body_html'], $vars);
        $text    = $this->replacePlaceholders($tpl['body_text'], $vars);

        return $this->sendMail($userEmail, $subject, $html, $text);
    }

    public function sendTicketAssignedEmail(array $ticketData, string $supporterEmail): bool {
        if (!$this->notificationsEnabled()) return false;
        $tpl = $this->getTemplate('ticket_assigned');
        if (!$tpl) return false;

        $vars    = $this->baseVars($ticketData);
        $subject = $this->replacePlaceholders($tpl['subject'],   $vars);
        $html    = $this->replacePlaceholders($tpl['body_html'], $vars);
        $text    = $this->replacePlaceholders($tpl['body_text'], $vars);

        return $this->sendMail($supporterEmail, $subject, $html, $text);
    }

    public function sendNewMessageToSupporter(array $ticketData, string $supporterEmail, string $message): bool {
        if (!$this->notificationsEnabled()) return false;
        $tpl = $this->getTemplate('ticket_new_message_supporter');
        if (!$tpl) return false;

        $vars = $this->baseVars($ticketData);
        $vars['reply_message'] = $message;

        $subject = $this->replacePlaceholders($tpl['subject'],   $vars);
        $html    = $this->replacePlaceholders($tpl['body_html'], $vars);
        $text    = $this->replacePlaceholders($tpl['body_text'], $vars);

        return $this->sendMail($supporterEmail, $subject, $html, $text);
    }

    // ── Hilfs-Methoden ───────────────────────────────────────────────────────
    private function notificationsEnabled(): bool {
        return !empty($this->settings['email_notifications'])
            && $this->settings['email_notifications'] === '1';
    }

    public function translateStatus(string $s): string {
        global $translator;
        if ($translator) return $translator->translate('status_' . $s);
        return ['open'=>'Open','in_progress'=>'In Progress','pending'=>'Pending',
                'resolved'=>'Resolved','closed'=>'Closed'][$s] ?? $s;
    }

    public function translatePriority(string $p): string {
        global $translator;
        if ($translator) return $translator->translate('priority_' . $p);
        return ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'][$p] ?? $p;
    }

    public function translateLevel(string $l): string {
        global $translator;
        if ($translator) return $translator->translate('level_' . $l);
        return ['first_level'=>'First Level','second_level'=>'Second Level',
                'third_level'=>'Third Level'][$l] ?? $l;
    }
}
