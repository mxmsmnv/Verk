<?php namespace ProcessWire;
/** @var Verk $this @var string $attachEntityType @var int $attachEntityId */
$existing = $this->files->listFor($attachEntityType, $attachEntityId);
$csrfN = $this->getCSRFName();
$csrfV = $this->getCSRFToken();
$uploadUrl = $this->page->url . '?view=file-upload';
$deleteUrl = $this->page->url . '?view=file-delete';
?>
<div class="vk-attachments" data-vk-attach
     data-type="<?= htmlspecialchars($attachEntityType) ?>"
     data-id="<?= (int)$attachEntityId ?>"
     data-upload="<?= htmlspecialchars($uploadUrl) ?>"
     data-delete="<?= htmlspecialchars($deleteUrl) ?>"
     data-csrf-name="<?= htmlspecialchars($csrfN) ?>"
     data-csrf-value="<?= htmlspecialchars($csrfV) ?>">
    <label class="uk-form-label"><?= __('Attachments') ?></label>
    <div class="vk-attach-drop" data-vk-attach-drop>
        <input type="file" multiple data-vk-attach-input hidden>
        <span><?= __('Drop files here or click to upload') ?></span>
    </div>
    <div class="vk-attach-grid" data-vk-attach-grid>
        <?php foreach ($existing as $f): ?>
        <?php require __DIR__ . '/attachment-item.php'; // renders one $f ?>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    const root = document.currentScript.previousElementSibling;
    if (!root || !root.matches('[data-vk-attach]')) return;
    const grid = root.querySelector('[data-vk-attach-grid]');
    const input = root.querySelector('[data-vk-attach-input]');
    const drop = root.querySelector('[data-vk-attach-drop]');
    const T = { del: <?= json_encode(__('Delete this file?')) ?>, fail: <?= json_encode(__('Upload failed')) ?> };

    function card(f) {
        const a = document.createElement('div');
        a.className = 'vk-attach-card';
        a.dataset.id = f.id;
        if (f.is_image) { a.dataset.vkFull = f.url; a.dataset.vkName = f.original_name; }
        const media = document.createElement('a');
        media.href = f.url;
        if (f.is_image) {
            media.dataset.vkOpen = '';
            const img = document.createElement('img');
            img.src = f.thumb;
            img.alt = '';
            media.appendChild(img);
        } else {
            media.target = '_blank';
            media.className = 'vk-attach-icon';
            const icon = document.createElement('i');
            icon.className = 'fa fa-file-o';
            media.appendChild(icon);
        }
        const meta = document.createElement('div');
        meta.className = 'vk-attach-meta';
        const name = document.createElement('a');
        name.href = f.url; name.className = 'vk-attach-name';
        name.textContent = f.original_name;
        if (f.is_image) { name.dataset.vkOpen = ''; } else { name.target = '_blank'; }
        const size = document.createElement('span');
        size.className = 'vk-attach-size'; size.textContent = f.human_size;
        const del = document.createElement('button');
        del.type = 'button'; del.className = 'vk-attach-del'; del.textContent = '×';
        del.addEventListener('click', () => removeFile(f.id, a));
        meta.append(name, size, del);
        a.appendChild(media);
        a.appendChild(meta);
        return a;
    }

    function upload(fileList) {
        [...fileList].forEach(file => {
            const fd = new FormData();
            fd.append('files', file);
            fd.append('entity_type', root.dataset.type);
            fd.append('entity_id', root.dataset.id);
            fd.append('embedded', '0');
            fd.append(root.dataset.csrfName, root.dataset.csrfValue);
            fetch(root.dataset.upload, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => { if (j.ok) j.files.forEach(f => grid.appendChild(card(f))); else alert(j.message || T.fail); })
                .catch(() => alert(T.fail));
        });
    }

    function removeFile(id, el) {
        if (!confirm(T.del)) return;
        const fd = new FormData();
        fd.append('id', id);
        fd.append(root.dataset.csrfName, root.dataset.csrfValue);
        fetch(root.dataset.delete, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => { if (j.ok) el.remove(); });
    }

    grid.querySelectorAll('[data-vk-del]').forEach(btn => {
        const cardEl = btn.closest('.vk-attach-card');
        btn.addEventListener('click', () => removeFile(cardEl.dataset.id, cardEl));
    });

    // Image lightbox with prev/next over this panel's image attachments.
    let lb = null, lbItems = [], lbIndex = 0;
    function buildLightbox() {
        const o = document.createElement('div');
        o.className = 'vk-lightbox';
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'vk-lightbox-close'; close.textContent = '×';
        const prev = document.createElement('button');
        prev.type = 'button'; prev.className = 'vk-lightbox-nav is-prev'; prev.textContent = '‹';
        const next = document.createElement('button');
        next.type = 'button'; next.className = 'vk-lightbox-nav is-next'; next.textContent = '›';
        const stage = document.createElement('div'); stage.className = 'vk-lightbox-stage';
        const img = document.createElement('img'); img.className = 'vk-lightbox-img'; img.alt = '';
        const cap = document.createElement('div'); cap.className = 'vk-lightbox-cap';
        stage.append(img, cap);
        o.append(close, prev, next, stage);
        document.body.appendChild(o);
        close.addEventListener('click', closeLightbox);
        prev.addEventListener('click', (e) => { e.stopPropagation(); stepLightbox(-1); });
        next.addEventListener('click', (e) => { e.stopPropagation(); stepLightbox(1); });
        o.addEventListener('click', (e) => { if (e.target === o) closeLightbox(); });
        lb = { o, img, cap, prev, next };
    }
    function renderLightbox() {
        const c = lbItems[lbIndex];
        if (!c) return;
        lb.img.src = c.dataset.vkFull;
        lb.cap.textContent = c.dataset.vkName || '';
        const multi = lbItems.length > 1;
        lb.prev.hidden = !multi;
        lb.next.hidden = !multi;
    }
    function openLightbox(cardEl) {
        lbItems = [...grid.querySelectorAll('.vk-attach-card[data-vk-full]')];
        lbIndex = lbItems.indexOf(cardEl);
        if (lbIndex < 0) return;
        if (!lb) buildLightbox();
        renderLightbox();
        lb.o.classList.add('is-open');
        document.addEventListener('keydown', onLightboxKey);
    }
    function stepLightbox(d) {
        if (!lbItems.length) return;
        lbIndex = (lbIndex + d + lbItems.length) % lbItems.length;
        renderLightbox();
    }
    function closeLightbox() {
        if (lb) lb.o.classList.remove('is-open');
        document.removeEventListener('keydown', onLightboxKey);
    }
    function onLightboxKey(e) {
        if (e.key === 'Escape') closeLightbox();
        else if (e.key === 'ArrowLeft') stepLightbox(-1);
        else if (e.key === 'ArrowRight') stepLightbox(1);
    }
    grid.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-vk-open]');
        if (!opener || !grid.contains(opener)) return;
        e.preventDefault();
        openLightbox(opener.closest('.vk-attach-card'));
    });

    drop.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { upload(input.files); input.value = ''; });
    ['dragover', 'dragenter'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', ev => upload(ev.dataTransfer.files));
})();
</script>
