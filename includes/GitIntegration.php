<?php
class GitIntegration {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        // Tabellen werden über database.sql angelegt – keine DDL im PHP-Code
    }

    // ── Alle aktiven Integrationen laden ──────────────────────────────────────
    public function getAll(bool $activeOnly = false): array {
        $sql  = "SELECT * FROM git_integrations";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Einzelne Integration laden ────────────────────────────────────────────
    public function getById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM git_integrations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Integration speichern ─────────────────────────────────────────────────
    public function save(array $data) {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("UPDATE git_integrations SET
                name=?, provider=?, api_url=?, token=?, owner=?, repo=?,
                default_labels=?, default_assignee=?, is_active=?
                WHERE id=?");
            $ok = $stmt->execute([
                $data['name'], $data['provider'], $data['api_url'], $data['token'],
                $data['owner'], $data['repo'],
                $data['default_labels'] ?? null, $data['default_assignee'] ?? null,
                (int)($data['is_active'] ?? 1), (int)$data['id']
            ]);
            return $ok ? (int)$data['id'] : false;
        } else {
            $stmt = $this->db->prepare("INSERT INTO git_integrations
                (name, provider, api_url, token, owner, repo, default_labels, default_assignee, is_active)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $ok = $stmt->execute([
                $data['name'], $data['provider'], $data['api_url'] ?? '', $data['token'],
                $data['owner'], $data['repo'],
                $data['default_labels'] ?? null, $data['default_assignee'] ?? null,
                (int)($data['is_active'] ?? 1)
            ]);
            return $ok ? (int)$this->db->lastInsertId() : false;
        }
    }

    // ── Integration löschen ───────────────────────────────────────────────────
    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM git_integrations WHERE id=?")->execute([$id]);
    }

    // ── Issue erstellen ───────────────────────────────────────────────────────
    public function createIssue(int $integrationId, array $issueData, int $createdBy): array {
        $integration = $this->getById($integrationId);
        if (!$integration) {
            return ['ok' => false, 'error' => 'Integration nicht gefunden.'];
        }
        if (!$integration['is_active']) {
            return ['ok' => false, 'error' => 'Integration ist deaktiviert.'];
        }

        $result = $integration['provider'] === 'gitlab'
            ? $this->createGitLabIssue($integration, $issueData)
            : $this->createGitHubIssue($integration, $issueData);

        if (!$result['ok']) return $result;

        // In DB speichern
        $stmt = $this->db->prepare("INSERT INTO git_issues
            (ticket_id, integration_id, issue_number, issue_url, issue_title, provider, created_by)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $issueData['ticket_id'],
            $integrationId,
            $result['number'],
            $result['url'],
            $issueData['title'],
            $integration['provider'],
            $createdBy
        ]);

        return ['ok' => true, 'number' => $result['number'], 'url' => $result['url']];
    }

    // ── GitHub Issue erstellen ────────────────────────────────────────────────
    private function createGitHubIssue(array $integration, array $data): array {
        $owner = trim($integration['owner']);
        $repo  = trim($integration['repo']);
        $url   = "https://api.github.com/repos/{$owner}/{$repo}/issues";

        $body = [
            'title' => $data['title'],
            'body'  => $data['body'] ?? '',
        ];
        if (!empty($data['labels'])) {
            $body['labels'] = array_map('trim', explode(',', $data['labels']));
        }
        if (!empty($data['assignees'])) {
            $body['assignees'] = array_filter(array_map('trim', explode(',', $data['assignees'])));
        }

        $response = $this->httpPost($url, $body, $this->githubHeaders($integration['token']));

        if (!$response['ok']) {
            $msg = is_array($response['body'])
                ? ($response['body']['message'] ?? json_encode($response['body']))
                : (string)$response['http_code'];
            $errors = isset($response['body']['errors']) ? ' – ' . json_encode($response['body']['errors']) : '';
            return ['ok' => false, 'error' => "GitHub API ({$response['http_code']}): {$msg}{$errors}"];
        }

        return [
            'ok'     => true,
            'number' => $response['body']['number'],
            'url'    => $response['body']['html_url'],
        ];
    }

    // ── GitLab Issue erstellen ────────────────────────────────────────────────
    private function createGitLabIssue(array $integration, array $data): array {
        $apiBase = rtrim($integration['api_url'] ?: 'https://gitlab.com', '/');
        $project = rawurlencode(trim($integration['owner']) . '/' . trim($integration['repo']));
        $url     = "{$apiBase}/api/v4/projects/{$project}/issues";

        $body = [
            'title'       => $data['title'],
            'description' => $data['body'] ?? '',
        ];
        if (!empty($data['labels'])) {
            $body['labels'] = $data['labels'];
        }
        if (!empty($data['assignees'])) {
            $body['assignee_ids'] = array_map('intval', array_filter(array_map('trim', explode(',', $data['assignees']))));
        }

        $response = $this->httpPost($url, $body, $this->gitlabHeaders($integration['token']));

        if (!$response['ok']) {
            $msg = is_array($response['body'])
                ? ($response['body']['message'] ?? json_encode($response['body']))
                : (string)$response['http_code'];
            return ['ok' => false, 'error' => "GitLab API ({$response['http_code']}): {$msg}"];
        }


        return [
            'ok'     => true,
            'number' => $response['body']['iid'],
            'url'    => $response['body']['web_url'],
        ];
    }

    // ── Verbindung testen ─────────────────────────────────────────────────────
    public function testConnection(int $id): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL ist auf diesem Server nicht verfügbar.'];
        }

        $integration = $this->getById($id);
        if (!$integration) return ['ok' => false, 'error' => 'Integration nicht gefunden.'];

        $owner = trim($integration['owner']);
        $repo  = trim($integration['repo']);
        $token = trim($integration['token']);

        if (!$owner || !$repo) {
            return ['ok' => false, 'error' => 'Owner und Repository dürfen nicht leer sein.'];
        }
        if (!$token) {
            return ['ok' => false, 'error' => 'Kein Token konfiguriert.'];
        }

        if ($integration['provider'] === 'github') {
            // Zuerst Token-Validität prüfen
            $authCheck = $this->httpGet('https://api.github.com/user', $this->githubHeaders($token));
            if (!$authCheck['ok']) {
                $msg = is_array($authCheck['body']) ? ($authCheck['body']['message'] ?? json_encode($authCheck['body'])) : 'HTTP ' . $authCheck['http_code'];
                return ['ok' => false, 'error' => "GitHub Token ungültig ({$authCheck['http_code']}): {$msg}"];
            }
            // Dann Repo prüfen
            $url      = "https://api.github.com/repos/{$owner}/{$repo}";
            $response = $this->httpGet($url, $this->githubHeaders($token));
            if (!$response['ok']) {
                $msg = is_array($response['body']) ? ($response['body']['message'] ?? json_encode($response['body'])) : 'HTTP ' . $response['http_code'];
                return ['ok' => false, 'error' => "GitHub Repo nicht gefunden ({$response['http_code']}): {$msg} — Bitte Owner '{$owner}' und Repo '{$repo}' prüfen."];
            }
            $repoName   = $response['body']['full_name']        ?? "{$owner}/{$repo}";
            $issueCount = $response['body']['open_issues_count'] ?? 0;
            $private    = ($response['body']['private'] ?? false) ? ' [privat]' : ' [öffentlich]';
            return ['ok' => true, 'info' => "✅ GitHub: {$repoName}{$private} – {$issueCount} offene Issues"];

        } else {
            $apiBase = rtrim($integration['api_url'] ?: 'https://gitlab.com', '/');
            $project = rawurlencode("{$owner}/{$repo}");
            $url     = "{$apiBase}/api/v4/projects/{$project}";
            $response = $this->httpGet($url, $this->gitlabHeaders($token));
            if (!$response['ok']) {
                $msg = is_array($response['body'])
                    ? ($response['body']['message'] ?? json_encode($response['body']))
                    : 'HTTP ' . $response['http_code'];
                return ['ok' => false, 'error' => "GitLab ({$response['http_code']}): {$msg} — Bitte Owner '{$owner}' und Repo '{$repo}' prüfen."];
            }
            $name = $response['body']['name_with_namespace'] ?? "{$owner}/{$repo}";
            $pid  = $response['body']['id'] ?? '?';
            return ['ok' => true, 'info' => "✅ GitLab: {$name} (ID: {$pid})"];
        }
    }

    // ── Issues für ein Ticket laden ───────────────────────────────────────────
    public function getIssuesForTicket(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT gi.*, u.full_name AS created_by_name,
                   g.name AS integration_name, g.provider
            FROM git_issues gi
            JOIN users u ON u.id = gi.created_by
            JOIN git_integrations g ON g.id = gi.integration_id
            WHERE gi.ticket_id = ?
            ORDER BY gi.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Header-Hilfsmethoden ──────────────────────────────────────────────────
    private function githubHeaders(string $token): array {
        return [
            'Authorization: Bearer ' . $token,   // Bearer statt "token" (neuer Standard)
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: SupportSystems/2.0',
        ];
    }

    private function gitlabHeaders(string $token): array {
        return [
            'PRIVATE-TOKEN: ' . $token,
            'Content-Type: application/json',
        ];
    }

    // ── HTTP POST ─────────────────────────────────────────────────────────────
    private function httpPost(string $url, array $data, array $headers): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'http_code' => 0, 'body' => 'cURL nicht verfügbar'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) return ['ok' => false, 'http_code' => 0, 'body' => $err];
        $body = json_decode($raw, true);
        if ($body === null) $body = $raw;
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => $body];
    }

    // ── HTTP GET ──────────────────────────────────────────────────────────────
    private function httpGet(string $url, array $headers): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'http_code' => 0, 'body' => 'cURL nicht verfügbar'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) return ['ok' => false, 'http_code' => 0, 'body' => $err];
        $body = json_decode($raw, true);
        if ($body === null) $body = $raw;
        return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => $body];
    }
}

