<?php
require_once __DIR__ . '/CategoryHelper.php';

class Ticket {
    private $db;
    private static bool $tablesChecked = false;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTablesExist();
    }

    /**
     * Stellt sicher, dass alle benötigten Tabellen existieren.
     * Wird einmalig pro Request ausgeführt (Self-Healing für ältere Installationen).
     */
    private function ensureTablesExist(): void {
        if (self::$tablesChecked) return;
        self::$tablesChecked = true;

        try {
            $this->db->exec("
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // supporter_categories (m:n)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS supporter_categories (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    user_id     INT NOT NULL,
                    category_id INT NOT NULL,
                    UNIQUE KEY uq_supporter_category (user_id, category_id),
                    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Stelle sicher dass tickets.category_id existiert (ältere Installationen)
            $cols = $this->db->query("SHOW COLUMNS FROM tickets LIKE 'category_id'")->fetchAll();
            if (empty($cols)) {
                $this->db->exec("ALTER TABLE tickets ADD COLUMN category_id INT NULL AFTER assigned_to");
                $this->db->exec("
                    ALTER TABLE tickets
                    ADD CONSTRAINT fk_tickets_category
                    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL
                ");
            }
        } catch (\Throwable $e) {
            error_log('[Ticket] ensureTablesExist: ' . $e->getMessage());
        }
    }

    public function create($userId, $subject, $description, $priority = 'medium', $categoryId = null) {
        $ticketCode = $this->generateTicketCode();

        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_code, user_id, subject, description, priority, status, support_level, category_id)
            VALUES (?, ?, ?, ?, ?, 'open', 'first_level', ?)
        ");

        $stmt->execute([$ticketCode, $userId, $subject, $description, $priority, $categoryId ?: null]);
        $ticketId = $this->db->lastInsertId();

        // Log history
        $this->addHistory($ticketId, $userId, 'Ticket erstellt', null, 'open');

        // Get ticket data for email
        $ticketData = $this->getById($ticketId);

        // Get user email
        $userStmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        // Send email notification
        $email = new Email();
        $email->sendTicketCreatedEmail($ticketData, $user['email']);

        // Discord-Benachrichtigung
        try {
            if (class_exists('Discord')) {
                $discord = new Discord();
                $discord->notifyNewTicket($ticketData);
            }
        } catch (Exception $e) {
            error_log('[Discord] Fehler bei Ticket-Benachrichtigung: ' . $e->getMessage());
        }

        return $ticketData;
    }

    public function getById($ticketId) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.full_name as user_name, u.email as user_email,
                   a.full_name as assigned_name, a.email as assigned_email,
                   c.name as category_name
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            LEFT JOIN ticket_categories c ON t.category_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetch();
    }

    public function getByCode($ticketCode) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.full_name as user_name, u.email as user_email,
                   a.full_name as assigned_name, a.email as assigned_email,
                   c.name as category_name
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            LEFT JOIN ticket_categories c ON t.category_id = c.id
            WHERE t.ticket_code = ?
        ");
        $stmt->execute([$ticketCode]);
        return $stmt->fetch();
    }

    public function getAll($filters = []) {
        $query = "
            SELECT t.*, u.full_name as user_name, a.full_name as assigned_name,
                   c.name as category_name, c.color as category_color
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            LEFT JOIN ticket_categories c ON t.category_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $query .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['support_level'])) {
            // Höhere Level sehen auch Tickets niedrigerer Level
            $levelHierarchy = [
                'first_level'  => ['first_level'],
                'second_level' => ['first_level', 'second_level'],
                'third_level'  => ['first_level', 'second_level', 'third_level'],
            ];
            $visibleLevels = $levelHierarchy[$filters['support_level']] ?? [$filters['support_level']];
            $placeholders  = implode(',', array_fill(0, count($visibleLevels), '?'));
            $query        .= " AND t.support_level IN ($placeholders)";
            foreach ($visibleLevels as $lvl) {
                $params[] = $lvl;
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['user_id'])) {
            $query .= " AND t.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['unassigned'])) {
            $query .= " AND t.assigned_to IS NULL";
        }

        // Category filtering for support staff
        if (isset($filters['current_user_id']) && isset($filters['current_user_role'])) {
            $userId = $filters['current_user_id'];
            $role   = $filters['current_user_role'];

            if (in_array($role, ['first_level', 'second_level', 'third_level'])) {
                $categoryHelper     = new CategoryHelper();
                $assignedCategories = $categoryHelper->getUserCategories($userId);

                if (!empty($assignedCategories)) {
                    $categoryIds = implode(',', array_map('intval', $assignedCategories));
                    // Tickets in zugewiesenen Kategorien ODER dem Supporter direkt zugewiesen ODER kein Kategorie-Filter (assigned_to = user)
                    $query .= " AND (t.category_id IN ($categoryIds) OR t.category_id IS NULL OR t.assigned_to = ?)";
                    $params[] = $userId;
                }
            }
        }

        $query .= " ORDER BY t.created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Öffentlicher Wrapper für direkte PM-Zustellung (z.B. Prioritätsänderung) */
    public function notifyPM(int $receiverId, int $senderId, string $subject, string $body, int $ticketId = 0) {
        if (!$receiverId) return;
        $this->sendThreadedPM($receiverId, $subject, $body, $ticketId);
    }

    private function notifyAssignedSupporterPM(array $ticketData, int $actorId, string $subject, string $body) {
        $assignedTo = (int)($ticketData['assigned_to'] ?? 0);

        if ($assignedTo && $assignedTo !== $actorId) {
            // Zugewiesenen Supporter direkt benachrichtigen
            $this->sendThreadedPM($assignedTo, $subject, $body, (int)$ticketData['id']);
            return;
        }

        if (!$assignedTo) {
            // Kein Supporter zugewiesen → alle Supporter des Ticket-Levels benachrichtigen
            $level = $ticketData['support_level'] ?? 'first_level';
            // Level-Hierarchie: third_level sieht alles, second_level sieht first+second usw.
            $levelMap = [
                'first_level'  => ['first_level'],
                'second_level' => ['first_level', 'second_level'],
                'third_level'  => ['first_level', 'second_level', 'third_level'],
            ];
            $roles = $levelMap[$level] ?? ['first_level'];
            $placeholders = implode(',', array_fill(0, count($roles), '?'));

            try {
                $stmt = $this->db->prepare(
                    "SELECT id FROM users
                     WHERE role IN ($placeholders)
                       AND id != ?
                     ORDER BY id ASC"
                );
                $stmt->execute(array_merge($roles, [$actorId]));
                $supporters = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($supporters as $supporterId) {
                    $this->sendThreadedPM((int)$supporterId, $subject, $body, (int)$ticketData['id']);
                }
            } catch (\Throwable $e) {
                error_log('[PM-Notify] Supporter-Lookup fehlgeschlagen: ' . $e->getMessage());
            }
        }
    }

    private function sendThreadedPM(int $receiverId, string $subject, string $body, int $ticketId) {
        try {
            // Tabellen via database.sql angelegt

            if ($ticketId) {
                // Root-PM suchen – NUR nicht gelöschte (deleted_receiver = 0)
                $rootStmt = $this->db->prepare(
                    "SELECT id FROM private_messages
                     WHERE ticket_id = ? AND receiver_id = ? AND parent_id IS NULL
                       AND deleted_receiver = 0
                     ORDER BY id DESC LIMIT 1"
                );
                $rootStmt->execute([$ticketId, $receiverId]);
                $rootId = $rootStmt->fetchColumn();

                if (!$rootId) {
                    // Root-PM als leerer Container anlegen (message = '' Platzhalter)
                    $this->db->prepare(
                        "INSERT INTO private_messages (sender_id, receiver_id, subject, message, ticket_id, is_read)
                         VALUES (NULL, ?, ?, '', ?, 0)"
                    )->execute([$receiverId, mb_substr($subject, 0, 255), $ticketId]);
                    $rootId = (int)$this->db->lastInsertId();
                }

                // Event als Reply anhängen
                $this->db->prepare(
                    "INSERT INTO private_messages (sender_id, receiver_id, subject, message, parent_id, ticket_id, is_read)
                     VALUES (NULL, ?, ?, ?, ?, ?, 0)"
                )->execute([$receiverId, mb_substr($subject, 0, 255), $body, $rootId, $ticketId]);

                // Root als ungelesen markieren
                $this->db->prepare("UPDATE private_messages SET is_read = 0 WHERE id = ?")
                         ->execute([$rootId]);
                return;
            }

            // Kein ticketId → einfache Root-PM mit Inhalt
            $this->db->prepare(
                "INSERT INTO private_messages (sender_id, receiver_id, subject, message, ticket_id)
                 VALUES (NULL, ?, ?, ?, NULL)"
            )->execute([$receiverId, mb_substr($subject, 0, 255), $body]);

        } catch (Exception $e) {
            error_log('[PM-Notify] ' . $e->getMessage());
        }
    }

    private function buildPmBody(array $ticketData, string $event, string $detail, string $actorName): string {
        $ticketUrl  = defined('SITE_URL') ? SITE_URL . "/support/view-ticket.php?id={$ticketData['id']}" : '';
        $status     = $this->translateStatus($ticketData['status']);
        $priority   = $this->translatePriority($ticketData['priority']);
        $category   = $ticketData['category_name'] ?? '–';
        $lines = [];
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "🎫  {$ticketData['ticket_code']}  –  {$ticketData['subject']}";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "📌 WAS IST PASSIERT?";
        $lines[] = "   {$event}";
        $lines[] = "";
        $lines[] = "📋 DETAILS:";
        $lines[] = $detail;
        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lines[] = "👤 Ausgelöst von:  {$actorName}";
        $lines[] = "🔖 Status:         {$status}";
        $lines[] = "⚡ Priorität:      {$priority}";
        $lines[] = "📂 Kategorie:      {$category}";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        if ($ticketUrl) {
            $lines[] = "";
            $lines[] = "🔗 TICKET ÖFFNEN:";
            $lines[] = $ticketUrl;
        }
        return implode("\n", $lines);
    }

    public function updateStatus($ticketId, $userId, $newStatus) {
        $ticket = $this->getById($ticketId);
        $oldStatus = $ticket['status'];

        $stmt = $this->db->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $ticketId]);

        $this->addHistory($ticketId, $userId, 'Status geändert', $oldStatus, $newStatus);

        $ticketData = $this->getById($ticketId);

        // PM an zugewiesenen Supporter
        $actorStmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
        $actorStmt->execute([$userId]);
        $actor = $actorStmt->fetchColumn() ?: 'System';
        $this->notifyAssignedSupporterPM(
            $ticketData, $userId,
            "Statusänderung: {$ticketData['ticket_code']}",
            $this->buildPmBody(
                $ticketData,
                "Status wurde geändert",
                "   " . $this->translateStatus($oldStatus) . "  →  " . $this->translateStatus($newStatus),
                $actor
            )
        );

        // E-Mail an Kunden
        $email = new Email();
        $email->sendTicketUpdatedEmail(
            $ticketData,
            $ticketData['user_email'],
            "Status wurde geändert von '" . $this->translateStatus($oldStatus) . "' zu '" . $this->translateStatus($newStatus) . "'"
        );

        return true;
    }

    public function assignTicket($ticketId, $userId, $supporterId) {
        $ticket = $this->getById($ticketId);
        $oldAssigned = $ticket['assigned_name'] ?? 'Nicht zugewiesen';

        $stmt = $this->db->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$supporterId, $ticketId]);

        // Get supporter name
        $supporterStmt = $this->db->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $supporterStmt->execute([$supporterId]);
        $supporter = $supporterStmt->fetch();

        $this->addHistory($ticketId, $userId, 'Ticket zugewiesen', $oldAssigned, $supporter['full_name']);

        // Send notification to supporter
        $ticketData = $this->getById($ticketId);
        $email = new Email();
        $email->sendTicketAssignedEmail($ticketData, $supporter['email']);

        return true;
    }

    public function forwardTicket($ticketId, $userId, $newLevel) {
        $ticket = $this->getById($ticketId);
        $oldLevel = $ticket['support_level'];

        $stmt = $this->db->prepare("UPDATE tickets SET support_level = ?, assigned_to = NULL WHERE id = ?");
        $stmt->execute([$newLevel, $ticketId]);

        $this->addHistory($ticketId, $userId, 'Ticket weitergeleitet', $oldLevel, $newLevel);

        $ticketData = $this->getById($ticketId);

        // PM an den bisherigen zugewiesenen Supporter (vor der Weiterleitung)
        if ($ticket['assigned_to'] && $ticket['assigned_to'] != $userId) {
            $actorStmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
            $actorStmt->execute([$userId]);
            $actor = $actorStmt->fetchColumn() ?: 'System';
            $this->notifyAssignedSupporterPM(
                $ticket, $userId,
                "Ticket weitergeleitet: {$ticket['ticket_code']}",
                $this->buildPmBody(
                    $ticket,
                    "Ticket wurde an ein anderes Support-Level weitergeleitet",
                    "   " . $this->translateLevel($oldLevel) . "  →  " . $this->translateLevel($newLevel),
                    $actor
                )
            );
        }

        // E-Mail an Kunden
        $email = new Email();
        $email->sendTicketUpdatedEmail(
            $ticketData,
            $ticketData['user_email'],
            "Ihr Ticket wurde an " . $this->translateLevel($newLevel) . " weitergeleitet"
        );

        return true;
    }

    public function markMessagesAsRead($ticketId, $userId) {
        $isSupport = isset($_SESSION['role']) && in_array($_SESSION['role'], ['first_level', 'second_level', 'third_level', 'admin']);
        $internalFilter = $isSupport ? '' : 'AND is_internal = 0';

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO message_read_status (message_id, user_id)
            SELECT id, ? FROM ticket_messages
            WHERE ticket_id = ? AND user_id != ? $internalFilter
        ");
        $stmt->execute([$userId, $ticketId, $userId]);
    }

    public function addMessage($ticketId, $userId, $message, $isInternal = false) {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $userId, $message, (int)$isInternal]);

        // Update ticket updated_at
        $updateStmt = $this->db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$ticketId]);

        $ticketData = $this->getById($ticketId);

        // Absender-Info holen
        $actorStmt = $this->db->prepare("SELECT full_name, role FROM users WHERE id = ?");
        $actorStmt->execute([$userId]);
        $actor     = $actorStmt->fetch();
        $actorName = $actor['full_name'] ?? 'Unbekannt';
        $actorRole = $actor['role'] ?? '';
        $isSupporter = in_array($actorRole, ['first_level','second_level','third_level','admin']);
        $ticketUrl   = defined('SITE_URL') ? SITE_URL . "/support/view-ticket.php?id={$ticketId}" : '';

        if ($isInternal) {
            // Interne Nachricht → PM an zugewiesenen Supporter (wenn nicht er selbst)
            $this->notifyAssignedSupporterPM(
                $ticketData, $userId,
                "🔒 Interne Nachricht: {$ticketData['ticket_code']}",
                $this->buildPmBody(
                    $ticketData,
                    "{$actorName} hat eine interne Nachricht verfasst",
                    $message,
                    $actorName
                )
            );
        } else {
            // Öffentliche Antwort → PM an zugewiesenen Supporter (oder alle des Levels)
            $label = $isSupporter ? "Supporter-Antwort" : "Kunden-Antwort";
            $this->notifyAssignedSupporterPM(
                $ticketData, $userId,
                "💬 Neue {$label}: {$ticketData['ticket_code']}",
                $this->buildPmBody(
                    $ticketData,
                    ($isSupporter ? "{$actorName} (Supporter)" : "{$actorName} (Kunde)") . " hat geantwortet",
                    $message,
                    $actorName
                )
            );

            // E-Mail-Benachrichtigungen
            try {
                $email = new Email();
                if (!$isSupporter) {
                    // Kunden-Antwort → E-Mail an zugewiesenen Supporter
                    $assignedTo = (int)($ticketData['assigned_to'] ?? 0);
                    if ($assignedTo) {
                        $supStmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
                        $supStmt->execute([$assignedTo]);
                        $supEmail = $supStmt->fetchColumn();
                        if ($supEmail) {
                            $email->sendNewMessageToSupporter($ticketData, $supEmail, $message);
                        }
                    }
                } else {
                    // Supporter-Antwort → E-Mail an Kunden
                    $email->sendTicketUpdatedEmail(
                        $ticketData,
                        $ticketData['user_email'],
                        $message
                    );
                }
            } catch (\Throwable $e) {
                error_log('[Email] Fehler bei Nachricht-Benachrichtigung: ' . $e->getMessage());
            }

            // Discord-Benachrichtigung
            try {
                if (class_exists('Discord')) {
                    (new Discord())->notifyNewReply($ticketData, $message, $actorName);
                }
            } catch (Exception $e) {
                error_log('[Discord] Fehler bei Antwort-Benachrichtigung: ' . $e->getMessage());
            }
        }

        return true;
    }

    public function getMessages($ticketId, $includeInternal = false) {
        $query = "
            SELECT tm.*, u.full_name as user_name, u.role as user_role,
                   u.avatar as user_avatar, u.bio as user_bio
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ?
        ";

        if (!$includeInternal) {
            $query .= " AND tm.is_internal = 0";
        }

        $query .= " ORDER BY tm.created_at ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function addInternalNote($ticketId, $userId, $note) {
        $stmt = $this->db->prepare("
            INSERT INTO internal_notes (ticket_id, user_id, note)
            VALUES (?, ?, ?)
        ");
        $result = $stmt->execute([$ticketId, $userId, $note]);

        // PM an zugewiesenen Supporter
        $ticketData = $this->getById($ticketId);
        $actorStmt  = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
        $actorStmt->execute([$userId]);
        $actorName  = $actorStmt->fetchColumn() ?: 'Unbekannt';
        $ticketUrl  = defined('SITE_URL') ? SITE_URL . "/support/view-ticket.php?id={$ticketId}" : '';

        $this->notifyAssignedSupporterPM(
            $ticketData, $userId,
            "📝 Neue Notiz: {$ticketData['ticket_code']}",
            $this->buildPmBody(
                $ticketData,
                "{$actorName} hat eine interne Notiz hinzugefügt",
                $note,
                $actorName
            )
        );

        return $result;
    }

    public function getInternalNotes($ticketId) {
        $stmt = $this->db->prepare("
            SELECT in_.*,  u.full_name as user_name
            FROM internal_notes in_
            JOIN users u ON in_.user_id = u.id
            WHERE in_.ticket_id = ?
            ORDER BY in_.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function getHistory($ticketId) {
        $stmt = $this->db->prepare("
            SELECT th.*, u.full_name as user_name
            FROM ticket_history th
            JOIN users u ON th.user_id = u.id
            WHERE th.ticket_id = ?
            ORDER BY th.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    private function addHistory($ticketId, $userId, $action, $oldValue, $newValue) {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$ticketId, $userId, $action, $oldValue, $newValue]);
    }

    private function generateTicketCode() {
        $prefix = 'TKT';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return $prefix . '-' . $year . '-' . $random;
    }

    private function translateStatus($status) {         global $translator;
        if ($translator) return $translator->translate('status_' . $status);
        $f = ['open'=>'Open','in_progress'=>'In Progress','pending'=>'Pending','resolved'=>'Resolved','closed'=>'Closed'];
        return $f[$status] ?? $status;
    }

    private function translatePriority($priority) {
        global $translator;
        if ($translator) return $translator->translate('priority_' . $priority);
        $f = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
        return $f[$priority] ?? $priority;
    }

    private function translateLevel($level) {
        global $translator;
        if ($translator) return $translator->translate('level_' . $level);
        $f = ['first_level'=>'First Level','second_level'=>'Second Level','third_level'=>'Third Level'];
        return $f[$level] ?? $level;
    }

    public function getStatistics($userId = null, $role = null) {
        $stats = [];

        if ($userId && in_array($role, ['first_level', 'second_level', 'third_level'])) {
            // Statistics for support staff
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                FROM tickets
                WHERE assigned_to = ?
            ");
            $stmt->execute([$userId]);
            $stats['my_tickets'] = $stmt->fetch();

            // Nicht-zugewiesene Tickets aller sichtbaren Level (Hierarchie)
            $levelHierarchy = [
                'first_level'  => ['first_level'],
                'second_level' => ['first_level', 'second_level'],
                'third_level'  => ['first_level', 'second_level', 'third_level'],
            ];
            $visibleLevels  = $levelHierarchy[$role] ?? [$role];
            $placeholders   = implode(',', array_fill(0, count($visibleLevels), '?'));
            $levelStmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned
                FROM tickets
                WHERE support_level IN ($placeholders)
            ");
            $levelStmt->execute($visibleLevels);
            $stats['level_tickets'] = $levelStmt->fetch();
        } else {
            // Statistics for users
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                FROM tickets
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['user_tickets'] = $stmt->fetch();
        }

        return $stats;
    }
}
