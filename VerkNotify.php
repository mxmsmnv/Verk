<?php namespace ProcessWire;

class VerkNotify {

    public function __construct(protected Verk $module) {}

    /**
     * Notify users newly added to a task role. Consolidates multiple new roles
     * for the same user into one email. Never emails the actor.
     * $before/$after shape: ['assignee'=>int, 'reviewer'=>[int], 'collaborator'=>[int]]
     */
    public function membershipChanged(int $taskId, string $title, array $before, array $after, int $actorId): void {
        // implemented in Task 3
    }

    /** Send a single digest email to an assignee given N freshly bulk-created tasks. */
    public function bulkAssigned(int $assigneeId, int $count, int $actorId): void {
        // implemented in Task 4
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
            $this->module->wire('log')->error('verk-notify: ' . $e->getMessage());
        }
    }
}
