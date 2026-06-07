<?php namespace ProcessWire;
/**
 * @var Verk $this
 * @var array  $notes
 * @var array  $categories
 * @var string $cat
 */
$url = $this->page->url;
$input = $this->wire('input');
$kbQuery = $kbQuery ?? trim($input->get('q', 'string') ?: '');
$kbStats = $kbStats ?? ['total' => count($notes ?? []), 'categories' => count($categories ?? []), 'updated_at' => ''];
$buildKbUrl = function(array $changes = []) use ($url, $cat, $kbQuery) {
    $params = ['view' => 'kb'];
    if ($cat) $params['cat'] = $cat;
    if ($kbQuery !== '') $params['q'] = $kbQuery;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    return $url . '?' . http_build_query($params);
};
$kbReturnUrl = $buildKbUrl([]);
$kbReturnParam = rawurlencode($kbReturnUrl);
ob_start();
?>

<div class="vk-page-head">
    <div>
        <h2 class="vk-page-title"><?= __('Knowledge Base') ?></h2>
        <p><?= __('Editorial notes and reusable operating knowledge.') ?></p>
    </div>
    <div class="vk-actions">
        <a href="<?= $url ?>?view=export-docx&type=kb_cat&cat=<?= urlencode($cat) ?>" class="uk-button uk-button-default"><i class="fa fa-file-word-o"></i> <?= __('Export') ?><?= $cat ? ' "'.htmlspecialchars($cat).'"' : ' '.__('all') ?></a>
        <a href="<?= $url ?>?view=note-edit&return_url=<?= $kbReturnParam ?>" class="uk-button uk-button-primary"><i class="fa fa-plus"></i> <?= __('New Note') ?></a>
    </div>
</div>

<div class="vk-search-toolbar vk-kb-toolbar">
    <form method="get" action="<?= $url ?>" class="vk-task-search-form">
        <input type="hidden" name="view" value="kb">
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
        <div class="vk-task-search-control">
            <i class="fa fa-search"></i>
            <input class="uk-input" type="search" name="q" value="<?= htmlspecialchars($kbQuery) ?>" placeholder="<?= __('Search notes, categories, content') ?>">
            <?php if ($kbQuery !== ''): ?><a href="<?= $buildKbUrl(['q' => null]) ?>" class="vk-filter-reset"><?= __('Clear') ?></a><?php endif; ?>
            <button class="uk-button uk-button-default uk-button-small"><?= __('Search') ?></button>
        </div>
    </form>
</div>

<?php if ($kbQuery !== '' || $cat): ?>
<div class="vk-active-filters">
    <span class="vk-active-filters-label"><?= __('Active filters') ?></span>
    <?php if ($cat): ?>
    <a class="vk-active-filter-chip" href="<?= $buildKbUrl(['cat' => null]) ?>"><?= sprintf(__('Category: %s'), htmlspecialchars($cat)) ?> <i class="fa fa-times"></i></a>
    <?php endif; ?>
    <?php if ($kbQuery !== ''): ?>
    <a class="vk-active-filter-chip" href="<?= $buildKbUrl(['q' => null]) ?>"><?= sprintf(__('Search: %s'), htmlspecialchars($kbQuery)) ?> <i class="fa fa-times"></i></a>
    <?php endif; ?>
    <a class="vk-active-filter-clear" href="<?= $url ?>?view=kb"><?= __('Clear all') ?></a>
</div>
<?php endif; ?>

<div class="vk-kb-summary">
    <div class="vk-kb-summary-card">
        <span><?= __('Notes') ?></span>
        <strong><?= (int)$kbStats['total'] ?></strong>
        <small><?= $cat ? htmlspecialchars($cat) : __('Visible documents') ?></small>
    </div>
    <div class="vk-kb-summary-card">
        <span><?= __('Categories') ?></span>
        <strong><?= (int)$kbStats['categories'] ?></strong>
        <small><?= __('In current view') ?></small>
    </div>
    <div class="vk-kb-summary-card">
        <span><?= __('Last update') ?></span>
        <strong><?= $kbStats['updated_at'] ? htmlspecialchars(substr($kbStats['updated_at'], 0, 10)) : '—' ?></strong>
        <small><?= __('Knowledge freshness') ?></small>
    </div>
</div>

<?php if ($categories): ?>
<ul class="uk-subnav uk-subnav-pill vk-view-switcher vk-kb-filters">
    <li class="<?= !$cat ? 'uk-active' : '' ?>"><a href="<?= $buildKbUrl(['cat' => null]) ?>"><?= __('All') ?></a></li>
    <?php foreach ($categories as $c): ?>
    <li class="<?= $cat===$c ? 'uk-active' : '' ?>"><a href="<?= $buildKbUrl(['cat' => $c]) ?>"><?= htmlspecialchars($c) ?></a></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($notes):
    $grouped = [];
    foreach ($notes as $n) {
        $k = $n['category'] ?: __('Uncategorized');
        $grouped[$k][] = $n;
    }
    foreach ($grouped as $catKey => $catNotes):
?>
<section class="uk-card uk-card-default vk-kb-group">
    <div class="uk-card-header vk-card-header-row">
        <h3 class="vk-card-title"><?= htmlspecialchars($catKey) ?></h3>
        <span class="vk-card-pager-meta"><?= sprintf(_n('%d note', '%d notes', count($catNotes)), count($catNotes)) ?></span>
    </div>
    <div class="vk-kb-list-head">
        <span><?= __('Document') ?></span>
        <span><?= __('Updated') ?></span>
        <span><?= __('Actions') ?></span>
    </div>
    <div class="vk-kb-documents">
        <?php foreach ($catNotes as $n): ?>
        <article class="vk-kb-document">
            <div class="vk-kb-icon"><i class="fa fa-file-text-o"></i></div>
            <div class="vk-kb-main">
                <a href="<?= $url ?>?view=note-edit&id=<?= (int)$n['id'] ?>&return_url=<?= $kbReturnParam ?>" class="vk-kb-title"><?= htmlspecialchars($n['title']) ?></a>
                <div class="vk-kb-document-meta">
                    <span><?= __('NOTE') ?>-<?= (int)$n['id'] ?></span>
                    <span><i class="fa fa-folder-o"></i> <?= htmlspecialchars($n['category'] ?: __('Uncategorized')) ?></span>
                    <?php if (!empty($n['created_at'])): ?><span><?= __('Created') ?> <?= htmlspecialchars(substr($n['created_at'], 0, 10)) ?></span><?php endif; ?>
                </div>
                <?php if ($n['body']): ?>
                <div class="vk-kb-excerpt"><?= htmlspecialchars(mb_strimwidth(trim(strip_tags($n['body'])), 0, 120, '...')) ?></div>
                <?php endif; ?>
            </div>
            <time class="vk-kb-date"><?= substr($n['updated_at'], 0, 10) ?></time>
            <div class="vk-kb-actions">
                <a href="<?= $url ?>?view=export-docx&type=kb_note&id=<?= $n['id'] ?>" class="vk-icon-button" title="<?= __('Export .docx') ?>" aria-label="<?= __('Export .docx') ?>"><i class="fa fa-file-word-o"></i></a>
                <a href="<?= $url ?>?view=note-edit&id=<?= (int)$n['id'] ?>&return_url=<?= $kbReturnParam ?>#vk-edit-note" class="vk-icon-button" title="<?= __('Edit note') ?>" aria-label="<?= __('Edit note') ?>"><i class="fa fa-pencil"></i></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
    <?php endforeach; ?>
<?php else: ?>
<div class="vk-empty vk-empty-panel">
    <i class="fa fa-book vk-empty-icon"></i>
    <div class="vk-empty-title"><?= __('No notes yet.') ?></div>
    <p class="vk-muted-line"><?= __('Create reusable operating knowledge for this site.') ?></p>
    <div class="vk-empty-actions">
        <a href="<?= $url ?>?view=note-edit&return_url=<?= $kbReturnParam ?>" class="uk-button uk-button-primary uk-button-small"><i class="fa fa-plus"></i> <?= __('Create the first one') ?></a>
    </div>
    <span class="vk-skeleton-line vk-skeleton"></span>
    <span class="vk-skeleton-line vk-skeleton is-short"></span>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
