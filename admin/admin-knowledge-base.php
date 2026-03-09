<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/User.php';
require_once '../includes/functions.php';
require_once '../includes/KnowledgeBase.php';

global $translator;



requireLogin();
requireRole('admin');

$db      = Database::getInstance()->getConnection();
$kb      = new KnowledgeBase();
$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST-Aktionen ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['grant_editor'])) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetId && $targetId !== $uid) {
            $kb->addEditor($targetId, $uid);
            $success = $translator->translate('knowledgebase_rights_granted');
        } else {
            $error = $translator->translate('knowledgebase_invalid_selection');
        }
    }

    elseif (isset($_POST['revoke_editor'])) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $kb->removeEditor($targetId);
        $success = $translator->translate('knowledgebase_rights_revoked');
    }
}

// ── Daten laden ───────────────────────────────────────────────────────────────
$editors    = $kb->getEditors();
$editorIds  = array_column($editors, 'user_id');
$stats      = $kb->getAllStats();
$categories = $kb->getCategories();

// Alle Supporter für Auswahl
$supporters = $db->query("
    SELECT id, full_name, username, role
    FROM users
    WHERE role IN ('first_level','second_level','third_level')
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Alle Artikel
$allArticles = $db->query("
    SELECT a.*, c.name as cat_name, c.icon as cat_icon,
           u.full_name as author_name
    FROM kb_articles a
    LEFT JOIN kb_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wissensdatenbank – Admin – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= loadTheme() ?>">
    <style>
    .admin-kb-wrap { max-width: 1200px; margin: 2rem auto; padding: 0 1.25rem; }
    .page-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .page-header h1 { font-size:1.55rem; font-weight:800; margin:0; display:flex; align-items:center; gap:.5rem; }
    .page-header p  { margin:.25rem 0 0; color:var(--text-light); font-size:.9rem; }

    .stats-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem; }
    .stat-box { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; padding:.75rem 1.25rem; min-width:100px; text-align:center; }
    .stat-val  { font-size:1.6rem; font-weight:800; color:var(--primary); }
    .stat-lbl  { font-size:.72rem; color:var(--text-light); }

    .card { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:1.5rem; }
    .card-header { padding:.9rem 1.25rem; border-bottom:1px solid var(--border); font-weight:700; font-size:.95rem; display:flex; align-items:center; justify-content:space-between; }
    .card-body   { padding:1.25rem; }

    table { width:100%; border-collapse:collapse; font-size:.88rem; }
    th, td { padding:.55rem .85rem; border-bottom:1px solid var(--border); text-align:left; }
    th { background:var(--background); font-weight:700; font-size:.78rem; text-transform:uppercase; letter-spacing:.3px; color:var(--text-light); }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:var(--background); }

    .badge { display:inline-block; padding:.1rem .5rem; border-radius:20px; font-size:.72rem; font-weight:700; }
    .badge-pub   { background:#dcfce7; color:#166534; }
    .badge-draft { background:#fef9c3; color:#854d0e; }
    .badge-editor{ background:#ede9fe; color:#5b21b6; }

    .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem 1rem; border:none; border-radius:7px; font-size:.82rem; font-weight:600; cursor:pointer; text-decoration:none; transition:opacity .15s; }
    .btn:hover { opacity:.85; }
    .btn-primary   { background:var(--primary); color:#fff; }
    .btn-secondary { background:var(--background); border:1.5px solid var(--border); color:var(--text); }
    .btn-danger    { background:#ef4444; color:#fff; }
    .btn-sm        { padding:.3rem .7rem; font-size:.78rem; }

    .alert { border-radius:8px; padding:.75rem 1rem; font-size:.88rem; margin-bottom:1rem; }
    .alert-success { background:#dcfce722; border:1px solid #86efac; color:#166534; }
    .alert-error   { background:#fee2e222; border:1px solid #fca5a5; color:#991b1b; }

    .form-inline { display:flex; gap:.65rem; align-items:center; flex-wrap:wrap; }
    .form-inline select, .form-inline input { padding:.45rem .75rem; border:1.5px solid var(--border); border-radius:7px; background:var(--background); color:var(--text); font-size:.88rem; }
    </style>
</head>
<body>
<?php require_once '../includes/navbar.php'; ?>

<div class="admin-kb-wrap">
    <div class="page-header">
        <div>
            <h1><?=$translator->translate('knowledgebase_title')?></h1>
            <p><?=$translator->translate('knowledgebase_subtitle')?></p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
            <a href="../support/knowledge-base.php" class="btn btn-secondary"><?=$translator->translate('knowledgebase_preview')?></a>
            <a href="../support/knowledge-base.php?view=edit_category" class="btn btn-secondary"><?=$translator->translate('knowledgebase_add_cateory')?></a>
            <a href="../support/knowledge-base.php?view=new_article" class="btn btn-primary"><?=$translator->translate('knowledgebase_add_article')?></a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= escape($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">❌ <?= escape($error) ?></div><?php endif; ?>

    <!-- Statistik -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-val"><?= $stats['cats'] ?></div>
            <div class="stat-lbl"><?=$translator->translate('knowledgebase_categorys')?></div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $stats['arts'] ?></div>
            <div class="stat-lbl"><?=$translator->translate('knowledgebase_published')?></div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:#f59e0b;"><?= $stats['drafts'] ?></div>
            <div class="stat-lbl"><?=$translator->translate('knowledgebase_unpublished')?></div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= number_format($stats['views']) ?></div>
            <div class="stat-lbl"><?=$translator->translate('knowledgebase_views_all')?></div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= count($editors) ?></div>
            <div class="stat-lbl"><?=$translator->translate('knowledgebase_autors')?></div>
        </div>
    </div>

    <!-- Editoren verwalten -->
    <div class="card">
        <div class="card-header">
            <?=$translator->translate('knowledgebase_editors_rights')?>
            <span style="font-size:.78rem;font-weight:400;color:var(--text-light);"><?=$translator->translate('knowledgebase_admin_has_rights')?></span>
        </div>
        <div class="card-body">
            <form method="post" action="admin-knowledge-base.php" class="form-inline" style="margin-bottom:1.25rem;">
                <input type="hidden" name="grant_editor" value="1">
                <select name="target_user_id" required>
                    <option value=""><?=$translator->translate('knowledgebase_select_suporter')?></option>
                    <?php foreach ($supporters as $s): ?>
                        <?php if (!in_array($s['id'], $editorIds)): ?>
                            <option value="<?= $s['id'] ?>"><?= escape($s['full_name'] . ' (' . $s['username'] . ')') ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><?=$translator->translate('knowledgebase_give_rights')?></button>
            </form>

            <?php if (empty($editors)): ?>
                <p style="color:var(--text-light);font-size:.88rem;"><?=$translator->translate('knowledgebase_no_autors')?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?=$translator->translate('knowledgebase_table_name')?></th>
                            <th><?=$translator->translate('knowledgebase_table_username')?></th>
                            <th><?=$translator->translate('knowledgebase_table_role')?></th>
                            <th><?=$translator->translate('knowledgebase_table_hasrights_from')?></th>
                            <th><?=$translator->translate('knowledgebase_date')?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($editors as $ed): ?>
                        <tr>
                            <td><strong><?= escape($ed['full_name']) ?></strong></td>
                            <td style="color:var(--text-light);"><?= escape($ed['username']) ?></td>
                            <td><span class="badge badge-editor"><?= escape($ed['role']) ?></span></td>
                            <td><?= escape($ed['granted_by_name'] ?? '–') ?></td>
                            <td style="color:var(--text-light);"><?= date('d.m.Y', strtotime($ed['granted_at'])) ?></td>
                            <td>
                                <form method="post" action="admin-knowledge-base.php" style="display:inline;" onsubmit="return confirm('Rechte entziehen?')">
                                    <input type="hidden" name="revoke_editor" value="1">
                                    <input type="hidden" name="target_user_id" value="<?= $ed['user_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><?=$translator->translate('knowledgebase_remove_rights')?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kategorien-Übersicht -->
    <div class="card">
        <div class="card-header">
            <?=$translator->translate('knowledgebase_categorys_overview')?>
            <a href="../support/knowledge-base.php?view=edit_category" class="btn btn-primary btn-sm"><?=$translator->translate('knowledgebase_new')?></a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($categories)): ?>
                <p style="padding:1.25rem;color:var(--text-light);"><?=$translator->translate('knowledgebase_no_categorys')?></p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?=$translator->translate('knowledgebase_table_icon')?></th>
                        <th><?=$translator->translate('knowledgebase_table_name')?></th>
                        <th><?=$translator->translate('knowledgebase_table_description')?></th>
                        <th><?=$translator->translate('knowledgebase_table_article')?></th>
                        <th><?=$translator->translate('knowledgebase_table_row')?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-size:1.4rem;"><?= escape($cat['icon']) ?></td>
                        <td><strong><?= escape($cat['name']) ?></strong></td>
                        <td style="color:var(--text-light);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= escape($cat['description'] ?? '–') ?></td>
                        <td><?= (int)$cat['article_count'] ?></td>
                        <td><?= (int)$cat['sort_order'] ?></td>
                        <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <a href="../support/knowledge-base.php?view=category&cat=<?= $cat['id'] ?>" class="btn btn-secondary btn-sm">👁</a>
                            <a href="../support/knowledge-base.php?view=edit_category&cat=<?= $cat['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
                            <form method="post" action="../support/knowledge-base.php" style="display:inline;" onsubmit="return confirm('<?=$translator->translate('knowledgebase_delete_category_confirm')?>')">
                                <input type="hidden" name="delete_category" value="1">
                                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alle Artikel -->
    <div class="card">
        <div class="card-header">
            <?=$translator->translate('knowledgebase_all_articles_title')?>
            <a href="../support/knowledge-base.php?view=new_article" class="btn btn-primary btn-sm"><?=$translator->translate('knowledgebase_add_article')?></a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($allArticles)): ?>
                <p style="padding:1.25rem;color:var(--text-light);"><?=$translator->translate('knowledgebase_no_article')?></p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?=$translator->translate('knowledgebase_table_title')?></th>
                        <th><?=$translator->translate('knowledgebase_table_category')?></th>
                        <th><?=$translator->translate('knowledgebase_table_autor')?></th>
                        <th><?=$translator->translate('knowledgebase_table_state')?></th>
                        <th><?=$translator->translate('knowledgebase_table_views')?></th>
                        <th><?=$translator->translate('knoledgebase_table_last_update')?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allArticles as $art): ?>
                    <tr>
                        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <strong><?= escape($art['title']) ?></strong>
                        </td>
                        <td style="white-space:nowrap;"><?= escape(($art['cat_icon'] ?? '') . ' ' . ($art['cat_name'] ?? '–')) ?></td>
                        <td><?= escape($art['author_name'] ?? '–') ?></td>
                        <td>
                            <?php if ($art['is_published']): ?>
                                <span class="badge badge-pub"><?=$translator->translate('knowledgebase_published')?></span>
                            <?php else: ?>
                                <span class="badge badge-draft"><?=$translator->translate('knowledgebase_unpublished')?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($art['views']) ?></td>
                        <td style="color:var(--text-light);white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($art['updated_at'])) ?></td>
                        <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <a href="../support/knowledge-base.php?view=article&article=<?= $art['id'] ?>" class="btn btn-secondary btn-sm">👁</a>
                            <a href="../support/knowledge-base.php?view=edit_article&article=<?= $art['id'] ?>" class="btn btn-primary btn-sm">✏️</a>
                            <form method="post" action="../support/knowledge-base.php" style="display:inline;" onsubmit="return confirm('Artikel löschen?')">
                                <input type="hidden" name="delete_article" value="1">
                                <input type="hidden" name="article_id" value="<?= $art['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>

