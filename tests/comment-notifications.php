<?php

namespace ProcessWire;

final class NotifyRecipient {
    public function __construct(public int $id, public string $name, public string $email) {}
}

final class NotifyUsers {
    public function __construct(private array $users) {}
    public function get(int $id): ?NotifyRecipient { return $this->users[$id] ?? null; }
}

final class NotifyMailMessage {
    public array $data = [];
    public function to(string $value): void { $this->data['to'] = $value; }
    public function subject(string $value): void { $this->data['subject'] = $value; }
    public function body(string $value): void { $this->data['body'] = $value; }
    public function send(): void { NotifyMail::$sent[] = $this->data; }
}

final class NotifyMail {
    public static array $sent = [];
    public function new(): NotifyMailMessage { return new NotifyMailMessage(); }
}

final class NotifyPages {
    public function get(string $selector): object { return (object)['id' => 1, 'httpUrl' => 'https://example.test/admin/verk/']; }
}

final class NotifyLog { public function save(string $name, string $message): void {} }

class Verk {
    public function __construct(public array $config, private array $services) {}
    public function getConfig(): array { return $this->config; }
    public function statusLabel(string $status): string { return ucfirst(str_replace('_', ' ', $status)); }
    public function wire(string $name): mixed { return $this->services[$name] ?? null; }
}

require_once dirname(__DIR__) . '/src/Services/VerkNotify.php';

$services = [
    'users' => new NotifyUsers([
        1 => new NotifyRecipient(1, 'author', 'author@example.test'),
        2 => new NotifyRecipient(2, 'assignee', 'assignee@example.test'),
        3 => new NotifyRecipient(3, 'reviewer', 'reviewer@example.test'),
        4 => new NotifyRecipient(4, 'empty', ''),
    ]),
    'mail' => new NotifyMail(),
    'pages' => new NotifyPages(),
    'log' => new NotifyLog(),
];
$module = new Verk(['notify_enabled' => 1, 'notify_comment' => 1], $services);
$notify = new VerkNotify($module);
$notify->commentAdded(7, 'Ship release', 'comment', '<p>Looks good &amp; <strong>ready</strong></p>', [1, 2, 2, 3, 4, 0], 1);

$checks = [
    'author excluded and recipients deduplicated' => array_column(NotifyMail::$sent, 'to') === ['assignee@example.test', 'reviewer@example.test'],
    'comment subject' => (NotifyMail::$sent[0]['subject'] ?? '') === '[Verk] New comment on "Ship release"',
    'plain-text excerpt included' => str_contains(NotifyMail::$sent[0]['body'] ?? '', 'Looks good & ready')
        && !str_contains(NotifyMail::$sent[0]['body'] ?? '', '<'),
    'task link included' => str_contains(NotifyMail::$sent[0]['body'] ?? '', 'https://example.test/admin/verk/?view=task-edit&id=7'),
];

NotifyMail::$sent = [];
$notify->commentAdded(7, 'Ship release', 'approved', '', [2], 1);
$checks['approval subject and no empty excerpt'] = (NotifyMail::$sent[0]['subject'] ?? '') === '[Verk] Task approved: "Ship release"'
    && str_contains(NotifyMail::$sent[0]['body'] ?? '', "author approved a Verk task you're on.\n\nTask: Ship release\n\nOpen the task:");

NotifyMail::$sent = [];
$notify->commentAdded(7, 'Ship release', 'changes_requested', str_repeat('word ', 100), [2], 1);
$checks['changes requested subject'] = (NotifyMail::$sent[0]['subject'] ?? '') === '[Verk] Changes requested: "Ship release"';
$checks['long excerpt truncated'] = str_contains(NotifyMail::$sent[0]['body'] ?? '', '…');

NotifyMail::$sent = [];
$module->config['notify_comment'] = 0;
$notify->commentAdded(7, 'Ship release', 'comment', 'Hi', [2], 1);
$checks['comment toggle is respected'] = NotifyMail::$sent === [];
$module->config = ['notify_enabled' => 0, 'notify_comment' => 1];
$notify->commentAdded(7, 'Ship release', 'comment', 'Hi', [2], 1);
$checks['master switch is respected'] = NotifyMail::$sent === [];

$failed = [];
foreach($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $label . PHP_EOL;
    if(!$passed) $failed[] = $label;
}
exit($failed ? 1 : 0);
