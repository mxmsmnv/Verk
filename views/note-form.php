<?php namespace ProcessWire;
/**
 * @var Verk $this
 * @var array|null $note
 * @var array $categories
 */
$url    = $this->page->url;
$csrf   = $this->getCSRFToken();
$csrfN  = $this->getCSRFName();
$isEdit = !empty($note);
$n      = $note ?? ['title'=>'','body'=>'','category'=>'','created_at'=>'','updated_at'=>''];
$textStats = $this->textStats((string)$n['body']);
$wordCount = $textStats['words'];
$charCount = $textStats['characters'];
$returnUrl = $this->safeLocalUrl((string)$this->wire('input')->get('return_url', 'string'));
$backUrl = $returnUrl ?: $url . '?view=kb';
ob_start();
?>

<?php if (!$isEdit): ?>
<div class="vk-page-head vk-compact-head">
    <div>
        <h2 class="vk-page-title"><?= __('New Note') ?></h2>
        <p><?= __('Capture reusable knowledge for the team.') ?></p>
    </div>
    <?php if ($returnUrl): ?>
    <div class="vk-actions">
        <a href="<?= htmlspecialchars($returnUrl) ?>" class="uk-button uk-button-default uk-button-small"><i class="fa fa-arrow-left"></i> <?= __('Back to Knowledge Base') ?></a>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<section class="vk-document-panel">
    <div class="vk-document-header">
        <div>
            <div class="vk-issue-key"><?= mb_strtoupper(__('Knowledge Base')) ?><?= $n['category'] ? ' / ' . htmlspecialchars(mb_strtoupper($n['category'])) : '' ?></div>
            <h2 class="vk-document-title"><?= htmlspecialchars($n['title']) ?></h2>
            <span class="vk-document-meta"><?= sprintf(__('Updated %s'), htmlspecialchars(substr($n['updated_at'], 0, 10))) ?></span>
        </div>
        <div class="vk-actions">
            <a href="#vk-edit-note" class="uk-button uk-button-default uk-button-small"><i class="fa fa-pencil"></i> <?= __('Edit note') ?></a>
            <a href="<?= $url ?>?view=export-docx&type=kb_note&id=<?= $n['id'] ?>" class="uk-button uk-button-default uk-button-small"><i class="fa fa-file-word-o"></i> <?= __('Export .docx') ?></a>
            <form method="post" action="<?= $url ?>" onsubmit="return confirm('<?= __('Delete this note?') ?>')">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <?php if ($returnUrl): ?><input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>"><?php endif; ?>
                <button class="uk-button uk-button-danger uk-button-small"><i class="fa fa-trash"></i> <?= __('Delete') ?></button>
            </form>
        </div>
    </div>
    <div class="vk-document-body vk-rich-text">
        <?= $n['body'] ? $this->renderRichText($n['body']) : '<p class="vk-muted-line">' . __('This note has no content yet.') . '</p>' ?>
    </div>
</section>
<?php endif; ?>

<div class="vk-note-workspace">
    <div id="vk-edit-note" class="uk-card uk-card-default vk-task-card vk-form-compact vk-note-form <?= $isEdit ? 'vk-edit-card' : '' ?>">
        <div class="uk-card-header">
            <h3 class="vk-card-title"><?= $isEdit ? __('Edit note') : __('Note Details') ?></h3>
            <span class="vk-card-pager-meta"><?= $isEdit ? __('Editing saved knowledge') : __('Draft a reusable note') ?></span>
        </div>
        <div class="uk-card-body">
            <form method="post" action="<?= $url ?>">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="save_note">
                <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $n['id'] ?>"><?php endif; ?>
                <?php if ($returnUrl): ?><input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>"><?php endif; ?>

                <div class="vk-note-fields">
                    <div class="vk-field">
                        <label class="uk-form-label"><?= __('Title') ?> *</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($n['title']) ?>" required autofocus class="uk-input">
                    </div>

                    <div class="vk-field">
                        <label class="uk-form-label"><?= __('Category') ?></label>
                        <select name="category" id="vk-cat-select" class="uk-select">
                            <option value="">&mdash; <?= __('None') ?> &mdash;</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $n['category']===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__"><?= __('+ New category...') ?></option>
                        </select>
                    </div>
                    <div class="vk-field vk-hidden-row" id="vk-new-cat-row">
                        <label class="uk-form-label"><?= __('New Category Name') ?></label>
                        <input type="text" name="new_category" id="vk-new-cat" maxlength="100" placeholder="<?= __('e.g. Editorial Policy') ?>" class="uk-input">
                    </div>
                </div>

                <div class="vk-field">
                    <label class="uk-form-label"><?= __('Content') ?></label>
                    <div class="vk-rich-editor vk-note-editor">
                        <?= $this->renderRichTextEditor('body', (string)$n['body'], 360) ?>
                    </div>
                </div>

                <div class="vk-form-actions">
                    <button type="submit" class="uk-button uk-button-primary"><?= $returnUrl ? ($isEdit ? __('Save and return') : __('Create and return')) : ($isEdit ? __('Save Changes') : __('Save Note')) ?></button>
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="uk-button uk-button-default"><?= __('Cancel') ?></a>
                </div>
            </form>
        </div>
    </div>

    <aside class="uk-card uk-card-default vk-note-side">
        <div class="uk-card-header">
            <h3 class="vk-card-title"><?= __('Note Summary') ?></h3>
            <span class="vk-panel-note"><?= $isEdit ? __('Saved knowledge') : __('Draft metadata') ?></span>
        </div>
        <div class="uk-card-body">
            <dl class="vk-note-info-list">
                <div>
                    <dt><?= __('Category') ?></dt>
                    <dd data-note-summary-category><?= $n['category'] ? htmlspecialchars($n['category']) : __('Uncategorized') ?></dd>
                </div>
                <div>
                    <dt><?= __('Words') ?></dt>
                    <dd data-note-summary-words><?= (int)$wordCount ?></dd>
                </div>
                <div>
                    <dt><?= __('Characters') ?></dt>
                    <dd data-note-summary-characters><?= (int)$charCount ?></dd>
                </div>
                <?php if ($isEdit && !empty($n['created_at'])): ?>
                <div>
                    <dt><?= __('Created') ?></dt>
                    <dd><?= htmlspecialchars(substr((string)$n['created_at'], 0, 10)) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($isEdit && !empty($n['updated_at'])): ?>
                <div>
                    <dt><?= __('Updated') ?></dt>
                    <dd><?= htmlspecialchars(substr((string)$n['updated_at'], 0, 10)) ?></dd>
                </div>
                <?php endif; ?>
            </dl>

            <?php if ($isEdit): ?>
            <div class="vk-note-side-actions">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="uk-button uk-button-default uk-button-small"><?= __('Back to Knowledge Base') ?></a>
                <a href="<?= $url ?>?view=export-docx&type=kb_note&id=<?= (int)$n['id'] ?>" class="uk-button uk-button-default uk-button-small"><i class="fa fa-file-word-o"></i> <?= __('Export') ?></a>
            </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
(() => {
const catSelect = document.getElementById('vk-cat-select');
const newCat = document.getElementById('vk-new-cat');
const uncategorizedLabel = <?= json_encode(__('Uncategorized')) ?>;
const categorySummary = document.querySelector('[data-note-summary-category]');
const wordsSummary = document.querySelector('[data-note-summary-words]');
const charactersSummary = document.querySelector('[data-note-summary-characters]');

function plainTextFromHtml(value) {
    const node = document.createElement('div');
    node.innerHTML = value || '';
    return (node.textContent || node.innerText || '').replace(/\u00a0/g, ' ').trim();
}

function updateTextStats(value) {
    const text = plainTextFromHtml(value);
    const words = text ? (text.match(/[\p{L}\p{N}][\p{L}\p{N}'-]*/gu) || []).length : 0;
    if (wordsSummary) wordsSummary.textContent = String(words);
    if (charactersSummary) charactersSummary.textContent = String(Array.from(text).length);
}

function currentEditorValue() {
    if (window.tinymce) {
        const editor = window.tinymce.get('vk-editor-body');
        if (editor) return editor.getContent();
    }
    const frameBody = document.getElementById('vk-editor-body_ifr')?.contentDocument?.body;
    if (frameBody) return frameBody.innerHTML;
    const textarea = document.querySelector('textarea[name="body"]');
    return textarea ? textarea.value : '';
}

function bindEditorStats(attempt = 0) {
    const textarea = document.querySelector('textarea[name="body"]');
    if (window.tinymce) {
        const editor = window.tinymce.get('vk-editor-body');
        if (editor) {
            editor.on('change input keyup undo redo setcontent', () => updateTextStats(editor.getContent()));
            updateTextStats(editor.getContent());
            return;
        }
    }
    const frameBody = document.getElementById('vk-editor-body_ifr')?.contentDocument?.body;
    if (frameBody) {
        const syncFrame = () => updateTextStats(frameBody.innerHTML);
        ['input', 'keyup', 'blur'].forEach((eventName) => frameBody.addEventListener(eventName, syncFrame));
        syncFrame();
        return;
    }
    if (textarea) {
        textarea.addEventListener('input', () => updateTextStats(textarea.value));
        updateTextStats(textarea.value);
    }
    if (attempt < 20) window.setTimeout(() => bindEditorStats(attempt + 1), 250);
}

function updateCategorySummary() {
    if (!categorySummary || !catSelect) return;
    const value = catSelect.value === '__new__' ? (newCat ? newCat.value.trim() : '') : catSelect.value;
    categorySummary.textContent = value || uncategorizedLabel;
}

if (catSelect) catSelect.addEventListener('change', function() {
    const row = document.getElementById('vk-new-cat-row');
    const inp = newCat;
    if (this.value === '__new__') {
        row.classList.remove('vk-hidden-row');
        inp.required = true;
        inp.focus();
    } else {
        row.classList.add('vk-hidden-row');
        inp.required = false;
        inp.value = '';
    }
    updateCategorySummary();
});
if (newCat) newCat.addEventListener('input', updateCategorySummary);

bindEditorStats();
updateTextStats(currentEditorValue());
updateCategorySummary();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
