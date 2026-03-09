<?php
global $translator;
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/KnowledgeBase.php';

requireLogin();
requireRole(['first_level','second_level','third_level','admin']);

$db   = Database::getInstance()->getConnection();
$kb   = new KnowledgeBase();
$role = $_SESSION['role'];
$uid  = (int)$_SESSION['user_id'];
$canEdit = $kb->canEdit($uid, $role);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {

    if (isset($_POST['create_category'])) {
        $r = $kb->createCategory(
            $_POST['cat_name'] ?? '',
            $_POST['cat_desc'] ?? '',
            $_POST['cat_icon'] ?? '📁',
            $_POST['cat_sort'] ?? 0,
            $uid
        );
        $r ? ($success = 'Kategorie erstellt.') : ($error = 'Fehler beim Erstellen.');
    }

    elseif (isset($_POST['update_category'])) {
        $r = $kb->updateCategory(
            (int)$_POST['cat_id'],
            $_POST['cat_name'] ?? '',
            $_POST['cat_desc'] ?? '',
            $_POST['cat_icon'] ?? '📁',
            $_POST['cat_sort'] ?? 0
        );
        $r ? ($success = 'Kategorie aktualisiert.') : ($error = 'Fehler beim Speichern.');
    }

    elseif (isset($_POST['delete_category'])) {
        $kb->deleteCategory((int)$_POST['cat_id']);
        $success = 'Kategorie gelöscht.';
    }

    elseif (isset($_POST['save_article'])) {
        $artId   = (int)($_POST['article_id'] ?? 0);
        $catId   = (int)($_POST['art_category'] ?? 0);
        $title   = trim($_POST['art_title'] ?? '');
        $content = $_POST['art_content'] ?? '';
        $tags    = trim($_POST['art_tags'] ?? '');
        $pub     = isset($_POST['art_published']) ? 1 : 0;

        if (empty($title) || empty($content) || !$catId) {
            $error = 'Titel, Kategorie und Inhalt sind Pflichtfelder.';
        } elseif ($artId > 0) {
            $kb->updateArticle($artId, $catId, $title, $content, $tags, $pub, $uid);
            $success = 'Artikel gespeichert.';
        } else {
            $newId = $kb->createArticle($catId, $title, $content, $tags, $pub, $uid);
            $success = 'Artikel erstellt.';
        }
    }

    elseif (isset($_POST['delete_article'])) {
        $kb->deleteArticle((int)$_POST['article_id']);
        $success = 'Artikel gelöscht.';
    }
}

$view       = $_GET['view']    ?? 'list';     // list | category | article | edit_article | edit_category
$catId      = (int)($_GET['cat']     ?? 0);
$articleId  = (int)($_GET['article'] ?? 0);
$searchQ    = trim($_GET['q'] ?? '');

$categories = $kb->getCategories();
$currentCat = $catId ? $kb->getCategoryById($catId) : null;
$currentArt = null;

if ($view === 'article' && $articleId) {
    $currentArt = $kb->getArticleById($articleId, true);
    if (!$currentArt) { header('Location: knowledge-base.php'); exit; }
}

$searchResults = $searchQ ? $kb->searchArticles($searchQ) : [];
$stats = $kb->getAllStats();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wissensdatenbank – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
    .kb-wrapper {
        max-width: 1280px;
        margin: 2rem auto;
        padding: 0 1.25rem;
    }
    .kb-hero {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 16px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.25rem;
    }
    .kb-hero-title {
        font-size: 1.65rem;
        font-weight: 800;
        margin: 0 0 0.3rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .kb-hero-sub {
        color: var(--text-light);
        font-size: 0.9rem;
        margin: 0;
    }
    .kb-stats {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .kb-stat {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0.6rem 1.1rem;
        text-align: center;
        min-width: 85px;
    }
    .kb-stat-val { font-size: 1.5rem; font-weight: 800; color: var(--primary); line-height: 1.1; }
    .kb-stat-lbl { font-size: 0.7rem; color: var(--text-light); margin-top: 0.2rem; }

    .kb-search-bar {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .kb-search-bar input {
        flex: 1;
        padding: 0.65rem 1rem;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        background: var(--surface);
        color: var(--text);
        transition: border-color .2s;
    }
    .kb-search-bar input:focus { outline: none; border-color: var(--primary); }
    .kb-search-bar button {
        padding: 0.65rem 1.25rem;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        font-weight: 600;
    }

    .kb-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.85rem;
        color: var(--text-light);
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }
    .kb-breadcrumb a { color: var(--primary); text-decoration: none; }
    .kb-breadcrumb a:hover { text-decoration: underline; }
    .kb-breadcrumb span { opacity: .5; }

    .kb-cat-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        margin-bottom: 2rem;
        list-style: none;
    }
    .kb-cat-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        padding: 1.5rem 1.25rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        position: relative;
        box-sizing: border-box;
        width: calc(25% - 1rem);
        min-width: 180px;
        flex-shrink: 0;
        transition: border-color .2s, box-shadow .2s, transform .15s;
        user-select: none;
    }
    @media (max-width: 1100px) { .kb-cat-card { width: calc(33.33% - 0.85rem); } }
    @media (max-width: 760px)  { .kb-cat-card { width: calc(50% - 0.65rem); } }
    @media (max-width: 480px)  { .kb-cat-card { width: 100%; } }
    .kb-cat-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 18px rgba(0,0,0,.18);
        transform: translateY(-2px);
    }
    .kb-cat-icon {
        font-size: 2rem;
        line-height: 1;
        display: block;
        margin-bottom: 0.2rem;
    }
    .kb-cat-name {
        font-size: 1rem;
        font-weight: 700;
        display: block;
        color: var(--text);
    }
    .kb-cat-desc {
        font-size: 0.82rem;
        color: var(--text-light);
        display: block;
        flex: 1;
    }
    .kb-cat-meta {
        font-size: 0.75rem;
        color: var(--text-light);
        display: block;
        margin-top: auto;
        padding-top: 0.6rem;
        border-top: 1px solid var(--border);
    }
    .kb-cat-actions {
        position: absolute;
        top: 0.6rem;
        right: 0.6rem;
        display: flex;
        gap: 0.3rem;
        opacity: 0;
        transition: opacity .15s;
    }
    .kb-cat-card:hover .kb-cat-actions { opacity: 1; }
    .kb-act-btn {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.15rem 0.4rem;
        font-size: 0.72rem;
        cursor: pointer;
        color: var(--text);
        text-decoration: none;
        transition: background .15s;
        line-height: 1.5;
        display: inline-block;
    }
    .kb-act-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    .kb-act-btn.danger:hover { background: #ef4444; border-color: #ef4444; }

    .kb-article-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .kb-article-row {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        color: var(--text);
        transition: border-color .2s, box-shadow .2s;
        box-sizing: border-box;
    }
    .kb-article-row:hover { border-color: var(--primary); box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .kb-article-info { flex: 1; min-width: 0; }
    .kb-article-title { font-weight: 700; font-size: 0.97rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kb-article-meta { font-size: 0.76rem; color: var(--text-light); margin-top: 0.2rem; }
    .kb-tag { display: inline-block; background: var(--background); border: 1px solid var(--border); border-radius: 20px; padding: 0.1rem 0.55rem; font-size: 0.7rem; color: var(--text-light); margin-right: 0.3rem; }
    .kb-draft-badge { background: #f59e0b22; color: #f59e0b; border: 1px solid #f59e0b55; border-radius: 6px; padding: 0.1rem 0.45rem; font-size: 0.7rem; font-weight: 700; }
    .kb-views { font-size: 0.75rem; color: var(--text-light); white-space: nowrap; }

    .kb-article-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }
    .kb-article-head {
        padding: 1.5rem 1.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
    }
    .kb-article-head h2 { font-size: 1.5rem; font-weight: 800; margin: 0 0 0.5rem; }
    .kb-article-head-meta { font-size: 0.8rem; color: var(--text-light); display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
    .kb-article-body {
        padding: 1.75rem;
        line-height: 1.75;
        font-size: 0.95rem;
    }
    .kb-article-body h1,.kb-article-body h2,.kb-article-body h3 { margin-top: 1.5rem; }
    .kb-article-body code {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 0.1rem 0.35rem;
        font-family: monospace;
        font-size: 0.88em;
    }
    .kb-article-body pre {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        overflow-x: auto;
        font-size: 0.88rem;
    }
    .kb-article-body pre code { background: none; border: none; padding: 0; }
    .kb-article-body blockquote {
        border-left: 3px solid var(--primary);
        margin-left: 0;
        padding-left: 1rem;
        color: var(--text-light);
        font-style: italic;
    }
    .kb-article-body ul, .kb-article-body ol { padding-left: 1.5rem; }
    .kb-article-body table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .kb-article-body th, .kb-article-body td { padding: 0.5rem 0.75rem; border: 1px solid var(--border); }
    .kb-article-body th { background: var(--background); font-weight: 700; }
    .kb-article-body img { max-width: 100%; border-radius: 8px; }

    .kb-editor-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    @media (max-width: 860px) { .kb-editor-grid { grid-template-columns: 1fr; } }
    .kb-form-group { margin-bottom: 1.1rem; }
    .kb-form-group label {
        display: block;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 0.4rem;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .kb-form-group input,
    .kb-form-group select,
    .kb-form-group textarea {
        width: 100%;
        padding: 0.6rem 0.85rem;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        background: var(--background);
        color: var(--text);
        font-size: 0.9rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color .2s;
    }
    .kb-form-group input:focus,
    .kb-form-group select:focus,
    .kb-form-group textarea:focus { outline: none; border-color: var(--primary); }
    .kb-form-group textarea { resize: vertical; min-height: 380px; font-family: monospace; }
    .kb-form-group textarea.prose { font-family: inherit; }

    .kb-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
        padding: 0.5rem;
        background: var(--background);
        border: 1.5px solid var(--border);
        border-bottom: none;
        border-radius: 8px 8px 0 0;
    }
    .kb-toolbar button {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 0.2rem 0.5rem;
        font-size: 0.8rem;
        cursor: pointer;
        color: var(--text);
        font-family: monospace;
        transition: background .15s;
    }
    .kb-toolbar button:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    .kb-toolbar-group { display: flex; gap: 0.25rem; }
    .kb-toolbar-sep { width: 1px; background: var(--border); margin: 0 0.2rem; }

    .kb-preview-box {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        min-height: 380px;
        line-height: 1.75;
        font-size: 0.92rem;
        overflow-y: auto;
    }

    .kb-search-results { display: flex; flex-direction: column; gap: 0.75rem; }
    .kb-search-hit {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 10px;
        padding: 1rem 1.25rem;
        cursor: pointer;
        color: var(--text);
        transition: border-color .2s;
        box-sizing: border-box;
    }
    .kb-search-hit:hover { border-color: var(--primary); }
    .kb-search-hit-title { font-weight: 700; margin-bottom: 0.25rem; }
    .kb-search-hit-excerpt { font-size: 0.82rem; color: var(--text-light); }
    .kb-search-hit-cat { font-size: 0.74rem; color: var(--primary); }

    .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border: none; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .15s, transform .1s; }
    .btn:hover { opacity: .87; transform: translateY(-1px); }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-secondary { background: var(--background); border: 1.5px solid var(--border); color: var(--text); }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; }

    .alert { border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.9rem; margin-bottom: 1rem; }
    .alert-success { background: #dcfce722; border: 1px solid #86efac; color: #166534; }
    .alert-error   { background: #fee2e222; border: 1px solid #fca5a5; color: #991b1b; }
    </style>
</head>
<body>
<?php require_once '../includes/navbar.php'; ?>

<div class="kb-wrapper">
    <!-- Hero-Header -->
    <div class="kb-hero">
        <div>
            <h1 class="kb-hero-title">📚 <?= $translator->translate('kb_title') ?></h1>
            <p class="kb-hero-sub"><?= $translator->translate('kb_subtitle') ?></p>
        </div>
        <div class="kb-stats">
            <div class="kb-stat">
                <div class="kb-stat-val"><?= $stats['cats'] ?></div>
                <div class="kb-stat-lbl"><?= $translator->translate('kb_stat_categories') ?></div>
            </div>
            <div class="kb-stat">
                <div class="kb-stat-val"><?= $stats['arts'] ?></div>
                <div class="kb-stat-lbl"><?= $translator->translate('kb_stat_articles') ?></div>
            </div>
            <?php if ($canEdit && $stats['drafts'] > 0): ?>
            <div class="kb-stat">
                <div class="kb-stat-val" style="color:#f59e0b;"><?= $stats['drafts'] ?></div>
                <div class="kb-stat-lbl"><?= $translator->translate('kb_stat_drafts') ?></div>
            </div>
            <?php endif; ?>
            <div class="kb-stat">
                <div class="kb-stat-val"><?= number_format($stats['views']) ?></div>
                <div class="kb-stat-lbl"><?= $translator->translate('kb_stat_views') ?></div>
            </div>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= escape($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">❌ <?= escape($error) ?></div><?php endif; ?>

    <!-- Suchleiste -->
    <form class="kb-search-bar" method="get" action="">
        <input type="hidden" name="view" value="search">
        <input type="text" name="q" placeholder="<?= $translator->translate('kb_search_ph') ?>"
               value="<?= escape($searchQ) ?>" autofocus>
        <button type="submit">🔍 <?= $translator->translate('search') ?></button>
    </form>

<?php /* ════ SEARCH RESULTS ════ */ ?>
<?php if ($view === 'search'): ?>
    <div class="kb-breadcrumb">
        <a href="knowledge-base.php"><?= $translator->translate('kb_title') ?></a>
        <span>›</span> <?= $translator->translate('kb_search_for') ?> „<?= escape($searchQ) ?>"
    </div>
    <?php if (empty($searchResults)): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-light);">
            <div style="font-size:2.5rem;margin-bottom:0.75rem;">🔍</div>
            <p><?= $translator->translate('kb_no_results') ?> „<?= escape($searchQ) ?>"</p>
        </div>
    <?php else: ?>
        <p style="color:var(--text-light);font-size:0.85rem;margin-bottom:1rem;"><?= count($searchResults) ?> <?= $translator->translate('kb_results') ?></p>
        <div class="kb-search-results">
        <?php foreach ($searchResults as $hit): ?>
            <div class="kb-search-hit" onclick="location.href='knowledge-base.php?view=article&article=<?= $hit['id'] ?>'">
                <div class="kb-search-hit-cat"><?= escape($hit['category_icon'] . ' ' . $hit['category_name']) ?></div>
                <div class="kb-search-hit-title"><?= escape($hit['title']) ?></div>
                <div class="kb-search-hit-excerpt"><?= escape(mb_strimwidth(strip_tags($hit['content']), 0, 180, '…')) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php /* ════════════════ ARTIKEL-DETAIL ════════════════ */ ?>
<?php elseif ($view === 'article' && $currentArt): ?>
    <div class="kb-breadcrumb">
        <a href="knowledge-base.php">Wissensdatenbank</a>
        <span>›</span>
        <a href="knowledge-base.php?view=category&cat=<?= $currentArt['category_id'] ?>"><?= escape($currentArt['category_icon'] . ' ' . $currentArt['category_name']) ?></a>
        <span>›</span>
        <?= escape($currentArt['title']) ?>
    </div>
    <div style="display:flex; gap:0.75rem; margin-bottom:1.25rem; flex-wrap:wrap; align-items:center;">
        <a href="knowledge-base.php?view=category&cat=<?= $currentArt['category_id'] ?>" class="btn btn-secondary btn-sm">← <?= $translator->translate('back') ?></a>
        <?php if ($canEdit): ?>
            <a href="knowledge-base.php?view=edit_article&article=<?= $currentArt['id'] ?>" class="btn btn-primary btn-sm">✏️ <?= $translator->translate('edit') ?></a>
        <?php endif; ?>
        <?php if (!$currentArt['is_published']): ?>
            <span class="kb-draft-badge"><?= $translator->translate('kb_draft_badge') ?></span>
        <?php endif; ?>
    </div>

    <div class="kb-article-card">
        <div class="kb-article-head">
            <h2><?= escape($currentArt['title']) ?></h2>
            <div class="kb-article-head-meta">
                <span>👤 <?= escape($currentArt['author_name']) ?></span>
                <span>📅 <?= date('d.m.Y H:i', strtotime($currentArt['created_at'])) ?></span>
                <?php if ($currentArt['updater_name']): ?>
                <span>✏️ <?= $translator->translate('kb_last_updated_by') ?> <?= escape($currentArt['updater_name']) ?> · <?= date('d.m.Y H:i', strtotime($currentArt['updated_at'])) ?></span>
                <?php endif; ?>
                <span>👁 <?= number_format($currentArt['views']) ?> <?= $translator->translate('kb_stat_views') ?></span>
            </div>
            <?php if ($currentArt['tags']): ?>
            <div style="margin-top:0.6rem;">
                <?php foreach (explode(',', $currentArt['tags']) as $tag): ?>
                    <span class="kb-tag"><?= escape(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="kb-article-body">
            <?= $currentArt['content'] /* HTML wird direkt gerendert – nur von Editoren befüllbar */ ?>
        </div>
    </div>

<?php /* ════════════════ ARTIKEL BEARBEITEN / NEU ════════════════ */ ?>
<?php elseif ($canEdit && ($view === 'edit_article' || $view === 'new_article')): ?>
    <?php $editArt = ($view === 'edit_article' && $articleId) ? $kb->getArticleById($articleId) : null; ?>
    <div class="kb-breadcrumb">
        <a href="knowledge-base.php"><?= $translator->translate('kb_title') ?></a>
        <span>›</span>
        <?= $editArt ? escape($editArt['title']) : $translator->translate('kb_new_article') ?>
    </div>
    <div style="display:flex; gap:0.75rem; margin-bottom:1.25rem;">
        <a href="knowledge-base.php" class="btn btn-secondary btn-sm">← <?= $translator->translate('back') ?></a>
        <h2 style="margin:0;font-size:1.25rem;font-weight:800;"><?= $editArt ? '✏️ ' . $translator->translate('kb_edit_article') : '✏️ ' . $translator->translate('kb_new_article') ?></h2>
    </div>

    <form method="post" action="knowledge-base.php">
        <input type="hidden" name="save_article" value="1">
        <input type="hidden" name="article_id" value="<?= $editArt ? $editArt['id'] : 0 ?>">

        <div class="kb-editor-grid">
            <!-- Left column: fields -->
            <div>
                <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:1.5rem;">
                    <div class="kb-form-group">
                        <label><?= $translator->translate('kb_field_title') ?> *</label>
                        <input type="text" name="art_title" required
                               value="<?= escape($editArt['title'] ?? '') ?>"
                               placeholder="<?= $translator->translate('kb_field_title_ph') ?>">
                    </div>
                    <div class="kb-form-group">
                        <label><?= $translator->translate('kb_field_category') ?> *</label>
                        <select name="art_category" required>
                            <option value="">– <?= $translator->translate('kb_field_category_ph') ?> –</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= (($editArt['category_id'] ?? 0) == $c['id'] || ($catId == $c['id'])) ? 'selected' : '' ?>>
                                    <?= escape($c['icon'] . ' ' . $c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kb-form-group">
                        <label><?= $translator->translate('kb_field_tags') ?> <span style="font-weight:400;text-transform:none;">(<?= $translator->translate('kb_tags_hint') ?>)</span></label>
                        <input type="text" name="art_tags"
                               value="<?= escape($editArt['tags'] ?? '') ?>"
                               placeholder="<?= $translator->translate('kb_tags_ph') ?>">
                    </div>
                    <div class="kb-form-group">
                        <label>
                            <input type="checkbox" name="art_published" value="1"
                                   <?= (!isset($editArt) || $editArt['is_published']) ? 'checked' : '' ?>>
                            <?= $translator->translate('kb_published') ?>
                        </label>
                    </div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
                        <button type="submit" name="art_published" value="1" class="btn btn-primary">💾 <?= $translator->translate('kb_btn_save_publish') ?></button>
                        <button type="submit" class="btn btn-secondary">📄 <?= $translator->translate('kb_btn_save_draft') ?></button>
                        <?php if ($editArt): ?>
                            <form method="post" action="knowledge-base.php" style="display:inline;" onsubmit="return confirm('<?= addslashes($translator->translate('kb_confirm_delete_article')) ?>')">
                                <input type="hidden" name="delete_article" value="1">
                                <input type="hidden" name="article_id" value="<?= $editArt['id'] ?>">
                                <button type="submit" class="btn btn-danger">🗑 <?= $translator->translate('delete') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rechte Spalte: Editor -->
            <div>
                <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:1.5rem;">
                    <div class="kb-form-group">
                        <label>Inhalt * <span style="font-weight:400;text-transform:none;">(HTML)</span></label>
                        <div class="kb-toolbar" id="kb-toolbar">
                            <div class="kb-toolbar-group">
                                <button type="button" onclick="wrapSel('art_content','<h2>','</h2>')">H2</button>
                                <button type="button" onclick="wrapSel('art_content','<h3>','</h3>')">H3</button>
                                <button type="button" onclick="wrapSel('art_content','<p>','</p>')">¶</button>
                            </div>
                            <div class="kb-toolbar-sep"></div>
                            <div class="kb-toolbar-group">
                                <button type="button" onclick="wrapSel('art_content','<strong>','</strong>')"><b>B</b></button>
                                <button type="button" onclick="wrapSel('art_content','<em>','</em>')"><i>I</i></button>
                                <button type="button" onclick="wrapSel('art_content','<u>','</u>')"><u>U</u></button>
                                <button type="button" onclick="wrapSel('art_content','<del>','</del>')"><del>S</del></button>
                            </div>
                            <div class="kb-toolbar-sep"></div>
                            <div class="kb-toolbar-group">
                                <button type="button" onclick="wrapSel('art_content','<ul>\n  <li>','</li>\n</ul>')">UL</button>
                                <button type="button" onclick="wrapSel('art_content','<ol>\n  <li>','</li>\n</ol>')">OL</button>
                                <button type="button" onclick="wrapSel('art_content','<blockquote>','</blockquote>')">❝</button>
                            </div>
                            <div class="kb-toolbar-sep"></div>
                            <div class="kb-toolbar-group">
                                <button type="button" onclick="wrapSel('art_content','<code>','</code>')">{'}'}</button>
                                <button type="button" onclick="wrapSel('art_content','<pre><code>','</code></pre>')">PRE</button>
                                <button type="button" onclick="insertLink()">🔗</button>
                                <button type="button" onclick="togglePreview()">👁 <?= $translator->translate('settings_tpl_preview') ?></button>
                            </div>
                        </div>
                        <textarea name="art_content" id="art_content" class="prose"
                                  style="border-top-left-radius:0;border-top-right-radius:0;"><?= escape($editArt['content'] ?? '') ?></textarea>
                    </div>
                    <div id="kb-preview-wrap" style="display:none;">
                        <label style="font-size:.82rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.4px;"><?= $translator->translate('settings_tpl_preview') ?></label>
                        <div class="kb-preview-box kb-article-body" id="kb-preview"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php /* ════════════════ KATEGORIE BEARBEITEN ════════════════ */ ?>
<?php elseif ($canEdit && $view === 'edit_category'): ?>
    <?php $editCat = $catId ? $kb->getCategoryById($catId) : null; ?>
    <div class="kb-breadcrumb">
        <a href="knowledge-base.php"><?= $translator->translate('kb_title') ?></a>
        <span>›</span> <?= $editCat ? $translator->translate('kb_edit_category') : $translator->translate('kb_new_category') ?>
    </div>
    <style>
    .icon-picker-wrap { position:relative; }
    .icon-picker-preview {
        display:inline-flex; align-items:center; gap:0.6rem;
        padding:0.45rem 0.85rem; border:1.5px solid var(--border);
        border-radius:8px; background:var(--background); cursor:pointer;
        font-size:1.5rem; user-select:none; transition:border-color .15s;
        min-width:60px;
    }
    .icon-picker-preview:hover { border-color:var(--primary); }
    .icon-picker-preview span.ip-arrow { font-size:0.75rem; opacity:.5; }
    .icon-picker-dropdown {
        display:none; position:absolute; top:calc(100% + 6px); left:0;
        z-index:9999; background:var(--surface); border:1.5px solid var(--border);
        border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.18);
        width:320px; padding:0.6rem;
    }
    .icon-picker-dropdown.open { display:block; }
    .ip-search {
        width:100%; box-sizing:border-box; padding:0.4rem 0.65rem;
        border:1px solid var(--border); border-radius:7px;
        background:var(--background); color:var(--text);
        font-size:0.85rem; margin-bottom:0.5rem; outline:none;
    }
    .ip-search:focus { border-color:var(--primary); }
    .ip-cats {
        display:flex; gap:2px; flex-wrap:nowrap; overflow-x:auto;
        scrollbar-width:none; margin-bottom:0.4rem;
    }
    .ip-cats::-webkit-scrollbar { display:none; }
    .ip-cat-btn {
        flex-shrink:0; background:none; border:none; cursor:pointer;
        padding:0.2rem 0.4rem; border-radius:5px; font-size:1rem;
        opacity:.5; transition:opacity .12s, background .12s;
    }
    .ip-cat-btn.active,
    .ip-cat-btn:hover { opacity:1; background:var(--background); }
    .ip-grid {
        display:grid; grid-template-columns:repeat(8,1fr); gap:2px;
        max-height:190px; overflow-y:auto;
        scrollbar-width:thin;
    }
    .ip-emoji {
        background:none; border:none; cursor:pointer; font-size:1.3rem;
        padding:0.15rem; border-radius:5px; text-align:center;
        transition:background .1s; line-height:1.4;
    }
    .ip-emoji:hover { background:var(--background); }
    .ip-no-result { color:var(--text-light); font-size:0.8rem; padding:0.5rem; text-align:center; }
    </style>

    <div style="max-width:540px;">
        <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:1.5rem;">
            <h3 style="margin:0 0 1.25rem;font-size:1.1rem;"><?= $editCat ? '✏️ ' . $translator->translate('kb_edit_category') : '➕ ' . $translator->translate('kb_new_category') ?></h3>
            <form method="post" action="knowledge-base.php">
                <input type="hidden" name="<?= $editCat ? 'update_category' : 'create_category' ?>" value="1">
                <?php if ($editCat): ?><input type="hidden" name="cat_id" value="<?= $editCat['id'] ?>"><?php endif; ?>

                <!-- Icon Picker -->
                <div class="kb-form-group">
                    <label><?= $translator->translate('kb_field_icon') ?></label>
                    <input type="hidden" name="cat_icon" id="cat_icon_val" value="<?= escape($editCat['icon'] ?? '📁') ?>">
                    <div class="icon-picker-wrap" id="iconPickerWrap">
                        <div class="icon-picker-preview" id="iconPickerPreview" onclick="toggleIconPicker(event)">
                            <span id="iconPickerDisplay"><?= escape($editCat['icon'] ?? '📁') ?></span>
                            <span class="ip-arrow">▾</span>
                        </div>
                        <div class="icon-picker-dropdown" id="iconPickerDropdown">
                            <input class="ip-search" type="text" placeholder="Emoji suchen…" id="ipSearch" oninput="filterIcons(this.value)">
                            <div class="ip-cats" id="ipCats"></div>
                            <div class="ip-grid" id="ipGrid"></div>
                        </div>
                    </div>
                </div>
                <div class="kb-form-group">
                    <label><?= $translator->translate('kb_field_name') ?> *</label>
                    <input type="text" name="cat_name" required value="<?= escape($editCat['name'] ?? '') ?>" placeholder="<?= $translator->translate('kb_category_name_ph') ?>">
                </div>
                <div class="kb-form-group">
                    <label><?= $translator->translate('kb_field_description') ?></label>
                    <textarea name="cat_desc" rows="3" class="prose"><?= escape($editCat['description'] ?? '') ?></textarea>
                </div>
                <div class="kb-form-group">
                    <label><?= $translator->translate('kb_field_sort') ?></label>
                    <input type="number" name="cat_sort" value="<?= (int)($editCat['sort_order'] ?? 0) ?>" style="width:100px;">
                </div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">💾 <?= $translator->translate('save') ?></button>
                    <a href="knowledge-base.php" class="btn btn-secondary"><?= $translator->translate('cancel') ?></a>
                </div>
            </form>
        </div>
    </div>

<script>
(function() {
    // ── Emoji-Kategorien ─────────────────────────────────────────────────────
    const CATS = {
        '📁': ['📁','📂','🗂️','🗃️','🗄️','📋','📊','📈','📉','📌','📍','🔖','🏷️','📎','🖇️','📐','📏','✂️','🗑️','🔍','🔎'],
        '⭐': ['⭐','🌟','💫','✨','🔥','💥','🎯','🏆','🥇','🎖️','🏅','🎗️','🎀','🎁','🎊','🎉','🎈','🎆','🎇'],
        '🛠️': ['🛠️','⚙️','🔧','🔨','⛏️','🪛','🔩','🗜️','⚒️','🪚','🔌','💡','🔦','🕯️','🔋','🖥️','💻','🖨️','⌨️','🖱️','📱','☎️','📞','📟','📠'],
        '📚': ['📚','📖','📝','✏️','🖊️','🖋️','📓','📔','📒','📕','📗','📘','📙','📃','📄','📑','🗒️','🗓️','📅','📆','🗑️'],
        '💬': ['💬','🗨️','🗯️','💭','📢','📣','🔔','🔕','💌','📧','📨','📩','📤','📥','📦','📫','📪','📬','📭','📮','🗳️'],
        '🧑‍💻': ['🧑‍💻','👩‍💻','👨‍💻','👤','👥','🤝','👋','🙌','👏','🫂','💪','🦾','🧠','👁️','👀','🫡','🙋','🤔','💡','🗣️'],
        '🌐': ['🌐','🌍','🌎','🌏','🗺️','🧭','🌐','🏔️','⛰️','🌋','🏕️','🏖️','🏜️','🏝️','🏞️','🏙️','🌆','🌇','🌃','🌉','🌁'],
        '🔒': ['🔒','🔓','🔐','🔑','🗝️','🛡️','⚔️','🪖','🚨','⚠️','🚫','❌','✅','✔️','☑️','🔴','🟠','🟡','🟢','🔵','🟣'],
        '💰': ['💰','💴','💵','💶','💷','💸','💳','🪙','💹','📊','📈','📉','💎','🏦','🏧','🛒','🛍️','🎫','🎟️','🧾'],
        '🚀': ['🚀','🛸','🛩️','✈️','🚁','🛶','⛵','🚢','🚂','🚃','🚄','🚅','🚆','🚇','🚈','🚉','🚊','🚞','🚝','🚋','🚌'],
        '❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️','🫀'],
        '🐾': ['🐾','🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦','🦆','🦅'],
        '🍕': ['🍕','🍔','🌮','🍣','🍜','🍝','🥗','🍱','🎂','🍰','☕','🍺','🍷','🥤','🍹','🧃','🫖','🍵','🥛','🍫','🍬'],
    };

    const ALL = Object.entries(CATS);
    const allEmojis = ALL.flatMap(([,e]) => e);

    let currentCat = ALL[0][0];

    function buildCatBar() {
        const bar = document.getElementById('ipCats');
        if (!bar) return;
        bar.innerHTML = '';
        ALL.forEach(([cat]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ip-cat-btn' + (cat === currentCat ? ' active' : '');
            b.textContent = cat;
            b.title = cat;
            b.onclick = () => {
                currentCat = cat;
                document.querySelectorAll('.ip-cat-btn').forEach(x => x.classList.remove('active'));
                b.classList.add('active');
                renderGrid(CATS[cat]);
                document.getElementById('ipSearch').value = '';
            };
            bar.appendChild(b);
        });
    }

    function renderGrid(emojis) {
        const grid = document.getElementById('ipGrid');
        if (!grid) return;
        if (!emojis.length) {
            grid.innerHTML = '<div class="ip-no-result">Keine Emojis gefunden</div>';
            return;
        }
        grid.innerHTML = '';
        emojis.forEach(em => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ip-emoji';
            b.textContent = em;
            b.title = em;
            b.onclick = () => selectEmoji(em);
            grid.appendChild(b);
        });
    }

    function selectEmoji(em) {
        document.getElementById('cat_icon_val').value = em;
        document.getElementById('iconPickerDisplay').textContent = em;
        document.getElementById('iconPickerDropdown').classList.remove('open');
    }

    window.toggleIconPicker = function(e) {
        e.stopPropagation();
        const dd = document.getElementById('iconPickerDropdown');
        const isOpen = dd.classList.contains('open');
        document.querySelectorAll('.icon-picker-dropdown.open').forEach(x => x.classList.remove('open'));
        if (!isOpen) {
            dd.classList.add('open');
            buildCatBar();
            renderGrid(CATS[currentCat]);
            setTimeout(() => document.getElementById('ipSearch')?.focus(), 80);
        }
    };

    window.filterIcons = function(q) {
        const v = q.trim().toLowerCase();
        if (!v) {
            renderGrid(CATS[currentCat]);
            return;
        }
        const hits = allEmojis.filter(em => {
            try { return [...em].some(c => c.codePointAt(0).toString(16).includes(v)); } catch(e) { return false; }
        });
        // Fallback: alle anzeigen wenn keine Treffer
        renderGrid(hits.length ? hits : allEmojis.filter((_,i) => i < 64));
    };

    // Picker schließen beim Klick außerhalb
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#iconPickerWrap')) {
            document.querySelectorAll('.icon-picker-dropdown.open').forEach(x => x.classList.remove('open'));
        }
    });
})();
</script>

<?php /* ════════════════ KATEGORIE-INHALT ════════════════ */ ?>
<?php elseif ($view === 'category' && $currentCat): ?>
    <div class="kb-breadcrumb">
        <a href="knowledge-base.php"><?= $translator->translate('kb_title') ?></a>
        <span>›</span> <?= escape($currentCat['icon'] . ' ' . $currentCat['name']) ?>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h2 style="margin:0;font-size:1.35rem;font-weight:800;"><?= escape($currentCat['icon'] . ' ' . $currentCat['name']) ?></h2>
            <?php if ($currentCat['description']): ?>
                <p style="margin:0.25rem 0 0;color:var(--text-light);font-size:0.88rem;"><?= escape($currentCat['description']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($canEdit): ?>
        <div style="display:flex;gap:0.5rem;">
            <a href="knowledge-base.php?view=new_article&cat=<?= $catId ?>" class="btn btn-primary btn-sm">➕ <?= $translator->translate('kb_stat_articles') ?></a>
            <a href="knowledge-base.php?view=edit_category&cat=<?= $catId ?>" class="btn btn-secondary btn-sm">✏️ <?= $translator->translate('edit') ?></a>
            <form method="post" action="knowledge-base.php" style="display:inline;" onsubmit="return confirm('<?= addslashes($translator->translate('kb_confirm_delete_cat_articles')) ?>')">
                <input type="hidden" name="delete_category" value="1">
                <input type="hidden" name="cat_id" value="<?= $catId ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php $articles = $kb->getArticlesByCategory($catId, !$canEdit); ?>
    <?php if (empty($articles)): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-light);">
            <div style="font-size:2.5rem;margin-bottom:0.75rem;">📭</div>
            <p><?= $translator->translate('kb_no_articles_in_cat') ?></p>
            <?php if ($canEdit): ?>
                <a href="knowledge-base.php?view=new_article&cat=<?= $catId ?>" class="btn btn-primary" style="margin-top:0.75rem;">➕ <?= $translator->translate('kb_create_first_article') ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="kb-article-list">
        <?php foreach ($articles as $art): ?>
            <div class="kb-article-row" onclick="location.href='knowledge-base.php?view=article&article=<?= $art['id'] ?>'">
                <div class="kb-article-info">
                    <div class="kb-article-title">
                        <?= escape($art['title']) ?>
                        <?php if (!$art['is_published']): ?><span class="kb-draft-badge"><?= $translator->translate('kb_draft') ?></span><?php endif; ?>
                    </div>
                    <div class="kb-article-meta">
                        👤 <?= escape($art['author_name']) ?> · 📅 <?= date('d.m.Y', strtotime($art['updated_at'])) ?>
                        <?php if ($art['tags']): ?>
                            · <?php foreach (explode(',', $art['tags']) as $t): ?>
                                <span class="kb-tag"><?= escape(trim($t)) ?></span>
                              <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kb-views">👁 <?= number_format($art['views']) ?></div>
                <?php if ($canEdit): ?>
                    <a href="knowledge-base.php?view=edit_article&article=<?= $art['id'] ?>" class="kb-act-btn" onclick="event.stopPropagation();">✏️</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php /* ════════════════ ÜBERSICHT ════════════════ */ ?>
<?php else: ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <h2 style="margin:0;font-size:1.15rem;font-weight:800;">📂 <?= $translator->translate('kb_all_categories') ?></h2>
        <?php if ($canEdit): ?>
        <div style="display:flex;gap:0.5rem;">
            <a href="knowledge-base.php?view=edit_category" class="btn btn-primary btn-sm">➕ <?= $translator->translate('kb_stat_categories') ?></a>
            <a href="knowledge-base.php?view=new_article" class="btn btn-secondary btn-sm">✏️ <?= $translator->translate('kb_new_article') ?></a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($categories)): ?>
        <div style="text-align:center;padding:4rem;color:var(--text-light);">
            <div style="font-size:3rem;margin-bottom:1rem;">📚</div>
            <p style="font-size:1rem;"><?= $translator->translate('kb_empty') ?></p>
            <?php if ($canEdit): ?>
                <a href="knowledge-base.php?view=edit_category" class="btn btn-primary" style="margin-top:0.75rem;">➕ <?= $translator->translate('kb_create_first_category') ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="kb-cat-grid">
        <?php foreach ($categories as $cat): ?>
            <div class="kb-cat-card" onclick="location.href='knowledge-base.php?view=category&cat=<?= $cat['id'] ?>'" style="cursor:pointer;">
                <?php if ($canEdit): ?>
                <div class="kb-cat-actions" onclick="event.stopPropagation();">
                    <a class="kb-act-btn" href="knowledge-base.php?view=edit_category&cat=<?= $cat['id'] ?>" onclick="event.stopPropagation();">✏️</a>
                    <form method="post" action="knowledge-base.php" style="display:inline;" onsubmit="return confirm('<?= addslashes($translator->translate('kb_confirm_delete_cat')) ?>')" onclick="event.stopPropagation();">
                        <input type="hidden" name="delete_category" value="1">
                        <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="kb-act-btn danger">🗑</button>
                    </form>
                </div>
                <?php endif; ?>
                <span class="kb-cat-icon"><?= escape($cat['icon']) ?></span>
                <span class="kb-cat-name"><?= escape($cat['name']) ?></span>
                <?php if ($cat['description']): ?>
                    <span class="kb-cat-desc"><?= escape($cat['description']) ?></span>
                <?php endif; ?>
                <span class="kb-cat-meta">📄 <?= (int)$cat['article_count'] ?> Artikel</span>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
function wrapSel(id, before, after) {
    const ta = document.getElementById(id);
    if (!ta) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || 'Text hier';
    ta.value = ta.value.substring(0, s) + before + sel + after + ta.value.substring(e);
    ta.selectionStart = s + before.length;
    ta.selectionEnd   = s + before.length + sel.length;
    ta.focus();
    updatePreview();
}
function insertLink() {
    const url = prompt('URL eingeben:', 'https://');
    if (!url) return;
    const text = prompt('Link-Text:', 'Link');
    if (!text) return;
    const ta = document.getElementById('art_content');
    if (!ta) return;
    const s = ta.selectionStart;
    const insert = `<a href="${url}">${text}</a>`;
    ta.value = ta.value.substring(0, s) + insert + ta.value.substring(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = s + insert.length;
    ta.focus();
    updatePreview();
}
let previewOpen = false;
function togglePreview() {
    previewOpen = !previewOpen;
    const wrap = document.getElementById('kb-preview-wrap');
    if (wrap) wrap.style.display = previewOpen ? 'block' : 'none';
    if (previewOpen) updatePreview();
}
function updatePreview() {
    const ta = document.getElementById('art_content');
    const box = document.getElementById('kb-preview');
    if (ta && box) box.innerHTML = ta.value;
}
const ta = document.getElementById('art_content');
if (ta) ta.addEventListener('input', () => { if (previewOpen) updatePreview(); });
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>



