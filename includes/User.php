<?php
class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {

        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if (!empty($user['locked_until'])) {
            $lockedUntil = strtotime($user['locked_until']);
            if ($lockedUntil > time()) {
                // Noch gesperrt – verbleibende Zeit in Sekunden zurückgeben
                return ['locked' => true, 'until' => $lockedUntil];
            }

            $this->db->prepare("UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=?")
                     ->execute([$user['id']]);
            $user['failed_login_attempts'] = 0;
            $user['locked_until'] = null;
        }


        if (password_verify($password, $user['password'])) {

            $this->db->prepare("UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=?")
                     ->execute([$user['id']]);


            if (!empty($user['two_fa_enabled']) && !empty($user['two_fa_secret'])) {
                $_SESSION['2fa_pending_user_id']  = $user['id'];
                $_SESSION['2fa_pending_username'] = $user['username'];
                $_SESSION['2fa_pending_role']     = $user['role'];
                $_SESSION['2fa_pending_name']     = $user['full_name'];
                return '2fa_required';
            }

            // Kein 2FA → direkt einloggen
            $this->setSession($user);
            return true;
        }


        $attempts = (int)($user['failed_login_attempts'] ?? 0) + 1;
        if ($attempts >= 3) {
            // 5 Minuten sperren
            $lockedUntil = date('Y-m-d H:i:s', time() + 300);
            $this->db->prepare("UPDATE users SET failed_login_attempts=?, locked_until=? WHERE id=?")
                     ->execute([$attempts, $lockedUntil, $user['id']]);
            return ['locked' => true, 'until' => strtotime($lockedUntil)];
        }
        $this->db->prepare("UPDATE users SET failed_login_attempts=? WHERE id=?")
                 ->execute([$attempts, $user['id']]);

        return false;
    }

    public function verify2FA($code) {
        $userId = $_SESSION['2fa_pending_user_id'] ?? null;
        if (!$userId) return false;

        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return false;

        require_once __DIR__ . '/TOTP.php';

        // TOTP-Code prüfen
        if (TOTP::verifyCode($user['two_fa_secret'], $code)) {
            $this->setSession($user);
            $this->clearPending();
            return true;
        }

        // Backup-Code prüfen
        if (!empty($user['backup_codes'])) {
            $backupCodes = json_decode($user['backup_codes'], true);
            $codeUpper   = strtoupper(trim($code));
            $idx         = array_search($codeUpper, $backupCodes);
            if ($idx !== false) {
                // Verwendeten Backup-Code entfernen
                array_splice($backupCodes, $idx, 1);
                $stmt = $this->db->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
                $stmt->execute([json_encode($backupCodes), $userId]);
                $this->setSession($user);
                $this->clearPending();
                return true;
            }
        }

        return false;
    }

    private function setSession($user) {
        $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
    }

    private function clearPending() {
        unset(
            $_SESSION['2fa_pending_user_id'],
            $_SESSION['2fa_pending_username'],
            $_SESSION['2fa_pending_role'],
            $_SESSION['2fa_pending_name']
        );
    }

    public function register($username, $email, $password, $fullName) {
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return false;
        }

        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, full_name, role)
            VALUES (?, ?, ?, ?, 'user')
        ");

        return $stmt->execute([$username, $email, $hashedPassword, $fullName]);
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT id, username, email, full_name, role, created_at, last_login FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getSupporters($level = null) {
        if ($level) {
            $stmt = $this->db->prepare("SELECT id, username, full_name, email FROM users WHERE role = ? ORDER BY full_name");
            $stmt->execute([$level]);
        } else {
            $stmt = $this->db->query("SELECT id, username, full_name, email FROM users WHERE role IN ('first_level', 'second_level', 'third_level', 'admin') ORDER BY full_name");
        }
        return $stmt->fetchAll();
    }

    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($_SESSION['role'], $roles);
        }

        return $_SESSION['role'] === $roles;
    }

    public function updateRole($userId, $newRole) {
        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
        return $stmt->execute([$newRole, $userId]);
    }

    public function delete($userId) {
        // Don't allow deletion of admin user
        $user = $this->getById($userId);
        if ($user['role'] === 'admin' && $user['username'] === 'admin') {
            return false;
        }

        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }
}
