<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/Email.php';

requireLogin();
requireRole('admin');

$db      = Database::getInstance()->getConnection();
$success = '';
$error   = '';

// Tabellen und Standard-Templates via database.sql angelegt

// Standard-Templates via database.sql eingespielt – keine Init-Logik nötig

// ── POST: Vorlage speichern ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id        = (int)($_POST['tpl_id'] ?? 0);
    $subject   = trim($_POST['tpl_subject']   ?? '');
    $bodyHtml  = $_POST['tpl_body_html']  ?? '';
    $bodyText  = $_POST['tpl_body_text']  ?? '';
    $isActive  = isset($_POST['tpl_active']) ? 1 : 0;

    if (!$id || empty($subject)) {
        'Dein Ticket {{ticket_code}} wurde erstellt – {{site_name}}',
        '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#1d4ed8,#1e40af);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{display:inline-block;background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#1d4ed8;letter-spacing:1px;margin-bottom:24px}
.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>🎫 Ticket erstellt</h1><p>{{site_name}} – Support</p></div>
  <div class="body">
    <p>Hallo <strong>{{customer_name}}</strong>,</p>
    <p>dein Support-Ticket wurde erfolgreich erstellt. Hier sind deine Ticket-Details:</p>
    <div class="ticket-code">{{ticket_code}}</div>
    <div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div>
    <div class="info-row"><div class="lbl">Status</div>{{status}}</div>
    <div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div>
    <div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div>
    <p style="margin-top:20px">Sobald unser Team antwortet, erhältst du eine E-Mail-Benachrichtigung.</p>
    <a href="{{ticket_url}}" class="btn">Ticket ansehen →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht – bitte nicht direkt antworten.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'Hallo {{customer_name}},

dein Support-Ticket wurde erstellt.

Ticket-Code : {{ticket_code}}
Betreff     : {{subject}}
Status      : {{status}}
Priorität   : {{priority}}

Ticket aufrufen: {{ticket_url}}

-- {{site_name}}'
    ]);

    // ── 2. Ticket aktualisiert (Kunde) ────────────────────────────────────────
    $ins->execute(['ticket_updated', 'Ticket aktualisiert (Kunde)', 'Wird gesendet wenn ein Supporter auf das Ticket antwortet.',
        'Neue Antwort auf Ticket {{ticket_code}} – {{site_name}}',
        '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#059669,#047857);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{font-size:16px;font-weight:700;color:#1d4ed8;margin-bottom:20px}
.reply-box{background:#f0fdf4;border-left:4px solid #10b981;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}
.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#059669;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>💬 Neue Antwort</h1><p>Ticket {{ticket_code}}</p></div>
  <div class="body">
    <p>Hallo <strong>{{customer_name}}</strong>,</p>
    <p>es gibt eine neue Antwort auf dein Support-Ticket.</p>
    <div class="ticket-code">🎫 {{ticket_code}} – {{subject}}</div>
    <div class="reply-box"><strong>Antwort von {{supporter_name}}:</strong><br><br>{{reply_message}}</div>
    <div class="info-row"><div class="lbl">Aktueller Status</div>{{status}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket ansehen &amp; antworten →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht – bitte nicht direkt antworten.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'Hallo {{customer_name}},

{{supporter_name}} hat auf dein Ticket geantwortet.

Ticket  : {{ticket_code}} – {{subject}}
Status  : {{status}}

Antwort:
{{reply_message}}

Ticket aufrufen: {{ticket_url}}

-- {{site_name}}'
    ]);

    // ── 3. Ticket zugewiesen (Supporter) ─────────────────────────────────────
    $ins->execute(['ticket_assigned', 'Ticket zugewiesen (Supporter)', 'Wird an den Supporter gesendet wenn ihm ein Ticket zugewiesen wird.',
        'Neues Ticket zugewiesen: {{ticket_code}} – {{site_name}}',
        '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#7c3aed,#5b21b6);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{display:inline-block;background:#f5f3ff;border:2px solid #7c3aed;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#7c3aed;margin-bottom:24px}
.info-row{background:#f8fafc;border-left:3px solid #7c3aed;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#7c3aed;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>📬 Neues Ticket zugewiesen</h1><p>{{site_name}} – Support-Team</p></div>
  <div class="body">
    <p>Hallo <strong>{{supporter_name}}</strong>,</p>
    <p>dir wurde ein neues Support-Ticket zugewiesen:</p>
    <div class="ticket-code">{{ticket_code}}</div>
    <div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div>
    <div class="info-row"><div class="lbl">Kundenname</div>{{customer_name}}</div>
    <div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div>
    <div class="info-row"><div class="lbl">Support-Level</div>{{support_level}}</div>
    <div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket bearbeiten →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht – bitte nicht direkt antworten.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'Hallo {{supporter_name}},

dir wurde ein neues Ticket zugewiesen.

Ticket-Code  : {{ticket_code}}
Betreff      : {{subject}}
Kunde        : {{customer_name}}
Priorität    : {{priority}}
Support-Level: {{support_level}}

Ticket bearbeiten: {{ticket_url}}

-- {{site_name}}'
    ]);

    // ── 4. Neue Kunden-Nachricht (Supporter) ─────────────────────────────────
    $ins->execute(['ticket_new_message_supporter', 'Neue Kunden-Nachricht (Supporter)', 'Wird an den Supporter gesendet wenn der Kunde antwortet.',
        'Neue Nachricht vom Kunden: {{ticket_code}} – {{site_name}}',
        '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#f59e0b,#d97706);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.85);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{font-size:16px;font-weight:700;color:#1d4ed8;margin-bottom:20px}
.msg-box{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}
.info-row{background:#f8fafc;border-left:3px solid #f59e0b;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#d97706;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>✉️ Neue Kunden-Nachricht</h1><p>Ticket {{ticket_code}}</p></div>
  <div class="body">
    <p>Hallo <strong>{{supporter_name}}</strong>,</p>
    <p>der Kunde <strong>{{customer_name}}</strong> hat auf sein Ticket geantwortet:</p>
    <div class="ticket-code">🎫 {{ticket_code}} – {{subject}}</div>
    <div class="msg-box">{{reply_message}}</div>
    <div class="info-row"><div class="lbl">Aktueller Status</div>{{status}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket öffnen →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht – bitte nicht direkt antworten.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'Hallo {{supporter_name}},

{{customer_name}} hat auf Ticket {{ticket_code}} geantwortet.

Betreff: {{subject}}
Status : {{status}}

Nachricht:
{{reply_message}}

Ticket: {{ticket_url}}

-- {{site_name}}'
    ]);
}

// ── Leere Templates mit Standard-Inhalten befüllen (Migrations-Fix) ──────────
$defaults_fill = [

    'ticket_created' => [
        'subject'   => 'Dein Ticket {{ticket_code}} wurde erstellt – {{site_name}}',
        'body_html' => '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#1d4ed8,#1e40af);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{display:inline-block;background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#1d4ed8;letter-spacing:1px;margin-bottom:24px}
.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>🎫 Ticket erstellt</h1><p>{{site_name}} – Support</p></div>
  <div class="body">
    <p>Hallo <strong>{{customer_name}}</strong>,</p>
    <p>dein Support-Ticket wurde erfolgreich erstellt.</p>
    <div class="ticket-code">{{ticket_code}}</div>
    <div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div>
    <div class="info-row"><div class="lbl">Status</div>{{status}}</div>
    <div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div>
    <div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div>
    <p style="margin-top:20px">Sobald unser Team antwortet, erhältst du eine Benachrichtigung.</p>
    <a href="{{ticket_url}}" class="btn">Ticket ansehen →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'body_text' => "Hallo {{customer_name}},\n\ndein Ticket wurde erstellt.\n\nTicket-Code : {{ticket_code}}\nBetreff     : {{subject}}\nStatus      : {{status}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
    ],

    'ticket_updated' => [
        'subject'   => 'Neue Antwort auf Ticket {{ticket_code}} – {{site_name}}',
        'body_html' => '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#059669,#047857);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.reply-box{background:#f0fdf4;border-left:4px solid #10b981;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}
.info-row{background:#f8fafc;border-left:3px solid #3b82f6;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#059669;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>💬 Neue Antwort</h1><p>Ticket {{ticket_code}}</p></div>
  <div class="body">
    <p>Hallo <strong>{{customer_name}}</strong>,</p>
    <p>es gibt eine neue Antwort auf dein Ticket <strong>{{ticket_code}}</strong>.</p>
    <div class="reply-box"><strong>Antwort von {{supporter_name}}:</strong><br><br>{{reply_message}}</div>
    <div class="info-row"><div class="lbl">Aktueller Status</div>{{status}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket ansehen &amp; antworten →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'body_text' => "Hallo {{customer_name}},\n\n{{supporter_name}} hat auf dein Ticket geantwortet.\n\nTicket : {{ticket_code}} – {{subject}}\nStatus : {{status}}\n\nAntwort:\n{{reply_message}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
    ],

    'ticket_assigned' => [
        'subject'   => 'Neues Ticket zugewiesen: {{ticket_code}} – {{site_name}}',
        'body_html' => '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#7c3aed,#5b21b6);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.ticket-code{display:inline-block;background:#f5f3ff;border:2px solid #7c3aed;border-radius:8px;padding:12px 20px;font-size:20px;font-weight:700;color:#7c3aed;margin-bottom:24px}
.info-row{background:#f8fafc;border-left:3px solid #7c3aed;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#7c3aed;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>📬 Neues Ticket zugewiesen</h1><p>{{site_name}} – Support-Team</p></div>
  <div class="body">
    <p>Hallo <strong>{{supporter_name}}</strong>,</p>
    <p>dir wurde ein neues Support-Ticket zugewiesen:</p>
    <div class="ticket-code">{{ticket_code}}</div>
    <div class="info-row"><div class="lbl">Betreff</div>{{subject}}</div>
    <div class="info-row"><div class="lbl">Kundenname</div>{{customer_name}}</div>
    <div class="info-row"><div class="lbl">Priorität</div>{{priority}}</div>
    <div class="info-row"><div class="lbl">Support-Level</div>{{support_level}}</div>
    <div class="info-row"><div class="lbl">Beschreibung</div>{{description}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket bearbeiten →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'body_text' => "Hallo {{supporter_name}},\n\ndir wurde ein neues Ticket zugewiesen.\n\nTicket-Code  : {{ticket_code}}\nBetreff      : {{subject}}\nKunde        : {{customer_name}}\nPriorität    : {{priority}}\nSupport-Level: {{support_level}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
    ],

    'ticket_new_message_supporter' => [
        'subject'   => 'Neue Nachricht vom Kunden: {{ticket_code}} – {{site_name}}',
        'body_html' => '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#333}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#f59e0b,#d97706);padding:32px 36px;text-align:center}
.header h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.header p{color:rgba(255,255,255,.85);margin:6px 0 0;font-size:14px}
.body{padding:32px 36px}
.msg-box{background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;padding:16px 18px;margin:20px 0;font-size:14px;line-height:1.6}
.info-row{background:#f8fafc;border-left:3px solid #f59e0b;border-radius:4px;padding:10px 14px;margin-bottom:10px;font-size:14px}
.info-row .lbl{font-weight:700;color:#555;margin-bottom:3px}
.btn{display:inline-block;background:#d97706;color:#fff;padding:12px 28px;border-radius:7px;text-decoration:none;font-weight:700;font-size:14px;margin-top:20px}
.footer{background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;font-size:12px;color:#9ca3af;text-align:center}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>✉️ Neue Kunden-Nachricht</h1><p>Ticket {{ticket_code}}</p></div>
  <div class="body">
    <p>Hallo <strong>{{supporter_name}}</strong>,</p>
    <p>der Kunde <strong>{{customer_name}}</strong> hat auf sein Ticket geantwortet:</p>
    <div style="font-size:16px;font-weight:700;color:#1d4ed8;margin-bottom:16px">🎫 {{ticket_code}} – {{subject}}</div>
    <div class="msg-box">{{reply_message}}</div>
    <div class="info-row"><div class="lbl">Aktueller Status</div>{{status}}</div>
    <a href="{{ticket_url}}" class="btn">Ticket öffnen →</a>
  </div>
  <div class="footer">Dies ist eine automatische Nachricht.<br>&copy; {{year}} {{site_name}}</div>
</div></body></html>',
        'body_text' => "Hallo {{supporter_name}},\n\n{{customer_name}} hat auf Ticket {{ticket_code}} geantwortet.\n\nBetreff: {{subject}}\nStatus : {{status}}\n\nNachricht:\n{{reply_message}}\n\n{{ticket_url}}\n\n-- {{site_name}}",
    ],
];

// Update leere Felder (betrifft bestehende Installationen die nur leere Einträge haben)
$upd = $db->prepare("UPDATE email_templates SET subject=?, body_html=?, body_text=? WHERE slug=? AND (body_html='' OR body_html IS NULL)");
foreach ($defaults_fill as $slug => $data) {
    $upd->execute([$data['subject'], $data['body_html'], $data['body_text'], $slug]);
}
// Fehlende Slugs komplett einfügen
$upsert = $db->prepare("INSERT IGNORE INTO email_templates (slug,name,description,subject,body_html,body_text) VALUES (?,?,?,?,?,?)");
$names = [
    'ticket_created'               => ['Ticket erstellt (Kunde)',          'Wird gesendet wenn ein neues Ticket erstellt wird.'],
    'ticket_updated'               => ['Ticket aktualisiert (Kunde)',      'Wird gesendet wenn ein Supporter antwortet.'],
    'ticket_assigned'              => ['Ticket zugewiesen (Supporter)',    'Wird an den Supporter gesendet bei Zuweisung.'],
    'ticket_new_message_supporter' => ['Neue Kunden-Nachricht (Supporter)','Wird an den Supporter gesendet wenn der Kunde antwortet.'],
];
foreach ($defaults_fill as $slug => $data) {
    $upsert->execute([$slug, $names[$slug][0], $names[$slug][1], $data['subject'], $data['body_html'], $data['body_text']]);
}

// ── POST: Vorlage speichern ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id        = (int)($_POST['tpl_id'] ?? 0);
    $subject   = trim($_POST['tpl_subject']   ?? '');
    $bodyHtml  = $_POST['tpl_body_html']  ?? '';
    $bodyText  = $_POST['tpl_body_text']  ?? '';
    $isActive  = isset($_POST['tpl_active']) ? 1 : 0;

    if (!$id || empty($subject)) {
        $error = 'Betreff darf nicht leer sein.';
    } else {
        $stmt = $db->prepare("UPDATE email_templates
            SET subject=?, body_html=?, body_text=?, is_active=?, updated_at=NOW()
            WHERE id=?");
        if ($stmt->execute([$subject, $bodyHtml, $bodyText, $isActive, $id])) {
            $success = 'Vorlage gespeichert.';
        } else {
            $error = 'Fehler beim Speichern.';
        }
    }
}

// ── POST: Vorlage auf Standard zurücksetzen (Reset) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_template'])) {
    // Reload default from Email.php static defaults would be ideal,
    // but since they're in SQL we just flag a notice
    $success = 'Bitte lege die Standardinhalte manuell über database.sql ein oder trage sie direkt ein.';
}

// ── Alle Templates laden ──────────────────────────────────────────────────────
$templates = $db->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Aktive Vorlage bestimmen (über GET oder ersten Eintrag)
$activeSlug = $_GET['tpl'] ?? ($templates[0]['slug'] ?? '');
$activeTpl  = null;
foreach ($templates as $t) {
    if ($t['slug'] === $activeSlug) { $activeTpl = $t; break; }
}
if (!$activeTpl && !empty($templates)) {
    $activeTpl  = $templates[0];
    $activeSlug = $activeTpl['slug'];
}

$placeholders = Email::getPlaceholders();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail-Vorlagen – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
    /* ── Layout ─────────────────────────────────────────────────────────────── */
    .etpl-wrap  { max-width: 1400px; margin: 2rem auto; padding: 0 1.25rem; display: flex; gap: 1.5rem; align-items: flex-start; }
    .etpl-sidebar { width: 280px; flex-shrink: 0; }
    .etpl-main    { flex: 1; min-width: 0; }

    /* ── Seiten-Header ──────────────────────────────────────────────────────── */
    .etpl-page-header {
        max-width: 1400px; margin: 2rem auto 0; padding: 0 1.25rem;
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
    }
    .etpl-page-header h1 { font-size: 1.5rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: .5rem; }
    .etpl-page-header p  { margin: .2rem 0 0; color: var(--text-light); font-size: .88rem; }

    /* ── Sidebar ────────────────────────────────────────────────────────────── */
    .tpl-nav-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .tpl-nav-card-hd {
        padding: .75rem 1rem;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--text-light);
        border-bottom: 1px solid var(--border);
        background: var(--background);
    }
    .tpl-nav-item {
        display: block;
        padding: .85rem 1rem;
        text-decoration: none;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        font-size: .88rem;
        transition: background .15s;
        position: relative;
    }
    .tpl-nav-item:last-child { border-bottom: none; }
    .tpl-nav-item:hover      { background: var(--background); }
    .tpl-nav-item.active     { background: var(--primary); color: #fff; }
    .tpl-nav-item.active .tpl-nav-sub { color: rgba(255,255,255,.7); }
    .tpl-nav-title { font-weight: 700; display: block; margin-bottom: .15rem; }
    .tpl-nav-sub   { font-size: .75rem; color: var(--text-light); display: block; }
    .tpl-status-dot {
        width: 8px; height: 8px; border-radius: 50%;
        display: inline-block; margin-right: .4rem; flex-shrink: 0;
    }
    .tpl-status-dot.on  { background: #10b981; }
    .tpl-status-dot.off { background: #ef4444; }

    /* ── Platzhalter-Karte ──────────────────────────────────────────────────── */
    .ph-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: 14px; overflow: hidden; }
    .ph-card-hd { padding: .75rem 1rem; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text-light); border-bottom: 1px solid var(--border); background: var(--background); }
    .ph-item { padding: .5rem 1rem; border-bottom: 1px solid var(--border); font-size: .82rem; cursor: pointer; transition: background .15s; }
    .ph-item:last-child { border-bottom: none; }
    .ph-item:hover { background: var(--background); }
    .ph-code { font-family: monospace; background: var(--background); border: 1px solid var(--border); border-radius: 4px; padding: .1rem .4rem; font-size: .78rem; color: var(--primary); user-select: all; }
    .ph-desc { color: var(--text-light); font-size: .75rem; margin-top: .15rem; }

    /* ── Editor-Card ────────────────────────────────────────────────────────── */
    .editor-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: 14px; overflow: hidden; }
    .editor-card-hd {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: .75rem;
    }
    .editor-card-title { font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
    .editor-card-desc  { font-size: .82rem; color: var(--text-light); margin-top: .15rem; }

    /* ── Tabs (HTML / Text / Vorschau) ──────────────────────────────────────── */
    .editor-tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--background); }
    .editor-tab {
        padding: .6rem 1.25rem;
        font-size: .85rem; font-weight: 600;
        color: var(--text-light);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: color .15s, border-color .15s;
        user-select: none;
    }
    .editor-tab:hover   { color: var(--text); }
    .editor-tab.active  { color: var(--primary); border-bottom-color: var(--primary); }

    /* ── Formular-Felder ────────────────────────────────────────────────────── */
    .etpl-group { padding: 1.25rem; border-bottom: 1px solid var(--border); }
    .etpl-group:last-child { border-bottom: none; }
    .etpl-label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--text-light); margin-bottom: .5rem; }
    .etpl-input, .etpl-textarea {
        width: 100%; box-sizing: border-box;
        padding: .6rem .85rem;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        background: var(--background);
        color: var(--text);
        font-size: .9rem;
        font-family: inherit;
        transition: border-color .2s;
    }
    .etpl-input:focus, .etpl-textarea:focus { outline: none; border-color: var(--primary); }
    .etpl-textarea { resize: vertical; min-height: 340px; font-family: 'Consolas', 'Monaco', monospace; font-size: .82rem; line-height: 1.55; }
    .etpl-textarea.prose { font-family: inherit; min-height: 240px; }

    /* ── Vorschau ───────────────────────────────────────────────────────────── */
    #preview-frame { width: 100%; min-height: 500px; border: none; background: #fff; border-radius: 0 0 12px 12px; }

    /* ── Toolbar ────────────────────────────────────────────────────────────── */
    .html-toolbar {
        display: flex; flex-wrap: wrap; gap: .3rem;
        padding: .5rem .85rem;
        background: var(--background);
        border-bottom: 1px solid var(--border);
    }
    .html-toolbar button {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 5px; padding: .2rem .5rem;
        font-size: .78rem; cursor: pointer; color: var(--text);
        transition: background .15s;
    }
    .html-toolbar button:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    .toolbar-sep { width: 1px; background: var(--border); margin: 0 .25rem; align-self: stretch; }

    /* ── Aktionsleiste unten ────────────────────────────────────────────────── */
    .editor-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border); background: var(--background); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; }
    .active-toggle { display: flex; align-items: center; gap: .6rem; font-size: .88rem; cursor: pointer; }
    .toggle-switch { position: relative; width: 40px; height: 22px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-track { position: absolute; inset: 0; background: #6b7280; border-radius: 22px; transition: .2s; cursor: pointer; }
    .toggle-track::before { content:''; position:absolute; width:16px; height:16px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
    input:checked + .toggle-track { background: var(--primary); }
    input:checked + .toggle-track::before { transform: translateX(18px); }

    /* ── Buttons ────────────────────────────────────────────────────────────── */
    .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem 1.1rem; border: none; border-radius: 8px; font-size: .88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .15s; }
    .btn:hover { opacity: .85; }
    .btn-primary   { background: var(--primary); color: #fff; }
    .btn-secondary { background: var(--background); border: 1.5px solid var(--border); color: var(--text); }
    .btn-sm        { padding: .35rem .75rem; font-size: .8rem; }

    /* ── Alerts ─────────────────────────────────────────────────────────────── */
    .alert { border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1.25rem; }
    .alert-success { background: #dcfce722; border: 1px solid #86efac; color: #166534; }
    .alert-error   { background: #fee2e222; border: 1px solid #fca5a5; color: #991b1b; }

    /* ── Letzte Änderung ────────────────────────────────────────────────────── */
    .last-updated { font-size: .75rem; color: var(--text-light); }

    @media (max-width: 900px) {
        .etpl-wrap { flex-direction: column; }
        .etpl-sidebar { width: 100%; }
    }
    </style>
</head>
<body>
<?php require_once '../includes/navbar.php'; ?>

<!-- Seiten-Header -->
<div class="etpl-page-header">
    <div>
        <h1>✉️ E-Mail-Vorlagen</h1>
        <p>Alle E-Mails die das System automatisch versendet – bearbeitbar mit HTML und Platzhaltern</p>
    </div>
    <a href="settings.php" class="btn btn-secondary btn-sm">← Zurück zu Einstellungen</a>
</div>

<div class="etpl-wrap">

    <!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════ -->
    <div class="etpl-sidebar">

        <!-- Vorlagen-Navigation -->
        <div class="tpl-nav-card">
            <div class="tpl-nav-card-hd">📧 Vorlagen</div>
            <?php foreach ($templates as $t): ?>
                <a class="tpl-nav-item <?= $t['slug'] === $activeSlug ? 'active' : '' ?>"
                   href="email-templates.php?tpl=<?= urlencode($t['slug']) ?>">
                    <span class="tpl-nav-title">
                        <span class="tpl-status-dot <?= $t['is_active'] ? 'on' : 'off' ?>"></span>
                        <?= escape($t['name']) ?>
                    </span>
                    <span class="tpl-nav-sub"><?= escape(mb_strimwidth($t['description'], 0, 55, '…')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Platzhalter-Referenz -->
        <div class="ph-card">
            <div class="ph-card-hd">🔖 Platzhalter</div>
            <?php foreach ($placeholders as $ph => $desc): ?>
                <div class="ph-item" onclick="insertPlaceholder('<?= escape($ph) ?>')" title="Klicken zum Einfügen">
                    <div class="ph-code"><?= escape($ph) ?></div>
                    <div class="ph-desc"><?= escape($desc) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- ═══ HAUPTBEREICH ══════════════════════════════════════════════════════ -->
    <div class="etpl-main">

        <?php if ($success): ?><div class="alert alert-success">✅ <?= escape($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-error">❌ <?= escape($error) ?></div><?php endif; ?>

        <?php if ($activeTpl): ?>
        <form method="post" action="email-templates.php?tpl=<?= urlencode($activeSlug) ?>" id="tpl-form">
            <input type="hidden" name="save_template" value="1">
            <input type="hidden" name="tpl_id"       value="<?= (int)$activeTpl['id'] ?>">

            <div class="editor-card">
                <!-- Card-Header -->
                <div class="editor-card-hd">
                    <div>
                        <div class="editor-card-title">
                            <?= escape($activeTpl['name']) ?>
                        </div>
                        <div class="editor-card-desc"><?= escape($activeTpl['description']) ?></div>
                    </div>
                    <?php if ($activeTpl['updated_at']): ?>
                        <span class="last-updated">Zuletzt geändert: <?= date('d.m.Y H:i', strtotime($activeTpl['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Betreff -->
                <div class="etpl-group">
                    <label class="etpl-label" for="tpl_subject">Betreff</label>
                    <input class="etpl-input" type="text" id="tpl_subject" name="tpl_subject"
                           value="<?= escape($activeTpl['subject']) ?>"
                           placeholder="Betreffzeile – Platzhalter erlaubt">
                </div>

                <!-- Tab-Leiste -->
                <div class="editor-tabs">
                    <div class="editor-tab active" data-tab="html"    onclick="switchTab('html')">🖥️ HTML-Version</div>
                    <div class="editor-tab"        data-tab="text"    onclick="switchTab('text')">📄 Plaintext-Fallback</div>
                    <div class="editor-tab"        data-tab="preview" onclick="switchTab('preview')">👁 Vorschau</div>
                </div>

                <!-- HTML-Editor -->
                <div id="tab-html">
                    <div class="html-toolbar">
                        <button type="button" onclick="wrapHtml('<strong>','</strong>')"><b>B</b></button>
                        <button type="button" onclick="wrapHtml('<em>','</em>')"><i>I</i></button>
                        <button type="button" onclick="wrapHtml('<u>','</u>')"><u>U</u></button>
                        <div class="toolbar-sep"></div>
                        <button type="button" onclick="wrapHtml('<h1>','</h1>')">H1</button>
                        <button type="button" onclick="wrapHtml('<h2>','</h2>')">H2</button>
                        <button type="button" onclick="wrapHtml('<p>','</p>')">¶</button>
                        <div class="toolbar-sep"></div>
                        <button type="button" onclick="wrapHtml('<a href=\'#\'>','</a>')">🔗</button>
                        <button type="button" onclick="insertHtml('<br>')">↵ BR</button>
                        <button type="button" onclick="insertHtml('<hr style=\'border:none;border-top:1px solid #e5e7eb;margin:16px 0\'>')">─ HR</button>
                        <div class="toolbar-sep"></div>
                        <button type="button" onclick="wrapHtml('<div style=\'background:#f9fafb;border-left:3px solid #3b82f6;padding:10px 14px;margin:8px 0\'>','</div>')">Box</button>
                        <button type="button" onclick="wrapHtml('<span style=\'color:#1d4ed8;font-weight:700\'>','</span>')">🎨 Blau</button>
                        <button type="button" onclick="insertHtml('{{ticket_code}}')">🎫 Code</button>
                        <button type="button" onclick="insertHtml('{{ticket_url}}')">🔗 URL</button>
                    </div>
                    <div class="etpl-group" style="padding-top:.75rem;">
                        <textarea class="etpl-textarea" id="tpl_body_html" name="tpl_body_html"
                                  rows="20"><?= escape($activeTpl['body_html']) ?></textarea>
                    </div>
                </div>

                <!-- Plaintext-Editor -->
                <div id="tab-text" style="display:none;">
                    <div class="etpl-group">
                        <label class="etpl-label">Plaintext-Version
                            <span style="font-weight:400;text-transform:none;"> – wird angezeigt wenn der E-Mail-Client kein HTML unterstützt</span>
                        </label>
                        <textarea class="etpl-textarea prose" id="tpl_body_text" name="tpl_body_text"
                                  rows="18"><?= escape($activeTpl['body_text']) ?></textarea>
                    </div>
                </div>

                <!-- Vorschau -->
                <div id="tab-preview" style="display:none;">
                    <div style="padding:.75rem 1.25rem; background:var(--background); border-bottom:1px solid var(--border); font-size:.82rem; color:var(--text-light);">
                        ⚠️ Vorschau zeigt den HTML-Code mit Demo-Platzhaltern. Echt versendete Mails enthalten die echten Ticket-Daten.
                    </div>
                    <iframe id="preview-frame" title="E-Mail Vorschau"></iframe>
                </div>

                <!-- Footer: Aktiv-Toggle + Speichern -->
                <div class="editor-footer">
                    <label class="active-toggle">
                        <span class="toggle-switch">
                            <input type="checkbox" id="tpl_active" name="tpl_active"
                                   <?= $activeTpl['is_active'] ? 'checked' : '' ?>>
                            <span class="toggle-track"></span>
                        </span>
                        <span>Vorlage aktiv
                            <small style="color:var(--text-light);margin-left:.3rem;">
                                (deaktiviert = diese Mail wird nicht gesendet)
                            </small>
                        </span>
                    </label>
                    <div style="display:flex;gap:.65rem;align-items:center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="refreshPreview()">👁 Vorschau aktualisieren</button>
                        <button type="submit" class="btn btn-primary">💾 Vorlage speichern</button>
                    </div>
                </div>
            </div>
        </form>

        <?php else: ?>
            <div style="text-align:center;padding:4rem;color:var(--text-light);">
                <div style="font-size:3rem;margin-bottom:1rem;">✉️</div>
                <p>Keine E-Mail-Vorlagen gefunden. Bitte führe die <code>database.sql</code> aus.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
// ── Tab-Switching ─────────────────────────────────────────────────────────────
function switchTab(tab) {
    document.getElementById('tab-html').style.display    = tab === 'html'    ? '' : 'none';
    document.getElementById('tab-text').style.display    = tab === 'text'    ? '' : 'none';
    document.getElementById('tab-preview').style.display = tab === 'preview' ? '' : 'none';
    document.querySelectorAll('.editor-tab').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === tab);
    });
    if (tab === 'preview') refreshPreview();
}

// ── HTML Vorschau ─────────────────────────────────────────────────────────────
const DEMO_VARS = {
    '{{site_name}}'      : '<?= escape(htmlspecialchars_decode(SITE_NAME, ENT_QUOTES)) ?>',
    '{{year}}'           : '<?= date('Y') ?>',
    '{{ticket_code}}'    : 'TKT-20260001',
    '{{ticket_url}}'     : '#',
    '{{subject}}'        : 'Ich brauche Hilfe beim Login',
    '{{description}}'    : 'Ich kann mich seit heute nicht mehr einloggen.',
    '{{status}}'         : 'Offen',
    '{{priority}}'       : 'Mittel',
    '{{support_level}}'  : 'First Level',
    '{{customer_name}}'  : 'Max Mustermann',
    '{{customer_email}}' : 'max@example.com',
    '{{supporter_name}}' : 'Anna Support',
    '{{reply_message}}'  : 'Hallo Max, bitte versuche dein Passwort zurückzusetzen.',
};

function replacePlaceholdersDemo(html) {
    for (const [k, v] of Object.entries(DEMO_VARS)) {
        html = html.replaceAll(k, v);
    }
    return html;
}

function refreshPreview() {
    const html = document.getElementById('tpl_body_html')?.value ?? '';
    const frame = document.getElementById('preview-frame');
    if (!frame) return;
    const doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(replacePlaceholdersDemo(html));
    doc.close();
}

// ── HTML-Toolbar ──────────────────────────────────────────────────────────────
function wrapHtml(before, after) {
    const ta = document.getElementById('tpl_body_html');
    if (!ta) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || 'Text';
    ta.value = ta.value.substring(0, s) + before + sel + after + ta.value.substring(e);
    ta.selectionStart = s + before.length;
    ta.selectionEnd   = s + before.length + sel.length;
    ta.focus();
}
function insertHtml(str) {
    const ta = document.getElementById('tpl_body_html');
    if (!ta) return;
    const s = ta.selectionStart;
    ta.value = ta.value.substring(0, s) + str + ta.value.substring(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = s + str.length;
    ta.focus();
}

// ── Platzhalter einfügen (aktives Tab) ───────────────────────────────────────
function insertPlaceholder(ph) {
    // Aktives Tab herausfinden
    const htmlTab = document.getElementById('tab-html');
    const textTab = document.getElementById('tab-text');
    let ta = null;
    if (htmlTab && htmlTab.style.display !== 'none') ta = document.getElementById('tpl_body_html');
    else if (textTab && textTab.style.display !== 'none') ta = document.getElementById('tpl_body_text');
    else ta = document.getElementById('tpl_subject');
    if (!ta) return;
    const s = ta.selectionStart ?? ta.value.length;
    ta.value = ta.value.substring(0, s) + ph + ta.value.substring(ta.selectionEnd ?? s);
    ta.selectionStart = ta.selectionEnd = s + ph.length;
    ta.focus();
    // Tooltip
    const tip = document.createElement('div');
    tip.textContent = '✅ Eingefügt';
    tip.style.cssText = 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;padding:.4rem 1rem;border-radius:20px;font-size:.82rem;font-weight:600;z-index:9999;pointer-events:none;';
    document.body.appendChild(tip);
    setTimeout(() => tip.remove(), 1500);
}

// ── Strg+S speichern ─────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('tpl-form')?.submit();
    }
});
</script>
</body>
</html>

