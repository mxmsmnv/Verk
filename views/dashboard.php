<?php namespace ProcessWire;
/**
 * @var Verk $this
 * @var array $myTasks
 * @var array $recentTasks
 * @var array $upcoming
 * @var array $auditSummary
 * @var array $taskStats
 * @var array $dashboardSprints
 */
$url   = $this->page->url;
$today = date('Y-m-d');

$open  = ($taskStats['open'] ?? 0) + ($taskStats['in_progress'] ?? 0) + ($taskStats['review'] ?? 0);
$done  = $taskStats['done'] ?? 0;
$dashboardQuarter = $dashboardQuarter ?? $this->quarterContextForDate(date('Y-m-d'));
$quarterLabel = $this->quarterLabel($dashboardQuarter);
$quarterTaskStats = $quarterTaskStats ?? ['total' => 0, 'done_count' => 0, 'open_count' => 0];
$quarterProgress = $quarterProgress ?? 0;
$overdueTaskCount = $overdueTaskCount ?? 0;
$unassignedTaskCount = $unassignedTaskCount ?? 0;
$dashboardSprintCount = $dashboardSprintCount ?? count($dashboardSprints ?? []);
$quarterBaseUrl = $url . '?view=dashboard';
$buildDashboardUrl = function(array $changes = []) use ($url, $dashboardQuarter, $myPage, $recentPage) {
    $params = [
        'view' => 'dashboard',
        'quarter' => (int)$dashboardQuarter['quarter'],
        'year' => (int)$dashboardQuarter['year'],
    ];
    if ($myPage > 1) $params['my_page'] = (int)$myPage;
    if ($recentPage > 1) $params['recent_page'] = (int)$recentPage;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || $value === 0 || $value === 1) unset($params[$key]);
        else $params[$key] = $value;
    }
    return $url . '?' . http_build_query($params);
};
$dashboardReturnUrl = $buildDashboardUrl([]);
$dashboardReturnParam = rawurlencode($dashboardReturnUrl);

ob_start();
?>

<div class="vk-dashboard-head vk-dashboard-toolbar">
    <div>
        <h2 class="vk-page-title"><?= __('Dashboard') ?></h2>
        <p><?= __('Tasks, upcoming publications, and audit signals for this ProcessWire site.') ?></p>
    </div>
    <div class="vk-actions">
        <div class="vk-quarter-nav">
            <?php for ($q = 1; $q <= 4; $q++): ?>
            <a class="<?= (int)$dashboardQuarter['quarter'] === $q ? 'is-active' : '' ?>" href="<?= $quarterBaseUrl ?>&quarter=<?= $q ?>&year=<?= (int)$dashboardQuarter['year'] ?>">Q<?= $q ?></a>
            <?php endfor; ?>
            <form method="get" action="<?= $url ?>" class="vk-quarter-year">
                <input type="hidden" name="view" value="dashboard">
                <input type="hidden" name="quarter" value="<?= (int)$dashboardQuarter['quarter'] ?>">
                <input class="uk-input" type="number" name="year" min="2000" max="2100" value="<?= (int)$dashboardQuarter['year'] ?>" aria-label="<?= __('Year') ?>">
            </form>
        </div>
        <a href="<?= $url ?>?view=task-edit&return_url=<?= $dashboardReturnParam ?>" class="uk-button uk-button-primary">
            <i class="fa fa-plus"></i> <?= __('New Task') ?>
        </a>
    </div>
</div>

<section class="vk-dashboard-focus">
    <div class="vk-dashboard-focus-main">
        <div>
            <span class="vk-section-label"><?= __('Quarter focus') ?></span>
            <h3><?= htmlspecialchars($quarterLabel) ?></h3>
            <p><?= sprintf(__('%1$d of %2$d due tasks completed'), (int)$quarterTaskStats['done_count'], (int)$quarterTaskStats['total']) ?></p>
        </div>
        <div class="vk-dashboard-focus-progress">
            <span><?= (int)$quarterProgress ?>%</span>
            <progress class="uk-progress vk-sprint-progress" value="<?= (int)$quarterProgress ?>" max="100"></progress>
        </div>
    </div>
    <a class="vk-dashboard-focus-card <?= $overdueTaskCount > 0 ? 'is-danger' : '' ?>" href="<?= $url ?>?view=tasks&sort=due">
        <span><?= __('Overdue') ?></span>
        <strong><?= (int)$overdueTaskCount ?></strong>
        <small><?= __('Open tasks past due date') ?></small>
    </a>
    <a class="vk-dashboard-focus-card" href="<?= $url ?>?view=sprints&quarter=<?= (int)$dashboardQuarter['quarter'] ?>&year=<?= (int)$dashboardQuarter['year'] ?>">
        <span><?= __('Sprint plan') ?></span>
        <strong><?= (int)$dashboardSprintCount ?></strong>
        <small><?= __('Active and planned in quarter') ?></small>
    </a>
</section>

<div class="vk-dashboard-stats">
    <div><a href="<?= $url ?>?view=tasks&status=open" class="vk-stat vk-stat-link uk-card uk-card-default uk-card-small"><div class="vk-stat-l"><?= __('Open') ?></div><div class="vk-stat-n"><?= $open ?></div><span class="vk-stat-note"><?= __('Needs attention') ?></span></a></div>
    <div><a href="<?= $url ?>?view=tasks&status=done" class="vk-stat vk-stat-link uk-card uk-card-default uk-card-small"><div class="vk-stat-l"><?= __('Done') ?></div><div class="vk-stat-n"><?= $done ?></div><span class="vk-stat-note"><?= __('Completed tasks') ?></span></a></div>
    <div><a href="<?= $url ?>?view=calendar" class="vk-stat vk-stat-link uk-card uk-card-default uk-card-small"><div class="vk-stat-l"><?= __('Upcoming') ?></div><div class="vk-stat-n"><?= (int)($upcomingTotal ?? count($upcoming)) ?></div><span class="vk-stat-note"><?= __('Next 14 days') ?></span></a></div>
    <div><a href="<?= $url ?>?view=tasks&quarter=<?= (int)$dashboardQuarter['quarter'] ?>&year=<?= (int)$dashboardQuarter['year'] ?>&sort=due" class="vk-stat vk-stat-link uk-card uk-card-default uk-card-small"><div class="vk-stat-l"><?= htmlspecialchars($quarterLabel) ?></div><div class="vk-stat-n"><?= (int)$quarterTaskStats['open_count'] ?></div><span class="vk-stat-note"><?= __('Open due this quarter') ?></span></a></div>
    <?php foreach ($auditSummary as $a): ?>
    <div><a href="<?= $url ?>?view=audit&rule=<?= (int)$a['index'] ?>" class="vk-stat vk-stat-link uk-card uk-card-default uk-card-small">
        <div class="vk-stat-l" title="<?= htmlspecialchars($a['label']) ?>"><?= __('Audit') ?></div>
        <div class="vk-stat-n <?= $a['count'] > 0 ? 'is-warning' : 'is-success' ?>"><?= $a['count'] ?></div>
        <span class="vk-stat-note"><?= htmlspecialchars(mb_strimwidth($a['label'], 0, 28, '...')) ?></span>
    </a></div>
    <?php endforeach; ?>
</div>

<div class="vk-dashboard-queue">
    <a href="<?= $url ?>?view=tasks&status=open" class="vk-dashboard-queue-item">
        <span><?= __('Open queue') ?></span>
        <strong><?= (int)($taskStats['open'] ?? 0) ?></strong>
    </a>
    <a href="<?= $url ?>?view=tasks&status=in_progress" class="vk-dashboard-queue-item">
        <span><?= __('In progress') ?></span>
        <strong><?= (int)($taskStats['in_progress'] ?? 0) ?></strong>
    </a>
    <a href="<?= $url ?>?view=tasks&status=review" class="vk-dashboard-queue-item">
        <span><?= __('Review') ?></span>
        <strong><?= (int)($taskStats['review'] ?? 0) ?></strong>
    </a>
    <a href="<?= $url ?>?view=tasks&assignee_id=-1" class="vk-dashboard-queue-item <?= $unassignedTaskCount > 0 ? 'is-warning' : '' ?>">
        <span><?= __('Unassigned') ?></span>
        <strong><?= (int)$unassignedTaskCount ?></strong>
    </a>
    <a href="<?= $url ?>?view=tasks&quarter=<?= (int)$dashboardQuarter['quarter'] ?>&year=<?= (int)$dashboardQuarter['year'] ?>&sort=due" class="vk-dashboard-queue-item">
        <span><?= __('Due this quarter') ?></span>
        <strong><?= (int)$quarterTaskStats['open_count'] ?></strong>
    </a>
    <a href="<?= $url ?>?view=tasks&sort=due" class="vk-dashboard-queue-item <?= $overdueTaskCount > 0 ? 'is-danger' : '' ?>">
        <span><?= __('Overdue') ?></span>
        <strong><?= (int)$overdueTaskCount ?></strong>
    </a>
</div>

<div class="vk-dashboard-grid">

    <div class="vk-dashboard-side">
        <div class="uk-card uk-card-default vk-dashboard-card">
            <div class="uk-card-header vk-card-header-row">
                <h3 class="vk-card-title"><?= __('My Tasks') ?></h3>
                <a href="<?= $url ?>?view=tasks&assignee_id=<?= (int)$uid ?>" class="vk-card-action"><?= __('All mine') ?> <i class="fa fa-arrow-right"></i></a>
            </div>
            <?php if ($myTasks): ?>
            <div class="vk-mini-list">
                <?php foreach ($myTasks as $t): ?>
                <article class="vk-mini-row">
                    <div class="vk-mini-main">
                        <a href="<?= $url ?>?view=task-edit&id=<?= (int)$t['id'] ?>&return_url=<?= $dashboardReturnParam ?>" class="vk-mini-title"><?= htmlspecialchars($t['title']) ?></a>
                        <div class="vk-mini-meta">
                            <span><?= __('TASK') ?>-<?= (int)$t['id'] ?></span>
                            <?php if ($t['due_date']): ?><span class="<?= $t['due_date'] < $today ? 'is-overdue' : '' ?>"><?= htmlspecialchars($t['due_date']) ?></span><?php endif; ?>
                            <?php if ($t['due_date']): ?><span class="vk-quarter-inline"><?= htmlspecialchars($this->quarterLabelForDate($t['due_date'])) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($t['linked_page_title'])): ?>
                        <a href="<?= $t['linked_page_edit'] ?>" class="vk-chip" target="_blank">
                            <i class="fa fa-pencil-square-o"></i> <?= htmlspecialchars((string)$t['linked_page_title']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="vk-mini-side">
                        <span class="uk-label vk-label-<?= $t['priority'] ?>"><?= htmlspecialchars($this->priorityLabel($t['priority'])) ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ($myTotalPages > 1): ?>
            <div class="vk-card-pager">
                <span class="vk-card-pager-meta"><?= sprintf(__('%d tasks'), $myTaskTotal) ?></span>
                <span class="vk-card-pager-links">
                    <?php if ($myPage > 1): ?><a href="<?= $buildDashboardUrl(['my_page' => $myPage - 1]) ?>" class="vk-pager-link"><i class="fa fa-chevron-left"></i> <?= __('Prev') ?></a><?php endif; ?>
                    <span class="vk-card-pager-meta"><?= sprintf(__('Page %1$d/%2$d'), $myPage, $myTotalPages) ?></span>
                    <?php if ($myPage < $myTotalPages): ?><a href="<?= $buildDashboardUrl(['my_page' => $myPage + 1]) ?>" class="vk-pager-link"><?= __('Next') ?> <i class="fa fa-chevron-right"></i></a><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="uk-card-body vk-dashboard-empty">
                <i class="fa fa-check-circle-o"></i>
                <span><?= __('All clear') ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($auditSummary): ?>
        <div class="uk-card uk-card-default vk-card-stack vk-dashboard-card">
            <div class="uk-card-header vk-card-header-row">
                <h3 class="vk-card-title"><?= __('Content Audit') ?></h3>
                <a href="<?= $url ?>?view=audit" class="vk-card-action"><?= __('Run audit') ?> <i class="fa fa-arrow-right"></i></a>
            </div>
            <div class="uk-card-body vk-card-body-flush">
                <?php foreach ($auditSummary as $a): ?>
                <div class="vk-audit-item">
                    <span class="vk-audit-item-label"><?= htmlspecialchars($a['label']) ?></span>
                    <a href="<?= $url ?>?view=audit&rule=<?= $a['index'] ?>" class="vk-audit-count <?= $a['count'] > 0 ? 'is-warning' : 'is-success' ?>">
                        <?= sprintf(__('%d pages'), $a['count']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="vk-dashboard-side">
        <div class="uk-card uk-card-default vk-dashboard-card">
            <div class="uk-card-header vk-card-header-row">
                <h3 class="vk-card-title"><?= __('Upcoming (14 days)') ?></h3>
                <a href="<?= $url ?>?view=calendar" class="vk-card-action"><?= __('Calendar') ?> <i class="fa fa-arrow-right"></i></a>
            </div>
            <?php if ($upcoming): ?>
            <div class="vk-mini-list">
                <?php foreach ($upcoming as $item): ?>
                <article class="vk-mini-row">
                    <div class="vk-mini-main">
                        <a href="<?= $item['edit'] ?>" target="_blank" class="vk-mini-title"><?= htmlspecialchars($item['title']) ?></a>
                        <div class="vk-mini-meta"><span><?= htmlspecialchars($item['date']) ?></span></div>
                    </div>
                    <div class="vk-mini-side">
                        <a href="<?= $item['url'] ?>" target="_blank" class="vk-icon-button" title="<?= __('Open page') ?>" aria-label="<?= __('Open page') ?>"><i class="fa fa-external-link"></i></a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="uk-card-body vk-dashboard-empty">
                <?php $cfg = $this->getConfig(); if (!$cfg['calendar_template']): ?>
                <a href="<?= $url ?>?view=settings" class="vk-card-action"><?= __('Configure calendar template') ?> <i class="fa fa-arrow-right"></i></a>
                <?php else: ?>
                <span class="vk-muted-line"><?= __('No upcoming publications.') ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="uk-card uk-card-default vk-card-stack vk-dashboard-card vk-dashboard-card-recent">
            <div class="uk-card-header vk-card-header-row">
                <h3 class="vk-card-title"><?= __('Recent Open Tasks') ?></h3>
                <a href="<?= $url ?>?view=tasks&sort=created" class="vk-card-action"><?= __('All open') ?> <i class="fa fa-arrow-right"></i></a>
            </div>
            <?php if ($recentTasks): ?>
            <div class="vk-mini-list">
                <?php foreach ($recentTasks as $t): ?>
                <article class="vk-mini-row">
                    <div class="vk-mini-main">
                        <a href="<?= $url ?>?view=task-edit&id=<?= (int)$t['id'] ?>&return_url=<?= $dashboardReturnParam ?>" class="vk-mini-title"><?= htmlspecialchars($t['title']) ?></a>
                        <div class="vk-mini-meta">
                            <span><?= __('TASK') ?>-<?= (int)$t['id'] ?></span>
                            <?php if (!empty($t['linked_page_title'])): ?><span><?= htmlspecialchars((string)$t['linked_page_title']) ?></span><?php endif; ?>
                            <?php if ($t['due_date']): ?><span class="vk-quarter-inline"><?= htmlspecialchars($this->quarterLabelForDate($t['due_date'])) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($t['linked_page_title'])): ?>
                        <a href="<?= $t['linked_page_edit'] ?>" class="vk-chip" target="_blank">
                            <i class="fa fa-pencil-square-o"></i> <?= htmlspecialchars(mb_strimwidth((string)$t['linked_page_title'], 0, 34, '...')) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="vk-mini-side">
                        <span class="uk-label vk-label-<?= $t['status'] ?>"><?= htmlspecialchars($this->statusLabel($t['status'])) ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ($recentTotalPages > 1): ?>
            <div class="vk-card-pager">
                <span class="vk-card-pager-meta"><?= sprintf(__('%d tasks'), $recentTotal) ?></span>
                <span class="vk-card-pager-links">
                    <?php if ($recentPage > 1): ?><a href="<?= $buildDashboardUrl(['recent_page' => $recentPage - 1]) ?>" class="vk-pager-link"><i class="fa fa-chevron-left"></i> <?= __('Prev') ?></a><?php endif; ?>
                    <span class="vk-card-pager-meta"><?= sprintf(__('Page %1$d/%2$d'), $recentPage, $recentTotalPages) ?></span>
                    <?php if ($recentPage < $recentTotalPages): ?><a href="<?= $buildDashboardUrl(['recent_page' => $recentPage + 1]) ?>" class="vk-pager-link"><?= __('Next') ?> <i class="fa fa-chevron-right"></i></a><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="uk-card-body vk-dashboard-empty">
                <span><?= __('No open tasks.') ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="uk-card uk-card-default vk-card-stack vk-dashboard-card vk-dashboard-card-sprints">
            <div class="uk-card-header vk-card-header-row">
                <h3 class="vk-card-title"><?= __('Sprints') ?> · <?= htmlspecialchars($quarterLabel) ?></h3>
                <a href="<?= $url ?>?view=sprints&quarter=<?= (int)$dashboardQuarter['quarter'] ?>&year=<?= (int)$dashboardQuarter['year'] ?>" class="vk-card-action"><?= __('All sprints') ?> <i class="fa fa-arrow-right"></i></a>
            </div>
            <?php if ($dashboardSprints): ?>
            <div class="vk-sprint-summary">
                <?php foreach ($dashboardSprints as $s):
                    $total = (int)$s['task_count'];
                    $done = (int)$s['done_count'];
                    $pct = $total ? (int)round($done / $total * 100) : 0;
                ?>
                <a class="vk-sprint-row" href="<?= $url ?>?view=sprint-edit&id=<?= (int)$s['id'] ?>&return_url=<?= $dashboardReturnParam ?>">
                    <div class="vk-sprint-row-head">
                        <span><?= htmlspecialchars($s['name']) ?></span>
                        <span class="uk-label vk-label-<?= $s['status'] === 'active' ? 'done' : 'open' ?>"><?= htmlspecialchars($this->sprintStatusLabel($s['status'])) ?></span>
                    </div>
                    <div class="vk-sprint-row-meta">
                        <span><?= sprintf(__('%1$d/%2$d tasks'), $done, $total) ?></span>
                        <span><?= $s['end_date'] ?: __('No end date') ?></span>
                    </div>
                    <progress class="uk-progress vk-sprint-progress" value="<?= $pct ?>" max="100"></progress>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="uk-card-body vk-dashboard-empty">
                <a href="<?= $url ?>?view=sprint-edit&return_url=<?= $dashboardReturnParam ?>"><i class="fa fa-plus"></i> <?= __('Create a sprint') ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
