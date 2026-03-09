<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/User.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

$validPages = ['imprint', 'privacy', 'terms', 'contact'];
$page = $_GET['page'] ?? 'imprint';
if (!in_array($page, $validPages)) $page = 'imprint';

$key = 'footer_' . $page;

// Settings laden
$rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'footer_%'")->fetchAll(PDO::FETCH_ASSOC);
$s = [];
foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];

$titles = [
    'imprint' => $s['footer_imprint_label'] ?? 'Impressum',
    'privacy' => $s['footer_privacy_label'] ?? 'Datenschutz',
    'terms'   => $s['footer_terms_label']   ?? 'AGB',
    'contact' => $s['footer_contact_label'] ?? 'Kontakt',
];

$content = $s[$key . '_content'] ?? '';
$show    = ($s[$key . '_show'] ?? '1') === '1';
$title   = $titles[$page];

if (!$show || !$content) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> – <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container" style="max-width:860px; margin:2rem auto; padding:0 1rem;">

    <div style="margin-bottom:1.25rem; font-size:0.85rem; color:var(--text-light); display:flex; align-items:center; gap:0.4rem;">
        <a href="<?= SITE_URL ?>/" style="color:var(--primary); text-decoration:none;">🏠 Startseite</a>
        <span>›</span>
        <span><?= htmlspecialchars($title) ?></span>
    </div>

    <div class="card">
        <div class="card-header" style="font-size:1.15rem; font-weight:700;">
            <?= htmlspecialchars($title) ?>
        </div>
        <div class="card-body legal-content" style="line-height:1.75;">
            <?= $content /* Bereits als HTML gespeichert – Admin trägt HTML oder Text ein */ ?>
        </div>
    </div>

    <div style="margin-top:1.5rem; display:flex; flex-wrap:wrap; gap:0.5rem;">
        <?php
        $legalNav = [
            'imprint' => ['label' => $s['footer_imprint_label'] ?? 'Impressum', 'icon' => '🏢'],
            'privacy' => ['label' => $s['footer_privacy_label'] ?? 'Datenschutz', 'icon' => '🔒'],
            'terms'   => ['label' => $s['footer_terms_label']   ?? 'AGB', 'icon' => '📄'],
            'contact' => ['label' => $s['footer_contact_label'] ?? 'Kontakt', 'icon' => '✉️'],
        ];
        foreach ($legalNav as $pg => $nav):
            $navContent = $s['footer_' . $pg . '_content'] ?? '';
            $navShow    = ($s['footer_' . $pg . '_show'] ?? '1') === '1';
            if (!$navShow || !$navContent) continue;
            $isActive = $pg === $page;
        ?>
            <a href="<?= SITE_URL ?>/legal.php?page=<?= $pg ?>"
               class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>"
               style="font-size:0.85rem; padding:0.4rem 0.85rem;">
                <?= $nav['icon'] ?> <?= htmlspecialchars($nav['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>

</div>

<style>
.legal-content h1, .legal-content h2, .legal-content h3 { margin-top: 1.5rem; margin-bottom: 0.5rem; }
.legal-content p { margin: 0.75rem 0; }
.legal-content ul, .legal-content ol { margin: 0.75rem 0; padding-left: 1.5rem; }
.legal-content a { color: var(--primary); }
.legal-content a:hover { text-decoration: underline; }
.legal-content hr { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
</style>

<?php include 'includes/footer.php'; ?>
</body>
</html>

