<?php

$root = dirname(__DIR__);
$module = (string)file_get_contents($root . '/Verk.module.php');
$readme = (string)file_get_contents($root . '/README.md');
$changelog = (string)file_get_contents($root . '/CHANGELOG.md');
$ui = (string)file_get_contents($root . '/src/Traits/VerkUiTrait.php');

$checks = [
    'release version is 1.8.0' => str_contains($module, '@version 180')
        && str_contains($module, "'version'  => 180")
        && str_contains($readme, 'Version `1.8.0`')
        && str_contains($changelog, '## [1.8.0]'),
    'new permissions default off' => str_contains($module, "'status_edit_reviewer' => 0")
        && str_contains($module, "'status_edit_collaborator' => 0")
        && str_contains($module, "'status_manager_roles' => ''"),
    'status mail defaults off' => str_contains($module, "'notify_status' => 0"),
    'comment mail defaults off' => str_contains($module, "'notify_comment' => 0"),
    'external reviews require both Mailbox permissions' => str_contains($ui, "hasPermission('mailbox-api')")
        && str_contains($ui, "hasPermission('mailbox-confirm-links')"),
];

$failed = [];
foreach($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $label . PHP_EOL;
    if(!$passed) $failed[] = $label;
}
exit($failed ? 1 : 0);
