# 🎫 SupportSystems

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/Lizenz-MIT-green?style=for-the-badge)
![Themes](https://img.shields.io/badge/Themes-27-blueviolet?style=for-the-badge)
![Languages](https://img.shields.io/badge/Sprachen-3-orange?style=for-the-badge)

**Ein modernes, voll ausgestattetes PHP-Support-Ticket-System**  
mit Multi-Level-Support, Discord-Integration, GitHub/GitLab-Integration,  
internem Chat, Wissensdatenbank, 27 Design-Themes und vielem mehr.

</div>
---

## 📋 Inhaltsverzeichnis

- [Bilder](#-bilder)
- [Features im Überblick](#-features-im-überblick)
- [Systemvoraussetzungen](#-systemvoraussetzungen)
- [Installation](#-installation)
- [Konfiguration](#-konfiguration)
- [Benutzerrollen & Berechtigungen](#-benutzerrollen--berechtigungen)
- [Ticket-System](#-ticket-system)
- [Support-Dashboard](#-support-dashboard)
- [Kommunikation](#-kommunikation)
- [Interner Chat](#-interner-chat)
- [Privates Postfach](#-privates-postfach)
- [Wissensdatenbank](#-wissensdatenbank)
- [Benutzerprofil & 2FA](#-benutzerprofil--2fa)
- [Design-Themes](#-design-themes)
- [Mehrsprachigkeit](#-mehrsprachigkeit)
- [Discord-Integration](#-discord-integration)
- [GitHub & GitLab Integration](#-github--gitlab-integration)
- [YouTrack-Integration](#-youtrack-integration)
- [E-Mail-Vorlagen](#-e-mail-vorlagen)
- [Admin-Bereich](#-admin-bereich)
- [Sicherheit](#-sicherheit)
- [Verzeichnisstruktur](#-verzeichnisstruktur)
- [Fehlerbehebung](#-fehlerbehebung)
- [Technische Details](#-technische-details)

---

## Bilder

<p float="left">
  <a href="https://www.techpotenziale.com/test/assets/images/dashboard.png">
    <img src="https://www.techpotenziale.com/test/assets/images/dashboard.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/ticket.png">
    <img src="https://www.techpotenziale.com/test/assets/images/ticket.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/settings.png">
    <img src="https://www.techpotenziale.com/test/assets/images/settings.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/rp.png">
    <img src="https://www.techpotenziale.com/test/assets/images/rp.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/lang.png">
    <img src="https://www.techpotenziale.com/test/assets/images/lang.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/gihub.png">
    <img src="https://www.techpotenziale.com/test/assets/images/gihub.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/kbdata.png">
    <img src="https://www.techpotenziale.com/test/assets/images/kbdata.png" width="300" />
  </a>
  <a href="https://www.techpotenziale.com/test/assets/images/discord.png">
    <img src="https://www.techpotenziale.com/test/assets/images/discord.png" width="300" />
  </a>
</p>

## 🚀 Features im Überblick

### 🎫 Ticket-System
- ✅ Ticket-Erstellung mit Kategorie-Auswahl (kein Prioritäts-Missbrauch durch Kunden)
- ✅ Eindeutige Ticket-Codes (z.B. `TKT-2026-A3F5D1`)
- ✅ 3-stufiges Support-Level-System (First / Second / Third Level)
- ✅ Automatische Zuweisung beim ersten Antworten
- ✅ Öffentliche & interne Nachrichten im selben Feed
- ✅ Interne Nachrichten optisch abgesetzt (gelb gestrichelt)
- ✅ Interne Notizen (separater Tab in der Action-Box)
- ✅ Ticket-History (vollständiges Änderungsprotokoll mit Icons)
- ✅ Status-, Prioritäts- & Level-Änderung per Dropdown (ohne Button, sofortige Wirkung)
- ✅ Öffentliches Ticket-Lookup (Ticket-ID + E-Mail-Bestätigung)
- ✅ Antworten im öffentlichen Ticket-Lookup möglich
- ✅ Ungelesene Nachrichten-Zähler (interne Nachrichten für Kunden ausgeblendet)
- ✅ Profilbilder und Bio der Gesprächspartner im Ticket-Header
- ✅ Benutzerdefinierte Ticket-Felder (vom Admin konfigurierbar, optional als Pflichtfeld)
- ✅ Nachrichten-Feed sortierbar (Neueste zuerst / Älteste zuerst)
- ✅ Automatisch einblendendes Antwort-Textfeld

### 💬 Kommunikation & Benachrichtigungen
- ✅ Interner Supporter-Chat (schwebende Bubble, Ungelesen-Zähler in Rot)
- ✅ Privates Postfach zwischen Supportern
- ✅ Papierkorb für private Nachrichten (Wiederherstellen / Endgültig löschen)
- ✅ System-Benachrichtigungen als Thread-PMs (ein PM-Thread pro Ticket)
- ✅ Ton-Benachrichtigung im Chat (ein/ausschaltbar pro Supporter)
- ✅ Ticket-Link im Chat teilen (formatierte Karte mit optionalem Kommentar)
- ✅ Emoji-Picker im Chat
- ✅ E-Mail-Benachrichtigungen (Ticket erstellt, Antwort, Statusänderung, Zuweisung)
- ✅ Discord Webhook-Integration (bei neuen Tickets, Antworten, Statusänderungen)

### 👤 Benutzerverwaltung
- ✅ Registrierung & Login
- ✅ Zwei-Faktor-Authentifizierung (TOTP / Google Authenticator)
- ✅ Backup-Codes bei 2FA-Aktivierung
- ✅ Brute-Force-Schutz (3 Fehlversuche → 5 Minuten Sperre)
- ✅ Avatar-Upload (individuell pro Nutzer)
- ✅ Bio & Profil-Einstellungen
- ✅ Antwort-Vorlagen mit Platzhaltern für Supporter
- ✅ Eigene Seite für Vorlagen-Verwaltung (`/users/templates.php`)

### 🛠 Administration
- ✅ 27 Design-Themes (inkl. Hintergrundbild + Blur + Dunkelheit für ausgewählte Themes)
- ✅ Ticket-Kategorien verwalten (mit Farben, Beschreibungen, Badges)
- ✅ Globale Antwort-Vorlagen für alle Supporter
- ✅ E-Mail-Vorlagen (HTML + Plaintext, editierbar mit Platzhaltern)
- ✅ Website-Einstellungen direkt im Admin-Bereich (inkl. Config-Schreiben)
- ✅ Discord-Integration vollständig konfigurierbar
- ✅ GitHub & GitLab Integration (Issues aus Tickets heraus erstellen)
- ✅ YouTrack-Integration
- ✅ Chat-Einstellungen (aktivieren/deaktivieren, Max-Länge, Emojis, GIFs)
- ✅ Wissensdatenbank verwalten
- ✅ Spracheinstellungen (Sprachen aktivieren/deaktivieren, Import/Export)
- ✅ Custom CSS (eigenes CSS über den Admin-Bereich eintragen & aktivieren)
- ✅ Benutzerdefinierte Ticket-Felder (Pflichtfelder, optionale Felder)
- ✅ Supporter-Verwaltung (Rollen, Levels, Berechtigungen)

### 🎨 Design & UX
- ✅ 27 vollständige CSS-Themes (inkl. animierter Themes)
- ✅ Responsive Design (Desktop & Mobile)
- ✅ Fixierte Navbar (Inhalt läuft nicht dahinter)
- ✅ Sprachauswahl als Flaggen-Dropdown (runde Flaggen-Icons)
- ✅ Witzige, thematisch passende 404-Seite
- ✅ Einrichtungsassistent (`install.php`) für die Erstinstallation

---

## 🖥 Systemvoraussetzungen

| Komponente | Mindestversion | Empfohlen |
|---|---|---|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| Webserver | Apache 2.4 / Nginx 1.18 | aktuelle Version |
| PHP-Erweiterungen | PDO, PDO_MySQL, session, mbstring, openssl | — |

> **Empfohlen:** PHP 8.1+ auf einem LAMP/LEMP-Stack.

---

## 📦 Installation

### Option 1: Einrichtungsassistent (empfohlen)

1. **Dateien hochladen**  
   Alle Dateien in das Webserver-Verzeichnis hochladen (z.B. `/var/www/html/support/`)

2. **Assistenten öffnen**
   ```
   http://ihre-domain.de/support/install.php
   ```

3. **Schritte durchlaufen**
   - ✔ Systemvoraussetzungen werden automatisch geprüft
   - ✔ Datenbankverbindung eingeben & testen
   - ✔ Datenbank + alle Tabellen werden automatisch angelegt
   - ✔ Administrator-Konto erstellen
   - ✔ Grundeinstellungen (Site-URL, Seitenname) speichern
   - ✔ `config.php` wird automatisch geschrieben

4. **Sicherheit nach der Installation**
   ```bash
   # install.php nach erfolgreicher Installation löschen!
   rm install.php
   ```

---

### Option 2: Manuelle Installation

1. **Dateien hochladen**

2. **Datenbank erstellen**
   ```sql
   CREATE DATABASE support_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Schema importieren**
   ```bash
   mysql -u root -p support_system < database.sql
   ```

4. **`config.php` anlegen** (aus `config.example.php` kopieren):
   ```php
   <?php
   define('DB_HOST',    'localhost');
   define('DB_NAME',    'support_system');
   define('DB_USER',    'ihr_benutzer');
   define('DB_PASS',    'ihr_passwort');
   define('SITE_URL',   'https://ihre-domain.de/support');
   define('SITE_NAME',  'Mein Support-System');
   ```

5. **Upload-Verzeichnis beschreibbar machen**
   ```bash
   chmod 755 /
   chmod 666 config.php
   chmod 755 uploads/
   chmod 755 uploads/avatars/
   chmod 755 uploads/backgrounds/
   ```

6. **Erster Login**
   - Der Benutzer wird während der Installation über `install.php` angelegt
   - Bei manueller Installation: Ersten Benutzer über `register.php` erstellen und in der DB auf `role = 'admin'` setzen

---

## ⚙️ Konfiguration

### `config.php` – Alle Parameter

```php
<?php
// ── Datenbank ──────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');         // Datenbank-Host
define('DB_NAME',    'support_system');   // Datenbankname
define('DB_USER',    'user');             // Datenbankbenutzer
define('DB_PASS',    'password');         // Datenbankpasswort

// ── Website ────────────────────────────────────────────────────────────────
define('SITE_URL',   'https://ihre-domain.de');  // Basis-URL (kein / am Ende)
define('SITE_NAME',  'SupportSystem');            // Anzeigename
```

> **Hinweis:** Datenbankverbindung kann nur vom **Hauptadmin (ID=1)** im Admin-Bereich geändert werden.

---

## 👥 Benutzerrollen & Berechtigungen

| Berechtigung | Kunde | First Level | Second Level | Third Level | Admin |
|---|:---:|:---:|:---:|:---:|:---:|
| Ticket erstellen | ✅ | ✅ | ✅ | ✅ | ✅ |
| Eigene Tickets einsehen | ✅ | ✅ | ✅ | ✅ | ✅ |
| Öffentliches Ticket-Lookup | ✅ | ✅ | ✅ | ✅ | ✅ |
| Tickets beantworten | ✅ | ✅ | ✅ | ✅ | ✅ |
| Interne Nachrichten lesen | ❌ | ✅ | ✅ | ✅ | ✅ |
| Interne Notizen | ❌ | ✅ | ✅ | ✅ | ✅ |
| Ticket-Zuweisung | ❌ | ✅ | ✅ | ✅ | ✅ |
| Status / Priorität ändern | ❌ | ✅ | ✅ | ✅ | ✅ |
| Ticket weiterleiten (Level) | ❌ | ✅ | ✅ | ✅ | ✅ |
| Alle Tickets sehen | ❌ | Level 1 | Level 1+2 | Alle | Alle |
| Nicht zugewiesene Tickets | ❌ | ✅ | ✅ | ✅ | ✅ |
| Interner Chat | ❌ | ✅ | ✅ | ✅ | ✅ |
| Privates Postfach | ❌ | ✅ | ✅ | ✅ | ✅ |
| Antwort-Vorlagen | ❌ | ✅ | ✅ | ✅ | ✅ |
| Wissensdatenbank lesen | ❌ | ✅ | ✅ | ✅ | ✅ |
| Wissensdatenbank bearbeiten | ❌ | (optional) | (optional) | (optional) | ✅ |
| Admin-Bereich | ❌ | ❌ | ❌ | ❌ | ✅ |

### Support-Level-Hierarchie

```
First Level  ──→  Second Level  ──→  Third Level
     ↑_________________↑__________________↑
          Rückleitung jederzeit möglich
```

---

## 🎫 Ticket-System

### Ticket erstellen (Kunde)

1. Auf der Startseite: **„Ticket erstellen"** (links) oder **„Bestehendes Ticket aufrufen"** (rechts)
2. Felder ausfüllen:
   - **Betreff** (Pflichtfeld)
   - **Kategorie** (Dropdown, vom Admin konfiguriert)
   - **Beschreibung** (Pflichtfeld)
   - Benutzerdefinierte Felder (falls vom Admin konfiguriert)
3. System generiert automatisch einen eindeutigen Ticket-Code
4. E-Mail-Benachrichtigung wird versendet (falls SMTP konfiguriert)

> Kunden können **keine Priorität** selbst setzen – das verhindert Missbrauch.

### Ticket-Status

| Status | Bedeutung |
|--------|-----------|
| 🟢 Offen | Neu, noch nicht bearbeitet |
| 🔵 In Bearbeitung | Aktiv bearbeitet |
| 🟡 Ausstehend | Wartet auf Kundenantwort |
| ✅ Gelöst | Problem behoben |
| ⛔ Geschlossen | Endgültig abgeschlossen |

### Ticket-Prioritäten (nur für Supporter & Admins)

| Priorität | Farbe |
|-----------|-------|
| Niedrig | Grau |
| Mittel | Blau |
| Hoch | Orange |
| Dringend | Rot |

### Automatische Zuweisung

Wenn ein Supporter als **erster** auf ein noch nicht zugewiesenes Ticket antwortet, wird ihm das Ticket **automatisch zugewiesen**.

### Öffentliches Ticket-Lookup

- URL: `/tickets/public_ticket.php`
- Eingabe: Ticket-ID + hinterlegte E-Mail-Adresse
- Kunden können dort auch **direkt antworten**

---

## 🖥 Support-Dashboard

Das Dashboard zeigt Supportern in zwei Bereichen:

**Nicht zugewiesene Tickets** – Alle offenen Tickets ohne Zuweisung  
**Mir zugewiesene Tickets** – Alle dem eingeloggten Supporter zugewiesenen Tickets

### Action-Box im Ticket (drei Tabs)

| Tab | Inhalt |
|---|---|
| Optionen | Status, Priorität, Support-Level per Dropdown |
| Interne Nachrichten | Interne Nachrichten + Notizen |
| Ticket-History | Vollständiges Änderungsprotokoll |

### Textfeld-Verhalten

- Standardmäßig **eingeklappt** (nur „Neue Nachricht:" sichtbar)
- Öffnet sich **automatisch** beim Erreichen des Nachrichtenendes
- Öffnet sich auch per Klick
- Erstreckt sich über die **gesamte Breite**

---

## 💬 Kommunikation

### Nachrichtentypen im Ticket

| Typ | Sichtbar für | Darstellung |
|-----|-------------|-------------|
| Öffentliche Nachricht | Alle (Kunde + Supporter) | Normal |
| Interne Nachricht | Nur Supporter | Gelb gestrichelter Rahmen |
| Interne Notiz | Nur Supporter (nur im Tab) | Separater Bereich |

### Antwort-Vorlagen – Platzhalter

| Platzhalter | Beschreibung |
|---|---|
| `{{name}}` | Name des Kunden |
| `{{email}}` | E-Mail des Kunden |
| `{{ticket_id}}` | Ticket-Code |
| `{{ticket_subject}}` | Betreff des Tickets |
| `{{supporter_name}}` | Name des Supporters |
| `{{date}}` | Aktuelles Datum |

---

## 💬 Interner Chat

Der Chat für das Support-Team ist unten rechts als **schwebende Bubble** verfügbar.

### Features

- **Ungelesen-Zähler** an der Chat-Bubble (rot)
- **Ton-Benachrichtigung** (ein/ausschaltbar pro Supporter)
- **Emoji-Picker** (wenn vom Admin aktiviert)
- **Ticket-Link teilen** per Button im Ticket → formatierte Karte mit optionalem Kommentar
- **Globale Nachrichten** vom Admin möglich

### Admin-Einstellungen für den Chat

| Einstellung | Beschreibung |
|---|---|
| Chat aktivieren/deaktivieren | Schaltet den Chat komplett aus |
| Maximale Nachrichtenlänge | Standard: 2000 Zeichen |
| Emojis erlauben | Emoji-Picker ein/ausschalten |
| GIFs erlauben | GIPHY-Integration ein/ausschalten |

---

## 📬 Privates Postfach

Erreichbar unter `/users/messages.php`

- Nachrichten zwischen Supportern schreiben
- Posteingang, Postausgang, Papierkorb
- **System-Benachrichtigungen** über Ticket-Ereignisse (ein Thread pro Ticket)
- Direktlink zum betreffenden Ticket in der Nachricht

---

## 📚 Wissensdatenbank

Erreichbar unter: `/support/knowledge-base.php`  
Admin-Verwaltung: `/admin/admin-knowledge-base.php`

- Artikel nach Kategorien gegliedert
- Ausgewählte Supporter können Artikel bearbeiten (optional)
- Artikel als Entwurf oder veröffentlicht
- Volltextsuche

### Vorinstallierte Artikel

| Kategorie | Artikel |
|---|---|
| Support System | Erste Schritte im SupportSystem |
| Support System | Ticket erstellen |
| Support System | Zwei-Faktor-Authentifizierung (2FA) |
| Support System | Systembesonderheiten |
| Support System | Kategorien und Beiträge verwalten |

---

## 🔐 Benutzerprofil & 2FA

### Zwei-Faktor-Authentifizierung

1. **Aktivieren:** `/users/setup-2fa.php` → QR-Code scannen → Backup-Codes notieren
2. **Beim Login:** Nach Passwort-Eingabe wird TOTP-Code abgefragt
3. **Deaktivieren:** `/users/disable-2fa.php` → neuer Schlüssel wird automatisch generiert
4. **Backup-Codes:** 8 Einmal-Codes bei verlorenem Authenticator

### Avatar-Upload

- Formate: JPG, PNG, WEBP, GIF · max. 2 MB
- Gespeichert in: `uploads/avatars/`

---

## 🎨 Design-Themes

Im Admin-Bereich → Tab „Theme" wählbar.

| Theme | Besonderheit |
|---|---|
| Modern Blue | Standard-Theme |
| iOS | Apple iOS Stil |
| Dark Purple | Dunkles Lila |
| Green Minimal | Aufgeräumtes Grün |
| Bordeaux Red | Elegantes Bordeaux |
| Bordeaux Black Metallic | Metallic + Bordeaux |
| Blue Dark Metallic | Metallic Dunkelblau |
| Minecraft Dark | Dunkles Pixel-Theme |
| Minecraft | Helles Pixel-Theme |
| Windows 95 | Retro-Klassiker |
| Windows XP | Mit Hintergrundbild-Upload |
| Windows Vista Aero | Glasoptik |
| TikTok | Animiert |
| WhatsApp | Grünes Chat-Theme |
| ARK: Survival Ascended | Gaming-Theme |
| DayZ | Survival + Hintergrundbild-Upload |
| Unicorn Magic | Pastellfarben |
| Animal Crossing | Freundliches Grün |
| Mario Kart | Animiert |
| Neon Cyber | Animiert, Neonfarben |
| Black & Gold | Animiert + Hintergrundbild-Upload |
| Black & Silver | Animiert |
| ROBLOX | Gaming-Theme |
| GTA Roleplay | Animiert + Hintergrundbild-Upload |
| Rotlicht | Animiert + Hintergrundbild-Upload |
| YouTube | Mit Hintergrundbild-Upload |

### Hintergrundbild-Einstellungen

Themes mit Bild-Upload unterstützen:
- Upload (JPG, PNG, WEBP, GIF, max. 8 MB)
- Blur (0–20 px), Dunkelheit (5–100%), Größe (100–300%), Position (X/Y)
- Live-Vorschau beim Einstellen

---

## 🌍 Mehrsprachigkeit

| Code | Sprache | Flagge |
|---|---|---|
| `DE-de` | Deutsch | 🇩🇪 |
| `EN-en` | English (Fallback) | 🇬🇧 |
| `FR-fr` | Français | 🇫🇷 |
| `ES-es` | Español | 🇪🇸 |
| `CH-ch` | Schweizerdeutsch | 🇨🇭 |
| `NDS-nds` | Plattdüütsch | 🌊 |

Sprachdateien liegen unter `assets/lang/` als PHP-Arrays.  
Im Admin-Bereich: Sprachen aktivieren, deaktivieren, exportieren (PHP/JSON) und importieren.  
Englisch (`EN-en`) ist die **Fallback-Sprache** und kann nicht deaktiviert werden.

---

## 🎮 Discord-Integration

1. Admin-Bereich → Tab „Discord"
2. Discord-Webhook-URL eintragen (Server-Einstellungen → Integrationen → Webhooks)
3. Bot-Name, Avatar, Erwähnung konfigurieren
4. Trigger wählen: Neues Ticket / Antwort / Statusänderung / Geschlossen
5. Embed-Aussehen anpassen (Farbe, Titel, Footer)
6. Test-Nachricht senden

---

## 🔗 GitHub & GitLab Integration

1. Admin-Bereich → „Git Integration"
2. Repository hinzufügen (Name, API-URL, Besitzer, Repo-Name, Access Token, Plattform)
3. Verbindung testen
4. Im Ticket: **„GitHub/GitLab Issue erstellen"** → wird automatisch vorausgefüllt

---

## 📊 YouTrack-Integration

1. Admin-Bereich → „YouTrack Integration"
2. YouTrack-URL + Permanent Token + Standard-Projekt
3. Verbindung testen
4. Im Ticket: **„YouTrack Issue erstellen"**

---

## 📧 E-Mail-Vorlagen

Alle Vorlagen im Admin-Bereich bearbeitbar (HTML + Plaintext).

| Vorlage | Auslöser |
|---|---|
| `ticket_created` | Neues Ticket erstellt (→ Kunde) |
| `ticket_updated` | Neue Antwort vom Supporter (→ Kunde) |
| `ticket_assigned` | Ticket zugewiesen (→ Supporter) |
| `ticket_new_message_supporter` | Neue Kunden-Nachricht (→ Supporter) |

**Platzhalter:** `{{site_name}}`, `{{ticket_code}}`, `{{ticket_url}}`, `{{subject}}`, `{{description}}`, `{{status}}`, `{{priority}}`, `{{customer_name}}`, `{{customer_email}}`, `{{supporter_name}}`, `{{reply_message}}`, `{{year}}`

---

## ⚙️ Admin-Bereich

Erreichbar unter: `/admin/settings.php`

| Tab | Inhalt |
|---|---|
| 🎨 Theme | Theme-Auswahl, Hintergrundbild-Einstellungen |
| 🌐 Website | Site-Name, URL, Zeitzone, DB-Verbindung |
| 📧 E-Mail | SMTP-Einstellungen |
| E-Mail-Vorlagen | HTML + Plaintext Vorlagen bearbeiten |
| Globale Vorlagen | Vorlagen für alle Supporter |
| 💬 Team-Chat | Chat-Einstellungen + Broadcast |
| Discord | Webhook + Embed-Konfiguration |
| Git | GitHub/GitLab Repositories |
| YouTrack | YouTrack-Verbindung |
| Custom CSS | Eigenes CSS einpflegen |
| 🌍 Sprachen | Sprachen verwalten |
| 🗂️ Ticket-Felder | Benutzerdefinierte Felder |

---

## 🔒 Sicherheit

| Maßnahme | Beschreibung |
|---|---|
| Brute-Force-Schutz | 3 Fehlversuche → 5 Min. Sperre |
| TOTP (2FA) | Google Authenticator kompatibel |
| Backup-Codes | 8 Einmal-Codes |
| PDO Prepared Statements | Schutz vor SQL-Injection |
| `escape()`-Funktion | XSS-Schutz bei der Ausgabe |
| Passwort-Hashing | `password_hash()` mit bcrypt |
| Rollenbasierter Zugriff | `requireLogin()` und `requireRole()` |
| DB-Zugangsdaten | Nur Hauptadmin (ID=1) kann sie ändern |

### Empfehlungen nach der Installation

1. `install.php` löschen
2. Standard-Admin-Passwort sofort ändern
3. HTTPS einrichten (Let's Encrypt)
4. 2FA für Admin-Accounts aktivieren

---

## 📁 Verzeichnisstruktur

```
SupportSystems/
├── admin/                      # Admin-Bereich
│   ├── settings.php            # Zentrale Einstellungen (alle Tabs)
│   ├── users.php               # Benutzerverwaltung
│   ├── supporters.php          # Supporter-Verwaltung
│   ├── categories.php          # Kategorienverwaltung
│   ├── admin-knowledge-base.php# Wissensdatenbank-Verwaltung
│   ├── global-templates.php    # Globale Antwort-Vorlagen
│   ├── git-integration.php     # GitHub/GitLab-Konfiguration
│   ├── youtrack-integration.php# YouTrack-Konfiguration
│   └── discord-handler.php     # Discord AJAX-Handler
├── assets/
│   ├── css/                    # 27 Theme-CSS-Dateien (theme-*.css)
│   ├── images/                 # Bilder (404, Hacker, etc.)
│   └── lang/                   # Sprachdateien (DE-de, EN-en, ...)
├── includes/                   # PHP-Klassen & Hilfsfunktionen
│   ├── Database.php, User.php, Ticket.php
│   ├── Email.php, Discord.php
│   ├── GitIntegration.php, YouTrackIntegration.php
│   ├── KnowledgeBase.php, CategoryHelper.php, TOTP.php
│   ├── functions.php, navbar.php, chat.php, pm.php
│   └── ...
├── support/                    # Supporter-Bereich
├── tickets/                    # Kunden-Ticket-Bereich
├── users/                      # Benutzerprofil-Bereich
├── uploads/                    # Hochgeladene Dateien
│   ├── avatars/
│   └── backgrounds/
├── config.php                  # Konfigurationsdatei
├── config.example.php          # Beispiel-Konfiguration
├── database.sql                # Vollständiges Datenbankschema
├── index.php                   # Startseite
├── install.php                 # Einrichtungsassistent
├── login.php, logout.php, register.php
└── 404.php                     # 404-Seite
```

---

## 🐛 Fehlerbehebung

| Problem | Lösung |
|---|---|
| „Headers already sent" | Keine Ausgabe vor `session_start()`, keine BOM in Dateien |
| DB-Verbindungsfehler | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in `config.php` prüfen |
| Upload funktioniert nicht | `chmod 755 uploads/` und Unterverzeichnisse |
| E-Mails nicht versendet | SMTP-Einstellungen prüfen, Port 587 (TLS) / 465 (SSL) |
| 2FA-Codes abgelehnt | Server-Uhrzeit synchronisieren: `ntpdate -u pool.ntp.org` |
| Themes nicht geladen | `assets/css/` lesbar? Theme-CSS-Datei vorhanden? |

---

## 🔧 Technische Details

- **Backend:** PHP 8.1+ (prozedural + OOP gemischt)
- **Datenbank:** MySQL / MariaDB via PDO
- **Frontend:** Vanilla HTML/CSS/JavaScript (kein Framework)
- **Authentifizierung:** Session-basiert mit TOTP-2FA
- **E-Mail:** Direkter SMTP-Versand (kein PHPMailer, kein Composer)

### Wichtigste Datenbanktabellen

| Tabelle | Beschreibung |
|---|---|
| `users` | Benutzerkonten |
| `tickets` | Ticket-Stammdaten |
| `ticket_messages` | Nachrichten + interne Kommentare |
| `ticket_history` | Änderungsprotokoll |
| `categories` | Ticket-Kategorien |
| `settings` | Systemeinstellungen (Key-Value) |
| `email_templates` | Bearbeitbare E-Mail-Vorlagen |
| `language_settings` | Sprach-Konfiguration |
| `knowledge_base` | Wissensdatenbank-Artikel |
| `private_messages` | Privates Postfach |
| `chat_messages` | Interner Chat |
| `ticket_custom_fields` | Benutzerdefinierte Ticket-Felder |
| `git_repositories` | GitHub/GitLab-Konfiguration |

---

## 📄 Lizenz

Dieses Projekt steht unter der **MIT-Lizenz**.  
Siehe [`LICENSE`](LICENSE) für Details.

---

## 🤝 Beitragen

Beiträge, Bug-Reports und Feature-Requests sind willkommen!  
Bitte lies zunächst die [`CONTRIBUTING.md`](CONTRIBUTING.md).

---

<div align="center">
Made with ❤️ · PHP · MySQL · Vanilla JS
</div>
