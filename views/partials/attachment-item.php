<?php namespace ProcessWire; /** @var array $f */ ?>
<div class="vk-attach-card" data-id="<?= (int)$f['id'] ?>"<?php if ($f['is_image']): ?> data-vk-full="<?= htmlspecialchars($f['url']) ?>" data-vk-name="<?= htmlspecialchars($f['original_name']) ?>"<?php endif; ?>>
    <?php if ($f['is_image']): ?>
    <a href="<?= htmlspecialchars($f['url']) ?>" data-vk-open><img src="<?= htmlspecialchars($f['thumb']) ?>" alt=""></a>
    <?php else: ?>
    <a href="<?= htmlspecialchars($f['url']) ?>" class="vk-attach-icon" target="_blank"><i class="fa fa-file-o"></i></a>
    <?php endif; ?>
    <div class="vk-attach-meta">
        <?php if ($f['is_image']): ?>
        <a href="<?= htmlspecialchars($f['url']) ?>" data-vk-open class="vk-attach-name"><?= htmlspecialchars($f['original_name']) ?></a>
        <?php else: ?>
        <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" class="vk-attach-name"><?= htmlspecialchars($f['original_name']) ?></a>
        <?php endif; ?>
        <span class="vk-attach-size"><?= htmlspecialchars($f['human_size']) ?></span>
        <button type="button" class="vk-attach-del" data-vk-del>×</button>
    </div>
</div>
