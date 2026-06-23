<?php namespace ProcessWire; /** @var array $f */ ?>
<div class="vk-attach-card" data-id="<?= (int)$f['id'] ?>">
    <?php if ($f['is_image']): ?>
    <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank"><img src="<?= htmlspecialchars($f['thumb']) ?>" alt=""></a>
    <?php else: ?>
    <a href="<?= htmlspecialchars($f['url']) ?>" class="vk-attach-icon"><i class="fa fa-file-o"></i></a>
    <?php endif; ?>
    <div class="vk-attach-meta">
        <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" class="vk-attach-name"><?= htmlspecialchars($f['original_name']) ?></a>
        <span class="vk-attach-size"><?= htmlspecialchars($f['human_size']) ?></span>
        <button type="button" class="vk-attach-del" data-vk-del>×</button>
    </div>
</div>
