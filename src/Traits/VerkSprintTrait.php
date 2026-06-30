<?php namespace ProcessWire;

trait VerkSprintTrait {

    protected function viewSprints(): string {
        $db     = $this->wire('database');
        $input  = $this->wire('input');
        $quarter = (int)$input->get('quarter');
        $sprintSearch = trim(substr($input->get('q', 'string') ?: '', 0, 120));
        $sprintStatus = $input->get('status', 'string');
        if (!in_array($sprintStatus, ['planned', 'active', 'completed'], true)) $sprintStatus = '';
        $sprintHealth = $input->get('health', 'string');
        if (!in_array($sprintHealth, ['attention'], true)) $sprintHealth = '';
        $sprintDateState = $input->get('date_state', 'string');
        if (!in_array($sprintDateState, ['none'], true)) $sprintDateState = '';
        $sprintQuarterYear = (int)$input->get('year');
        if (!$sprintQuarterYear) $sprintQuarterYear = (int)$this->quarterContextForDate(date('Y-m-d'))['year'];
        if ($sprintDateState === 'none') $quarter = 0;
        $sprintQuarter = $quarter ? $this->quarterContext($quarter, $sprintQuarterYear) : null;
        $sprintCountWhere = [];
        $sprintCountParams = [];
        if ($sprintStatus) {
            $sprintCountWhere[] = "s.status = :count_status";
            $sprintCountParams[':count_status'] = $sprintStatus;
        }
        if ($sprintSearch !== '') {
            $sprintCountWhere[] = "(s.name LIKE :count_search OR s.goal LIKE :count_search)";
            $sprintCountParams[':count_search'] = '%' . $sprintSearch . '%';
        }
        $sprintCountSql = $sprintCountWhere ? ' AND ' . implode(' AND ', $sprintCountWhere) : '';
        $sprintQuarterCounts = [];
        for ($q = 1; $q <= 4; $q++) {
            $ctx = $this->quarterContext($q, $sprintQuarterYear);
            $quarterStmt = $db->prepare(
                "SELECT COUNT(*)
                 FROM vk_sprints s
                 WHERE s.start_date IS NOT NULL
                   AND s.start_date <= :quarter_end
                   AND (s.end_date IS NULL OR s.end_date >= :quarter_start)
                   $sprintCountSql"
            );
            $quarterStmt->execute($sprintCountParams + [
                ':quarter_start' => $ctx['start'],
                ':quarter_end' => $ctx['end'],
            ]);
            $sprintQuarterCounts[$q] = [
                'context' => $ctx,
                'count' => (int)$quarterStmt->fetchColumn(),
            ];
        }

        $noDateStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM vk_sprints s
             WHERE (s.start_date IS NULL OR s.end_date IS NULL)
               $sprintCountSql"
        );
        $noDateStmt->execute($sprintCountParams);
        $sprintNoDateCount = (int)$noDateStmt->fetchColumn();
        $sprintBacklogCount = (int)$db
            ->query("SELECT COUNT(*) FROM vk_tasks t WHERE t.sprint_id IS NULL OR t.sprint_id = 0")
            ->fetchColumn();
        $baseWhereParts = [];
        $baseParams = [];
        if ($sprintQuarter) {
            $baseWhereParts[] = "s.start_date IS NOT NULL AND s.start_date <= :quarter_end AND (s.end_date IS NULL OR s.end_date >= :quarter_start)";
            $baseParams[':quarter_start'] = $sprintQuarter['start'];
            $baseParams[':quarter_end'] = $sprintQuarter['end'];
        }
        if ($sprintDateState === 'none') {
            $baseWhereParts[] = "(s.start_date IS NULL OR s.end_date IS NULL)";
        }
        if ($sprintSearch !== '') {
            $baseWhereParts[] = "(s.name LIKE :sprint_search OR s.goal LIKE :sprint_search)";
            $baseParams[':sprint_search'] = '%' . $sprintSearch . '%';
        }

        $statusSummaryWhere = $baseWhereParts ? 'WHERE ' . implode(' AND ', $baseWhereParts) : '';
        $statusSummaryStmt = $db->prepare(
            "SELECT s.status, COUNT(*) AS n
             FROM vk_sprints s
             $statusSummaryWhere
             GROUP BY s.status"
        );
        $statusSummaryStmt->execute($baseParams);
        $sprintStatusCounts = ['active' => 0, 'planned' => 0, 'completed' => 0];
        foreach ($statusSummaryStmt->fetchAll(\PDO::FETCH_ASSOC) as $statusRow) {
            $statusKey = (string)($statusRow['status'] ?? '');
            if (isset($sprintStatusCounts[$statusKey])) $sprintStatusCounts[$statusKey] = (int)$statusRow['n'];
        }

        $whereParts = $baseWhereParts;
        $params = $baseParams;
        if ($sprintStatus) {
            $whereParts[] = "s.status = :sprint_status";
            $params[':sprint_status'] = $sprintStatus;
        }
        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $stmt = $db->prepare(
            "SELECT s.*,
                    COUNT(t.id) as task_count,
                    SUM(CASE WHEN t.status='done' THEN 1 ELSE 0 END) as done_count,
                    COALESCE(SUM(t.story_points), 0) as total_sp,
                    COALESCE(SUM(t.estimate_h), 0) as total_est,
                    COALESCE(SUM(t.actual_h), 0) as total_actual
             FROM vk_sprints s
             LEFT JOIN vk_tasks t ON t.sprint_id = s.id
             $where
             GROUP BY s.id
             ORDER BY FIELD(s.status,'active','planned','completed'), s.start_date DESC"
        );
        $stmt->execute($params);
        $sprints = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $today = date('Y-m-d');
        $sprintHealthMap = [];
        foreach ($sprints as $sprintRow) {
            $sid = (int)$sprintRow['id'];
            $hasMissingDates = empty($sprintRow['start_date']) || empty($sprintRow['end_date']);
            $hasMissingGoal = trim(strip_tags((string)($sprintRow['goal'] ?? ''))) === '';
            $hasNoTasks = (int)($sprintRow['task_count'] ?? 0) === 0;
            $isOverdue = !empty($sprintRow['end_date']) && (string)$sprintRow['end_date'] < $today && (string)$sprintRow['status'] !== 'completed';
            $sprintHealthMap[$sid] = [
                'needs_attention' => $hasMissingDates || $hasMissingGoal || $hasNoTasks || $isOverdue,
                'missing_dates' => $hasMissingDates,
                'missing_goal' => $hasMissingGoal,
                'no_tasks' => $hasNoTasks,
                'overdue' => $isOverdue,
            ];
        }
        if ($sprintHealth === 'attention') {
            $sprints = array_values(array_filter($sprints, function(array $sprintRow) use ($sprintHealthMap): bool {
                return !empty($sprintHealthMap[(int)$sprintRow['id']]['needs_attention']);
            }));
        }
        $sprintTaskPreview = [];
        $sprintIds = array_values(array_filter(array_map('intval', array_column($sprints, 'id'))));
        if ($sprintIds) {
            $placeholders = implode(',', array_fill(0, count($sprintIds), '?'));
            $taskPreviewStmt = $db->prepare(
                "SELECT id, sprint_id, title, status, priority, due_date, page_id
                 FROM vk_tasks
                 WHERE sprint_id IN ($placeholders)
                 ORDER BY sprint_id ASC,
                          FIELD(status,'open','in_progress','review','done'),
                          FIELD(priority,'critical','high','medium','low'),
                          due_date IS NULL,
                          due_date ASC,
                          id DESC"
            );
            $taskPreviewStmt->execute($sprintIds);
            $taskPreviewRows = $this->enrichTaskPagesBatch($taskPreviewStmt->fetchAll(\PDO::FETCH_ASSOC));
            foreach ($taskPreviewRows as $taskPreview) {
                $sid = (int)$taskPreview['sprint_id'];
                if (!isset($sprintTaskPreview[$sid])) $sprintTaskPreview[$sid] = [];
                if (count($sprintTaskPreview[$sid]) < 4) $sprintTaskPreview[$sid][] = $taskPreview;
            }
        }
        $sprintStats = [
            'active' => 0,
            'planned' => 0,
            'completed' => 0,
            'tasks' => 0,
            'done_tasks' => 0,
            'story_points' => 0,
            'estimate_h' => 0,
            'actual_h' => 0,
            'needs_attention' => 0,
        ];
        foreach ($sprints as $sprintRow) {
            $statusKey = (string)($sprintRow['status'] ?? '');
            if (isset($sprintStats[$statusKey])) $sprintStats[$statusKey]++;
            $sprintStats['tasks'] += (int)($sprintRow['task_count'] ?? 0);
            $sprintStats['done_tasks'] += (int)($sprintRow['done_count'] ?? 0);
            $sprintStats['story_points'] += (int)($sprintRow['total_sp'] ?? 0);
            $sprintStats['estimate_h'] += (float)($sprintRow['total_est'] ?? 0);
            $sprintStats['actual_h'] += (float)($sprintRow['total_actual'] ?? 0);
            if (!empty($sprintHealthMap[(int)$sprintRow['id']]['needs_attention'])) $sprintStats['needs_attention']++;
        }
        ob_start(); require dirname(__DIR__, 2) . '/views/sprints.php'; return ob_get_clean();
    }

    protected function viewSprintEdit(): string {
        $id     = (int)$this->wire('input')->get('id');
        $sprint = $id ? $this->getSprint($id) : null;
        if ($id && !$sprint) {
            $this->redirectMissingRecord($this->_('Sprint does not exist.'), 'sprints', 'sprint-edit');
        }

        $tasks = $id ? $this->getSprintTasks($id) : [];

        ob_start(); require dirname(__DIR__, 2) . '/views/sprint-form.php'; return ob_get_clean();
    }

    protected function getSprint(int $id): ?array {
        $stmt = $this->wire('database')->prepare("SELECT * FROM vk_sprints WHERE id=:id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getAllSprints(): array {
        $stmt = $this->wire('database')->query(
            "SELECT id, name, status, start_date, end_date FROM vk_sprints
             ORDER BY FIELD(status,'active','planned','completed'), start_date DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function getSprintTasks(int $sprintId): array {
        $stmt = $this->wire('database')->prepare(
            "SELECT t.* FROM vk_tasks t
             WHERE t.sprint_id = :sid
             ORDER BY FIELD(t.status,'open','in_progress','review','done'),
                      FIELD(t.priority,'critical','high','medium','low')"
        );
        $stmt->execute([':sid' => $sprintId]);
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $userMap = $this->getUserMap(array_column($tasks, 'assignee_id'));
        $returnUrl = $this->page->url . '?view=sprint-edit&id=' . $sprintId . '#vk-sprint-tasks';
        foreach ($tasks as &$t) {
            $t['assignee_name'] = $userMap[(int)$t['assignee_id']] ?? null;
            $t['status_label'] = $this->statusLabel((string)$t['status']);
            $t['priority_label'] = $this->priorityLabel((string)$t['priority']);
            $t['due_label'] = !empty($t['due_date']) ? date('M j', strtotime((string)$t['due_date'])) : $this->_('No due date');
            $t['quarter_label'] = !empty($t['due_date']) ? $this->quarterLabelForDate((string)$t['due_date']) : '';
            $t['edit_url'] = $this->page->url . '?view=task-edit&id=' . (int)$t['id'] . '&return_url=' . rawurlencode($returnUrl);
        }
        unset($t);
        return $tasks;
    }

    protected function sprintTaskPayload(int $sprintId): array {
        $tasks = $this->getSprintTasks($sprintId);
        $sprint = $this->getSprint($sprintId);
        $totalSP = (int)array_sum(array_column($tasks, 'story_points'));
        $totalEst = (float)array_sum(array_column($tasks, 'estimate_h'));
        $totalActual = (float)array_sum(array_column($tasks, 'actual_h'));
        $dueInWindow = 0;
        $dueMissing = 0;
        $dueOutside = 0;
        $hasWindow = $sprint && !empty($sprint['start_date']) && !empty($sprint['end_date']);
        foreach ($tasks as $task) {
            $due = (string)($task['due_date'] ?? '');
            if ($due === '') {
                $dueMissing++;
            } elseif ($hasWindow && $due >= $sprint['start_date'] && $due <= $sprint['end_date']) {
                $dueInWindow++;
            } elseif ($hasWindow) {
                $dueOutside++;
            }
        }
        return [
            'tasks' => $tasks,
            'summary' => [
                'tasks' => count($tasks),
                'done' => count(array_filter($tasks, fn($t) => $t['status'] === 'done')),
                'story_points' => $totalSP,
                'estimated' => $totalEst,
                'actual' => $totalActual,
                'due_in_window' => $dueInWindow,
                'due_missing' => $dueMissing,
                'due_outside' => $dueOutside,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Sprint actions
    // -------------------------------------------------------------------------

    protected function actionSaveSprint(): string {
        $this->requireCSRF();
        $input = $this->wire('input');
        $db    = $this->wire('database');
        $user  = $this->wire('user');
        $id    = (int)$input->post('id');
        $this->requireOwnerForExisting('vk_sprints', $id);

        $name = substr($this->san($input->post('name')), 0, 255);
        if (!$name) {
            $this->error($this->_('Sprint name is required.'));
            $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));
            $editUrl = $this->page->url . '?view=sprint-edit' . ($id ? '&id=' . $id : '');
            if ($returnUrl) $editUrl .= '&return_url=' . rawurlencode($returnUrl);
            $this->wire('session')->redirect($editUrl);
        }

        $startDate = $this->sanDate($input->post('start_date'));
        $endDate   = $this->sanDate($input->post('end_date'));
        if ($startDate && $endDate && $endDate < $startDate) {
            $this->error($this->_('Sprint end date must be after the start date.'));
            $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));
            $editUrl = $this->page->url . '?view=sprint-edit' . ($id ? '&id=' . $id : '');
            if ($returnUrl) {
                $editUrl .= '&return_url=' . rawurlencode($returnUrl);
            }
            $this->wire('session')->redirect($editUrl . '#vk-sprint-schedule');
        }

        $data = [
            ':name'       => $name,
            ':status'     => $this->sanEnum($input->post('status'), ['planned','active','completed']),
            ':start_date' => $startDate,
            ':end_date'   => $endDate,
            ':goal'       => $this->sanRichText($input->post('goal', 'string')),
        ];

        if ($id) {
            $stmt = $db->prepare("UPDATE vk_sprints SET name=:name, status=:status, start_date=:start_date, end_date=:end_date, goal=:goal WHERE id=:id");
            $data[':id'] = $id;
            $stmt->execute($data);
            $this->message($this->_('Sprint updated.'));
        } else {
            $stmt = $db->prepare("INSERT INTO vk_sprints (name, status, start_date, end_date, goal, created_by, created_at) VALUES (:name, :status, :start_date, :end_date, :goal, :uid, NOW())");
            $data[':uid'] = $user->id;
            $stmt->execute($data);
            $id = (int)$db->lastInsertId();
            $this->message($this->_('Sprint created.'));
        }

        $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));
        if ($returnUrl) {
            $this->wire('session')->redirect($returnUrl);
        }
        $this->redirect('sprint-edit', $id);
    }

    protected function actionDeleteSprint(): string {
        $this->requireCSRF();
        $input = $this->wire('input');
        $id = (int)$input->post('id');
        if (!$id) { $this->redirect('sprints'); }
        $this->requireOwner('vk_sprints', $id);
        $db = $this->wire('database');
        // Unlink tasks — don't delete them
        $db->prepare("UPDATE vk_tasks SET sprint_id=NULL WHERE sprint_id=:id")->execute([':id' => $id]);
        $db->prepare("DELETE FROM vk_sprints WHERE id=:id")->execute([':id' => $id]);
        $this->message($this->_('Sprint deleted. Tasks were unlinked.'));
        $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));
        if ($returnUrl) {
            $this->wire('session')->redirect($returnUrl);
        }
        $this->redirect('sprints');
    }

    protected function actionUpdateSprintStatus(): string {
        $this->requireCSRF();
        $input = $this->wire('input');
        $id = (int)$input->post('id');
        $status = $this->sanEnum($input->post('status'), ['planned', 'active', 'completed']);
        if (!$id) { $this->redirect('sprints'); }
        $this->requireOwner('vk_sprints', $id);
        $this->wire('database')
            ->prepare("UPDATE vk_sprints SET status=:status WHERE id=:id")
            ->execute([':status' => $status, ':id' => $id]);
        $this->message($this->_('Sprint status updated.'));
        $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));
        if ($returnUrl) {
            $this->wire('session')->redirect($returnUrl);
        }
        $this->redirect('sprints');
    }

    protected function actionAttachSprintTask(): string {
        $this->requireAjaxCSRF();
        $input    = $this->wire('input');
        $sprintId = (int)$input->post('sprint_id');
        $taskId   = (int)$input->post('task_id');
        if (!$this->fwSprintExists($sprintId) || !$this->fwTaskExists($taskId)) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('Task or sprint was not found.')], 404);
        }

        $stmt = $this->wire('database')->prepare("UPDATE vk_tasks SET sprint_id=:sprint_id WHERE id=:task_id");
        $stmt->execute([':sprint_id' => $sprintId, ':task_id' => $taskId]);
        $this->jsonResponse(['ok' => true] + $this->sprintTaskPayload($sprintId));
    }

    protected function actionDetachSprintTask(): string {
        $this->requireAjaxCSRF();
        $input    = $this->wire('input');
        $sprintId = (int)$input->post('sprint_id');
        $taskId   = (int)$input->post('task_id');
        if (!$this->fwSprintExists($sprintId) || !$this->fwTaskExists($taskId)) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('Task or sprint was not found.')], 404);
        }

        $stmt = $this->wire('database')->prepare(
            "UPDATE vk_tasks SET sprint_id=NULL WHERE id=:task_id AND sprint_id=:sprint_id"
        );
        $stmt->execute([':sprint_id' => $sprintId, ':task_id' => $taskId]);
        $this->jsonResponse(['ok' => true] + $this->sprintTaskPayload($sprintId));
    }
}
