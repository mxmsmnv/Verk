<?php

namespace ProcessWire;

require_once dirname(__DIR__) . '/src/Traits/VerkUiTrait.php';

final class PermissionUser {
    public function __construct(public int $id, private bool $superuser = false) {}
    public function isSuperuser(): bool { return $this->superuser; }
}

final class PermissionHarness {
    use VerkUiTrait;

    public function __construct(private PermissionUser $user, private array $config, private bool $manager = false) {}
    public function wire(string $name): mixed { return $name === 'user' ? $this->user : null; }
    public function getConfig(): array { return $this->config; }
    public function isStatusManager(): bool { return $this->manager; }
    public function allowed(array $row): bool { return $this->canChangeTaskStatus($row); }
}

$row = static fn(array $changes = []): array => array_merge([
    'created_by' => 10,
    'assignee_id' => 20,
    'is_reviewer' => 0,
    'is_collaborator' => 0,
], $changes);

$cases = [
    'superuser' => [(new PermissionHarness(new PermissionUser(99, true), []))->allowed($row()), true],
    'creator' => [(new PermissionHarness(new PermissionUser(10), []))->allowed($row()), true],
    'assignee' => [(new PermissionHarness(new PermissionUser(20), []))->allowed($row()), true],
    'manager' => [(new PermissionHarness(new PermissionUser(99), [], true))->allowed($row()), true],
    'reviewer disabled' => [(new PermissionHarness(new PermissionUser(30), []))->allowed($row(['is_reviewer' => 1])), false],
    'reviewer enabled' => [(new PermissionHarness(new PermissionUser(30), ['status_edit_reviewer' => 1]))->allowed($row(['is_reviewer' => 1])), true],
    'collaborator disabled' => [(new PermissionHarness(new PermissionUser(40), []))->allowed($row(['is_collaborator' => 1])), false],
    'collaborator enabled' => [(new PermissionHarness(new PermissionUser(40), ['status_edit_collaborator' => 1]))->allowed($row(['is_collaborator' => 1])), true],
    'unrelated user' => [(new PermissionHarness(new PermissionUser(99), ['status_edit_reviewer' => 1, 'status_edit_collaborator' => 1]))->allowed($row()), false],
];

$failed = [];
foreach($cases as $label => [$actual, $expected]) {
    $passed = $actual === $expected;
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $label . PHP_EOL;
    if(!$passed) $failed[] = $label;
}
exit($failed ? 1 : 0);
