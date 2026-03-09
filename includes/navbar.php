﻿﻿﻿﻿﻿﻿<?php global $translator;
$isSupporter = isset($_SESSION['role']) && in_array($_SESSION['role'], ['first_level','second_level','third_level','admin']);
?>
<style>
    .navbar,
    .public-navbar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 9000 !important;
        width: 100% !important;
        margin-bottom: 0 !important;
    }

    /* 2. Body-Abstand – Fallback 64 px, JS schärft exakten Wert nach */
    html {
        scroll-padding-top: 64px;
    }
    body {
        padding-top: 64px;
    }

    /* 3. Sobald JS den echten Wert kennt, CSS-Variable aktiv schalten */
    body.navbar-ready {
        padding-top: var(--navbar-h) !important;
    }
    html.navbar-ready {
        scroll-padding-top: var(--navbar-h) !important;
    }

    /* 4. Sticky Sidebar (Action-Box) darf nicht hinter die Navbar rutschen.
          calc(var(--navbar-h, 64px) + 1rem) ersetzt das hard-codierte top:1rem
          aus allen Theme-CSS-Dateien in einem einzigen Override.             */
    .internal-feed {
        top: calc(var(--navbar-h, 64px) + 1rem) !important;
        max-height: calc(100vh - var(--navbar-h, 64px) - 2rem) !important;
    }
</style>
<script>
    (function () {
        function setOffset() {
            var nav = document.querySelector('.navbar') || document.querySelector('.public-navbar');
            if (!nav) return;
            var h = nav.getBoundingClientRect().height;
            if (h <= 0) return;
            document.documentElement.style.setProperty('--navbar-h', h + 'px');
            document.documentElement.classList.add('navbar-ready');
            document.body.classList.add('navbar-ready');
        }
        /* Sofort nach dem Nav-Tag ausführen */
        setOffset();
        /* Nochmals nach vollem Render (Fonts, Bilder) */
        document.addEventListener('DOMContentLoaded', setOffset);
        window.addEventListener('load', setOffset);
        window.addEventListener('resize', setOffset);
    })();
</script>

<?php if ($isSupporter): ?>
    <script>
        // ── Postfach-Badge in Navbar aktualisieren ───────────────────────────────────
        (function pmNavBadgeInit() {
            function refreshPmBadge() {
                fetch('<?= SITE_URL ?>/includes/pm.php?action=unread_count')
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        var badge = document.getElementById('pm-navbar-badge');
                        if (!badge) return;
                        if (d.count > 0) {
                            badge.textContent = d.count;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }).catch(function() {});
            }
            document.addEventListener('DOMContentLoaded', refreshPmBadge);
            setInterval(refreshPmBadge, 20000);
        })();
    </script>
<?php endif; ?>
<?php injectGtaBgStyle(); ?>
<?php injectRotlichtBgStyle(); ?>
<?php injectDayzBgStyle(); ?>
<?php injectBlackGoldBgStyle(); ?>
<?php injectWinXpBgStyle(); ?>
<?php injectYoutubeBgStyle(); ?>
<?php
$currentLang = $_SESSION['lang'] ?? 'DE-de';
$_navActiveLangs = function_exists('getSupportedLanguages') ? getSupportedLanguages() : ['DE-de','EN-en'];
if (!in_array($currentLang, $_navActiveLangs, true)) {
    $currentLang = 'DE-de';
}

// Lade alle aktiven Sprachen mit Label+Flag aus DB für das Dropdown
function getActiveLangsForNav(): array {
    static $navLangs = null;
    if ($navLangs !== null) return $navLangs;

    $builtinFlags  = ['DE-de'=>'🇩🇪','EN-en'=>'🇬🇧','FR-fr'=>'🇫🇷','CH-ch'=>'🇨🇭','NDS-nds'=>'🌊'];
    $builtinLabels = ['DE-de'=>'Deutsch','EN-en'=>'English','FR-fr'=>'Français','CH-ch'=>'Schwiizerdüütsch','NDS-nds'=>'Plattdüütsch'];

    try {
        $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        $rows = $pdo->query(
                "SELECT lang_code, label, flag FROM language_settings WHERE is_active=1 ORDER BY sort_order, lang_code"
        )->fetchAll(PDO::FETCH_ASSOC);
        // Flags/Labels normalisieren
        $navLangs = array_map(function($r) use ($builtinFlags, $builtinLabels) {
            $code = $r['lang_code'];
            return [
                    'lang_code' => $code,
                    'label'     => (!empty($r['label']) && $r['label'] !== $code)
                            ? $r['label']
                            : ($builtinLabels[$code] ?? $code),
                    'flag'      => (!empty($r['flag']) && $r['flag'] !== '🌐')
                            ? $r['flag']
                            : ($builtinFlags[$code] ?? '🌐'),
            ];
        }, $rows);
    } catch (\Throwable $e) {
        $dir = __DIR__ . '/../assets/lang/';
        $navLangs = [];
        foreach (glob($dir . '*.php') ?: [] as $f) {
            $code = basename($f, '.php');
            if ($code === 'translator') continue;
            $navLangs[] = [
                    'lang_code' => $code,
                    'label'     => $builtinLabels[$code] ?? $code,
                    'flag'      => $builtinFlags[$code]  ?? '🌐',
            ];
        }
    }
    return $navLangs ?? [['lang_code'=>'DE-de','label'=>'Deutsch','flag'=>'🇩🇪']];
}

// Flag der aktuellen Sprache bestimmen
function getCurrentLangFlag(string $langCode): string {
    static $flagCache = [];
    if (isset($flagCache[$langCode])) return $flagCache[$langCode];

    $builtinFlags = ['DE-de'=>'🇩🇪','EN-en'=>'🇬🇧','FR-fr'=>'🇫🇷','CH-ch'=>'🇨🇭','NDS-nds'=>'🌊'];

    try {
        $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        $r = $pdo->prepare("SELECT flag FROM language_settings WHERE lang_code=?");
        $r->execute([$langCode]);
        $flag = $r->fetchColumn();
        if ($flag && $flag !== '🌐') {
            $flagCache[$langCode] = $flag;
            return $flag;
        }
    } catch (\Throwable $e) {}
    // Fallback-Mapping
    $flagCache[$langCode] = $builtinFlags[$langCode] ?? '🌐';
    return $flagCache[$langCode];
}

function buildLangUrl($lang) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query['lang'] = $lang;
    $newQuery = http_build_query($query);
    return $path . ($newQuery ? '?' . $newQuery : '');
}
?>
<nav class="navbar">
    <div class="navbar-content">
        <a href="<?= SITE_URL ?>/index.php" class="navbar-brand"><?= SITE_NAME ?></a>

        <div class="navbar-menu">
            <a href="<?= SITE_URL ?>/index.php"><?=$translator->translate('dashboard')?></a>

            <!-- Tickets Dropdown -->
            <div class="dropdown">
                <a href="#" class="dropdown-toggle">
                    Tickets
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left: 4px;">
                        <path d="M6 9L1 4h10z"/>
                    </svg>
                </a>
                <div class="dropdown-menu">
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['first_level', 'second_level', 'third_level', 'admin'])): ?>
                        <a href="<?= SITE_URL ?>/support/tickets.php"><?=$translator->translate('tickets_all')?></a>
                        <a href="<?= SITE_URL ?>/support/tickets.php?filter=my">Meine zugewiesenen Tickets</a>
                        <a href="<?= SITE_URL ?>/support/tickets.php?filter=unassigned">Nicht zugewiesen</a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/tickets/my-tickets.php">Meine Tickets</a>
                        <a href="<?= SITE_URL ?>/tickets/create.php">Neues Ticket erstellen</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isSupporter): ?>
                <!-- Wissensdatenbank -->
                <a href="<?= SITE_URL ?>/support/knowledge-base.php">WIKI</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Administration Dropdown -->
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle">
                        Administration
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left: 4px;">
                            <path d="M6 9L1 4h10z"/>
                        </svg>
                    </a>
                    <div class="dropdown-menu">
                        <div class="dropdown-section-title">Verwaltung</div>
                        <a href="<?= SITE_URL ?>/admin/supporters.php">Support-Team</a>
                        <a href="<?= SITE_URL ?>/admin/users.php">Alle Benutzer</a>
                        <a href="<?= SITE_URL ?>/admin/categories.php">Ticket-Kategorien</a>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-section-title">Wissensdatenbank</div>
                        <a href="<?= SITE_URL ?>/admin/admin-knowledge-base.php">KB-Verwaltung</a>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-section-title">System</div>
                        <a href="<?= SITE_URL ?>/admin/settings.php">Einstellungen</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="navbar-user">
            <?php if (isset($_SESSION['full_name'])): ?>
                <?php if ($isSupporter): ?>
                    <!-- Postfach-Button mit Badge -->
                    <a href="<?= SITE_URL ?>/users/messages.php" class="pm-navbar-btn" title="Privates Postfach" style="position:relative;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:var(--surface);border:1px solid var(--border);color:var(--text);text-decoration:none;transition:background .15s;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span id="pm-navbar-badge" style="display:none;position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;font-size:0.65rem;font-weight:700;min-width:17px;height:17px;border-radius:50%;align-items:center;justify-content:center;padding:0 3px;line-height:1;">0</span>
                    </a>
                <?php endif; ?>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle user-menu-toggle">
                        <span><?= escape($_SESSION['full_name']) ?></span>
                        <span class="user-badge"><?= translateRole($_SESSION['role']) ?></span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left: 4px;">
                            <path d="M6 9L1 4h10z"/>
                        </svg>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <div style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; color: var(--text-light);">
                            Angemeldet als<br>
                            <strong style="color: var(--text);"><?= escape($_SESSION['username']) ?></strong>
                        </div>
                        <a href="<?= SITE_URL ?>/users/profile.php">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Mein Profil
                        </a>
                        <a href="<?= SITE_URL ?>/logout.php">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Abmelden
                        </a>
                    </div>
                </div>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle lang-toggle" title="Sprache ändern" style="display:flex;align-items:center;gap:4px;">
                        <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($currentLang, 24) : '<span class="lang-flag-circular">' . htmlspecialchars(getCurrentLangFlag($currentLang)) . '</span>' ?>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left: 2px;">
                            <path d="M6 9L1 4h10z"/>
                        </svg>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <?php foreach (getActiveLangsForNav() as $_nl): ?>
                            <a href="<?= escape(buildLangUrl($_nl['lang_code'])) ?>"
                               title="<?= htmlspecialchars($_nl['label']) ?>"
                               style="display:flex;align-items:center;gap:0.6rem;<?= $currentLang === $_nl['lang_code'] ? 'font-weight:700;color:var(--primary);' : '' ?>">
                                <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($_nl['lang_code'], 20) : htmlspecialchars($_nl['flag']) ?>
                                <?= htmlspecialchars($_nl['label']) ?>
                                <?php if ($currentLang === $_nl['lang_code']): ?>
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:auto;color:var(--primary);"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    // Dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');

            toggle.addEventListener('click', function(e) {
                e.preventDefault();

                // Close other dropdowns
                dropdowns.forEach(other => {
                    if (other !== dropdown) {
                        other.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                dropdown.classList.toggle('active');
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
    });
</script>

<?php if ($isSupporter): ?>
    <style>
        #chat-widget {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            font-family: inherit;
        }
        #chat-toggle-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            position: relative;
            transition: transform .15s, background .15s;
        }
        #chat-toggle-btn:hover { transform: scale(1.08); }
        #chat-toggle-btn svg { width: 24px; height: 24px; }
        #chat-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: #fff;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            font-size: 0.65rem;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--background, #fff);
        }
        #chat-badge.visible { display: flex; }

        #chat-box {
            display: none;
            flex-direction: column;
            width: 340px;
            max-height: 480px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            overflow: hidden;
            position: absolute;
            bottom: 62px;
            right: 0;
        }
        #chat-box.open { display: flex; }

        #chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 1rem;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.9rem;
            gap: 0.5rem;
        }
        #chat-header-left { display: flex; align-items: center; gap: 0.5rem; }
        #chat-sound-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,.8);
            cursor: pointer;
            padding: 0.2rem;
            border-radius: 4px;
            font-size: 1rem;
            line-height: 1;
            transition: color .15s;
        }
        #chat-sound-btn:hover { color: #fff; }

        #chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-height: 0;
        }
        .chat-msg {
            display: flex;
            flex-direction: column;
            max-width: 85%;
        }
        .chat-msg.mine { align-self: flex-end; align-items: flex-end; }
        .chat-msg.other { align-self: flex-start; align-items: flex-start; }

        .chat-msg-meta {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.2rem;
            font-size: 0.7rem;
            color: var(--text-light);
        }
        .chat-msg-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
        }
        .chat-msg-avatar-ph {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-bubble {
            padding: 0.45rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            line-height: 1.45;
            word-break: break-word;
        }
        .chat-msg.mine  .chat-bubble { background: var(--primary); color: #fff; border-bottom-right-radius: 3px; }
        .chat-msg.other .chat-bubble { background: var(--background); color: var(--text); border: 1px solid var(--border); border-bottom-left-radius: 3px; }

        .chat-time { font-size: 0.65rem; color: var(--text-light); margin-top: 0.1rem; }

        .chat-system-bubble {
            align-self: center;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1.5px solid #f59e0b;
            border-radius: 10px;
            padding: 0.6rem 0.9rem;
            font-size: 0.82rem;
            line-height: 1.45;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            color: #78350f;
        }
        .chat-msg.system-msg { align-self: stretch; max-width: 100%; }

        #gif-panel {
            display: none;
            padding: 0.5rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }
        #gif-search-row { display: flex; gap: 0.4rem; margin-bottom: 0.4rem; }
        #gif-search { flex:1; padding:0.3rem 0.5rem; font-size:0.8rem; border:1px solid var(--border); border-radius:4px; background:var(--background); color:var(--text); }
        #gif-search-btn { padding:0.3rem 0.6rem; font-size:0.8rem; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer; }
        #gif-grid { display:flex; flex-wrap:wrap; gap:4px; max-height:130px; overflow-y:auto; }

        #emoji-panel {
            display: none;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }
        #emoji-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            scrollbar-width: none;
        }
        #emoji-tabs::-webkit-scrollbar { display: none; }
        .emoji-tab-btn {
            background: none;
            border: none;
            padding: 0.3rem 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity .15s;
            flex-shrink: 0;
        }
        .emoji-tab-btn.active { opacity: 1; border-bottom: 2px solid var(--primary); }
        .emoji-tab-btn:hover { opacity: 0.85; }
        #emoji-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1px;
            padding: 0.4rem;
            max-height: 140px;
            overflow-y: auto;
        }
        .emoji-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.15rem 0.2rem;
            border-radius: 4px;
            line-height: 1;
            transition: background .1s;
        }
        .emoji-btn:hover { background: var(--background); }

        #chat-footer {
            border-top: 1px solid var(--border);
            background: var(--surface);
        }
        #chat-input-row {
            display: flex;
            align-items: center;
        }
        #chat-counter {
            font-size: 0.65rem;
            color: var(--text-light);
            padding: 0 0.4rem 0.25rem;
            text-align: right;
        }
        #chat-gif-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0 0.5rem;
            font-size: 1rem;
            display: none;
            align-items: center;
            color: var(--text-light);
            transition: color .15s;
        }
        #chat-gif-btn:hover { color: var(--primary); }
        #chat-input {
            flex: 1;
            border: none;
            outline: none;
            padding: 0.6rem 0.75rem;
            font-size: 0.875rem;
            background: transparent;
            color: var(--text);
            resize: none;
            height: 42px;
            line-height: 1.4;
        }
        #chat-send-btn {
            background: var(--primary);
            border: none;
            color: #fff;
            padding: 0 1rem;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: opacity .15s;
        }
        #chat-send-btn:hover { opacity: .85; }

        #chat-empty {
            text-align: center;
            color: var(--text-light);
            font-size: 0.8rem;
            padding: 1.5rem 0;
        }
    </style>

    <div id="chat-widget">
        <div id="chat-box">
            <div id="chat-header">
                <div id="chat-header-left">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    Team-Chat
                </div>
                <div style="display:flex; gap:0.25rem; align-items:center;">
                    <button id="chat-sound-btn" title="Ton ein/aus" onclick="toggleSound()">🔔</button>
                    <button onclick="closeChat()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem;padding:0.2rem;">✕</button>
                </div>
            </div>
            <div id="chat-messages">
                <div id="chat-empty">Lade Nachrichten…</div>
            </div>
            <div id="chat-footer">
                <div id="gif-panel">
                    <div id="gif-search-row">
                        <input id="gif-search" type="text" placeholder="GIF suchen…" onkeydown="if(event.key==='Enter') searchGifs()">
                        <button id="gif-search-btn" onclick="searchGifs()">Suchen</button>
                    </div>
                    <div id="gif-grid"></div>
                </div>
                <div id="emoji-panel">
                    <div id="emoji-tabs"></div>
                    <div id="emoji-grid"></div>
                </div>
                <div id="chat-input-row">
                    <button id="chat-emoji-btn" onclick="toggleEmojiPanel()" title="Emoji" style="background:none;border:none;cursor:pointer;padding:0 0.4rem;font-size:1.1rem;color:var(--text-light);transition:color .15s;" onmouseenter="this.style.color='var(--primary)'" onmouseleave="this.style.color='var(--text-light)'">😊</button>
                    <button id="chat-gif-btn" onclick="toggleGifMode()" title="GIF senden">🎞️</button>
                    <textarea id="chat-input" placeholder="Nachricht…" rows="1"
                              onkeydown="chatKeydown(event)" oninput="updateCounter()"></textarea>
                    <button id="chat-send-btn" onclick="sendMessage()">➤</button>
                </div>
                <div id="chat-counter" style="font-size:0.65rem;color:var(--text-light);padding:0 0.75rem 0.3rem;text-align:right;">0/2000</div>
            </div>
        </div>

        <button id="chat-toggle-btn" onclick="toggleChat()" title="Team-Chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span id="chat-badge"></span>
        </button>
    </div>

    <script>
        (function() {
            const API       = '<?= SITE_URL ?>/includes/chat.php';
            const ME_ID     = <?= (int)$_SESSION['user_id'] ?>;
            const IS_ADMIN  = <?= $isSupporter && ($_SESSION['role'] === 'admin') ? 'true' : 'false' ?>;

            let isOpen      = false;
            let lastId      = 0;
            let unreadCount = 0;
            let soundOn     = true;
            let pollTimer   = null;
            let audioCtx    = null;
            let chatEnabled = true;
            let gifsAllowed = false;
            let maxLength   = 2000;
            let gifMode     = false;

            function getAudioCtx() {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                return audioCtx;
            }
            function playPing() {
                if (!soundOn) return;
                try {
                    const ctx = getAudioCtx();
                    const osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.15);
                    gain.gain.setValueAtTime(0.25, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.3);
                } catch(e) {}
            }

            function fmtTime(str) {
                const d = new Date(str.replace(' ','T'));
                return d.toLocaleTimeString('de-DE', {hour:'2-digit', minute:'2-digit'});
            }

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            function renderMsg(msg) {
                const mine   = parseInt(msg.user_id) === ME_ID;
                const system = parseInt(msg.is_system) === 1;
                const div    = document.createElement('div');

                if (system) {
                    div.className = 'chat-msg system-msg';
                    div.dataset.id = msg.id;
                    div.innerHTML = `
                <div class="chat-system-bubble">
                    <span style="font-weight:700;">📢 Admin-Nachricht</span><br>
                    ${escHtml(msg.message).replace(/\n/g,'<br>')}
                    <div class="chat-time">${fmtTime(msg.created_at)}</div>
                </div>`;
                    return div;
                }

                div.className = 'chat-msg ' + (mine ? 'mine' : 'other');
                div.dataset.id = msg.id;

                let avatarHtml = msg.avatar
                    ? `<img class="chat-msg-avatar" src="<?= SITE_URL ?>/${msg.avatar}" alt="">`
                    : `<div class="chat-msg-avatar-ph">${(msg.full_name||'?').charAt(0).toUpperCase()}</div>`;

                // GIF detection – vor escHtml verarbeiten damit die URL erhalten bleibt
                let rawMsg = msg.message;
                let bubbleContent;
                let ticketPreviewHtml = '';

                // ── Ticket-Karte erkennen (VOR escHtml, auf Roh-Text) ─────────────────
                const lines = rawMsg.split('\n');

                // Zeile 0: "📋 Ticket CODE – Betreff"
                const headerMatch = lines[0] && lines[0].match(/^📋 Ticket ([^\s]+)\s+[\u2013\u2014\-]+\s+(.+)$/u);
                // Zeile 1: "🔗 URL"  – URL bis Zeilenende, trim()
                const urlLine     = lines[1] && lines[1].trim().match(/^🔗\s+(https?:\/\/.+)$/u);
                // Zeile 2: "Status: X | Priorität: Y"
                let statusVal = '', prioVal = '';
                if (lines[2] && lines[2].startsWith('Status:')) {
                    const sep = lines[2].includes(' | ') ? ' | '
                        : lines[2].includes(' · ') ? ' · '
                            : lines[2].includes(' • ') ? ' • '
                                : lines[2].includes(' - ') ? ' - ' : null;
                    if (sep) {
                        const parts = lines[2].split(sep);
                        statusVal = (parts[0] || '').replace(/^Status:\s*/, '').trim();
                        prioVal   = (parts[1] || '').replace(/^Priorität:\s*/, '').trim();
                    }
                }
                // Zeile 3: "Geteilt von: Name"
                const sharedLine  = lines[3] && lines[3].match(/^Geteilt von:?\s+(.+)$/u);

                if (headerMatch && urlLine && statusVal && prioVal && sharedLine) {
                    const code     = headerMatch[1];
                    const subject  = headerMatch[2];
                    const url      = urlLine[1].trim();
                    const status   = statusVal;
                    const prio     = prioVal;
                    const sharedBy = sharedLine[1];
                    // Kommentar: alles ab Zeile 4 (falls vorhanden)
                    const commentRaw = lines.slice(4)
                        .filter(l => l.startsWith('Kommentar: '))
                        .map(l => l.replace(/^Kommentar: /, ''))
                        .join(' ').trim();

                    const cardBg  = mine ? 'rgba(0,0,0,0.18)'     : 'var(--background)';
                    const cardBd  = mine ? 'rgba(255,255,255,0.2)' : 'var(--border)';
                    const cardClr = mine ? '#fff'                  : 'var(--text)';
                    const dimClr  = mine ? 'rgba(255,255,255,0.55)': 'var(--text-light)';
                    const btnBg   = mine ? 'rgba(255,255,255,0.15)': 'var(--primary)';
                    const btnClr  = '#fff';
                    const btnBd   = mine ? '1px solid rgba(255,255,255,0.3)' : 'none';

                    const commentHtml = commentRaw
                        ? `<div style="
                    margin:0.45rem 0 0;
                    padding-top:0.4rem;
                    border-top:1px solid ${cardBd};
                    font-size:0.78rem;
                    font-style:italic;
                    opacity:.85;
                  ">"${escHtml(commentRaw)}"</div>`
                        : '';

                    ticketPreviewHtml = `
                <div style="
                    background:${cardBg};
                    border:1px solid ${cardBd};
                    border-top:none;
                    border-radius:0 0 10px 10px;
                    color:${cardClr};
                    overflow:hidden;
                ">
                    <div style="padding:0.55rem 0.75rem 0.5rem;">
                        <div style="font-weight:700; font-size:0.82rem; margin-bottom:0.1rem;">🎫 ${escHtml(code)}</div>
                        <div style="
                            font-size:0.82rem;
                            white-space:nowrap;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            max-width:260px;
                            opacity:.9;
                            margin-bottom:0.15rem;
                        ">${escHtml(subject)}</div>
                        <div style="font-size:0.72rem; color:${dimClr};">${escHtml(status)} · ${escHtml(prio)}</div>
                        ${commentHtml}
                        <div style="font-size:0.68rem; color:${dimClr}; margin-top:0.35rem;">
                            Geteilt von ${escHtml(sharedBy)}
                        </div>
                    </div>
                    <div style="border-top:1px solid ${cardBd};">
                        <a href="${escHtml(url)}" target="_blank" style="
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            gap:0.4rem;
                            width:100%;
                            padding:0.5rem 0;
                            background:${btnBg};
                            color:${btnClr};
                            border:${btnBd};
                            font-size:0.8rem;
                            font-weight:700;
                            text-decoration:none;
                            letter-spacing:.2px;
                            box-sizing:border-box;
                        ">↗ Ticket ansehen</a>
                    </div>
                </div>`;

                    // Bubble zeigt nur kurzen Hinweis – kein roher Link
                    bubbleContent = `📋 Ticket geteilt`;

                } else if (gifsAllowed && rawMsg.match(/\[GIF:https?:\/\//)) {
                    // GIF-Nachricht
                    bubbleContent = rawMsg.replace(/\[GIF:(https?:\/\/[^\]]+)\]/g, function(_, url) {
                        const safeUrl = url.replace(/"/g, '');
                        return `<img src="${safeUrl}" style="max-width:200px;border-radius:6px;display:block;margin-top:4px;" loading="lazy" alt="GIF">`;
                    });
                    bubbleContent = bubbleContent.replace(/(?<!<img[^>]*>)([^<]+)/g, function(t) {
                        return escHtml(t);
                    });
                } else {
                    // Normale Nachricht
                    bubbleContent = escHtml(rawMsg).replace(/\n/g, '<br>');
                }

                div.innerHTML = `
            <div class="chat-msg-meta">
                ${!mine ? avatarHtml : ''}
                <span>${mine ? 'Du' : escHtml(msg.full_name)}</span>
            </div>
            <div style="display:flex; flex-direction:column; align-items:${mine ? 'flex-end' : 'flex-start'}; max-width:85%;">
                <div class="chat-bubble" style="${ticketPreviewHtml ? 'border-radius:12px 12px 0 0; margin-bottom:0;' : ''}">${bubbleContent}</div>
                ${ticketPreviewHtml}
            </div>
            <span class="chat-time">${fmtTime(msg.created_at)}</span>`;
                return div;
            }

            async function poll() {
                try {
                    const r    = await fetch(`${API}?action=messages&since=${lastId}`);
                    const data = await r.json();
                    if (data.error === 'chat_disabled') { chatEnabled = false; showDisabled(); return; }
                    const msgs = data.messages || [];
                    applySettings(data.settings);
                    const container = document.getElementById('chat-messages');
                    const empty     = document.getElementById('chat-empty');
                    if (msgs.length > 0) {
                        if (empty) empty.remove();
                        const atBottom = container.scrollHeight - container.clientHeight - container.scrollTop < 60;
                        let hadNew = false;
                        msgs.forEach(m => {
                            if (container.querySelector(`[data-id="${m.id}"]`)) return;
                            container.appendChild(renderMsg(m));
                            lastId = Math.max(lastId, parseInt(m.id));
                            if (parseInt(m.user_id) !== ME_ID) hadNew = true;
                        });
                        if (atBottom || isOpen) container.scrollTop = container.scrollHeight;
                        if (hadNew && !isOpen) {
                            unreadCount += msgs.filter(m => parseInt(m.user_id) !== ME_ID).length;
                            updateBadge(); playPing();
                        } else if (hadNew && isOpen) {
                            fetch(`${API}?action=read`, {method:'POST'});
                        }
                    }
                } catch(e) {}
                pollTimer = setTimeout(poll, 3000);
            }

            function applySettings(s) {
                if (!s) return;
                chatEnabled = s.enabled;
                gifsAllowed = s.gifs;
                maxLength   = s.max_length || 2000;
                const input = document.getElementById('chat-input');
                if (input) input.maxLength = maxLength;
                // Show/hide GIF button
                const gifBtn = document.getElementById('chat-gif-btn');
                if (gifBtn) gifBtn.style.display = gifsAllowed ? 'flex' : 'none';
                // counter
                updateCounter();
            }

            function showDisabled() {
                const btn = document.getElementById('chat-toggle-btn');
                if (btn) btn.style.opacity = '0.4';
                const box = document.getElementById('chat-box');
                if (box) {
                    box.innerHTML = `<div id="chat-header" style="background:var(--primary);color:#fff;padding:0.7rem 1rem;font-weight:700;">💬 Team-Chat</div>
            <div style="padding:2rem;text-align:center;color:var(--text-light);">
                <div style="font-size:2rem;margin-bottom:0.5rem;">🔒</div>
                <div style="font-weight:600;">Chat deaktiviert</div>
                <div style="font-size:0.8rem;margin-top:0.25rem;">Der Administrator hat den Chat deaktiviert.</div>
            </div>`;
                }
            }

            function updateBadge() {
                const badge = document.getElementById('chat-badge');
                if (!badge) return;
                if (unreadCount > 0) { badge.textContent = unreadCount > 99 ? '99+' : unreadCount; badge.classList.add('visible'); }
                else badge.classList.remove('visible');
            }

            function updateCounter() {
                const input = document.getElementById('chat-input');
                const counter = document.getElementById('chat-counter');
                if (!input || !counter) return;
                const len = input.value.length;
                counter.textContent = `${len}/${maxLength}`;
                counter.style.color = len > maxLength * 0.9 ? '#ef4444' : 'var(--text-light)';
            }

            window.toggleChat = function() {
                const box = document.getElementById('chat-box');
                isOpen = !isOpen;
                box.classList.toggle('open', isOpen);
                if (isOpen) {
                    unreadCount = 0; updateBadge();
                    fetch(`${API}?action=read`, {method:'POST'});
                    document.getElementById('chat-input')?.focus();
                    const container = document.getElementById('chat-messages');
                    if (container) setTimeout(() => container.scrollTop = container.scrollHeight, 50);
                }
            };

            window.closeChat = function() {
                isOpen = false;
                document.getElementById('chat-box')?.classList.remove('open');
            };

            window.sendMessage = async function(overrideMsg) {
                const input = document.getElementById('chat-input');
                const msg   = overrideMsg || input?.value.trim();
                if (!msg) return;
                if (input && !overrideMsg) { input.value = ''; updateCounter(); }
                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('message', msg);
                await fetch(API, {method:'POST', body:fd});
            };

            window.chatKeydown = function(e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
                updateCounter();
            };

            window.toggleSound = async function() {
                soundOn = !soundOn;
                const btn = document.getElementById('chat-sound-btn');
                btn.textContent = soundOn ? '🔔' : '🔕';
                btn.title = soundOn ? 'Ton deaktivieren' : 'Ton aktivieren';
                const fd = new FormData(); fd.append('action','sound'); fd.append('enabled', soundOn ? 1 : 0);
                await fetch(API, {method:'POST', body:fd});
            };

            // ── Emoji Picker ──────────────────────────────────────────────────────────
            const EMOJI_CATS = {
                '😊': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖'],
                '👍': ['👋','🤚','🖐','✋','🖖','👌','🤌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','🦻','👃','🫀','🫁','🧠','🦷','🦴','👀','👁','👅','👄','💋','🩸'],
                '❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️','🕉','☸️','✡️','🔯','🕎','☯️','☦️','🛐','⛎','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','🆔','⚛️','🉑','☢️','☣️','📴','📳','🈶','🈚','🈸','🈺','🈷️','✴️','🆚','💮','🉐','㊙️','㊗️','🈴','🈵','🈹','🈲','🅰️','🅱️','🆎','🆑','🅾️','🆘','❌','⭕','🛑','⛔','📛','🚫'],
                '🎉': ['🎉','🎊','🎈','🎁','🎀','🎗','🎟','🎫','🎖','🏆','🥇','🥈','🥉','🏅','🎪','🤹','🎭','🩰','🎨','🎬','🎤','🎧','🎼','🎵','🎶','🎙','🎚','🎛','📻','🎷','🪗','🎸','🎹','🎺','🎻','🥁','🪘','🎮','🕹','🎲','🧩','🧸','🪆','♟','🃏','🀄','🎯','🎳','🏓','🏸','🥊','🥋','🎽','🛹','🛼','🛷','⛸','🥌','🎿','⛷','🏂','🪂','🏋️'],
                '🐶': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🦟','🦗','🕷','🦂','🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀','🐡','🐟','🐠','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🦣','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🪶','🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿','🦔'],
                '🍕': ['🍕','🍔','🌭','🥪','🌮','🌯','🫔','🥙','🧆','🥚','🍳','🥘','🍲','🫕','🥣','🥗','🍿','🧈','🧂','🥫','🍱','🍘','🍙','🍚','🍛','🍜','🍝','🍠','🍢','🍣','🍤','🍥','🥮','🍡','🥟','🥠','🥡','🦀','🦞','🦐','🦑','🦪','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🥧','🍫','🍬','🍭','🍮','🍯','🍼','🥛','☕','🫖','🍵','🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂','🥃','🫗','🥤','🧋','🧃','🧉','🧊','🥢','🍽','🍴','🥄'],
                '🚗': ['🚗','🚕','🚙','🚌','🚎','🏎','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🏍','🛵','🛺','🚲','🛴','🛹','🛼','🚏','🛣','🛤','⛽','🛞','🚨','🚥','🚦','🛑','🚧','⚓','🛟','⛵','🛶','🚤','🛳','⛴','🛥','🚢','✈️','🛩','🛫','🛬','🪂','💺','🚁','🚟','🚠','🚡','🛰','🚀','🛸','🎆','🎇','🗺','🧭','🏔','⛰','🌋','🗻','🏕','🏖','🏜','🏝','🏞','🏟','🏛','🏗','🧱','🪨','🪵','🛖','🏘','🏚','🏠','🏡','🏢','🏣','🏤','🏥','🏦','🏨','🏩','🏪','🏫','🏬','🏭','🏯','🏰','💒','🗼','🗽','⛪','🕌','🛕','🕍','⛩','🕋'],
                '💡': ['💡','🔦','🕯','🪔','💰','💴','💵','💶','💷','💸','💳','🪙','💹','📈','📉','📊','📋','📌','📍','📎','🖇','📏','📐','✂️','🗃','🗄','🗑','🔒','🔓','🔏','🔐','🔑','🗝','🔨','🪓','⛏','⚒','🛠','🗡','⚔️','🔫','🪃','🏹','🛡','🪚','🔧','🪛','🔩','⚙️','🗜','⚖️','🦯','🔗','⛓','🪝','🧲','🪜','⚗️','🔭','🔬','🩺','💊','🩹','🩼','🩻','🩸','🧬','🦠','🧫','🧪','🌡','🧹','🪣','🧺','🧻','🚽','🪠','🚿','🛁','🪤','🧴','🧷','🧹','🪣','🧸','🪞','🪟','🛋','🪑','🚪','🧳','⌛','⏳','⌚','⏰','⏱','⏲','🕰','📡','🔋','🔌','💻','🖥','🖨','⌨️','🖱','💾','💿','📀','📱','☎️','📞','📟','📠','📺','📷','📸','📹','🎥','📽','🎞','📞','🔉','🔊','📢','📣','🔔','🔕'],
            };
            let emojiOpen = false;
            let currentEmojiCat = Object.keys(EMOJI_CATS)[0];

            function buildEmojiPicker() {
                const tabs = document.getElementById('emoji-tabs');
                const grid = document.getElementById('emoji-grid');
                if (!tabs || !grid) return;
                tabs.innerHTML = '';
                Object.keys(EMOJI_CATS).forEach(cat => {
                    const btn = document.createElement('button');
                    btn.className = 'emoji-tab-btn' + (cat === currentEmojiCat ? ' active' : '');
                    btn.textContent = cat;
                    btn.title = cat;
                    btn.onclick = () => {
                        currentEmojiCat = cat;
                        document.querySelectorAll('.emoji-tab-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        renderEmojiGrid();
                    };
                    tabs.appendChild(btn);
                });
                renderEmojiGrid();
            }

            function renderEmojiGrid() {
                const grid = document.getElementById('emoji-grid');
                if (!grid) return;
                grid.innerHTML = '';
                (EMOJI_CATS[currentEmojiCat] || []).forEach(em => {
                    const btn = document.createElement('button');
                    btn.className = 'emoji-btn';
                    btn.textContent = em;
                    btn.title = em;
                    btn.onclick = () => insertEmoji(em);
                    grid.appendChild(btn);
                });
            }

            function insertEmoji(emoji) {
                const ta = document.getElementById('chat-input');
                if (!ta) return;
                const start = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.substring(0, start) + emoji + ta.value.substring(end);
                ta.selectionStart = ta.selectionEnd = start + emoji.length;
                ta.focus();
                updateCounter();
            }

            window.toggleEmojiPanel = function() {
                emojiOpen = !emojiOpen;
                const panel = document.getElementById('emoji-panel');
                if (!panel) return;
                panel.style.display = emojiOpen ? 'block' : 'none';
                if (emojiOpen) {
                    // GIF-Panel schließen
                    gifMode = false;
                    const gifPanel = document.getElementById('gif-panel');
                    if (gifPanel) gifPanel.style.display = 'none';
                    buildEmojiPicker();
                }
            };

            // ── GIF Search ────────────────────────────────────────────────────────────
            window.toggleGifMode = function() {
                gifMode = !gifMode;
                const panel = document.getElementById('gif-panel');
                if (panel) panel.style.display = gifMode ? 'block' : 'none';
                if (gifMode) {
                    // Emoji-Panel schließen
                    emojiOpen = false;
                    const emojiPanel = document.getElementById('emoji-panel');
                    if (emojiPanel) emojiPanel.style.display = 'none';
                    document.getElementById('gif-search')?.focus();
                }
            };

            window.searchGifs = async function() {
                const q = document.getElementById('gif-search')?.value.trim();
                if (!q) return;
                const grid = document.getElementById('gif-grid');
                if (!grid) return;
                grid.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">Suche…</div>';
                try {
                    // Tenor API v2 (Google) – kostenloser öffentlicher Key
                    const r = await fetch(`https://tenor.googleapis.com/v2/search?q=${encodeURIComponent(q)}&key=AIzaSyAyimkuYQYF_FXVALexPuGQctUWRURdCYQ&limit=12&media_filter=gif`);
                    const d = await r.json();
                    grid.innerHTML = '';
                    const results = d.results || [];
                    if (!results.length) {
                        grid.innerHTML = '<div style="text-align:center;padding:0.5rem;font-size:0.8rem;color:var(--text-light);">Keine GIFs gefunden</div>';
                        return;
                    }
                    results.forEach(item => {
                        const media = item.media_formats;
                        // Tenor liefert tinygif oder nanogif als Vorschau, gif als Vollbild
                        const previewUrl = media?.tinywebp?.url || media?.nanogif?.url || media?.gif?.url;
                        const sendUrl    = media?.mediumgif?.url || media?.gif?.url;
                        if (!previewUrl || !sendUrl) return;
                        const img = document.createElement('img');
                        img.src = previewUrl;
                        img.style.cssText = 'width:80px;height:60px;object-fit:cover;border-radius:4px;cursor:pointer;';
                        img.title = item.content_description || '';
                        img.onclick = () => {
                            sendMessage(`[GIF:${sendUrl}]`);
                            gifMode = false;
                            document.getElementById('gif-panel').style.display = 'none';
                            const searchInput = document.getElementById('gif-search');
                            if (searchInput) searchInput.value = '';
                            grid.innerHTML = '';
                        };
                        grid.appendChild(img);
                    });
                } catch(e) {
                    grid.innerHTML = '<div style="text-align:center;padding:0.5rem;font-size:0.8rem;color:#ef4444;">Fehler beim Laden. Bitte erneut versuchen.</div>';
                    console.error('GIF search error:', e);
                }
            };

            async function init() {
                const sr = await fetch(`${API}?action=get_sound`);
                const sd = await sr.json();
                soundOn = sd.sound !== 0;
                const btn = document.getElementById('chat-sound-btn');
                if (btn) { btn.textContent = soundOn ? '🔔' : '🔕'; btn.title = soundOn ? 'Ton deaktivieren' : 'Ton aktivieren'; }

                const r    = await fetch(`${API}?action=messages&since=0`);
                const data = await r.json();

                if (data.error === 'chat_disabled') { showDisabled(); return; }

                applySettings(data.settings);
                const msgs      = data.messages || [];
                const container = document.getElementById('chat-messages');
                const empty     = document.getElementById('chat-empty');

                if (msgs.length > 0 && empty) empty.remove();
                else if (!msgs.length && empty) empty.textContent = 'Noch keine Nachrichten.';

                msgs.forEach(m => { container.appendChild(renderMsg(m)); lastId = Math.max(lastId, parseInt(m.id)); });
                container.scrollTop = container.scrollHeight;
                unreadCount = data.unread || 0;
                updateBadge();
                pollTimer = setTimeout(poll, 3000);
            }

            document.addEventListener('DOMContentLoaded', init);
        })();
    </script>
<?php endif; ?>
<?php if (function_exists('injectCustomCss')) injectCustomCss(); ?>