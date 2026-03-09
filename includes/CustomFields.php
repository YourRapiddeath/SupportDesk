<?php

class CustomFields
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Gibt alle aktiven Felder zurück.
     * @param bool $publicOnly  true = nur Felder mit show_public=1
     */
    public function getActiveFields(bool $publicOnly = false): array
    {
        try {
            $where = 'is_active = 1';
            if ($publicOnly) $where .= ' AND show_public = 1';
            return $this->db->query(
                "SELECT * FROM ticket_custom_fields WHERE {$where} ORDER BY sort_order, id"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Gibt die gespeicherten Werte für ein Ticket zurück.
     * @return array  [field_id => value]
     */
    public function getValues(int $ticketId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ?"
            );
            $stmt->execute([$ticketId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Gibt Felder+Werte zusammen für ein Ticket zurück (für Anzeige).
     */
    public function getFieldsWithValues(int $ticketId): array
    {
        $fields = $this->getActiveFields();
        $values = $this->getValues($ticketId);
        foreach ($fields as &$f) {
            $f['value'] = $values[$f['id']] ?? null;
        }
        return $fields;
    }

    /**
     * Speichert Custom-Field-Werte aus $_POST nach dem Erstellen eines Tickets.
     * Gibt Array mit Fehlern zurück (leer = alles ok).
     */
    public function saveFromPost(int $ticketId, array $post, bool $isPublic = false): array
    {
        $fields = $this->getActiveFields($isPublic);
        $errors = [];

        $stmt = $this->db->prepare(
            "INSERT INTO ticket_field_values (ticket_id, field_id, value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()"
        );

        foreach ($fields as $field) {
            $key   = 'cf_' . $field['id'];
            $value = null;

            if ($field['field_type'] === 'checkbox') {
                $value = isset($post[$key]) ? '1' : '0';
            } else {
                $value = isset($post[$key]) ? trim((string)$post[$key]) : '';
            }

            // Pflichtfeld-Validierung
            if ($field['is_required'] && ($value === '' || $value === null)) {
                $errors[] = 'Das Feld "' . $field['field_label'] . '" ist ein Pflichtfeld.';
                continue;
            }

            $stmt->execute([$ticketId, $field['id'], $value === '' ? null : $value]);
        }

        return $errors;
    }

    /**
     * Validiert POST-Daten ohne zu speichern.
     * Gibt Array mit Fehlern zurück.
     */
    public function validatePost(array $post, bool $isPublic = false): array
    {
        $fields = $this->getActiveFields($isPublic);
        $errors = [];

        foreach ($fields as $field) {
            $key   = 'cf_' . $field['id'];
            $value = '';

            if ($field['field_type'] === 'checkbox') {
                $value = isset($post[$key]) ? '1' : '0';
            } else {
                $value = isset($post[$key]) ? trim((string)$post[$key]) : '';
            }

            if ($field['is_required'] && $value === '') {
                $errors[] = 'Das Feld "' . $field['field_label'] . '" ist ein Pflichtfeld.';
            }
        }

        return $errors;
    }

    /**
     * Rendert Formularfelder als HTML.
     * @param array  $fields   Felder (aus getActiveFields())
     * @param array  $values   Vorausgefüllte Werte [field_id => value]
     * @param array  $postData POST-Daten für Re-fill nach Fehler
     */
    public static function renderFields(array $fields, array $values = [], array $postData = []): string
    {
        if (empty($fields)) return '';

        $html = '';
        foreach ($fields as $field) {
            $id       = (int)$field['id'];
            $name     = 'cf_' . $id;
            $label    = htmlspecialchars($field['field_label']);
            $required = $field['is_required'] ? ' required' : '';
            $reqMark  = $field['is_required'] ? ' <span style="color:var(--danger,#ef4444);">*</span>' : '';
            $ph       = htmlspecialchars($field['placeholder'] ?? '');
            $help     = $field['help_text'] ? '<small style="color:var(--text-light,#6b7280);">' . htmlspecialchars($field['help_text']) . '</small>' : '';

            // Wert: POST hat Vorrang (bei Validation-Error), dann gespeicherter Wert
            $val = $postData[$name] ?? $values[$id] ?? '';
            $valEsc = htmlspecialchars((string)$val);

            $html .= '<div class="form-group">';
            $html .= "<label class=\"form-label\">{$label}{$reqMark}</label>";

            switch ($field['field_type']) {
                case 'textarea':
                    $html .= "<textarea name=\"{$name}\" class=\"form-control\" rows=\"4\" placeholder=\"{$ph}\"{$required}>{$valEsc}</textarea>";
                    break;

                case 'select':
                    $options = [];
                    if ($field['field_options']) {
                        $options = json_decode($field['field_options'], true) ?: [];
                    }
                    $html .= "<select name=\"{$name}\" class=\"form-control\"{$required}>";
                    $html .= '<option value="">-- Bitte wählen --</option>';
                    foreach ($options as $opt) {
                        $optEsc = htmlspecialchars($opt);
                        $sel    = ($val === $opt) ? ' selected' : '';
                        $html  .= "<option value=\"{$optEsc}\"{$sel}>{$optEsc}</option>";
                    }
                    $html .= '</select>';
                    break;

                case 'checkbox':
                    $checked = ($val === '1' || $val === 'on') ? ' checked' : '';
                    $html .= "<label style=\"display:flex;align-items:center;gap:.5rem;cursor:pointer;\">";
                    $html .= "<input type=\"checkbox\" name=\"{$name}\" value=\"1\"{$checked}{$required} style=\"width:16px;height:16px;\"> ";
                    $html .= "Ja</label>";
                    break;

                case 'number':
                    $html .= "<input type=\"number\" name=\"{$name}\" class=\"form-control\" placeholder=\"{$ph}\" value=\"{$valEsc}\"{$required}>";
                    break;

                case 'date':
                    $html .= "<input type=\"date\" name=\"{$name}\" class=\"form-control\" value=\"{$valEsc}\"{$required}>";
                    break;

                case 'email':
                    $html .= "<input type=\"email\" name=\"{$name}\" class=\"form-control\" placeholder=\"{$ph}\" value=\"{$valEsc}\"{$required}>";
                    break;

                case 'url':
                    $html .= "<input type=\"url\" name=\"{$name}\" class=\"form-control\" placeholder=\"{$ph}\" value=\"{$valEsc}\"{$required}>";
                    break;

                case 'phone':
                    $html .= "<input type=\"tel\" name=\"{$name}\" class=\"form-control\" placeholder=\"{$ph}\" value=\"{$valEsc}\"{$required}>";
                    break;

                default: // text
                    $html .= "<input type=\"text\" name=\"{$name}\" class=\"form-control\" placeholder=\"{$ph}\" value=\"{$valEsc}\"{$required}>";
                    break;
            }

            $html .= $help;
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Rendert die gespeicherten Werte als Read-Only HTML für die Ticket-Ansicht.
     */
    public static function renderValues(array $fieldsWithValues): string
    {
        $nonEmpty = array_filter($fieldsWithValues, function($f) { return $f['value'] !== null && $f['value'] !== ''; });
        if (empty($nonEmpty)) return '';

        $html  = '<div class="custom-fields-display">';
        $html .= '<h4 style="font-size:.9rem;font-weight:600;color:var(--text-light);margin:0 0 .75rem;text-transform:uppercase;letter-spacing:.05em;">Zusätzliche Informationen</h4>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.6rem;">';

        foreach ($nonEmpty as $field) {
            $label = htmlspecialchars($field['field_label']);
            $value = htmlspecialchars((string)$field['value']);

            // Checkbox-Wert lesbarer machen
            if ($field['field_type'] === 'checkbox') {
                $value = $field['value'] === '1' ? '✅ Ja' : '❌ Nein';
            }
            // URL als Link
            if ($field['field_type'] === 'url' && filter_var($field['value'], FILTER_VALIDATE_URL)) {
                $value = '<a href="' . htmlspecialchars($field['value']) . '" target="_blank" rel="noopener">' . $value . '</a>';
            }
            // E-Mail als Link
            if ($field['field_type'] === 'email' && filter_var($field['value'], FILTER_VALIDATE_EMAIL)) {
                $value = '<a href="mailto:' . htmlspecialchars($field['value']) . '">' . $value . '</a>';
            }

            $html .= '<div style="background:var(--bg-secondary,#f9fafb);border-radius:8px;padding:.55rem .75rem;">';
            $html .= "<div style=\"font-size:.72rem;font-weight:600;color:var(--text-light,#9ca3af);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.2rem;\">{$label}</div>";
            $html .= "<div style=\"font-size:.9rem;color:var(--text);word-break:break-word;\">{$value}</div>";
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }
}

