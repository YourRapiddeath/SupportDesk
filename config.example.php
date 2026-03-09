<?php
/**
 * Support System - Konfigurationsvorlage
 *
 * ACHTUNG: Dies ist eine Beispieldatei!
 * Kopieren Sie diese Datei zu 'config.php' und passen Sie die Werte an.
 * Alternativ können Sie den Einrichtungsassistenten unter install.php verwenden.
 */

require 'assets/lang/translator.php';
$translator = new Translator('DE-de');

// ============================================================================
// DATENBANK-KONFIGURATION
// ============================================================================

// Hostname des Datenbankservers (meist 'localhost')
define('DB_HOST', 'localhost');

// Name der Datenbank
define('DB_NAME', 'support_system');

// Datenbank-Benutzername
define('DB_USER', 'ihr_datenbankbenutzer');

// Datenbank-Passwort
define('DB_PASS', 'ihr_datenbankpasswort');

// ============================================================================
// WEBSITE-KONFIGURATION
// ============================================================================

// Vollständige URL Ihrer Website (OHNE abschließenden Slash)
// Beispiele:
//   - http://localhost:8000
//   - https://support.ihre-domain.de
//   - https://www.ihre-domain.de/support
define('SITE_URL', 'http://localhost:8000');

// Name Ihrer Support-Website
define('SITE_NAME', 'Support System');

// ============================================================================
// SESSION-KONFIGURATION
// ============================================================================

// Session-Cookie nur über HTTP zugänglich (verhindert JavaScript-Zugriff)
ini_set('session.cookie_httponly', 1);

// Verwende nur Cookies für Sessions (keine URL-Parameter)
ini_set('session.use_only_cookies', 1);

// Cookie nur über HTTPS senden (setzen Sie auf 1 bei HTTPS-Nutzung)
ini_set('session.cookie_secure', 0); // 0 = HTTP erlaubt, 1 = nur HTTPS

// Session-Name (optional anpassen)
// session_name('SUPPORT_SESSION');

// Session-Lebensdauer (optional, Standard ist bis Browser-Schließung)
// ini_set('session.gc_maxlifetime', 3600); // 1 Stunde

// Starte Session
session_start();

// ============================================================================
// ZEITZONE
// ============================================================================

// Setze Standard-Zeitzone für Datumsangaben
// Liste aller Zeitzonen: https://www.php.net/manual/de/timezones.php
date_default_timezone_set('Europe/Berlin');

// Weitere Beispiele:
// date_default_timezone_set('Europe/Vienna');    // Österreich
// date_default_timezone_set('Europe/Zurich');    // Schweiz
// date_default_timezone_set('America/New_York'); // New York
// date_default_timezone_set('UTC');              // UTC

// ============================================================================
// FEHLERBEHANDLUNG
// ============================================================================

// ENTWICKLUNGSUMGEBUNG: Alle Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PRODUKTIONSUMGEBUNG: Fehler nicht anzeigen (auskommentieren für Entwicklung)
// error_reporting(0);
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/error.log');

// ============================================================================
// ZUSÄTZLICHE EINSTELLUNGEN (Optional)
// ============================================================================

// PHP Memory Limit erhöhen (falls benötigt)
// ini_set('memory_limit', '256M');

// Upload-Größe anpassen (falls benötigt)
// ini_set('upload_max_filesize', '10M');
// ini_set('post_max_size', '10M');

// Maximale Ausführungszeit (falls benötigt)
// ini_set('max_execution_time', 60);

// ============================================================================
// KONSTANTEN FÜR ERWEITERTE FUNKTIONEN (Optional)
// ============================================================================

// Upload-Verzeichnis für Ticket-Anhänge
// define('UPLOAD_DIR', __DIR__ . '/uploads');

// Maximale Upload-Größe in Bytes (z.B. 10MB)
// define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Erlaubte Dateiendungen für Uploads
// define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt']);

// Debug-Modus (nur für Entwicklung)
// define('DEBUG_MODE', true);

// Maintenance-Modus (aktivieren für Wartungsarbeiten)
// define('MAINTENANCE_MODE', false);

// ============================================================================
// ENDE DER KONFIGURATION
// ============================================================================

