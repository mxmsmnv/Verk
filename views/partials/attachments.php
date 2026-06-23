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
        const thumb = f.is_image
            ? '<a href="' + f.url + '" target="_blank"><img src="' + f.thumb + '" alt=""></a>'
            : '<a href="' + f.url + '" class="vk-attach-icon"><i class="fa fa-file-o"></i></a>';
        const meta = document.createElement('div');
        meta.className = 'vk-attach-meta';
        const name = document.createElement('a');
        name.href = f.url; name.target = '_blank'; name.className = 'vk-attach-name';
        name.textContent = f.original_name;
        const size = document.createElement('span');
        size.className = 'vk-attach-size'; size.textContent = f.human_size;
        const del = document.createElement('button');
        del.type = 'button'; del.className = 'vk-attach-del'; del.textContent = '×';
        del.addEventListener('click', () => removeFile(f.id, a));
        meta.append(name, size, del);
        a.innerHTML = thumb;
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

    drop.addEventListener('click', () => input.click());
    input.addEventListener('change', () => { upload(input.files); input.value = ''; });
    ['dragover', 'dragenter'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', ev => upload(ev.dataTransfer.files));
})();
</script>
