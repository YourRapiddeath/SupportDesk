-- =============================================================================
-- Support System – Vollständiges Datenbankschema
-- Version: 2026-03-07
-- Alle Tabellen die das System benötigt in einem einzigen File.
-- Ausführen mit: mysql -u USER -p DBNAME < database.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- TABELLEN
-- =============================================================================

-- users
CREATE TABLE IF NOT EXISTS users (
    id                    INT           AUTO_INCREMENT PRIMARY KEY,
    username              VARCHAR(100)  NOT NULL UNIQUE,
    email                 VARCHAR(255)  NOT NULL UNIQUE,
    password              VARCHAR(255)  NOT NULL,
    full_name             VARCHAR(255)  NOT NULL,
    role                  ENUM('user','first_level','second_level','third_level','admin') DEFAULT 'user',
    avatar                VARCHAR(500)  NULL,
    bio                   TEXT          NULL,
    profile_background    VARCHAR(500)  NULL,
    two_fa_enabled        TINYINT(1)    DEFAULT 0,
    two_fa_secret         VARCHAR(100)  NULL,
    backup_codes          TEXT          NULL,
    chat_sound            TINYINT(1)    DEFAULT 1,
    failed_login_attempts INT           DEFAULT 0,
    locked_until          TIMESTAMP     NULL,
    created_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_login            TIMESTAMP     NULL,
    INDEX idx_role  (role),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_categories
CREATE TABLE IF NOT EXISTS ticket_categories (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    description   TEXT         NULL,
    color         VARCHAR(20)  DEFAULT '#3b82f6',
    support_level ENUM('first_level','second_level','third_level') DEFAULT 'first_level',
    is_active     TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active        (is_active),
    INDEX idx_support_level (support_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- supporter_categories (m:n)
CREATE TABLE IF NOT EXISTS supporter_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    category_id INT NOT NULL,
    UNIQUE KEY uq_supporter_category (user_id, category_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tickets
CREATE TABLE IF NOT EXISTS tickets (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    ticket_code   VARCHAR(20)  NOT NULL UNIQUE,
    user_id       INT          NOT NULL,
    subject       VARCHAR(255) NOT NULL,
    description   TEXT         NOT NULL,
    status        ENUM('open','in_progress','pending','resolved','closed') DEFAULT 'open',
    priority      ENUM('low','medium','high','urgent')                     DEFAULT 'medium',
    assigned_to   INT          NULL,
    category_id   INT          NULL,
    support_level ENUM('first_level','second_level','third_level')         DEFAULT 'first_level',
    dsgvo_consent TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at   TIMESTAMP    NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id)             ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
    INDEX idx_ticket_code   (ticket_code),
    INDEX idx_user          (user_id),
    INDEX idx_status        (status),
    INDEX idx_support_level (support_level),
    INDEX idx_assigned      (assigned_to),
    INDEX idx_category      (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_messages
CREATE TABLE IF NOT EXISTS ticket_messages (
    id          INT        AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT        NOT NULL,
    user_id     INT        NOT NULL,
    message     TEXT       NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_ticket   (ticket_id),
    INDEX idx_internal (is_internal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- message_read_status
CREATE TABLE IF NOT EXISTS message_read_status (
    message_id INT NOT NULL,
    user_id    INT NOT NULL,
    PRIMARY KEY (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_history
CREATE TABLE IF NOT EXISTS ticket_history (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT          NOT NULL,
    user_id    INT          NOT NULL,
    action     VARCHAR(255) NOT NULL,
    old_value  VARCHAR(255) NULL,
    new_value  VARCHAR(255) NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- internal_notes
CREATE TABLE IF NOT EXISTS internal_notes (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT       NOT NULL,
    user_id    INT       NOT NULL,
    note       TEXT      NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_custom_fields
CREATE TABLE IF NOT EXISTS ticket_custom_fields (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    field_name    VARCHAR(100) NOT NULL,
    field_label   VARCHAR(255) NOT NULL,
    field_type    ENUM('text','textarea','number','select','checkbox','date','email','url','phone') NOT NULL DEFAULT 'text',
    field_options TEXT         NULL,
    is_required   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    show_in_list  TINYINT(1)   NOT NULL DEFAULT 0,
    show_public   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order    INT          NOT NULL DEFAULT 99,
    placeholder   VARCHAR(255) NULL,
    help_text     VARCHAR(500) NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_field_values
CREATE TABLE IF NOT EXISTS ticket_field_values (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT       NOT NULL,
    field_id   INT       NOT NULL,
    value      TEXT      NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticket_field (ticket_id, field_id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)              ON DELETE CASCADE,
    FOREIGN KEY (field_id)  REFERENCES ticket_custom_fields(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_field  (field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- settings
CREATE TABLE IF NOT EXISTS settings (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT         NOT NULL,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- response_templates (persönliche Supporter-Vorlagen)
CREATE TABLE IF NOT EXISTS response_templates (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(255) NOT NULL,
    content    TEXT         NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- global_templates (Admin-Vorlagen für alle Supporter)
CREATE TABLE IF NOT EXISTS global_templates (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    content    TEXT         NOT NULL,
    created_by INT          NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_templates
CREATE TABLE IF NOT EXISTS email_templates (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    name        VARCHAR(150) NOT NULL,
    description VARCHAR(255) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_html   LONGTEXT     NOT NULL,
    body_text   LONGTEXT     NOT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- private_messages (Supporter-Postfach + System-Benachrichtigungen)
CREATE TABLE IF NOT EXISTS private_messages (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    sender_id        INT          DEFAULT NULL,
    receiver_id      INT          NOT NULL,
    subject          VARCHAR(255) NOT NULL DEFAULT '',
    message          TEXT         NOT NULL DEFAULT '',
    is_read          TINYINT(1)   DEFAULT 0,
    deleted_sender   TINYINT(1)   DEFAULT 0,
    deleted_receiver TINYINT(1)   DEFAULT 0,
    trashed_sender   TINYINT(1)   DEFAULT 0,
    trashed_receiver TINYINT(1)   DEFAULT 0,
    parent_id        INT          DEFAULT NULL,
    ticket_id        INT          DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receiver (receiver_id),
    INDEX idx_sender   (sender_id),
    INDEX idx_parent   (parent_id),
    INDEX idx_ticket   (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- supporter_chat
CREATE TABLE IF NOT EXISTS supporter_chat (
    id         INT        AUTO_INCREMENT PRIMARY KEY,
    user_id    INT        NOT NULL,
    message    TEXT       NOT NULL,
    is_system  TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- supporter_chat_read
CREATE TABLE IF NOT EXISTS supporter_chat_read (
    user_id   INT       NOT NULL,
    last_read TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kb_categories
CREATE TABLE IF NOT EXISTS kb_categories (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    description TEXT         NULL,
    icon        VARCHAR(10)  DEFAULT '📁',
    sort_order  INT          DEFAULT 0,
    created_by  INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kb_articles
CREATE TABLE IF NOT EXISTS kb_articles (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    category_id  INT          NOT NULL,
    title        VARCHAR(255) NOT NULL,
    content      LONGTEXT     NOT NULL,
    tags         VARCHAR(500) DEFAULT '',
    is_published TINYINT(1)   DEFAULT 1,
    views        INT          DEFAULT 0,
    created_by   INT          NOT NULL,
    updated_by   INT          NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE CASCADE,
    INDEX idx_category  (category_id),
    INDEX idx_published (is_published),
    FULLTEXT INDEX ft_search (title, content, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kb_editors
CREATE TABLE IF NOT EXISTS kb_editors (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL UNIQUE,
    granted_by INT       NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- git_integrations
CREATE TABLE IF NOT EXISTS git_integrations (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    provider         ENUM('github','gitlab') NOT NULL DEFAULT 'github',
    api_url          VARCHAR(500) NOT NULL DEFAULT '',
    token            VARCHAR(500) NOT NULL DEFAULT '',
    owner            VARCHAR(200) NOT NULL DEFAULT '',
    repo             VARCHAR(200) NOT NULL DEFAULT '',
    default_labels   TEXT         DEFAULT NULL,
    default_assignee VARCHAR(200) DEFAULT NULL,
    is_active        TINYINT(1)   DEFAULT 1,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- git_issues
CREATE TABLE IF NOT EXISTS git_issues (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT           NOT NULL,
    integration_id INT           NOT NULL,
    issue_number   INT           NOT NULL,
    issue_url      VARCHAR(1000) NOT NULL,
    issue_title    VARCHAR(500)  NOT NULL,
    provider       ENUM('github','gitlab') NOT NULL,
    created_by     INT           NOT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)  REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- youtrack_integrations
CREATE TABLE IF NOT EXISTS youtrack_integrations (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    base_url         VARCHAR(500) NOT NULL DEFAULT '',
    token            VARCHAR(500) NOT NULL DEFAULT '',
    project_id       VARCHAR(200) NOT NULL DEFAULT '',
    default_type     VARCHAR(100) DEFAULT 'Bug',
    default_priority VARCHAR(100) DEFAULT 'Normal',
    default_assignee VARCHAR(200) DEFAULT NULL,
    default_tags     TEXT         DEFAULT NULL,
    is_active        TINYINT(1)   DEFAULT 1,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- youtrack_issues
CREATE TABLE IF NOT EXISTS youtrack_issues (
    id             INT           AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT           NOT NULL,
    integration_id INT           NOT NULL,
    issue_id       VARCHAR(100)  NOT NULL,
    issue_url      VARCHAR(1000) NOT NULL,
    issue_summary  VARCHAR(500)  NOT NULL,
    created_by     INT           NOT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)  REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- language_settings
CREATE TABLE IF NOT EXISTS language_settings (
    lang_code  VARCHAR(20) NOT NULL PRIMARY KEY,
    is_active  TINYINT(1)  NOT NULL DEFAULT 1,
    label      VARCHAR(60) NOT NULL DEFAULT '',
    flag       VARCHAR(10) NOT NULL DEFAULT '',
    sort_order INT         NOT NULL DEFAULT 99,
    is_builtin TINYINT(1)  NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- STANDARD-DATEN
-- =============================================================================

-- Standard-Einstellungen
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('theme',               'modern-blue'),
    ('site_name',           'Support System'),
    ('email_notifications', '1'),
    ('smtp_host',           ''),
    ('smtp_port',           '587'),
    ('smtp_user',           ''),
    ('smtp_password',       ''),
    ('smtp_from_email',     'noreply@support.local'),
    ('smtp_from_name',      'Support System'),
    ('chat_enabled',        '1'),
    ('chat_max_length',     '2000'),
    ('chat_emojis',         '1'),
    ('chat_gifs',           '0'),
    ('discord_enabled',     '0'),
    ('discord_webhook_url', ''),
    ('discord_bot_name',    'SupportBot'),
    ('custom_css_enabled',  '0'),
    ('custom_css',          ''),
    ('language_switcher',   '1'),
    ('footer_enabled',          '1'),
    ('footer_copyright',        'Made with ❤️ from YourRapiddeath'),
    ('footer_bg_color',         ''),
    ('footer_text_color',       ''),
    ('footer_links',            '[]'),
    ('footer_imprint_show',     '1'),
    ('footer_imprint_label',    'Impressum'),
    ('footer_imprint_content',  ''),
    ('footer_privacy_show',     '1'),
    ('footer_privacy_label',    'Datenschutz'),
    ('footer_privacy_content',  ''),
    ('footer_terms_show',       '1'),
    ('footer_terms_label',      'AGB'),
    ('footer_terms_content',    ''),
    ('footer_contact_show',     '1'),
    ('footer_contact_label',    'Kontakt'),
    ('footer_contact_content',  '');

-- Standard-Sprachen
INSERT IGNORE INTO language_settings (lang_code, is_active, label, flag, sort_order, is_builtin) VALUES
    ('DE-de',   1, 'Deutsch',          '🇩🇪', 1, 1),
    ('EN-en',   1, 'English',          '🇬🇧', 2, 1),
    ('FR-fr',   1, 'Français',         '🇫🇷', 3, 1),
    ('CH-ch',   1, 'Schwiizerdüütsch', '🇨🇭', 4, 1),
    ('NDS-nds', 0, 'Plattdüütsch',     '🌊',  5, 1);

-- Standard E-Mail-Vorlagen
INSERT IGNORE INTO email_templates (slug, name, description, subject, body_html, body_text) VALUES
('ticket_created', 'Ticket erstellt (Kunde)', 'Wird gesendet wenn ein neues Ticket angelegt wurde.',
 'Dein Ticket {{ticket_code}} wurde erstellt – {{site_name}}',
 '<p>Hallo {{customer_name}},<br>dein Ticket <strong>{{ticket_code}}</strong> wurde erstellt.<br><a href="{{ticket_url}}">Ticket ansehen</a></p>',
 'Hallo {{customer_name}}, dein Ticket {{ticket_code}} wurde erstellt. {{ticket_url}}'),
('ticket_updated', 'Neue Supporter-Antwort (Kunde)', 'Wird gesendet wenn ein Supporter antwortet.',
 'Neue Antwort auf Ticket {{ticket_code}} – {{site_name}}',
 '<p>Hallo {{customer_name}},<br>{{supporter_name}} hat geantwortet:<br>{{reply_message}}<br><a href="{{ticket_url}}">Ticket ansehen</a></p>',
 'Hallo {{customer_name}}, {{supporter_name}} hat geantwortet: {{reply_message}} -- {{ticket_url}}'),
('ticket_assigned', 'Ticket zugewiesen (Supporter)', 'Wird gesendet wenn ein Ticket zugewiesen wird.',
 'Neues Ticket zugewiesen: {{ticket_code}} – {{site_name}}',
 '<p>Hallo {{supporter_name}},<br>Ticket <strong>{{ticket_code}}</strong> wurde dir zugewiesen.<br><a href="{{ticket_url}}">Ticket bearbeiten</a></p>',
 'Hallo {{supporter_name}}, Ticket {{ticket_code}} wurde dir zugewiesen. {{ticket_url}}'),
('ticket_new_message_supporter', 'Neue Kunden-Nachricht (Supporter)', 'Wird an den Supporter gesendet wenn der Kunde antwortet.',
 'Neue Nachricht vom Kunden: {{ticket_code}} – {{site_name}}',
 '<p>Hallo {{supporter_name}},<br>{{customer_name}} hat geantwortet:<br>{{reply_message}}<br><a href="{{ticket_url}}">Ticket öffnen</a></p>',
 'Hallo {{supporter_name}}, {{customer_name}} hat geantwortet: {{reply_message}} -- {{ticket_url}}');

-- =============================================================================
-- KNOWLEDGE BASE – Categories & Articles (English)
-- =============================================================================

INSERT IGNORE INTO kb_categories (id, name, description, icon, sort_order, created_by) VALUES
(1, 'Getting Started',       'Introduction to the support system – basics for new supporters.',          '🚀', 1, 1),
(2, 'Ticket Management',     'Everything about tickets: creating, editing, assigning and closing.',      '🎫', 2, 1),
(3, 'Security & Account',    'Passwords, 2FA, sessions and account settings.',                          '🔒', 3, 1),
(4, 'Team & Communication',  'Team chat, private messages and notifications.',                          '💬', 4, 1),
(5, 'Admin & Settings',      'Configuration, themes, email, Discord and integrations.',                 '⚙️', 5, 1),
(6, 'Knowledge Base',        'How the KB is managed, structured and maintained.',                       '📚', 6, 1);

-- ─────────────────────────────────────────────────────────────────────────────
-- Category 1: Getting Started
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(1, 'Welcome to the Support System – Overview',
'<h2>🚀 Welcome to the Support System</h2>
<p>This system is a complete, multilingual helpdesk solution for professional customer support. It provides everything a modern support team needs – from ticket management to internal communication.</p>

<h3>🏗️ System Architecture</h3>
<ul>
  <li><strong>PHP 8.x</strong> – Backend logic and templates</li>
  <li><strong>MySQL / MariaDB</strong> – Database layer</li>
  <li><strong>CSS Custom Properties</strong> – Theme system (25+ themes)</li>
  <li><strong>No framework overhead</strong> – Lean, maintainable system</li>
</ul>

<h3>👥 Roles in the System</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Role</th><th>Description</th><th>Access</th></tr></thead>
  <tbody>
    <tr><td>👤 <strong>User / Customer</strong></td><td>Registered customer</td><td>Create &amp; view own tickets</td></tr>
    <tr><td>🟢 <strong>First Level</strong></td><td>First point of contact</td><td>Handle first-level tickets</td></tr>
    <tr><td>🔵 <strong>Second Level</strong></td><td>Technical supporter</td><td>First- &amp; second-level tickets</td></tr>
    <tr><td>🟣 <strong>Third Level</strong></td><td>Expert / Developer</td><td>All ticket levels</td></tr>
    <tr><td>🔴 <strong>Admin</strong></td><td>System administrator</td><td>Full access to all areas</td></tr>
  </tbody>
</table>

<h3>📌 Key URLs</h3>
<ul>
  <li><code>/index.php</code> – Home / Dashboard</li>
  <li><code>/tickets/create.php</code> – Create new ticket</li>
  <li><code>/support/tickets.php</code> – Ticket overview (supporters)</li>
  <li><code>/admin/settings.php</code> – Admin settings</li>
  <li><code>/users/profile.php</code> – Profile management</li>
</ul>',
'overview, introduction, roles, architecture, system', 1, 1),


(1, 'Login and Initial Setup',
'<h2>🔑 Login and Initial Setup</h2>
<p>On the first visit the installation wizard starts automatically (<code>/install.php</code>) and guides you through all setup steps.</p>

<h3>🛠️ Installation Steps</h3>
<ol>
  <li><strong>Step 1 – System requirements:</strong> PHP version, database access and write permissions are checked</li>
  <li><strong>Step 2 – Database configuration:</strong> Enter host, database name, user and password</li>
  <li><strong>Step 3 – Database initialisation:</strong> All tables are created from <code>database.sql</code></li>
  <li><strong>Step 4 – Admin account:</strong> Name, username, email and password for the first admin</li>
  <li><strong>Step 5 – Done:</strong> System is ready to use</li>
</ol>

<h3>🔐 First Login</h3>
<ul>
  <li>Navigate to <code>/login.php</code></li>
  <li>Enter username or email + password</li>
  <li>If 2FA is enabled: enter the 6-digit code from your authenticator app</li>
</ul>

<h3>⚠️ Security Notes After Installation</h3>
<ul>
  <li>Protect <code>install.php</code> and <code>installed.lock</code> after installation</li>
  <li>Use a strong admin password</li>
  <li>Enable 2FA for the admin account</li>
  <li>Configure SMTP for email notifications</li>
</ul>',
'installation, setup, login, first steps', 1, 1),


(1, 'Understanding the Dashboard',
'<h2>📊 The Dashboard</h2>
<p>The dashboard is the central overview after login. Different information is shown depending on the role.</p>

<h3>👤 Customer Dashboard</h3>
<ul>
  <li>Stat cards: Open, in progress, resolved and closed tickets</li>
  <li>List of recent own tickets with status and priority</li>
  <li>Quick access to ticket creation</li>
</ul>

<h3>🧑‍💻 Supporter Dashboard</h3>
<p>Supporters see two sections:</p>
<ul>
  <li><strong>Unassigned tickets</strong> – Tickets not yet assigned to any supporter</li>
  <li><strong>My assigned tickets</strong> – Tickets directly assigned to you</li>
</ul>
<blockquote><strong>💡 Tip:</strong> Higher-level supporters also see tickets from lower levels. A third-level supporter sees first- and second-level tickets as well.</blockquote>

<h3>🔔 Notifications</h3>
<p>In the top-right navbar area:</p>
<ul>
  <li>📬 <strong>Inbox icon</strong> with badge for unread private messages</li>
  <li>💬 <strong>Chat bubble</strong> bottom-right with badge for unread chat messages</li>
</ul>',
'dashboard, overview, supporter, statistics, notifications', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Category 2: Ticket Management
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(2, 'Creating a Ticket – Step by Step',
'<h2>🎫 Creating a Ticket</h2>
<p>There are two ways to create a ticket: as a logged-in user or as a guest via the public form.</p>

<h3>As a Logged-In User</h3>
<ol>
  <li>Go to the dashboard or navigate directly to <code>/tickets/create.php</code></li>
  <li>Enter a <strong>subject</strong> – a short, clear description of the issue</li>
  <li>Select a <strong>category</strong> (if available)</li>
  <li>Write a <strong>description</strong> – as detailed as possible: What happens? When does it occur? Any error messages?</li>
  <li>Fill in custom fields (if configured by the admin)</li>
  <li>Click <strong>"Create Ticket"</strong></li>
</ol>

<h3>As a Guest (Public Form)</h3>
<p>Via the homepage (<code>/index.php</code>) or directly at <code>/tickets/public_ticket.php</code>:</p>
<ol>
  <li>Enter your name and email address</li>
  <li>Fill in subject, category and description</li>
  <li>Confirm GDPR consent</li>
  <li>Note your ticket code – needed to check the ticket status later</li>
</ol>

<h3>📋 After Creation</h3>
<ul>
  <li>Automatic email confirmation (if SMTP is configured)</li>
  <li>Ticket appears as "Unassigned" in the supporter overview</li>
  <li>The first supporter to reply is automatically assigned as the handler</li>
</ul>',
'create ticket, guest, form, subject, description', 1, 1),


(2, 'Ticket Status and Priorities',
'<h2>📊 Status and Priorities</h2>

<h3>🔄 Ticket Status</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Status</th><th>Meaning</th></tr></thead>
  <tbody>
    <tr><td>🟢 <strong>Open</strong></td><td>Newly created, not yet handled</td></tr>
    <tr><td>🔵 <strong>In Progress</strong></td><td>Supporter has accepted and is actively working on it</td></tr>
    <tr><td>🟡 <strong>Pending</strong></td><td>Waiting for customer reply or external information</td></tr>
    <tr><td>🟣 <strong>Resolved</strong></td><td>Issue fixed; customer can still reactivate the ticket</td></tr>
    <tr><td>⚫ <strong>Closed</strong></td><td>Ticket completed, no further messages possible</td></tr>
  </tbody>
</table>

<h3>⚡ Priorities</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Priority</th><th>Meaning</th><th>Response Time (recommended)</th></tr></thead>
  <tbody>
    <tr><td>⬇️ <strong>Low</strong></td><td>No urgency, general enquiry</td><td>72 hours</td></tr>
    <tr><td>➡️ <strong>Medium</strong></td><td>Normal support case</td><td>24 hours</td></tr>
    <tr><td>⬆️ <strong>High</strong></td><td>Productivity impacted</td><td>8 hours</td></tr>
    <tr><td>🚨 <strong>Urgent</strong></td><td>Complete outage, critical problem</td><td>1–2 hours</td></tr>
  </tbody>
</table>

<h3>🎯 Support Levels</h3>
<ul>
  <li><strong>First Level:</strong> General enquiries, common problems, FAQ-based support</li>
  <li><strong>Second Level:</strong> Technical issues requiring deeper knowledge</li>
  <li><strong>Third Level:</strong> Developer interventions, database problems, system-critical errors</li>
</ul>
<blockquote>Tickets can be forwarded between levels via the action box inside the ticket.</blockquote>',
'status, priority, level, open, closed, urgent', 1, 1),


(2, 'Handling a Ticket as a Supporter',
'<h2>🧑‍💻 Handling a Ticket</h2>
<p>When opening a ticket as a supporter, various tools are available.</p>

<h3>📋 Action Box (right side)</h3>
<p>The action box contains three tabs:</p>
<ul>
  <li><strong>Options:</strong> Change status, priority and support level via dropdown – changes are saved immediately without a button</li>
  <li><strong>Internal Messages:</strong> Messages only visible to the support team (highlighted in yellow)</li>
  <li><strong>History:</strong> Full ticket history with all changes</li>
</ul>

<h3>✍️ Sending Messages</h3>
<ul>
  <li>The message input appears automatically when you scroll to the bottom</li>
  <li>Clicking the input field also opens it manually</li>
  <li>Checkbox <strong>"Mark as internal message"</strong> – message is only visible to supporters</li>
  <li>Templates can be inserted quickly via the template dropdown</li>
</ul>

<h3>🔀 Automatic Assignment</h3>
<blockquote>If a ticket is not yet assigned and a supporter writes a reply, the ticket is automatically assigned to that supporter.</blockquote>

<h3>🔗 GitHub / GitLab / YouTrack</h3>
<p>Use the <strong>"Create Issue"</strong> button in the action box to create issues in external systems directly from the ticket – if the integration has been configured by the admin.</p>

<h3>📤 Share in Chat</h3>
<p>Use <strong>"Send link to chat"</strong> to share a ticket as a formatted card in the team chat – optionally with a comment.</p>',
'handle ticket, action box, messages, assignment, supporter', 1, 1),


(2, 'Custom Ticket Fields',
'<h2>📋 Custom Ticket Fields</h2>
<p>Admins can define additional fields for tickets that are displayed in the creation form.</p>

<h3>⚙️ Managing Fields (Admin)</h3>
<ol>
  <li>Admin → Settings → Ticket Fields</li>
  <li>Click <strong>"New Field"</strong></li>
  <li>Configure the field properties</li>
</ol>

<h3>🎛️ Available Field Types</h3>
<ul>
  <li>📝 <strong>Text (single-line)</strong> – Short text inputs (e.g. serial number)</li>
  <li>📄 <strong>Textarea (multi-line)</strong> – Longer descriptions</li>
  <li>🔢 <strong>Number</strong> – Numeric values</li>
  <li>📋 <strong>Dropdown</strong> – Selection from predefined options</li>
  <li>☑️ <strong>Checkbox</strong> – Yes/No decisions</li>
  <li>📅 <strong>Date</strong> – Date picker</li>
  <li>✉️ <strong>Email</strong> – Email addresses with validation</li>
  <li>🔗 <strong>URL</strong> – Web links</li>
  <li>📞 <strong>Phone</strong> – Phone numbers</li>
</ul>

<h3>⚙️ Field Options</h3>
<ul>
  <li><strong>Required:</strong> Must be filled in by the customer</li>
  <li><strong>Public Form:</strong> Also visible in the guest ticket form</li>
  <li><strong>Show in List:</strong> Value appears as a column in the overview</li>
  <li><strong>Order:</strong> Sortable via drag &amp; drop</li>
</ul>',
'fields, custom fields, required, dropdown, textarea', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Category 3: Security & Account
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(3, 'Password and Account Security',
'<h2>🔐 Password and Account Security</h2>

<h3>🔑 Changing Your Password</h3>
<ol>
  <li>Open your profile (<code>/users/profile.php</code>)</li>
  <li>Select the <strong>"Password"</strong> tab</li>
  <li>Enter your current password</li>
  <li>Enter the new password (min. 6 characters) and confirm it</li>
  <li>Save</li>
</ol>

<h3>🚫 Login Lockout</h3>
<p>After <strong>3 failed login attempts</strong> the account is locked for <strong>5 minutes</strong>. A corresponding message is shown on the homepage. The lock is lifted automatically after the time expires.</p>

<h3>💡 Password Recommendations</h3>
<ul>
  <li>Use at least 12 characters</li>
  <li>Combine uppercase, lowercase, numbers and special characters</li>
  <li>Never reuse passwords</li>
  <li>Use a password manager</li>
  <li>Additionally enable 2FA (recommended!)</li>
</ul>

<h3>🖼️ Profile Picture and Bio</h3>
<p>A profile picture (JPG/PNG/GIF, max. 2 MB) can be uploaded in the profile tab. The bio is shown to other supporters in the ticket view.</p>',
'password, security, login, account, profile picture', 1, 1),


(3, 'Setting Up Two-Factor Authentication (2FA)',
'<h2>🔐 Two-Factor Authentication (2FA)</h2>
<p>2FA significantly increases security by requiring a time-based code (TOTP) in addition to the password.</p>

<h3>📱 Requirement</h3>
<p>A TOTP-compatible app on your smartphone:</p>
<ul>
  <li>Google Authenticator (iOS / Android)</li>
  <li>Microsoft Authenticator</li>
  <li>Authy</li>
  <li>1Password, Bitwarden or other password managers with OTP support</li>
</ul>

<h3>⚙️ Enabling 2FA</h3>
<ol>
  <li>Open your profile → <strong>"2FA"</strong> tab</li>
  <li>Click <strong>"Set up 2FA"</strong></li>
  <li><strong>Step 1:</strong> Scan the QR code with your authenticator app <em>(alternatively: enter the manual key)</em></li>
  <li><strong>Step 2:</strong> Enter the 6-digit code from the app and confirm</li>
  <li><strong>Step 3:</strong> Save your backup codes in a safe place!</li>
</ol>

<h3>🔑 Backup Codes</h3>
<blockquote>⚠️ <strong>Important:</strong> Backup codes are one-time codes for emergencies when you do not have access to your authenticator app. Store them safely – each code can only be used once!</blockquote>

<h3>🔄 Disabling 2FA</h3>
<ol>
  <li>Profile → "2FA" tab → "Disable 2FA"</li>
  <li>Enter your current 2FA code</li>
  <li>After disabling, a new key is automatically generated if 2FA is enabled again later</li>
</ol>

<h3>🔐 2FA at Login</h3>
<p>After entering username and password, a second form appears for the 6-digit code. Backup codes are also accepted.</p>',
'2fa, two-factor, totp, authenticator, backup codes, security', 1, 1),


(3, 'Managing Your Profile and Reply Templates',
'<h2>👤 Managing Your Profile</h2>
<p>Your profile is accessible at <code>/users/profile.php</code> or via the user menu in the top right.</p>

<h3>📑 Profile Tabs</h3>
<ul>
  <li><strong>Profile:</strong> Edit name and bio</li>
  <li><strong>Avatar:</strong> Upload or remove profile picture (JPG/PNG/GIF, max. 2 MB)</li>
  <li><strong>Password:</strong> Change password</li>
  <li><strong>2FA:</strong> Manage two-factor authentication</li>
  <li><strong>Templates:</strong> Link to template management</li>
</ul>

<h3>✍️ Reply Templates</h3>
<p>Personal text templates with placeholders can be created at <code>/users/templates.php</code>:</p>
<ul>
  <li><code>{{kunde_name}}</code> – Customer name</li>
  <li><code>{{ticket_nr}}</code> – Ticket number</li>
  <li><code>{{supporter_name}}</code> – Your name</li>
  <li><code>{{datum}}</code> – Today\'s date</li>
  <li><code>{{betreff}}</code> – Ticket subject</li>
  <li><code>{{status}}</code> – Current status</li>
  <li><code>{{email}}</code> – Customer email</li>
</ul>
<p>Templates are inserted in the ticket input field via the template dropdown.</p>',
'profile, avatar, templates, placeholders, settings', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Category 4: Team & Communication
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(4, 'Using the Team Chat',
'<h2>💬 Team Chat</h2>
<p>The team chat is an internal real-time chat for all supporters. It is only visible to supporters and appears as a floating chat bubble in the bottom right of every page.</p>

<h3>🖱️ Opening and Closing</h3>
<ul>
  <li>Click the <strong>chat bubble</strong> (💬) in the bottom right</li>
  <li>A red badge shows the number of unread messages</li>
  <li>✕ button to close</li>
</ul>

<h3>📝 Sending Messages</h3>
<ul>
  <li>Type a message and press <kbd>Enter</kbd> (or click the ➤ button)</li>
  <li><kbd>Shift</kbd>+<kbd>Enter</kbd> for a line break</li>
  <li>Emoji picker via the 😊 button</li>
  <li>GIF search via the 🎞️ button (when enabled by admin)</li>
</ul>

<h3>🎫 Sharing a Ticket in Chat</h3>
<p>In the ticket view via <strong>"Send link to chat"</strong>:</p>
<ul>
  <li>Add an optional comment</li>
  <li>The ticket appears as a formatted card in the chat</li>
  <li>All supporters can click <strong>"View ticket"</strong> directly</li>
</ul>

<h3>🔔 Sound Settings</h3>
<ul>
  <li>🔔 button in the chat header: toggle notification sound on/off</li>
  <li>Setting is saved and persists after page reload</li>
</ul>

<h3>📢 Admin Messages</h3>
<p>Admins can send global messages to all supporters. These appear highlighted in yellow in the chat feed of all supporters.</p>',
'chat, team chat, messages, emoji, gif, share ticket', 1, 1),


(4, 'Private Inbox (Private Messages)',
'<h2>📬 Private Inbox</h2>
<p>The private inbox allows supporters to send private messages to each other. Accessible via the envelope icon in the navbar or at <code>/users/messages.php</code>.</p>

<h3>📁 Inbox Sections</h3>
<ul>
  <li><strong>📥 Inbox:</strong> Received messages</li>
  <li><strong>📤 Sent:</strong> Sent messages</li>
  <li><strong>🗑️ Trash:</strong> Deleted messages (restorable)</li>
</ul>

<h3>✍️ Sending a New Message</h3>
<ol>
  <li>Select recipient from the dropdown list</li>
  <li>Enter a subject</li>
  <li>Write your message</li>
  <li>Click <strong>"Send"</strong></li>
</ol>

<h3>↩️ Replying to Messages</h3>
<p>When opening a message, a reply field appears at the bottom. Replies appear as a continuous thread – the same ticket conversations are not listed multiple times.</p>

<h3>🔔 System Notifications</h3>
<p>The system automatically sends inbox notifications when:</p>
<ul>
  <li>An accepted ticket receives a new reply</li>
  <li>The ticket status changes</li>
  <li>An internal message is written on one of your tickets</li>
</ul>
<p>The sender is called <strong>"System"</strong>. If a system notification is deleted, it will reappear on the next event.</p>',
'inbox, private messages, notifications, system, thread', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Category 5: Admin & Settings
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(5, 'Admin Settings – Overview',
'<h2>⚙️ Admin Settings</h2>
<p>The settings page (<code>/admin/settings.php</code>) is divided into tabs. Only admins have access.</p>

<h3>📑 Available Tabs</h3>
<ul>
  <li>🌐 <strong>Website:</strong> Site name, URL, timezone, database connection (main admin only)</li>
  <li>🎨 <strong>Theme:</strong> Select a design (25+ themes), custom CSS</li>
  <li>✉️ <strong>Email:</strong> SMTP configuration and email templates</li>
  <li>📄 <strong>Global Templates:</strong> Text templates available to all supporters</li>
  <li>💬 <strong>Team Chat:</strong> Enable/disable chat, character limit, GIFs, emojis</li>
  <li>🎮 <strong>Discord:</strong> Webhook URL, triggers, embed design</li>
  <li>🔗 <strong>Git:</strong> GitHub/GitLab integrations</li>
  <li>📋 <strong>YouTrack:</strong> YouTrack integrations</li>
  <li>🌍 <strong>Languages:</strong> Manage, import and export language files</li>
  <li>🖌️ <strong>Custom CSS:</strong> Custom CSS for all pages</li>
  <li>🦶 <strong>Footer:</strong> Footer content, legal pages, custom links</li>
</ul>

<h3>🎨 Switching Themes</h3>
<ol>
  <li>Settings → "Theme" tab</li>
  <li>Click the desired theme</li>
  <li>Click <strong>"Save Theme"</strong></li>
</ol>
<blockquote>💡 Some themes (GTA, DayZ, Rotlicht, Black&amp;Gold, Windows XP, YouTube) support background images with blur and brightness controls.</blockquote>',
'admin, settings, theme, smtp, discord, languages', 1, 1),


(5, 'Configuring Email Templates',
'<h2>✉️ Email Templates</h2>
<p>The system sends automatic emails for various events. All content is fully customisable.</p>

<h3>📧 Available Templates</h3>
<ul>
  <li><strong>ticket_created:</strong> Confirmation for the customer after ticket creation</li>
  <li><strong>ticket_updated:</strong> Notification when a supporter replies</li>
  <li><strong>ticket_assigned:</strong> Notification for the supporter on assignment</li>
  <li><strong>ticket_new_message_supporter:</strong> Notification when the customer replies</li>
</ul>

<h3>🔧 Editing a Template</h3>
<ol>
  <li>Admin → Settings → "Email Templates" tab</li>
  <li>Select a template from the list</li>
  <li>Edit the subject and HTML/text content</li>
  <li>Insert placeholders from the sidebar (click to insert automatically)</li>
  <li>Check the preview in the preview tab</li>
  <li>Save</li>
</ol>

<h3>🔀 Available Placeholders</h3>
<ul>
  <li><code>{{customer_name}}</code> – Customer name</li>
  <li><code>{{supporter_name}}</code> – Supporter name</li>
  <li><code>{{ticket_code}}</code> – Ticket number (e.g. T-A1B2C3D4)</li>
  <li><code>{{ticket_url}}</code> – Direct link to the ticket</li>
  <li><code>{{reply_message}}</code> – Content of the last message</li>
  <li><code>{{site_name}}</code> – Website name</li>
</ul>

<h3>📝 HTML and Plain-Text Emails</h3>
<p>Each template has an <strong>HTML body</strong> (for modern email clients) and a <strong>plain-text fallback</strong> (for older clients or spam filters).</p>',
'email, templates, smtp, placeholders, notifications', 1, 1),


(5, 'Setting Up the Discord Integration',
'<h2>🎮 Discord Integration</h2>
<p>The system can automatically post new tickets and status changes to a Discord channel.</p>

<h3>🔧 Creating a Webhook (Discord)</h3>
<ol>
  <li>Open your Discord server → Settings → Integrations → Webhooks</li>
  <li>Click <strong>"New Webhook"</strong></li>
  <li>Set a name and channel</li>
  <li>Copy the webhook URL</li>
</ol>

<h3>⚙️ Configuring in the System</h3>
<ol>
  <li>Admin → Settings → "Discord" tab</li>
  <li><strong>Enable</strong> the integration</li>
  <li>Paste the webhook URL</li>
  <li>Enter bot name and optional avatar image URL</li>
  <li>Select triggers: New ticket, New reply, Status change, Ticket closed</li>
  <li>Customise the embed design (colour, title, description, displayed fields)</li>
  <li>Click <strong>"Send test message"</strong> to verify</li>
  <li>Save</li>
</ol>

<h3>📋 Embed Content</h3>
<p>The following information can be shown in the Discord embed:</p>
<ul>
  <li>Subject, description, priority, category, creator, direct link</li>
  <li>Custom fields with placeholders are also supported</li>
</ul>',
'discord, webhook, notification, embed, integration', 1, 1),


(5, 'GitHub and GitLab Integration',
'<h2>🔗 GitHub &amp; GitLab Integration</h2>
<p>Supporters can create issues in GitHub or GitLab directly from tickets.</p>

<h3>⚙️ Setting Up an Integration (Admin)</h3>
<ol>
  <li>Admin → Settings → "Git" tab → <strong>"New Integration"</strong></li>
  <li>Select provider: GitHub or GitLab</li>
  <li>Enter the access token (stored encrypted)</li>
  <li>Enter owner/namespace and repository name</li>
  <li>Optional: default labels and default assignee</li>
  <li>Enable the integration and save</li>
  <li>Test the connection via <strong>"Test connection"</strong></li>
</ol>

<h3>🔑 Token Permissions</h3>
<ul>
  <li><strong>GitHub (Classic):</strong> Scope <code>repo</code> or <code>public_repo</code></li>
  <li><strong>GitHub (Fine-grained):</strong> Repository permissions → Issues: Read and write</li>
  <li><strong>GitLab:</strong> Scope <code>api</code></li>
</ul>

<h3>🎫 Creating an Issue from a Ticket (Supporter)</h3>
<ol>
  <li>Open a ticket</li>
  <li>Click <strong>"GitHub Issue"</strong> or <strong>"GitLab Issue"</strong> in the action box</li>
  <li>Review/edit title, description, labels and assignees</li>
  <li>Select integration (if multiple are configured)</li>
  <li>Click <strong>"Create Issue"</strong></li>
  <li>The link to the created issue is displayed in the ticket</li>
</ol>',
'github, gitlab, integration, issues, token', 1, 1),


(5, 'Managing and Importing Languages',
'<h2>🌍 Language Management</h2>
<p>The system supports multiple languages simultaneously. Language files are stored under <code>assets/lang/</code>.</p>

<h3>🌐 Built-in Languages</h3>
<ul>
  <li>🇩🇪 German (DE-de)</li>
  <li>🇬🇧 English (EN-en) – <strong>Fallback language, always active</strong></li>
  <li>🇫🇷 French (FR-fr)</li>
  <li>🇨🇭 Swiss German (CH-ch)</li>
  <li>🌊 Low German (NDS-nds)</li>
</ul>

<h3>⚙️ Enabling / Disabling Languages</h3>
<ol>
  <li>Admin → Settings → "Languages" tab</li>
  <li>Activate or deactivate the desired language</li>
  <li>Deactivated languages are not selectable by users</li>
</ol>

<h3>📥 Importing a Custom Language File</h3>
<ol>
  <li>Export an existing language file (PHP or JSON)</li>
  <li>Edit the file locally – translate all keys</li>
  <li>In the import area: select the file, enter a language code (e.g. <code>ES-es</code>)</li>
  <li>Optional: enable "Overwrite existing file"</li>
  <li>Import – the new language appears immediately in the list</li>
</ol>

<blockquote>📌 English (EN-en) cannot be disabled – it serves as the system fallback when a key is missing in the selected language.</blockquote>',
'language, translation, import, export, multilingual', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Category 6: Knowledge Base
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(6, 'Knowledge Base – Overview and Structure',
'<h2>📚 Knowledge Base</h2>
<p>The knowledge base (KB) is an internal reference for the support team. It is accessible at <code>/support/knowledge-base.php</code> or via "WIKI" in the navigation.</p>

<h3>🏗️ Structure</h3>
<ul>
  <li><strong>Categories:</strong> Topic areas with icon, name, description and sort order</li>
  <li><strong>Articles:</strong> Content with title, HTML content, tags and publication status</li>
</ul>

<h3>🔍 Searching Articles</h3>
<p>Use the search field in the top right to search articles by title, content and tags. The search uses MySQL FULLTEXT indexes for fast results.</p>

<h3>👁️ Publication Status</h3>
<ul>
  <li><strong>Published:</strong> Visible to all supporters</li>
  <li><strong>Draft:</strong> Only visible to editors and admins (with a draft badge)</li>
</ul>

<h3>✏️ Who Can Edit?</h3>
<p>By default only <strong>admins</strong> can edit the KB. Admins can grant editing rights to individual supporters:</p>
<ol>
  <li>Open KB → "Editing Permissions" section</li>
  <li>Select a supporter from the list</li>
  <li>Click <strong>"Grant permissions"</strong></li>
</ol>',
'knowledge base, wiki, category, article, permissions', 1, 1),


(6, 'Creating and Managing Categories',
'<h2>📂 Managing Categories</h2>
<p>Categories are the top-level structure of the knowledge base. Each article belongs to exactly one category.</p>

<h3>➕ Creating a New Category</h3>
<ol>
  <li>Open the Knowledge Base</li>
  <li>Click <strong>"+ Category"</strong> (top right)</li>
  <li>Select an <strong>icon</strong> via the emoji picker</li>
  <li>Enter a <strong>name</strong> (required)</li>
  <li>Optionally add a <strong>description</strong></li>
  <li>Set the <strong>sort order</strong> (lower number = higher up)</li>
  <li>Save</li>
</ol>

<h3>🎨 Icon Picker</h3>
<p>The icon picker shows emojis in categorised groups:</p>
<ul>
  <li>📁 Files &amp; Folders · ⭐ Highlights · 🛠️ Tools · 📚 Knowledge · 💬 Communication · and more</li>
</ul>
<p>You can also search for specific emojis using the search field.</p>

<h3>✏️ Editing a Category</h3>
<ul>
  <li>Click the ✏️ icon in the category view</li>
  <li>All fields are editable</li>
  <li>Sort order can be adjusted at any time</li>
</ul>

<h3>🗑️ Deleting a Category</h3>
<blockquote>⚠️ <strong>Warning:</strong> Deleting a category also deletes all articles it contains! This action cannot be undone.</blockquote>',
'category, create, icon, emoji, sort order', 1, 1),


(6, 'Creating and Editing Articles',
'<h2>✏️ Creating Articles</h2>

<h3>➕ Creating a New Article</h3>
<ol>
  <li>Open a category → click <strong>"+ Article"</strong></li>
  <li>Enter a <strong>title</strong> – short and descriptive</li>
  <li>Assign a <strong>category</strong></li>
  <li>Add <strong>tags</strong> comma-separated (for better search)</li>
  <li>Write the content in the <strong>HTML editor</strong></li>
  <li>Choose the publication status</li>
  <li>Click <strong>"Save &amp; Publish"</strong> or <strong>"Save as Draft"</strong></li>
</ol>

<h3>📝 HTML Editor Tips</h3>
<p>The editor accepts HTML. Useful elements:</p>
<ul>
  <li><code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code> – Headings</li>
  <li><code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code> – Lists</li>
  <li><code>&lt;table border="1" cellpadding="6"&gt;</code> – Tables</li>
  <li><code>&lt;blockquote&gt;</code> – Note / hint boxes</li>
  <li><code>&lt;code&gt;</code> – Inline code</li>
  <li><code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code> – Bold, italic</li>
</ul>

<h3>🏷️ Tags</h3>
<p>Tags significantly improve discoverability. Recommendations:</p>
<ul>
  <li>3–7 relevant tags per article</li>
  <li>Use synonyms and common search terms</li>
  <li>Lowercase, comma-separated</li>
</ul>

<h3>🔄 Editing an Article</h3>
<p>Via the ✏️ icon in the article view or the category overview. All changes are saved with a timestamp and the editor name.</p>',
'article, editor, html, tags, publish, draft', 1, 1);

-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 1: Erste Schritte
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(1, 'Willkommen im Support-System – Systemübersicht',
'<h2>🚀 Willkommen im Support-System</h2>
<p>Dieses System ist eine vollständige, mehrsprachige Helpdesk-Lösung für professionellen Kundensupport. Es bietet alles was ein modernes Support-Team benötigt – von der Ticketverwaltung bis zur internen Kommunikation.</p>

<h3>🏗️ Systemarchitektur</h3>
<ul>
  <li><strong>PHP 8.x</strong> – Backend-Logik und Templates</li>
  <li><strong>MySQL / MariaDB</strong> – Datenbankschicht</li>
  <li><strong>CSS Custom Properties</strong> – Theme-System</li>
  <li><strong>Kein Framework-Overhead</strong> – Schlankes, wartbares System</li>
</ul>

<h3>👥 Rollen im System</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Rolle</th><th>Beschreibung</th><th>Zugriff</th></tr></thead>
  <tbody>
    <tr><td>👤 <strong>Benutzer / Kunde</strong></td><td>Registrierter Kunde</td><td>Eigene Tickets erstellen &amp; einsehen</td></tr>
    <tr><td>🟢 <strong>First Level</strong></td><td>Erster Ansprechpartner</td><td>First-Level-Tickets bearbeiten</td></tr>
    <tr><td>🔵 <strong>Second Level</strong></td><td>Technischer Supporter</td><td>First- &amp; Second-Level-Tickets</td></tr>
    <tr><td>🟣 <strong>Third Level</strong></td><td>Experte / Entwickler</td><td>Alle Ticket-Level</td></tr>
    <tr><td>🔴 <strong>Admin</strong></td><td>Systemadministrator</td><td>Vollzugriff auf alle Bereiche</td></tr>
  </tbody>
</table>

<h3>📌 Wichtige URLs</h3>
<ul>
  <li><code>/index.php</code> – Startseite / Dashboard</li>
  <li><code>/tickets/create.php</code> – Neues Ticket erstellen</li>
  <li><code>/support/tickets.php</code> – Ticket-Übersicht (Supporter)</li>
  <li><code>/admin/settings.php</code> – Admin-Einstellungen</li>
  <li><code>/users/profile.php</code> – Profilverwaltung</li>
</ul>',
'systemübersicht, einführung, rollen, architektur', 1, 1),


(1, 'Anmeldung und erste Einrichtung',
'<h2>🔑 Anmeldung und erste Einrichtung</h2>
<p>Beim ersten Aufruf des Systems wird automatisch der Installationsassistent gestartet (<code>/install.php</code>). Dieser führt durch alle Schritte der Ersteinrichtung.</p>

<h3>🛠️ Installationsschritte</h3>
<ol>
  <li><strong>Schritt 1 – Systemvoraussetzungen:</strong> PHP-Version, Datenbankzugang, Schreibrechte werden geprüft</li>
  <li><strong>Schritt 2 – Datenbankkonfiguration:</strong> Host, Datenbankname, Benutzer und Passwort eingeben</li>
  <li><strong>Schritt 3 – Datenbankinitialisierung:</strong> Alle Tabellen werden aus <code>database.sql</code> angelegt</li>
  <li><strong>Schritt 4 – Admin-Konto:</strong> Name, Benutzername, E-Mail und Passwort für den ersten Admin</li>
  <li><strong>Schritt 5 – Abschluss:</strong> System ist einsatzbereit</li>
</ol>

<h3>🔐 Erster Login</h3>
<ul>
  <li>Navigiere zu <code>/login.php</code></li>
  <li>Benutzername oder E-Mail + Passwort eingeben</li>
  <li>Falls 2FA aktiviert: 6-stelligen Code aus der Authenticator-App eingeben</li>
</ul>

<h3>⚠️ Sicherheitshinweise nach der Installation</h3>
<ul>
  <li><code>install.php</code> und <code>installed.lock</code> nach der Installation schützen</li>
  <li>Starkes Admin-Passwort verwenden</li>
  <li>2FA für den Admin-Account aktivieren</li>
  <li>SMTP für E-Mail-Benachrichtigungen einrichten</li>
</ul>',
'installation, einrichtung, login, ersteinrichtung', 1, 1),


(1, 'Das Dashboard verstehen',
'<h2>📊 Das Dashboard</h2>
<p>Das Dashboard ist die zentrale Übersicht nach dem Login. Je nach Rolle werden unterschiedliche Informationen angezeigt.</p>

<h3>👤 Kunden-Dashboard</h3>
<ul>
  <li>Statistik-Karten: Offene, in Bearbeitung, gelöste und geschlossene Tickets</li>
  <li>Liste der letzten eigenen Tickets mit Status und Priorität</li>
  <li>Schnellzugriff auf Ticket-Erstellung</li>
</ul>

<h3>🧑‍💻 Supporter-Dashboard</h3>
<p>Supporter sehen zwei Bereiche:</p>
<ul>
  <li><strong>Nicht zugewiesene Tickets</strong> – Tickets die noch keinem Supporter zugewiesen sind</li>
  <li><strong>Meine zugewiesenen Tickets</strong> – Tickets die dir direkt zugewiesen wurden</li>
</ul>
<blockquote><strong>💡 Tipp:</strong> Higher-Level-Supporter sehen auch Tickets niedrigerer Level. Ein Third-Level-Supporter sieht z.B. auch First- und Second-Level-Tickets.</blockquote>

<h3>🔔 Benachrichtigungen</h3>
<p>Im Navbar-Bereich oben rechts befinden sich:</p>
<ul>
  <li>📬 <strong>Postfach-Icon</strong> mit Badge für ungelesene private Nachrichten</li>
  <li>💬 <strong>Chat-Bubble</strong> unten rechts mit Badge für ungelesene Chat-Nachrichten</li>
</ul>',
'dashboard, übersicht, supporter, statistik', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 2: Ticket-Management
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(2, 'Ticket erstellen – Schritt für Schritt',
'<h2>🎫 Ticket erstellen</h2>
<p>Es gibt zwei Wege ein Ticket zu erstellen: als angemeldeter Benutzer oder als Gast über das öffentliche Formular.</p>

<h3>Als angemeldeter Benutzer</h3>
<ol>
  <li>Dashboard aufrufen oder <code>/tickets/create.php</code> direkt ansteuern</li>
  <li><strong>Betreff</strong> eingeben – kurze, prägnante Beschreibung des Problems</li>
  <li><strong>Kategorie</strong> auswählen (falls verfügbar)</li>
  <li><strong>Beschreibung</strong> – so detailliert wie möglich: Was passiert? Wann tritt das Problem auf? Fehlermeldungen?</li>
  <li>Benutzerdefinierte Felder ausfüllen (falls vom Admin konfiguriert)</li>
  <li>Auf <strong>„Ticket erstellen"</strong> klicken</li>
</ol>

<h3>Als Gast (öffentliches Formular)</h3>
<p>Über die Startseite (<code>/index.php</code>) oder direkt <code>/tickets/public_ticket.php</code>:</p>
<ol>
  <li>Name und E-Mail-Adresse eingeben</li>
  <li>Betreff, Kategorie und Beschreibung ausfüllen</li>
  <li>DSGVO-Einwilligung bestätigen</li>
  <li>Ticket-Code notieren – dieser wird für spätere Einsicht benötigt</li>
</ol>

<h3>📋 Nach der Erstellung</h3>
<ul>
  <li>Automatische E-Mail-Bestätigung (wenn SMTP konfiguriert)</li>
  <li>Ticket erscheint in der Supporter-Übersicht als "Nicht zugewiesen"</li>
  <li>Der erste Supporter der antwortet wird automatisch als Bearbeiter zugewiesen</li>
</ul>',
'ticket erstellen, gast, formular, betreff, beschreibung', 1, 1),


(2, 'Ticket-Status und Prioritäten',
'<h2>📊 Status und Prioritäten</h2>

<h3>🔄 Ticket-Status</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Status</th><th>Bedeutung</th></tr></thead>
  <tbody>
    <tr><td>🟢 <strong>Offen</strong></td><td>Neu erstellt, noch nicht bearbeitet</td></tr>
    <tr><td>🔵 <strong>In Bearbeitung</strong></td><td>Supporter hat das Ticket angenommen und bearbeitet es aktiv</td></tr>
    <tr><td>🟡 <strong>Ausstehend</strong></td><td>Warte auf Rückmeldung vom Kunden oder externe Info</td></tr>
    <tr><td>🟣 <strong>Gelöst</strong></td><td>Problem behoben, Kunde kann das Ticket noch reaktivieren</td></tr>
    <tr><td>⚫ <strong>Geschlossen</strong></td><td>Ticket abgeschlossen, keine weiteren Nachrichten möglich</td></tr>
  </tbody>
</table>

<h3>⚡ Prioritäten</h3>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
  <thead><tr style="background:var(--surface)"><th>Priorität</th><th>Bedeutung</th><th>Reaktionszeit (empfohlen)</th></tr></thead>
  <tbody>
    <tr><td>⬇️ <strong>Niedrig</strong></td><td>Keine Dringlichkeit, allgemeine Anfrage</td><td>72 Stunden</td></tr>
    <tr><td>➡️ <strong>Mittel</strong></td><td>Normaler Support-Fall</td><td>24 Stunden</td></tr>
    <tr><td>⬆️ <strong>Hoch</strong></td><td>Einschränkung der Produktivität</td><td>8 Stunden</td></tr>
    <tr><td>🚨 <strong>Dringend</strong></td><td>Kompletter Ausfall, kritisches Problem</td><td>1–2 Stunden</td></tr>
  </tbody>
</table>

<h3>🎯 Support-Level</h3>
<ul>
  <li><strong>First Level:</strong> Allgemeine Anfragen, häufige Probleme, FAQ-basierter Support</li>
  <li><strong>Second Level:</strong> Technische Probleme die tiefere Kenntnisse erfordern</li>
  <li><strong>Third Level:</strong> Entwickler-Eingriffe, Datenbankprobleme, Systemkritische Fehler</li>
</ul>
<blockquote>Tickets können über die Aktionsbox innerhalb des Tickets zwischen Leveln weitergeleitet werden.</blockquote>',
'status, priorität, level, offen, geschlossen, dringend', 1, 1),


(2, 'Ticket bearbeiten als Supporter',
'<h2>🧑‍💻 Ticket bearbeiten</h2>
<p>Beim Öffnen eines Tickets als Supporter stehen verschiedene Werkzeuge zur Verfügung.</p>

<h3>📋 Aktionsbox (rechte Seite)</h3>
<p>Die Aktionsbox enthält drei Tabs:</p>
<ul>
  <li><strong>Optionen:</strong> Status, Priorität und Support-Level per Dropdown ändern – Änderungen werden sofort gespeichert ohne Button</li>
  <li><strong>Interne Nachrichten:</strong> Nachrichten die nur für das Support-Team sichtbar sind (gelb hervorgehoben)</li>
  <li><strong>Verlauf:</strong> Vollständige Ticket-Historie mit allen Änderungen</li>
</ul>

<h3>✍️ Nachrichten senden</h3>
<ul>
  <li>Nachrichten-Eingabefeld erscheint automatisch wenn man ans Ende der Nachrichten scrollt</li>
  <li>Durch Klick auf das Eingabefeld öffnet es sich auch manuell</li>
  <li>Checkbox <strong>„Als interne Nachricht markieren"</strong> – Nachricht ist nur für Supporter sichtbar</li>
  <li>Vorlagen können über das Vorlagen-Dropdown schnell eingefügt werden</li>
</ul>

<h3>🔀 Automatische Zuweisung</h3>
<blockquote>Wenn ein Ticket noch nicht zugewiesen ist und ein Supporter eine Antwort schreibt, wird das Ticket automatisch diesem Supporter zugewiesen.</blockquote>

<h3>🔗 GitHub / GitLab / YouTrack</h3>
<p>Über den Button <strong>„Issue erstellen"</strong> in der Aktionsbox können direkt aus dem Ticket heraus Issues in externen Systemen angelegt werden – wenn die Integration vom Admin konfiguriert wurde.</p>

<h3>📤 Im Chat teilen</h3>
<p>Über <strong>„Link an Chat senden"</strong> kann ein Ticket als formatierte Karte im Team-Chat geteilt werden – optional mit Kommentar.</p>',
'ticket bearbeiten, aktionsbox, nachrichten, zuweisung, supporter', 1, 1),


(2, 'Benutzerdefinierte Ticket-Felder',
'<h2>📋 Benutzerdefinierte Felder</h2>
<p>Admins können zusätzliche Felder für Tickets definieren, die im Erstellformular angezeigt werden.</p>

<h3>⚙️ Felder verwalten (Admin)</h3>
<ol>
  <li>Admin → Einstellungen → Ticket-Felder</li>
  <li>Auf <strong>„Neues Feld"</strong> klicken</li>
  <li>Feldeigenschaften konfigurieren</li>
</ol>

<h3>🎛️ Verfügbare Feldtypen</h3>
<ul>
  <li>📝 <strong>Text (einzeilig)</strong> – Kurze Texteingaben (z.B. Seriennummer)</li>
  <li>📄 <strong>Textarea (mehrzeilig)</strong> – Längere Beschreibungen</li>
  <li>🔢 <strong>Zahl</strong> – Numerische Werte</li>
  <li>📋 <strong>Dropdown</strong> – Auswahl aus vordefinierten Optionen</li>
  <li>☑️ <strong>Checkbox</strong> – Ja/Nein-Entscheidungen</li>
  <li>📅 <strong>Datum</strong> – Datumsauswahl</li>
  <li>✉️ <strong>E-Mail</strong> – E-Mail-Adressen mit Validierung</li>
  <li>🔗 <strong>URL</strong> – Weblinks</li>
  <li>📞 <strong>Telefon</strong> – Telefonnummern</li>
</ul>

<h3>⚙️ Feldoptionen</h3>
<ul>
  <li><strong>Pflichtfeld:</strong> Muss vom Kunden ausgefüllt werden</li>
  <li><strong>Öffentliches Formular:</strong> Auch im Gast-Ticket-Formular sichtbar</li>
  <li><strong>In Ticket-Liste anzeigen:</strong> Wert erscheint als Spalte in der Übersicht</li>
  <li><strong>Reihenfolge:</strong> Per Drag &amp; Drop sortierbar</li>
</ul>',
'felder, custom fields, pflichtfeld, dropdown, textarea', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 3: Sicherheit & Konto
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(3, 'Passwort und Kontosicherheit',
'<h2>🔐 Passwort und Kontosicherheit</h2>

<h3>🔑 Passwort ändern</h3>
<ol>
  <li>Profil aufrufen (<code>/users/profile.php</code>)</li>
  <li>Tab <strong>„Passwort"</strong> wählen</li>
  <li>Aktuelles Passwort eingeben</li>
  <li>Neues Passwort (min. 6 Zeichen) und Bestätigung eingeben</li>
  <li>Speichern</li>
</ol>

<h3>🚫 Login-Sperre</h3>
<p>Nach <strong>3 fehlgeschlagenen Login-Versuchen</strong> wird der Account für <strong>5 Minuten gesperrt</strong>. Eine entsprechende Meldung erscheint auf der Startseite. Die Sperre hebt sich nach Ablauf der Zeit automatisch auf.</p>

<h3>💡 Passwort-Empfehlungen</h3>
<ul>
  <li>Mindestens 12 Zeichen verwenden</li>
  <li>Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen kombinieren</li>
  <li>Kein Passwort mehrfach verwenden</li>
  <li>Einen Passwort-Manager verwenden</li>
  <li>2FA zusätzlich aktivieren (empfohlen!)</li>
</ul>

<h3>🖼️ Profilbild und Bio</h3>
<p>Im Profil-Tab kann ein Profilbild (JPG/PNG/GIF, max. 2MB) hochgeladen werden. Die Bio wird anderen Supportern in der Ticket-Ansicht angezeigt.</p>',
'passwort, sicherheit, login, konto, profilbild', 1, 1),


(3, 'Zwei-Faktor-Authentifizierung (2FA) einrichten',
'<h2>🔐 Zwei-Faktor-Authentifizierung (2FA)</h2>
<p>2FA erhöht die Sicherheit erheblich, indem neben dem Passwort ein zeitbasierter Code (TOTP) erforderlich ist.</p>

<h3>📱 Voraussetzung</h3>
<p>Eine TOTP-kompatible App auf dem Smartphone:</p>
<ul>
  <li>Google Authenticator (iOS / Android)</li>
  <li>Microsoft Authenticator</li>
  <li>Authy</li>
  <li>1Password, Bitwarden oder andere Passwort-Manager mit OTP-Unterstützung</li>
</ul>

<h3>⚙️ 2FA aktivieren</h3>
<ol>
  <li>Profil aufrufen → Tab <strong>„2FA"</strong></li>
  <li>Auf <strong>„2FA einrichten"</strong> klicken</li>
  <li><strong>Schritt 1:</strong> QR-Code mit der Authenticator-App scannen <em>(alternativ: manuellen Schlüssel eingeben)</em></li>
  <li><strong>Schritt 2:</strong> Den 6-stelligen Code aus der App eingeben und bestätigen</li>
  <li><strong>Schritt 3:</strong> Backup-Codes sicher speichern!</li>
</ol>

<h3>🔑 Backup-Codes</h3>
<blockquote>⚠️ <strong>Wichtig:</strong> Backup-Codes sind Einmalcodes für den Notfall wenn du keinen Zugriff auf deine Authenticator-App hast. Speichere sie an einem sicheren Ort – sie können nur einmal verwendet werden!</blockquote>

<h3>🔄 2FA deaktivieren</h3>
<ol>
  <li>Profil → Tab „2FA" → „2FA deaktivieren"</li>
  <li>Aktuellen 2FA-Code eingeben</li>
  <li>Nach Deaktivierung wird automatisch ein neuer Schlüssel generiert, falls 2FA später erneut aktiviert wird</li>
</ol>

<h3>🔐 2FA beim Login</h3>
<p>Nach Eingabe von Benutzername und Passwort erscheint ein zweites Formular für den 6-stelligen Code. Backup-Codes werden ebenfalls akzeptiert.</p>',
'2fa, zwei-faktor, totp, authenticator, backup-codes, sicherheit', 1, 1),


(3, 'Profil und Einstellungen verwalten',
'<h2>👤 Profil verwalten</h2>
<p>Das Profil ist unter <code>/users/profile.php</code> erreichbar oder über das Benutzermenü oben rechts.</p>

<h3>📑 Profil-Tabs</h3>
<ul>
  <li><strong>Profil:</strong> Name und Bio bearbeiten</li>
  <li><strong>Profilbild:</strong> Avatar hochladen oder entfernen (JPG/PNG/GIF, max. 2MB)</li>
  <li><strong>Passwort:</strong> Passwort ändern</li>
  <li><strong>2FA:</strong> Zwei-Faktor-Authentifizierung verwalten</li>
  <li><strong>Vorlagen:</strong> Persönliche Antwortvorlagen (Link zur Vorlagenverwaltung)</li>
</ul>

<h3>✍️ Antwortvorlagen</h3>
<p>Unter <code>/users/templates.php</code> können persönliche Textvorlagen mit Platzhaltern erstellt werden:</p>
<ul>
  <li><code>{{kunde_name}}</code> – Name des Kunden</li>
  <li><code>{{ticket_nr}}</code> – Ticket-Nummer</li>
  <li><code>{{supporter_name}}</code> – Eigener Name</li>
  <li><code>{{datum}}</code> – Heutiges Datum</li>
  <li><code>{{betreff}}</code> – Ticket-Betreff</li>
  <li><code>{{status}}</code> – Aktueller Status</li>
  <li><code>{{email}}</code> – E-Mail des Kunden</li>
</ul>
<p>Vorlagen werden im Ticket-Eingabefeld über das Vorlagen-Dropdown eingefügt.</p>',
'profil, avatar, vorlagen, platzhalter, einstellungen', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 4: Team & Kommunikation
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(4, 'Team-Chat nutzen',
'<h2>💬 Team-Chat</h2>
<p>Der Team-Chat ist ein interner Echtzeit-Chat für alle Supporter. Er ist nur für Supporter sichtbar und erscheint als schwebende Chat-Blase unten rechts auf jeder Seite.</p>

<h3>🖱️ Chat öffnen und schließen</h3>
<ul>
  <li>Auf die <strong>Chat-Blase</strong> (💬) unten rechts klicken</li>
  <li>Ein roter Badge zeigt die Anzahl ungelesener Nachrichten an</li>
  <li>✕-Button zum Schließen</li>
</ul>

<h3>📝 Nachrichten senden</h3>
<ul>
  <li>Text eingeben und <kbd>Enter</kbd> drücken (oder ➤-Button klicken)</li>
  <li><kbd>Shift</kbd>+<kbd>Enter</kbd> für Zeilenumbruch</li>
  <li>Emoji-Picker über den 😊-Button</li>
  <li>GIF-Suche über den 🎞️-Button (wenn vom Admin aktiviert)</li>
</ul>

<h3>🎫 Ticket im Chat teilen</h3>
<p>In der Ticket-Ansicht über <strong>„Link an Chat senden"</strong>:</p>
<ul>
  <li>Optionalen Kommentar hinzufügen</li>
  <li>Das Ticket erscheint als formatierte Karte im Chat</li>
  <li>Alle Supporter können direkt auf <strong>„Ticket ansehen"</strong> klicken</li>
</ul>

<h3>🔔 Ton-Einstellungen</h3>
<ul>
  <li>🔔-Button im Chat-Header: Benachrichtigungston ein/ausschalten</li>
  <li>Einstellung wird gespeichert und bleibt auch nach dem Neuladen erhalten</li>
</ul>

<h3>📢 Admin-Nachrichten</h3>
<p>Admins können globale Nachrichten an alle Supporter senden. Diese erscheinen gelb hervorgehoben im Chat-Feed aller Supporter.</p>',
'chat, team-chat, nachrichten, emoji, gif, ticket teilen', 1, 1),


(4, 'Privates Postfach (Private Nachrichten)',
'<h2>📬 Privates Postfach</h2>
<p>Das private Postfach ermöglicht es Supportern, sich gegenseitig private Nachrichten zu schicken. Erreichbar über das Briefumschlag-Icon in der Navbar oder <code>/users/messages.php</code>.</p>

<h3>📁 Postfach-Bereiche</h3>
<ul>
  <li><strong>📥 Posteingang:</strong> Empfangene Nachrichten</li>
  <li><strong>📤 Gesendet:</strong> Gesendete Nachrichten</li>
  <li><strong>🗑️ Papierkorb:</strong> Gelöschte Nachrichten (wiederherstellbar)</li>
</ul>

<h3>✍️ Neue Nachricht senden</h3>
<ol>
  <li>Empfänger aus der Dropdown-Liste wählen</li>
  <li>Betreff eingeben</li>
  <li>Nachricht verfassen</li>
  <li>Auf <strong>„Senden"</strong> klicken</li>
</ol>

<h3>↩️ Auf Nachrichten antworten</h3>
<p>Beim Öffnen einer Nachricht erscheint unten ein Antwortfeld. Antworten erscheinen als zusammenhängender Thread – gleiche Tickets werden nicht als neue Nachrichten gelistet.</p>

<h3>🔔 System-Benachrichtigungen</h3>
<p>Das System sendet automatisch Benachrichtigungen ins Postfach wenn:</p>
<ul>
  <li>Ein angenommenes Ticket eine neue Antwort erhält</li>
  <li>Der Ticket-Status sich ändert</li>
  <li>Eine interne Nachricht zu einem deiner Tickets geschrieben wird</li>
</ul>
<p>Der Absender heißt <strong>„System"</strong>. Wird eine Systembenachrichtigung gelöscht, erscheint sie beim nächsten Ereignis erneut.</p>',
'postfach, private nachrichten, posteingang, system, benachrichtigungen', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 5: Admin & Einstellungen
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(5, 'Admin-Einstellungen – Übersicht',
'<h2>⚙️ Admin-Einstellungen</h2>
<p>Die Einstellungsseite (<code>/admin/settings.php</code>) ist in Tabs gegliedert. Nur Admins haben Zugriff.</p>

<h3>📑 Verfügbare Tabs</h3>
<ul>
  <li>🌐 <strong>Website:</strong> Seitenname, URL, Zeitzone, Datenbankverbindung (nur Hauptadmin)</li>
  <li>🎨 <strong>Theme:</strong> Design auswählen (25+ Themes), Custom CSS</li>
  <li>✉️ <strong>E-Mail:</strong> SMTP-Konfiguration und E-Mail-Vorlagen</li>
  <li>📄 <strong>Globale Vorlagen:</strong> Textvorlagen für alle Supporter</li>
  <li>💬 <strong>Team-Chat:</strong> Chat aktivieren/deaktivieren, Zeichenlimit, GIFs, Emojis</li>
  <li>🎮 <strong>Discord:</strong> Webhook-URL, Trigger, Embed-Design</li>
  <li>🔗 <strong>Git:</strong> GitHub/GitLab-Integrationen</li>
  <li>📋 <strong>YouTrack:</strong> YouTrack-Integrationen</li>
  <li>🌍 <strong>Sprachen:</strong> Sprachdateien verwalten, importieren, exportieren</li>
  <li>🖌️ <strong>Custom CSS:</strong> Eigenes CSS für alle Seiten</li>
  <li>🦶 <strong>Footer:</strong> Footer-Inhalt, rechtliche Seiten, eigene Links</li>
</ul>

<h3>🎨 Theme wechseln</h3>
<ol>
  <li>Einstellungen → Tab "Theme"</li>
  <li>Gewünschtes Theme anklicken</li>
  <li>Auf <strong>„Theme Speichern"</strong> klicken</li>
</ol>
<blockquote>💡 Manche Themes (GTA, DayZ, Rotlicht, Black&amp;Gold, Windows XP, YouTube) unterstützen Hintergrundbilder mit Blur- und Helligkeitsregelung.</blockquote>',
'admin, einstellungen, theme, smtp, discord, sprachen', 1, 1),


(5, 'E-Mail-Vorlagen konfigurieren',
'<h2>✉️ E-Mail-Vorlagen</h2>
<p>Das System versendet automatische E-Mails für verschiedene Ereignisse. Die Inhalte sind vollständig anpassbar.</p>

<h3>📧 Verfügbare Vorlagen</h3>
<ul>
  <li><strong>ticket_created:</strong> Bestätigung für den Kunden nach Ticket-Erstellung</li>
  <li><strong>ticket_updated:</strong> Benachrichtigung wenn Supporter antwortet</li>
  <li><strong>ticket_assigned:</strong> Benachrichtigung für Supporter bei Zuweisung</li>
  <li><strong>ticket_new_message_supporter:</strong> Benachrichtigung wenn Kunde antwortet</li>
</ul>

<h3>🔧 Vorlage bearbeiten</h3>
<ol>
  <li>Admin → Einstellungen → Tab „E-Mail-Vorlagen"</li>
  <li>Vorlage aus der Liste auswählen</li>
  <li>Betreff und HTML-/Text-Inhalt bearbeiten</li>
  <li>Platzhalter aus der Sidebar einfügen (Klick fügt automatisch ein)</li>
  <li>Vorschau über den Vorschau-Tab prüfen</li>
  <li>Speichern</li>
</ol>

<h3>🔀 Verfügbare Platzhalter</h3>
<ul>
  <li><code>{{customer_name}}</code> – Name des Kunden</li>
  <li><code>{{supporter_name}}</code> – Name des Supporters</li>
  <li><code>{{ticket_code}}</code> – Ticket-Nummer (z.B. T-A1B2C3D4)</li>
  <li><code>{{ticket_url}}</code> – Direktlink zum Ticket</li>
  <li><code>{{reply_message}}</code> – Inhalt der letzten Nachricht</li>
  <li><code>{{site_name}}</code> – Name der Website</li>
</ul>

<h3>📝 HTML- und Text-Mails</h3>
<p>Jede Vorlage hat einen <strong>HTML-Body</strong> (für moderne E-Mail-Clients) und einen <strong>Plain-Text-Fallback</strong> (für ältere Clients oder Spam-Filter).</p>',
'e-mail, vorlagen, smtp, platzhalter, benachrichtigungen', 1, 1),


(5, 'Discord-Integration einrichten',
'<h2>🎮 Discord-Integration</h2>
<p>Das System kann neue Tickets und Statusänderungen automatisch in einen Discord-Kanal posten.</p>

<h3>🔧 Webhook erstellen (Discord)</h3>
<ol>
  <li>Discord-Server öffnen → Einstellungen → Integrationen → Webhooks</li>
  <li>Auf <strong>„Neuer Webhook"</strong> klicken</li>
  <li>Namen und Kanal festlegen</li>
  <li>Webhook-URL kopieren</li>
</ol>

<h3>⚙️ Im System konfigurieren</h3>
<ol>
  <li>Admin → Einstellungen → Tab „Discord"</li>
  <li>Integration <strong>aktivieren</strong></li>
  <li>Webhook-URL einfügen</li>
  <li>Bot-Name und optionales Avatar-Bild eintragen</li>
  <li>Trigger auswählen: Neues Ticket, Neue Antwort, Statusänderung, Ticket geschlossen</li>
  <li>Embed-Design anpassen (Farbe, Titel, Beschreibung, angezeigte Felder)</li>
  <li><strong>„Test-Nachricht senden"</strong> zum Testen</li>
  <li>Speichern</li>
</ol>

<h3>📋 Embed-Inhalt</h3>
<p>Folgende Informationen können im Discord-Embed angezeigt werden:</p>
<ul>
  <li>Betreff, Beschreibung, Priorität, Kategorie, Ersteller, Direktlink</li>
  <li>Eigene Felder mit Platzhaltern sind ebenfalls möglich</li>
</ul>',
'discord, webhook, benachrichtigung, embed, integration', 1, 1),


(5, 'GitHub und GitLab Integration',
'<h2>🔗 GitHub &amp; GitLab Integration</h2>
<p>Supporter können direkt aus Tickets heraus Issues in GitHub oder GitLab anlegen.</p>

<h3>⚙️ Integration einrichten (Admin)</h3>
<ol>
  <li>Admin → Einstellungen → Tab „Git" → <strong>„Neue Integration"</strong></li>
  <li>Provider wählen: GitHub oder GitLab</li>
  <li>Access Token eingeben (wird verschlüsselt gespeichert)</li>
  <li>Owner/Namespace und Repository-Name eintragen</li>
  <li>Optional: Standard-Labels und Standard-Assignee</li>
  <li>Integration aktivieren und speichern</li>
  <li>Verbindung über <strong>„Verbindung testen"</strong> prüfen</li>
</ol>

<h3>🔑 Token-Berechtigungen</h3>
<ul>
  <li><strong>GitHub (Classic):</strong> Scope <code>repo</code> oder <code>public_repo</code></li>
  <li><strong>GitHub (Fine-grained):</strong> Repository permissions → Issues: Read and write</li>
  <li><strong>GitLab:</strong> Scope <code>api</code></li>
</ul>

<h3>🎫 Issue aus Ticket erstellen (Supporter)</h3>
<ol>
  <li>Ticket öffnen</li>
  <li>In der Aktionsbox auf <strong>„GitHub Issue"</strong> oder <strong>„GitLab Issue"</strong> klicken</li>
  <li>Titel, Beschreibung, Labels und Assignees prüfen/anpassen</li>
  <li>Integration auswählen (wenn mehrere konfiguriert)</li>
  <li>Auf <strong>„Issue erstellen"</strong> klicken</li>
  <li>Der Link zum erstellten Issue wird im Ticket angezeigt</li>
</ol>',
'github, gitlab, integration, issues, token', 1, 1),


(5, 'Sprachen verwalten und importieren',
'<h2>🌍 Sprachverwaltung</h2>
<p>Das System unterstützt mehrere Sprachen gleichzeitig. Sprachdateien liegen unter <code>assets/lang/</code>.</p>

<h3>🌐 Mitgelieferte Sprachen</h3>
<ul>
  <li>🇩🇪 Deutsch (DE-de)</li>
  <li>🇬🇧 Englisch (EN-en) – <strong>Fallback-Sprache, immer aktiv</strong></li>
  <li>🇫🇷 Französisch (FR-fr)</li>
  <li>🇨🇭 Schweizerdeutsch (CH-ch)</li>
  <li>🌊 Plattdeutsch (NDS-nds)</li>
</ul>

<h3>⚙️ Sprachen aktivieren/deaktivieren</h3>
<ol>
  <li>Admin → Einstellungen → Tab „Sprachen"</li>
  <li>Gewünschte Sprache aktivieren oder deaktivieren</li>
  <li>Deaktivierte Sprachen sind für Nutzer nicht wählbar</li>
</ol>

<h3>📥 Eigene Sprachdatei importieren</h3>
<ol>
  <li>Bestehende Sprachdatei exportieren (PHP oder JSON)</li>
  <li>Datei lokal bearbeiten – alle Keys übersetzen</li>
  <li>Im Import-Bereich: Datei wählen, Sprachcode eingeben (z.B. <code>ES-es</code>)</li>
  <li>Optional: „Vorhandene Datei überschreiben" aktivieren</li>
  <li>Importieren – die neue Sprache erscheint sofort in der Liste</li>
</ol>

<blockquote>📌 Englisch (EN-en) kann nicht deaktiviert werden – es dient als System-Fallback wenn ein Key in der gewählten Sprache fehlt.</blockquote>',
'sprache, übersetzung, import, export, mehrsprachigkeit', 1, 1);


-- ─────────────────────────────────────────────────────────────────────────────
-- Kategorie 6: Wissensdatenbank
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO kb_articles (category_id, title, content, tags, is_published, created_by) VALUES

(6, 'Wissensdatenbank – Übersicht und Struktur',
'<h2>📚 Wissensdatenbank</h2>
<p>Die Wissensdatenbank (KB) ist ein internes Nachschlagewerk für das Support-Team. Sie ist erreichbar unter <code>/support/knowledge-base.php</code> oder über „WIKI" in der Navigation.</p>

<h3>🏗️ Struktur</h3>
<ul>
  <li><strong>Kategorien:</strong> Themenbereiche mit Icon, Name, Beschreibung und Sortierreihenfolge</li>
  <li><strong>Artikel:</strong> Inhalte mit Titel, HTML-Inhalt, Tags und Veröffentlichungsstatus</li>
</ul>

<h3>🔍 Artikel suchen</h3>
<p>Über das Suchfeld oben rechts können Artikel nach Titel, Inhalt und Tags durchsucht werden. Die Suche nutzt MySQL FULLTEXT-Indizes für schnelle Ergebnisse.</p>

<h3>👁️ Veröffentlichungsstatus</h3>
<ul>
  <li><strong>Veröffentlicht:</strong> Für alle Supporter sichtbar</li>
  <li><strong>Entwurf:</strong> Nur für Editoren und Admins sichtbar (mit Hinweis-Badge)</li>
</ul>

<h3>✏️ Wer darf bearbeiten?</h3>
<p>Standardmäßig können nur <strong>Admins</strong> die KB bearbeiten. Admins können einzelnen Supportern Bearbeitungsrechte erteilen:</p>
<ol>
  <li>KB öffnen → Bereich „Bearbeitungsberechtigungen"</li>
  <li>Supporter aus der Liste wählen</li>
  <li>Auf <strong>„Berechtigungen erteilen"</strong> klicken</li>
</ol>',
'wissensdatenbank, wiki, kategorie, artikel, berechtigungen', 1, 1),


(6, 'Kategorien erstellen und verwalten',
'<h2>📂 Kategorien verwalten</h2>
<p>Kategorien sind die oberste Strukturebene der Wissensdatenbank. Jeder Artikel gehört zu genau einer Kategorie.</p>

<h3>➕ Neue Kategorie erstellen</h3>
<ol>
  <li>Wissensdatenbank öffnen</li>
  <li>Auf <strong>„+ Kategorie"</strong> klicken (oben rechts)</li>
  <li><strong>Icon</strong> über den Emoji-Picker auswählen</li>
  <li><strong>Name</strong> eingeben (Pflichtfeld)</li>
  <li><strong>Beschreibung</strong> optional hinzufügen</li>
  <li><strong>Reihenfolge</strong> festlegen (niedrigere Zahl = weiter oben)</li>
  <li>Speichern</li>
</ol>

<h3>🎨 Icon-Picker</h3>
<p>Der Icon-Picker zeigt Emojis in kategorisierten Gruppen:</p>
<ul>
  <li>📁 Dateien &amp; Ordner</li>
  <li>⭐ Highlights</li>
  <li>🛠️ Tools &amp; Werkzeuge</li>
  <li>📚 Wissen &amp; Dokumente</li>
  <li>💬 Kommunikation</li>
  <li>... und mehr</li>
</ul>
<p>Über das Suchfeld können auch gezielt Emojis gefunden werden.</p>

<h3>✏️ Kategorie bearbeiten</h3>
<ul>
  <li>In der Kategorieansicht auf das ✏️-Symbol klicken</li>
  <li>Alle Felder sind bearbeitbar</li>
  <li>Reihenfolge jederzeit anpassbar</li>
</ul>

<h3>🗑️ Kategorie löschen</h3>
<blockquote>⚠️ <strong>Achtung:</strong> Das Löschen einer Kategorie löscht auch alle darin enthaltenen Artikel! Diese Aktion kann nicht rückgängig gemacht werden.</blockquote>',
'kategorie, erstellen, icon, emoji, reihenfolge', 1, 1),


(6, 'Artikel erstellen und bearbeiten',
'<h2>✏️ Artikel erstellen</h2>

<h3>➕ Neuen Artikel erstellen</h3>
<ol>
  <li>Kategorie öffnen → <strong>„+ Artikel"</strong> klicken</li>
  <li><strong>Titel</strong> eingeben – kurz und prägnant</li>
  <li><strong>Kategorie</strong> zuweisen</li>
  <li><strong>Tags</strong> kommagetrennt hinzufügen (für bessere Suche)</li>
  <li>Inhalt im <strong>HTML-Editor</strong> verfassen</li>
  <li>Veröffentlichungsstatus wählen</li>
  <li>Auf <strong>„Speichern &amp; Veröffentlichen"</strong> oder <strong>„Als Entwurf speichern"</strong> klicken</li>
</ol>

<h3>📝 HTML-Editor Tipps</h3>
<p>Der Editor akzeptiert HTML. Nützliche Elemente:</p>
<ul>
  <li><code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code> – Überschriften</li>
  <li><code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code> – Listen</li>
  <li><code>&lt;table&gt;</code> – Tabellen (mit <code>border="1" cellpadding="6"</code>)</li>
  <li><code>&lt;blockquote&gt;</code> – Hinweis-Boxen</li>
  <li><code>&lt;code&gt;</code> – Inline-Code</li>
  <li><code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code> – Fett, Kursiv</li>
</ul>

<h3>🏷️ Tags</h3>
<p>Tags verbessern die Auffindbarkeit erheblich. Empfehlung:</p>
<ul>
  <li>3–7 relevante Tags pro Artikel</li>
  <li>Synonyme und häufige Suchbegriffe verwenden</li>
  <li>Kleinschreibung, kommagetrennt</li>
</ul>

<h3>🔄 Artikel bearbeiten</h3>
<p>Über das ✏️-Symbol in der Artikelansicht oder der Kategorieübersicht. Alle Änderungen werden mit Zeitstempel und Bearbeiter-Name gespeichert.</p>',
'artikel, editor, html, tags, veröffentlichen, entwurf', 1, 1);
