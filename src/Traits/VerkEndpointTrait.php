<?php namespace ProcessWire;

trait VerkEndpointTrait {

    protected function viewBulkAuditForm(): string {
        $raw     = $this->wire('input')->get('page_ids', 'string');
        $pageIds = $this->sanIdList($raw, 200);
        if (!$pageIds) { $this->redirect('audit'); }

        $pages = [];
        foreach ($pageIds as $pid) {
            $p = $this->wire('pages')->get($pid);
            if ($p->id) $pages[] = ['id'=>$p->id, 'title'=>$p->title, 'url'=>$p->url, 'status'=>$this->pageStatusFlags($p)];
        }

        if (!$pages) {
            $this->error($this->_('None of the selected pages could be resolved.'));
            $this->redirect('audit');
        }

        $ruleLabel = $this->san($this->wire('input')->get('rule_label'));
        $users     = $this->getAllUsers();
        $sprints   = $this->getAllSprints();

        ob_start(); require dirname(__DIR__, 2) . '/views/bulk-task-form.php'; return ob_get_clean();
    }


    // -------------------------------------------------------------------------
    // DOCX export
    // -------------------------------------------------------------------------

    protected function viewExportDocx(): string {
        $input = $this->wire('input');
        $type  = $input->get('type', 'string');
        $id    = (int)$input->get('id');

        while (ob_get_level() > 0) ob_end_clean();
        switch ($type) {
            case 'sprint':   $this->export->exportSprintDocx($id);                                          break;
            case 'tasks':    $this->export->exportTasksDocx(
                                 ...array_values($this->exportTaskFilters([
                                     'status' => $input->get('status', 'string') ?: '',
                                     'priority' => $input->get('priority', 'string') ?: '',
                                     'assignee_id' => (int)$input->get('assignee_id'),
                                     'sprint_id' => (int)$input->get('sprint_id'),
                                     'quarter' => (int)$input->get('quarter'),
                                     'year' => (int)$input->get('year'),
                                     'q' => $input->get('q', 'string') ?: '',
                                     'date_state' => $input->get('date_state', 'string') ?: '',
                                 ]))
                             );                                                                               break;
            case 'kb_note':  $this->export->exportKbNoteDocx($id);                                          break;
            case 'kb_cat':   $this->export->exportKbCatDocx($input->get('cat', 'string') ?: '');            break;
            case 'audit':    $this->export->exportAuditDocx((int)$input->get('rule'), $this->getAuditRules()); break;
            default:         header('Location: ' . $this->page->url); exit;
        }
        exit;
    }

    protected function exportTaskFilters(array $input): array {
        $status = in_array((string)($input['status'] ?? ''), ['active', 'open', 'in_progress', 'review', 'done'], true)
            ? (string)$input['status']
            : '';
        $priority = in_array((string)($input['priority'] ?? ''), ['low', 'medium', 'high', 'critical'], true)
            ? (string)$input['priority']
            : '';
        $quarter = (int)($input['quarter'] ?? 0);
        return [
            'status' => $status,
            'priority' => $priority,
            'assignee_id' => (int)($input['assignee_id'] ?? 0),
            'sprint_id' => (int)($input['sprint_id'] ?? 0),
            'quarter' => $quarter ? $this->quarterContext($quarter, (int)($input['year'] ?? 0) ?: (int)date('Y')) : null,
            'search' => trim(substr((string)($input['q'] ?? ''), 0, 120)),
            'date_state' => (string)($input['date_state'] ?? '') === 'none' ? 'none' : '',
        ];
    }

    // -------------------------------------------------------------------------
    // Sprint views
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Time Log actions
    // -------------------------------------------------------------------------

    protected function actionLogTime(): string {
        $this->requireCSRF();
        $input   = $this->wire('input');
        $db      = $this->wire('database');
        $user    = $this->wire('user');
        $taskId  = (int)$input->post('task_id');
        $back = $this->safeLocalUrl($input->post('back', 'string') ?: '');
        if (!$taskId) {
            $this->error($this->_('Invalid task.'));
            $this->redirect('tasks');
        }
        if (!$this->fwTaskExists($taskId)) {
            $this->error($this->_('Task does not exist.'));
            $this->redirect('tasks');
        }

        $hours = $this->sanPositiveDecimal($input->post('hours', 'string'));
        if ($hours === null) {
            $this->error($this->_('Hours must be greater than 0.'));
            if ($back) $this->wire('session')->redirect($back);
            $this->redirect('task-edit', $taskId);
        }

        $logDate = $this->sanDate($input->post('logged_date')) ?: date('Y-m-d');
        $note    = substr($this->san($input->post('note')), 0, 255);

        $stmt = $db->prepare(
            "INSERT INTO vk_time_logs (task_id, user_id, hours, note, logged_date, created_at)
             VALUES (:task_id, :uid, :hours, :note, :date, NOW())"
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':uid'     => $user->id,
            ':hours'   => $hours,
            ':note'    => $note,
            ':date'    => $logDate,
        ]);

        // Update actual_h on task with sum of all time logs
        $sum = $db->prepare("SELECT SUM(hours) FROM vk_time_logs WHERE task_id = :id");
        $sum->execute([':id' => $taskId]);
        $total = (float)$sum->fetchColumn();
        $db->prepare("UPDATE vk_tasks SET actual_h = :h WHERE id = :id")
           ->execute([':h' => $total, ':id' => $taskId]);

        $this->message(sprintf($this->_('%sh logged.'), number_format($hours, 1)));
        if ($back) $this->wire('session')->redirect($back);
        $this->redirect('task-edit', $taskId);
    }

    protected function actionDeleteTimeLog(): string {
        $this->requireCSRF();
        $input  = $this->wire('input');
        $db     = $this->wire('database');
        $user   = $this->wire('user');
        $id     = (int)$input->post('id');
        $taskId = (int)$input->post('task_id');
        $back = $this->safeLocalUrl($input->post('back', 'string') ?: '');

        $stmt = $db->prepare("SELECT task_id, user_id FROM vk_time_logs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            if (!$user->isSuperuser() && (int)$row['user_id'] !== (int)$user->id) {
                $this->error($this->_('You do not have permission to delete this time log entry.'));
                if ($back) $this->wire('session')->redirect($back);
                $this->redirect('task-edit', (int)$row['task_id']);
            }
            $taskId = (int)$row['task_id'];
            $db->prepare("DELETE FROM vk_time_logs WHERE id = :id")->execute([':id' => $id]);

            // Recalculate actual_h
            $sum = $db->prepare("SELECT COALESCE(SUM(hours), 0) FROM vk_time_logs WHERE task_id = :id");
            $sum->execute([':id' => $taskId]);
            $total = (float)$sum->fetchColumn();
            $db->prepare("UPDATE vk_tasks SET actual_h = :h WHERE id = :id")
               ->execute([':h' => $total ?: null, ':id' => $taskId]);

            $this->message($this->_('Time log deleted.'));
            if ($back) $this->wire('session')->redirect($back);
            $this->redirect('task-edit', $taskId);
        }
        // Log entry not found - go back to task list
        $this->error($this->_('Time log entry not found.'));
        $this->redirect('tasks');
    }

    // -------------------------------------------------------------------------
    // Bulk audit task creation
    // -------------------------------------------------------------------------

    protected function actionBulkAuditTasks(): string {
        $this->requireCSRF();
        $input    = $this->wire('input');
        $db       = $this->wire('database');
        $user     = $this->wire('user');
        $returnUrl = $this->safeLocalUrl((string)$input->post('return_url'));

        // page_ids comes as comma-separated string from hidden field
        $pageIds = $this->sanIdList($input->post('page_ids', 'string'), 200);
        if (!$pageIds) {
            $this->error($this->_('No pages selected.'));
            if ($returnUrl) $this->wire('session')->redirect($returnUrl);
            $this->redirect('audit');
        }

        $titleTpl   = $this->san($input->post('title_template')) ?: '{page_title}';
        $assigneeId = (int)$input->post('assignee_id') ?: null;
        $sprintId   = (int)$input->post('sprint_id')   ?: null;

        if ($assigneeId && !$this->fwUserExists($assigneeId)) {
            $this->error($this->_('Assignee does not exist.'));
            if ($returnUrl) $this->wire('session')->redirect($returnUrl);
            $this->redirect('audit');
        }
        if ($sprintId && !$this->fwSprintExists($sprintId)) {
            $this->error($this->_('Sprint does not exist.'));
            if ($returnUrl) $this->wire('session')->redirect($returnUrl);
            $this->redirect('audit');
        }

        $priority   = $this->sanEnum($input->post('priority'), ['low','medium','high','critical']);
        $status     = $this->sanEnum($input->post('status'),   ['open','in_progress','review','done']);
        $dueDate    = $this->sanDate($input->post('due_date'));
        $estimateH  = $this->sanAllowedInt($input->post('estimate_h'), [2, 4, 8]);
        $spVal      = $this->sanAllowedInt($input->post('story_points'), [1, 2, 3, 5, 8, 13, 21]);
        $desc       = $this->san($input->post('description'));

        $stmt = $db->prepare(
            "INSERT INTO vk_tasks (title, description, status, priority, due_date, page_id,
             assignee_id, sprint_id, estimate_h, story_points, created_by, created_at)
             VALUES (:title, :desc, :status, :priority, :due_date, :page_id,
             :assignee, :sprint, :est, :sp, :uid, NOW())"
        );

        $created = 0;
        foreach ($pageIds as $pid) {
            $page = $this->wire('pages')->get($pid);
            if (!$page->id) continue;
            $title = str_replace('{page_title}', $page->title, $titleTpl);
            $stmt->execute([
                ':title'    => substr($title, 0, 255),
                ':desc'     => $desc,
                ':status'   => $status,
                ':priority' => $priority,
                ':due_date' => $dueDate,
                ':page_id'  => $pid,
                ':assignee' => $assigneeId,
                ':sprint'   => $sprintId,
                ':est'      => $estimateH,
                ':sp'       => $spVal,
                ':uid'      => $user->id,
            ]);
            $created++;
        }

        // One digest email to the bulk assignee (skips actor, gated by config).
        if ($assigneeId) {
            $this->notify->bulkAssigned((int) $assigneeId, $created, (int) $user->id);
        }

        $this->message(sprintf($this->_n('%d task created.', '%d tasks created.', $created), $created));
        if ($returnUrl) $this->wire('session')->redirect($returnUrl);
        $this->redirect('tasks');
    }

    // Page search (AJAX for task page picker)
    // -------------------------------------------------------------------------

    protected function viewFileUpload(): string {
        $this->requireAjaxCSRF();
        $input = $this->wire('input');
        $type  = (string) $input->post('entity_type');
        $id    = (int) $input->post('entity_id');
        $emb   = (int) (bool) $input->post('embedded');
        if (!$this->files->isValidEntity($type) || $id < 1) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('Invalid target.')], 400);
        }
        $table = ['task' => 'vk_tasks', 'note' => 'vk_notes', 'comment' => 'vk_comments'][$type];
        $stmt = $this->wire('database')->prepare("SELECT id FROM `$table` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('Target not found.')], 404);
        }
        try {
            $stored = $this->files->store($type, $id, (bool) $emb);
        } catch (\Exception $e) {
            $this->jsonResponse(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if (!$stored) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('No file was uploaded (check type and size).')], 422);
        }
        $files = array_map(fn($r) => [
            'id' => (int) $r['id'], 'original_name' => $r['original_name'], 'url' => $r['url'],
            'thumb' => $r['thumb'], 'mime' => $r['mime'], 'human_size' => $r['human_size'], 'is_image' => $r['is_image'],
        ], $stored);
        $this->jsonResponse(['ok' => true, 'files' => $files]);
    }

    protected function viewFile(): string {
        $id  = (int) $this->wire('input')->get('id');
        $row = $id ? $this->files->get($id) : null;
        if (!$row) { http_response_code(404); exit; }

        $wantThumb = $this->wire('input')->get('size') === 'thumb';
        $path = $wantThumb ? ($this->files->thumbPathFor($row) ?: $this->files->streamPath($row))
                           : $this->files->streamPath($row);
        if (!is_file($path)) { http_response_code(404); exit; }

        while (ob_get_level() > 0) ob_end_clean();

        // SVG renders fine inside an <img> (script-inert there), but must never run
        // as a document: force-download on direct navigation and strip all scripting
        // via CSP/sandbox/nosniff. The grid + lightbox <img> tags still display it.
        if (strtolower($row['ext']) === 'svg') {
            header('Content-Type: image/svg+xml');
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
            header('Content-Disposition: attachment; filename="' . addslashes($row['original_name']) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }

        // Inline only true raster images; everything else downloads.
        $this->wire('files')->send($path, [
            'forceDownload' => !$this->files->isInlineImage($row),
            'downloadFilename' => $row['original_name'],
        ]);
        exit;
    }

    protected function viewFileDelete(): string {
        $this->requireAjaxCSRF();
        $id  = (int) $this->wire('input')->post('id');
        $row = $id ? $this->files->get($id) : null;
        if (!$row) $this->jsonResponse(['ok' => false, 'message' => $this->_('File not found.')], 404);
        $this->files->deleteFile($id);
        $this->jsonResponse(['ok' => true]);
    }

    protected function viewAjaxSearch(): string {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        $q = $this->wire('sanitizer')->text($this->wire('input')->get('q'));
        if (strlen($q) < 2) { echo json_encode([]); exit; }

        // include=unpublished covers both hidden and unpublished pages (but not
        // trash); selectorValue() safely escapes the user-supplied query.
        $selector = 'title%=' . $this->wire('sanitizer')->selectorValue($q)
            . ', template!=admin, include=unpublished, limit=15, sort=title';
        $pages = $this->wire('pages')->find($selector);
        $out   = [];
        foreach ($pages as $p) {
            $out[] = ['id' => $p->id, 'title' => $p->title, 'url' => $p->url, 'template' => (string)$p->template] + $this->pageStatusFlags($p);
        }
        echo json_encode($out);
        exit;
    }

    protected function viewAjaxSprintTasks(): string {
        $input    = $this->wire('input');
        $sprintId = (int)$input->get('sprint_id');
        if (!$this->fwSprintExists($sprintId)) {
            $this->jsonResponse(['ok' => false, 'message' => $this->_('Sprint was not found.')], 404);
        }

        $q      = trim($this->wire('sanitizer')->text($input->get('q')));
        $params = [':sprint_id' => $sprintId];
        $where  = '(t.sprint_id IS NULL OR t.sprint_id != :sprint_id)';
        if ($q !== '') {
            $where .= ' AND t.title LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $stmt = $this->wire('database')->prepare(
            "SELECT t.id, t.title, t.status, t.priority, t.due_date, t.sprint_id, s.name AS sprint_name
             FROM vk_tasks t
             LEFT JOIN vk_sprints s ON s.id = t.sprint_id
             WHERE {$where}
             ORDER BY FIELD(t.status,'open','in_progress','review','done'),
                      FIELD(t.priority,'critical','high','medium','low'), t.created_at DESC
             LIMIT 20"
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tasks as &$task) {
            $task['status_label'] = $this->statusLabel((string)$task['status']);
            $task['priority_label'] = $this->priorityLabel((string)$task['priority']);
            $task['quarter_label'] = !empty($task['due_date']) ? $this->quarterLabelForDate((string)$task['due_date']) : '';
        }
        unset($task);
        $this->jsonResponse(['ok' => true, 'tasks' => $tasks]);
    }
}
