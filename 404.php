<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
try {
    require_once __DIR__ . '/includes/Database.php';
} catch (Exception $e) { /* DB nicht verfügbar – egal für 404 */ }

$_404ActiveLangs = function_exists('getSupportedLanguages') ? getSupportedLanguages() : ['DE-de','EN-en'];
$currentLang = $_SESSION['lang'] ?? 'DE-de';
if (!in_array($currentLang, $_404ActiveLangs, true)) {
    $currentLang = 'DE-de';
}

if (!function_exists('buildLangUrl')) {
    function buildLangUrl($lang) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['lang'] = $lang;
        return $path . '?' . http_build_query($query);
    }
}

if (!function_exists('get404ActiveLangs')) {
    $_404BuiltinFlags  = ['DE-de'=>'🇩🇪','EN-en'=>'🇬🇧','FR-fr'=>'🇫🇷','ES-es'=>'🇪🇸','CH-ch'=>'🇨🇭','NDS-nds'=>'🌊'];
    $_404BuiltinLabels = ['DE-de'=>'Deutsch','EN-en'=>'English','FR-fr'=>'Français','ES-es'=>'Español','CH-ch'=>'Schwiizerdüütsch','NDS-nds'=>'Plattdüütsch'];

    function get404ActiveLangs(): array {
        global $_404BuiltinFlags, $_404BuiltinLabels;
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $rows = $pdo->query(
                "SELECT lang_code, label, flag FROM language_settings WHERE is_active=1 ORDER BY sort_order, lang_code"
            )->fetchAll(PDO::FETCH_ASSOC);
            return array_map(function($r) use ($_404BuiltinFlags, $_404BuiltinLabels) {
                $c = $r['lang_code'];
                return [
                    'lang_code' => $c,
                    'label' => (!empty($r['label']) && $r['label'] !== $c) ? $r['label'] : ($_404BuiltinLabels[$c] ?? $c),
                    'flag'  => (!empty($r['flag'])  && $r['flag']  !== '🌐') ? $r['flag']  : ($_404BuiltinFlags[$c]  ?? '🌐'),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [['lang_code'=>'DE-de','label'=>'Deutsch','flag'=>'🇩🇪']];
        }
    }
    function get404LangFlag(string $code): string {
        global $_404BuiltinFlags;
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $r = $pdo->prepare("SELECT flag FROM language_settings WHERE lang_code=?");
            $r->execute([$code]);
            $f = $r->fetchColumn();
            if ($f && $f !== '🌐') return $f;
        } catch (\Throwable $e) {}
        return $_404BuiltinFlags[$code] ?? '🌐';
    }
}
$langFlag = get404LangFlag($currentLang);

http_response_code(404);

$jokes = [
    'Die Seite hat wohl ein Support-Ticket erstellt – und wartet noch auf Antwort.',
    'Fehler 404: Seite auf Urlaub. Kommt vielleicht zurück. Vielleicht auch nicht.',
    'Wir haben unser bestes Support-Team losgeschickt. Die suchen noch.',
    'Diese Seite wurde in ein höheres Support-Level eskaliert und nie zurückgegeben.',
    'Tipp: Hast du die URL schon 3x falsch eingegeben? Dann wird dein Browser gesperrt. 😅',
    'Die Seite existiert so wenig wie unsere Antwortzeit-Versprechen an Montagen.',
    'Ticket T-404NOPE – Status: Existiert nicht · Priorität: Egal',
];
$joke = $jokes[array_rand($jokes)];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Nicht gefunden · <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
        .public-navbar { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; z-index: 9000 !important; width: 100% !important; margin-bottom: 0 !important; }
        html { scroll-padding-top: 64px; }
        body { padding-top: 64px; }
        body.navbar-ready { padding-top: var(--navbar-h) !important; }
        html.navbar-ready { scroll-padding-top: var(--navbar-h) !important; }

        .notfound-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 75vh;
            padding: 2rem 1rem;
        }
        .notfound-card {
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        .notfound-img {
            width: 400px;
            height: 280px;
            object-fit: cover;
            margin: 0 auto 1.5rem;
            display: block;
        }
        .notfound-code {
            font-size: 5rem;
            font-weight: 900;
            line-height: 1;
            color: var(--primary);
            letter-spacing: -4px;
            margin-bottom: .25rem;
            text-shadow: 0 4px 20px rgba(0,0,0,.15);
        }
        .notfound-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: .75rem;
            color: var(--text);
        }
        .notfound-joke {
            font-size: .95rem;
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 2rem;
            font-style: italic;
            background: var(--surface);
            border-left: 3px solid var(--primary);
            border-radius: 0 8px 8px 0;
            padding: .75rem 1rem;
            text-align: left;
        }
        .notfound-actions {
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .notfound-ticket {
            display: inline-block;
            font-size: .75rem;
            font-family: monospace;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: .3rem .65rem;
            color: var(--text-light);
            margin-bottom: 1.25rem;
        }
    </style>
    <?php injectGtaBgStyle(); ?>
    <?php injectRotlichtBgStyle(); ?>
    <?php injectDayzBgStyle(); ?>
    <?php injectBlackGoldBgStyle(); ?>
    <?php injectWinXpBgStyle(); ?>
    <?php injectYoutubeBgStyle(); ?>
</head>
<body>
    <nav class="public-navbar">
        <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="public-navbar-brand">
            <?= SITE_NAME ?>
        </a>
        <div class="public-navbar-actions">
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-secondary">Anmelden</a>
            <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary">Registrieren</a>

            <!-- Sprachauswahl -->
            <div class="dropdown">
                <a href="#" class="dropdown-toggle lang-toggle" title="Sprache ändern" style="display:flex;align-items:center;gap:4px;">
                    <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($currentLang, 24) : '<span class="lang-flag-circular">' . htmlspecialchars($langFlag) . '</span>' ?>
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:4px;">
                        <path d="M6 9L1 4h10z"/>
                    </svg>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php foreach (get404ActiveLangs() as $_al): ?>
                    <a href="<?= escape(buildLangUrl($_al['lang_code'])) ?>"
                       style="display:flex;align-items:center;gap:0.6rem;<?= $currentLang === $_al['lang_code'] ? 'font-weight:700;color:var(--primary);' : '' ?>">
                        <?= function_exists('getLangFlagHtml') ? getLangFlagHtml($_al['lang_code'], 20) : htmlspecialchars($_al['flag']) ?>
                        <?= htmlspecialchars($_al['label']) ?>
                        <?php if ($currentLang === $_al['lang_code']): ?>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-left:auto;color:var(--primary);"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </nav>
    <script>(function(){function s(){var n=document.querySelector('.public-navbar');if(!n)return;var h=n.getBoundingClientRect().height;if(h<=0)return;document.documentElement.style.setProperty('--navbar-h',h+'px');document.documentElement.classList.add('navbar-ready');document.body.classList.add('navbar-ready');}s();document.addEventListener('DOMContentLoaded',s);window.addEventListener('load',s);window.addEventListener('resize',s);})();</script>

    <div class="public-container">
        <div class="notfound-wrap">
            <div class="notfound-card">

                <img src="<?= SITE_URL ?>/assets/images/404.webp"
                     alt="404"
                     class="notfound-img"
                     onerror="this.style.display='none'">

                <div class="notfound-code">404</div>
                <div class="notfound-title">Seite nicht gefunden</div>

                <div class="notfound-ticket">
                    🎫 T-404ERROR &nbsp;·&nbsp; Status: Vermisst &nbsp;·&nbsp; Priorität: Irgendwann™
                </div>

                <div class="notfound-joke">
                    💬 <?= $joke ?>
                </div>

                <div class="notfound-actions">
                    <a href="<?= SITE_URL ?>/tickets/public_ticket.php" class="btn btn-primary">🏠 Zurück zur Startseite</a>
                </div>

            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdowns = document.querySelectorAll('.dropdown');
        dropdowns.forEach(function (dropdown) {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (!toggle) return;
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                dropdowns.forEach(function (other) {
                    if (other !== dropdown) other.classList.remove('active');
                });
                dropdown.classList.toggle('active');
            });
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.dropdown')) {
                dropdowns.forEach(function (d) { d.classList.remove('active'); });
            }
        });
    });
    </script>
<?php
try { include __DIR__ . '/includes/footer.php'; } catch (\Throwable $e) {}
?>
</body>
</html>

