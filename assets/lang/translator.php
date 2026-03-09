<?php

class Translator {

    private $translations = [];

    public function __construct($lang = 'EN-en') {
        $file = __DIR__ . "/$lang.php";

        if (file_exists($file) && is_readable($file)) {
            $this->translations = include $file;
            if (!is_array($this->translations)) {
                $this->translations = [];
            }
        } elseif (file_exists($file) && !is_readable($file)) {
            // Versuche Berechtigungen zu korrigieren
            @chmod($file, 0644);
            if (is_readable($file)) {
                $this->translations = include $file;
                if (!is_array($this->translations)) {
                    $this->translations = [];
                }
            }
            // Fallback auf EN-en wenn Datei nicht lesbar
            if (empty($this->translations) && $lang !== 'EN-en') {
                $fallback = __DIR__ . '/EN-en.php';
                if (file_exists($fallback) && is_readable($fallback)) {
                    $this->translations = include $fallback;
                    if (!is_array($this->translations)) {
                        $this->translations = [];
                    }
                }
            }
        }
    }

    public function translate($key, $params = []) {
        if (!isset($this->translations[$key])) {
            return $key; // Fallback: Key anzeigen
        }

        $text = $this->translations[$key];

        // Platzhalter ersetzen
        foreach ($params as $placeholder => $value) {
            $text = str_replace(":$placeholder", $value, $text);
        }
        return $text;
    }
}