<?php

class YouTrackIntegration {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }


    public function getAll(bool $activeOnly = false): array {
        $sql = "SELECT * FROM youtrack_integrations";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM youtrack_integrations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function save(array $data) {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("UPDATE youtrack_integrations SET
                name=?, base_url=?, token=?, project_id=?,
                default_type=?, default_priority=?, default_assignee=?, default_tags=?, is_active=?
                WHERE id=?");
            $ok = $stmt->execute([
                $data['name'], $data['base_url'], $data['token'], $data['project_id'],
                $data['default_type']     ?? 'Bug',
                $data['default_priority'] ?? 'Normal',
                $data['default_assignee'] ?? null,
                $data['default_tags']     ?? null,
                (int)($data['is_active']  ?? 1),
                (int)$data['id']
            ]);
            return $ok ? (int)$data['id'] : false;
        } else {
            $stmt = $this->db->prepare("INSERT INTO youtrack_integrations
                (name, base_url, token, project_id, default_type, default_priority, default_assignee, default_tags, is_active)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $ok = $stmt->execute([
                $data['name'], $data['base_url'], $data['token'], $data['project_id'],
                $data['default_type']     ?? 'Bug',
                $data['default_priority'] ?? 'Normal',
                $data['default_assignee'] ?? null,
                $data['default_tags']     ?? null,
                (int)($data['is_active']  ?? 1),
            ]);
            return $ok ? (int)$this->db->lastInsertId() : false;
        }
    }


    public function delete(int $id): bool {
        return $this->db->prepare("DELETE FROM youtrack_integrations WHERE id = ?")->execute([$id]);
    }


    public function createIssue(int $integrationId, array $data, int $createdBy): array {
        $integration = $this->getById($integrationId);
        if (!$integration) {
            return ['ok' => false, 'error' => 'Integration nicht gefunden.'];
        }
        if (!$integration['is_active']) {
            return ['ok' => false, 'error' => 'Integration ist deaktiviert.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL ist auf diesem Server nicht verfügbar.'];
        }

        $base    = rtrim($integration['base_url'], '/');
        $token   = trim($integration['token']);
        $project = trim($integration['project_id']);


        $body = [
            'summary'     => $data['summary'],
            'description' => $data['description'] ?? '',
            'project'     => ['id' => $project],
        ];

        $customFields = [];
        if (!empty($data['type'])) {
            $customFields[] = [
                '$type' => 'SingleEnumIssueCustomField',
                'name'  => 'Type',
                'value' => ['name' => $data['type']],
            ];
        }
        if (!empty($data['priority'])) {
            $customFields[] = [
                '$type' => 'SingleEnumIssueCustomField',
                'name'  => 'Priority',
                'value' => ['name' => $data['priority']],
            ];
        }
        if (!empty($customFields)) {
            $body['customFields'] = $customFields;
        }

        $response = $this->httpPost(
            "{$base}/api/issues?fields=id,idReadable,url,summary",
            $body,
            $this->headers($token)
        );

        if (!$response['ok']) {
            $msg = is_array($response['body'])
                ? ($response['body']['error_description'] ?? $response['body']['error'] ?? json_encode($response['body']))
                : (string)$response['http_code'];
            return ['ok' => false, 'error' => "YouTrack API ({$response['http_code']}): {$msg}"];
        }

        $issueId  = $response['body']['idReadable'] ?? $response['body']['id'] ?? '?';
        $issueUrl = $response['body']['url']        ?? "{$base}/issue/{$issueId}";


        if (!empty($data['assignee'])) {
            $realId = $response['body']['id'] ?? null;
            if ($realId) {
                $this->httpPost("{$base}/api/issues/{$realId}/assignees", [
                    ['login' => $data['assignee']]
                ], $this->headers($token));
            }
        }

        if (!empty($data['tags'])) {
            $realId = $response['body']['id'] ?? null;
            if ($realId) {
                foreach (array_filter(array_map('trim', explode(',', $data['tags']))) as $tag) {
                    $this->httpPost("{$base}/api/issues/{$realId}/tags", ['name' => $tag], $this->headers($token));
                }
            }
        }

        $stmt = $this->db->prepare("INSERT INTO youtrack_issues
            (ticket_id, integration_id, issue_id, issue_url, issue_summary, created_by)
            VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $data['ticket_id'],
            $integrationId,
            $issueId,
            $issueUrl,
            $data['summary'],
            $createdBy,
        ]);

        return ['ok' => true, 'issue_id' => $issueId, 'url' => $issueUrl];
    }

    public function testConnection(int $id): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL ist auf diesem Server nicht verfügbar.'];
        }

        $integration = $this->getById($id);
        if (!$integration) return ['ok' => false, 'error' => 'Integration nicht gefunden.'];

        $base    = rtrim(trim($integration['base_url']), '/');
        $token   = trim($integration['token']);
        $project = trim($integration['project_id']);

        if (!$base)    return ['ok' => false, 'error' => 'Keine Base-URL konfiguriert.'];
        if (!$token)   return ['ok' => false, 'error' => 'Kein Token konfiguriert.'];
        if (!$project) return ['ok' => false, 'error' => 'Keine Projekt-ID konfiguriert.'];

        $authCheck = $this->httpGet("{$base}/api/users/me?fields=id,login,fullName", $this->headers($token));
        if (!$authCheck['ok']) {
            $msg = is_array($authCheck['body'])
                ? ($authCheck['body']['error_description'] ?? $authCheck['body']['error'] ?? json_encode($authCheck['body']))
                : 'HTTP ' . $authCheck['http_code'];
            return ['ok' => false, 'error' => "Token ungültig ({$authCheck['http_code']}): {$msg}"];
        }
        $userName = $authCheck['body']['fullName'] ?? $authCheck['body']['login'] ?? '?';

        // Projekt prüfen
        $projCheck = $this->httpGet("{$base}/api/admin/projects/{$project}?fields=id,name,shortName", $this->headers($token));
        if (!$projCheck['ok']) {
            $msg = is_array($projCheck['body'])
                ? ($projCheck['body']['error_description'] ?? $projCheck['body']['error'] ?? json_encode($projCheck['body']))
                : 'HTTP ' . $projCheck['http_code'];
            return ['ok' => false, 'error' => "Projekt nicht gefunden ({$projCheck['http_code']}): {$msg} — Projekt-ID '{$project}' prüfen."];
        }
        $projName  = $projCheck['body']['name']      ?? $project;
        $projShort = $projCheck['body']['shortName'] ?? '';

        return ['ok' => true, 'info' => "✅ YouTrack: Angemeldet als {$userName} · Projekt: {$projName} ({$projShort})"];
    }

    public function getIssuesForTicket(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT yi.*, u.full_name AS created_by_name, i.name AS integration_name
            FROM youtrack_issues yi
            JOIN users u ON u.id = yi.created_by
            JOIN youtrack_integrations i ON i.id = yi.integration_id
            WHERE yi.ticket_id = ?
            ORDER BY yi.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function headers(string $token): array {
        return [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
    }

    private function httpPost(string $url, array $data, array $headers): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
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

    private function httpGet(string $url, array $headers): array {
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

