<?php namespace ProcessWire;

class VerkNotify {

    protected Verk $module;

    public function __construct(Verk $module) {
        $this->module = $module;
    }

    /** Whether comment/review notifications are enabled by both switches. */
    public function commentNotificationsEnabled(): bool {
        return $this->cfgOn('notify_comment');
    }

    /**
     * Notify users newly added to a task role. Consolidates multiple new roles
     * for the same user into one email. Never emails the actor.
     * $before/$after shape: ['assignee'=>int, 'reviewer'=>[int], 'collaborator'=>[int]]
     */
    public function membershipChanged(int $taskId, string $title, array $before, array $after, int $actorId): void {
        $roles = [
            'assignee'     => ['cfg' => 'notify_assignee',     'label' => 'Assignee'],
            'collaborator' => ['cfg' => 'notify_collaborator', 'label' => 'Collaborator'],
            'reviewer'     => ['cfg' => 'notify_reviewer',     'label' => 'Reviewer'],
        ];

        // userId => [role labels they were newly given on this save]
        $newRolesByUser = [];

        foreach ($roles as $role => $meta) {
            if (!$this->cfgOn($meta['cfg'])) continue;

            if ($role === 'assignee') {
                $newId = (int) ($after['assignee'] ?? 0);
                $oldId = (int) ($before['assignee'] ?? 0);
                $added = ($newId > 0 && $newId !== $oldId) ? [$newId] : [];
            } else {
                $afterIds  = array_map('intval', (array) ($after[$role] ?? []));
                $beforeIds = array_map('intval', (array) ($before[$role] ?? []));
                $added = array_values(array_diff($afterIds, $beforeIds));
            }

            foreach ($added as $uid) {
                if ($uid === $actorId) continue; // never notify the actor about their own change
                $newRolesByUser[$uid][] = $meta['label'];
            }
        }

        if (!$newRolesByUser) return;

        $base = $this->deskUrl();
        $taskUrl = $base . '?view=task-edit&id=' . $taskId;
        $actor = $this->actorName($actorId);

        foreach ($newRolesByUser as $uid => $labels) {
            $to = $this->recipient((int) $uid);
            if (!$to) continue;

            $rolesText = implode(', ', $labels);
            $subject = sprintf('[Verk] You\'ve been added to a task: "%s"', $title);
            $body = sprintf(
                "Hi %s,\n\n%s added you to a Verk task as: %s\n\nTask: %s\n\nOpen the task:\n%s\n",
                $to['name'] ?: 'there',
                $actor,
                $rolesText,
                $title,
                $taskUrl
            );
            $this->sendPlain($to['email'], $subject, $body);
        }
    }

    /**
     * Notify everyone on a task that its status moved. Recipients are the task's
     * people (creator, assignee, reviewers, collaborators) as resolved by
     * Verk::taskNotifyRecipients(); the actor is never emailed. No-ops when the
     * status did not actually change.
     */
    public function statusChanged(int $taskId, string $title, string $from, string $to, array $recipientIds, int $actorId): void {
        if ($from === $to) return;
        if (!$this->cfgOn('notify_status')) return;

        $ids = [];
        foreach ($recipientIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0 && $uid !== $actorId) $ids[$uid] = $uid;
        }
        if (!$ids) return;

        $taskUrl   = $this->deskUrl() . '?view=task-edit&id=' . $taskId;
        $actor     = $this->actorName($actorId);
        $fromLabel = $this->module->statusLabel($from);
        $toLabel   = $this->module->statusLabel($to);

        foreach ($ids as $uid) {
            $rcpt = $this->recipient($uid);
            if (!$rcpt) continue;

            $subject = sprintf('[Verk] Task status changed: "%s" → %s', $title, $toLabel);
            $body = sprintf(
                "Hi %s,\n\n%s changed the status of a Verk task you're on.\n\nTask: %s\nStatus: %s → %s\n\nOpen the task:\n%s\n",
                $rcpt['name'] ?: 'there',
                $actor,
                $title,
                $fromLabel,
                $toLabel,
                $taskUrl
            );
            $this->sendPlain($rcpt['email'], $subject, $body);
        }
    }

    /**
     * Notify everyone on a task that a comment or review decision was added.
     * Recipients come from Verk::taskNotifyRecipients(); the author is never
     * emailed. $kind: 'comment' | 'approved' | 'changes_requested'.
     */
    public function commentAdded(int $taskId, string $title, string $kind, string $html, array $recipientIds, int $actorId): void {
        if (!$this->commentNotificationsEnabled()) return;

        $ids = [];
        foreach ($recipientIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0 && $uid !== $actorId) $ids[$uid] = $uid;
        }
        if (!$ids) return;

        switch ($kind) {
            case 'approved':
                $subject = sprintf('[Verk] Task approved: "%s"', $title);
                $verb = 'approved';
                break;
            case 'changes_requested':
                $subject = sprintf('[Verk] Changes requested: "%s"', $title);
                $verb = 'requested changes on';
                break;
            default:
                $subject = sprintf('[Verk] New comment on "%s"', $title);
                $verb = 'commented on';
                break;
        }

        // Comment text is sanitized HTML; send a short plain-text excerpt.
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $excerpt = $plain === '' ? '' : (mb_strlen($plain) > 200 ? rtrim(mb_substr($plain, 0, 200)) . '…' : $plain);

        $taskUrl = $this->deskUrl() . '?view=task-edit&id=' . $taskId;
        $actor   = $this->actorName($actorId);

        foreach ($ids as $uid) {
            $rcpt = $this->recipient($uid);
            if (!$rcpt) continue;

            $body = sprintf(
                "Hi %s,\n\n%s %s a Verk task you're on.\n\nTask: %s\n",
                $rcpt['name'] ?: 'there',
                $actor,
                $verb,
                $title
            );
            if ($excerpt !== '') $body .= sprintf("\n%s\n", $excerpt);
            $body .= sprintf("\nOpen the task:\n%s\n", $taskUrl);
            $this->sendPlain($rcpt['email'], $subject, $body);
        }
    }

    /** Send a single digest email to an assignee given N freshly bulk-created tasks. */
    public function bulkAssigned(int $assigneeId, int $count, int $actorId): void {
        if ($count < 1) return;
        if ($assigneeId === $actorId) return;        // actor assigned themselves
        if (!$this->cfgOn('notify_assignee')) return; // gated by master + assignee toggle

        $to = $this->recipient($assigneeId);
        if (!$to) return;

        $listUrl = $this->deskUrl() . '?view=tasks&assignee_id=' . $assigneeId;
        $actor = $this->actorName($actorId);

        $subject = sprintf('[Verk] You\'ve been assigned %d new task%s', $count, $count === 1 ? '' : 's');
        $body = sprintf(
            "Hi %s,\n\n%s assigned you %d new Verk task%s.\n\nView your tasks:\n%s\n",
            $to['name'] ?: 'there',
            $actor,
            $count,
            $count === 1 ? '' : 's',
            $listUrl
        );
        $this->sendPlain($to['email'], $subject, $body);
    }

    // ---- private helpers -------------------------------------------------

    /** Master toggle AND the per-role toggle must both be on. */
    protected function cfgOn(string $key): bool {
        $cfg = $this->module->getConfig();
        return !empty($cfg['notify_enabled']) && !empty($cfg[$key]);
    }

    /** Absolute URL of the Verk admin (desk) page, e.g. https://host/admin/verk/ */
    protected function deskUrl(): string {
        $desk = $this->module->wire('pages')->get('name=verk, template=admin');
        return $desk && $desk->id ? $desk->httpUrl : '';
    }

    /** Resolve a recipient's name+email, or null if the user has no usable email. */
    protected function recipient(int $userId): ?array {
        if ($userId < 1) return null;
        $u = $this->module->wire('users')->get($userId);
        if (!$u || !$u->id) return null;
        $email = trim((string) $u->email);
        if ($email === '') return null;
        return ['name' => (string) $u->name, 'email' => $email];
    }

    /** Display name for the actor (who made the change), falling back gracefully. */
    protected function actorName(int $actorId): string {
        $u = $this->module->wire('users')->get($actorId);
        return ($u && $u->id) ? (string) $u->name : 'Someone';
    }

    /** Send one plain-text email. Never throws; logs failures to verk-notify. */
    protected function sendPlain(string $email, string $subject, string $body): void {
        try {
            $mail = $this->module->wire('mail')->new();
            $mail->to($email);
            $mail->subject($subject);
            $mail->body($body); // plain text only — do not set bodyHTML
            $mail->send();
        } catch (\Throwable $e) {
            $this->module->wire('log')->save('verk-notify', 'sendPlain failed: ' . $e->getMessage());
        }
    }
}
