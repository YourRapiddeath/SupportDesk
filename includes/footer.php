<?php
if (!isset($db)) {
    try {
        $db = Database::getInstance()->getConnection();
    } catch (\Throwable $e) { $db = null; }
}

$footerSettings = [];
if ($db) {
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'footer_%'")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $r) $footerSettings[$r['setting_key']] = $r['setting_value'];
        // Defaults einmalig anlegen falls noch nicht vorhanden
        if (empty($footerSettings)) {
            $defaults = [
                'footer_enabled'         => '1',
                'footer_copyright'       => 'Made with ❤️ from YourRapiddeath',
                'footer_bg_color'        => '',
                'footer_text_color'      => '',
                'footer_custom_links'    => '[]',
                'footer_imprint_show'    => '1',
                'footer_imprint_label'   => 'Impressum',
                'footer_imprint_content' => '',
                'footer_privacy_show'    => '1',
                'footer_privacy_label'   => 'Datenschutz',
                'footer_privacy_content' => '',
                'footer_terms_show'      => '1',
                'footer_terms_label'     => 'AGB',
                'footer_terms_content'   => '',
                'footer_contact_show'    => '1',
                'footer_contact_label'   => 'Kontakt',
                'footer_contact_content' => '',
            ];
            $ins = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($defaults as $k => $v) {
                $ins->execute([$k, $v]);
                $footerSettings[$k] = $v;
            }
        }
    } catch (\Throwable $e) {}
}

$footerEnabled    = ($footerSettings['footer_enabled']    ?? '1') === '1';
$footerCopyright  = $footerSettings['footer_copyright']   ?? 'Made with ❤️ from YourRapiddeath';
$footerBgColor    = $footerSettings['footer_bg_color']    ?? '';  // leer = Theme-Farbe
$footerTextColor  = $footerSettings['footer_text_color']  ?? '';

// Footer-Links als JSON
$footerLinksRaw   = $footerSettings['footer_custom_links']       ?? '';
$footerLinks      = [];
if ($footerLinksRaw) {
    $decoded = json_decode($footerLinksRaw, true);
    if (is_array($decoded)) $footerLinks = $decoded;
}

// Eingebaute Links (Impressum, Datenschutz, AGB, Kontakt)
$builtinPages = [
    'footer_imprint'  => ['icon' => '🏢', 'default_label' => 'Impressum'],
    'footer_privacy'  => ['icon' => '🔒', 'default_label' => 'Datenschutz'],
    'footer_terms'    => ['icon' => '📄', 'default_label' => 'AGB'],
    'footer_contact'  => ['icon' => '✉️',  'default_label' => 'Kontakt'],
];

if (!$footerEnabled) return;
?>
<footer class="site-footer" style="<?= $footerBgColor ? 'background:'.htmlspecialchars($footerBgColor).'!important;' : '' ?><?= $footerTextColor ? 'color:'.htmlspecialchars($footerTextColor).'!important;' : '' ?>">
    <div class="site-footer-inner">

        <!-- Links-Bereich -->
        <?php
        $hasLinks = false;
        foreach ($builtinPages as $key => $cfg) {
            $label   = $footerSettings[$key . '_label'] ?? $cfg['default_label'];
            $content = $footerSettings[$key . '_content'] ?? '';
            $show    = ($footerSettings[$key . '_show'] ?? '1') === '1';
            if ($show && $content) { $hasLinks = true; break; }
        }
        if (!$hasLinks && !empty($footerLinks)) $hasLinks = true;
        ?>
        <?php if ($hasLinks): ?>
        <nav class="site-footer-links">
            <?php foreach ($builtinPages as $key => $cfg):
                $label   = htmlspecialchars($footerSettings[$key . '_label']   ?? $cfg['default_label']);
                $content = $footerSettings[$key . '_content'] ?? '';
                $show    = ($footerSettings[$key . '_show']   ?? '1') === '1';
                if (!$show || !$content) continue;
                $slug    = str_replace('footer_', '', $key);
            ?>
                <a href="<?= htmlspecialchars(SITE_URL) ?>/legal.php?page=<?= $slug ?>" class="site-footer-link">
                    <?= $cfg['icon'] ?> <?= $label ?>
                </a>
            <?php endforeach; ?>

            <?php foreach ($footerLinks as $lnk):
                if (empty($lnk['label']) || empty($lnk['url'])) continue;
            ?>
                <a href="<?= htmlspecialchars($lnk['url']) ?>"
                   class="site-footer-link"
                   <?= !empty($lnk['new_tab']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?= htmlspecialchars($lnk['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div class="site-footer-copyright">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($footerCopyright) ?>
        </div>

    </div>
</footer>

<style>
html, body {
    min-height: 100%;
}
body {
    padding-bottom: 56px; /* Platz für den Footer damit letzter Content nicht verdeckt wird */
}
.site-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 8990; /* unter Navbar (9000) und Modals, aber über Content */
    padding: 0.6rem 1.5rem;
    background: var(--surface);
    border-top: 1px solid var(--border);
    font-size: 0.82rem;
    color: var(--text-light);
}
.site-footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: nowrap;
    gap: 0.75rem;
}
.site-footer-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem 1.1rem;
    align-items: center;
}
.site-footer-link {
    color: var(--text-light);
    text-decoration: none;
    transition: color .15s;
    white-space: nowrap;
}
.site-footer-link:hover {
    color: var(--primary);
    text-decoration: underline;
}
.site-footer-copyright {
    color: var(--text-light);
    white-space: nowrap;
    margin-left: auto;
}
@media (max-width: 600px) {
    .site-footer-inner { flex-wrap: wrap; }
    .site-footer-copyright { margin-left: 0; }
    body { padding-bottom: 72px; }
}
</style>

