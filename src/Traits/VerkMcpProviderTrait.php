<?php namespace ProcessWire;

/** Aggregate-only MCP integration for the private operations workspace. */
trait VerkMcpProviderTrait {
    public function mcpProviderInfo(): array {
        return ['name' => 'verk', 'title' => 'Verk', 'version' => '1.6.1'];
    }

    public function mcpTools(): array {
        return [[
            'name' => 'verk_status',
            'title' => 'Verk operational status',
            'description' => 'Return bounded aggregate task and sprint counts without titles, notes, people, files, knowledge-base content, or private page data.',
            'handler' => [$this, 'mcpVerkStatus'],
            'scope' => 'read', 'read_only' => true, 'destructive' => false,
            'idempotent' => true, 'open_world' => false,
            'input_schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => [], 'additionalProperties' => false],
        ]];
    }

    public function mcpVerkStatus(): array {
        $db = $this->wire('database');
        $tasks = ['total' => 0, 'plan' => 0, 'progress' => 0, 'done' => 0, 'overdue' => 0];
        try {
            $row = $db->query("SELECT COUNT(*) total, SUM(status='plan') plan, SUM(status='progress') progress, SUM(status='done') done, SUM(status<>'done' AND due_date IS NOT NULL AND due_date<CURDATE()) overdue FROM vk_tasks")->fetch(\PDO::FETCH_ASSOC) ?: [];
            foreach($tasks as $key => $_) $tasks[$key] = (int)($row[$key] ?? 0);
            $sprints = (int)$db->query('SELECT COUNT(*) FROM vk_sprints')->fetchColumn();
        } catch(\Throwable) {
            $sprints = 0;
        }
        return ['version' => '1.6.1', 'tasks' => $tasks, 'sprints' => $sprints, 'private_records_exposed' => false, 'write_tools' => false];
    }
}
