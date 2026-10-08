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
        1 => new NotifyRecipient(1, 'actor', 'actor@example.test'),
        2 => new NotifyRecipient(2, 'reviewer', 'reviewer@example.test'),
        3 => new NotifyRecipient(3, 'collaborator', 'collaborator@example.test'),
        4 => new NotifyRecipient(4, 'empty', ''),
    ]),
    'mail' => new NotifyMail(),
    'pages' => new NotifyPages(),
    'log' => new NotifyLog(),
];
$module = new Verk(['notify_enabled' => 1, 'notify_status' => 1], $services);
$notify = new VerkNotify($module);
$notify->statusChanged(7, 'Ship release', 'review', 'done', [1, 2, 2, 3, 4, 0], 1);

$checks = [
    'actor excluded and recipients deduplicated' => count(NotifyMail::$sent) === 2,
    'usable recipients selected' => array_column(NotifyMail::$sent, 'to') === ['reviewer@example.test', 'collaborator@example.test'],
    'status and task included' => str_contains(NotifyMail::$sent[0]['body'] ?? '', 'Review → Done')
        && str_contains(NotifyMail::$sent[0]['body'] ?? '', 'Ship release'),
];

$before = count(NotifyMail::$sent);
$notify->statusChanged(7, 'Ship release', 'done', 'done', [2], 1);
$checks['unchanged status is silent'] = count(NotifyMail::$sent) === $before;
$module->config['notify_enabled'] = 0;
$notify->statusChanged(7, 'Ship release', 'done', 'open', [2], 1);
$checks['master switch is respected'] = count(NotifyMail::$sent) === $before;

$failed = [];
foreach($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $label . PHP_EOL;
    if(!$passed) $failed[] = $label;
}
exit($failed ? 1 : 0);
