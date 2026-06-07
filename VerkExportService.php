<?php namespace ProcessWire;

class VerkExportService {

    private Wire $wire;
    private Verk $module;

    public function __construct(Verk $module) {
        $this->wire   = $module->wire();
        $this->module = $module;
    }

    private function userMap(): array {
        $map = [];
        $guestId = (int)$this->wire->config->guestUserPageID;
        foreach ($this->wire->users->find("id!=$guestId, sort=name, limit=500") as $u) {
            $map[$u->id] = $u->name;
        }
        return $map;
    }

    private function enrichAssigneeNames(array &$tasks, array $userMap): void {
        foreach ($tasks as &$t) {
            $t['assignee_name'] = $userMap[(int)$t['assignee_id']] ?? null;
        }
    }

    public function exportSprintDocx(int $id): void {
        $sprint = $this->module->getSprint($id);
        if (!$sprint) {
            header('Location: ' . $this->module->page->url . '?view=sprints');
            exit;
        }

        $stmt = $this->wire->database->prepare(
            "SELECT t.* FROM vk_tasks t WHERE t.sprint_id=:sid
             ORDER BY FIELD(t.status,'open','in_progress','review','done'),
                      FIELD(t.priority,'critical','high','medium','low')"
        );
        $stmt->execute([':sid' => $id]);
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->enrichAssigneeNames($tasks, $this->userMap());

        $totalSP     = array_sum(array_column($tasks, 'story_points'));
        $totalEst    = array_sum(array_column($tasks, 'estimate_h'));
        $totalActual = array_sum(array_column($tasks, 'actual_h'));
        $done        = count(array_filter($tasks, fn($t) => $t['status']==='done'));

        $rows = [[__('Task'),__('Status'),__('Priority'),__('Assignee'),__('SP'),__('Est'),__('Actual'),__('Due')]];
        foreach ($tasks as $t) {
            $rows[] = [
                $t['title'],
                $this->module->statusLabel($t['status']),
                $this->module->priorityLabel($t['priority']),
                $t['assignee_name'] ?? '',
                $t['story_points']  ?? '',
                $t['estimate_h'] ? $t['estimate_h'].'h' : '',
                $t['actual_h']   ? number_format((float)$t['actual_h'],1).'h' : '',
                $t['due_date']   ?? '',
            ];
        }

        $summary = sprintf(__('%1$d/%2$d tasks done'), $done, count($tasks))
            . ($totalSP     ? ' | ' . sprintf(__('%d SP'), $totalSP) : '')
            . ($totalEst    ? ' | ' . sprintf(__('Est: %dh'), $totalEst) : '')
            . ($totalActual ? ' | ' . sprintf(__('Actual: %sh'), number_format($totalActual,1)) : '');

        $sections = [['heading1', $sprint['name']]];
        if ($sprint['goal']) $sections[] = ['para', __('Goal:').' '.$this->plainText($sprint['goal'])];
        if ($sprint['start_date']||$sprint['end_date'])
            $sections[] = ['para', __('Duration:').' '.($sprint['start_date']?:'?').' — '.($sprint['end_date']?:'?')];
        $sections[] = ['para', $summary];
        if (count($rows)>1) $sections[] = ['table', $rows];

        $slug = $this->wire->sanitizer->pageName($sprint['name'] ?: 'sprint');
        $this->sendDocx("sprint-{$slug}.docx", $sections);
    }

    public function exportTasksDocx(string $status, string $priority, int $assigneeId, int $sprintId, ?array $quarter = null, string $search = '', string $dateState = ''): void {
        $db     = $this->wire->database;
        $where  = ['1=1'];
        $params = [];
        if ($status)   { $where[] = 't.status=:status';       $params[':status']    = $status; }
        if ($priority) { $where[] = 't.priority=:priority';   $params[':priority']  = $priority; }
        if ($assigneeId === -1) { $where[] = '(t.assignee_id IS NULL OR t.assignee_id = 0)'; }
        elseif ($assigneeId > 0) { $where[] = 't.assignee_id=:assignee_id'; $params[':assignee_id'] = $assigneeId; }
        if ($sprintId === -1) { $where[] = '(t.sprint_id IS NULL OR t.sprint_id = 0)'; }
        elseif ($sprintId > 0) { $where[] = 't.sprint_id=:sprint_id'; $params[':sprint_id'] = $sprintId; }
        $search = trim(substr($search, 0, 120));
        if ($search) {
            $where[] = '(t.title LIKE :search OR t.section LIKE :search OR t.description LIKE :search OR t.id = :search_id)';
            $params[':search'] = '%' . $search . '%';
            $params[':search_id'] = ctype_digit($search) ? (int)$search : 0;
        }
        if ($quarter) {
            $where[] = 't.due_date IS NOT NULL AND t.due_date >= :quarter_start AND t.due_date <= :quarter_end';
            $params[':quarter_start'] = $quarter['start'];
            $params[':quarter_end'] = $quarter['end'];
        } elseif ($dateState === 'none') {
            $where[] = 't.due_date IS NULL';
        }

        $stmt = $db->prepare(
            "SELECT t.*, s.name as sprint_name FROM vk_tasks t
             LEFT JOIN vk_sprints s ON s.id = t.sprint_id
             WHERE ".implode(' AND ',$where)."
             ORDER BY FIELD(t.status,'open','in_progress','review','done'),
                      FIELD(t.priority,'critical','high','medium','low'), t.due_date ASC"
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->enrichAssigneeNames($tasks, $this->userMap());

        $rows = [[__('Task'),__('Status'),__('Priority'),__('Assignee'),__('Sprint'),__('SP'),__('Est'),__('Actual'),__('Due')]];
        foreach ($tasks as $t) {
            $rows[] = [
                $t['title'],
                $this->module->statusLabel($t['status']),
                $this->module->priorityLabel($t['priority']),
                $t['assignee_name'] ?? '',
                $t['sprint_name']   ?? '',
                $t['story_points']  ?? '',
                $t['estimate_h'] ? $t['estimate_h'].'h' : '',
                $t['actual_h']   ? number_format((float)$t['actual_h'],1).'h' : '',
                $t['due_date']   ?? '',
            ];
        }

        $sections = [
            ['heading1', __('Task List')],
            ['para',     sprintf(__('%d tasks'), count($tasks)) . ($search ? ' | ' . sprintf(__('Search: %s'), $search) : '') . ($quarter ? ' | ' . $this->module->quarterLabel($quarter) : '') . ($dateState === 'none' ? ' | ' . __('No due date') : '')],
        ];
        if (count($rows)>1) $sections[] = ['table', $rows];
        $this->sendDocx('tasks-export.docx', $sections);
    }

    public function exportKbNoteDocx(int $id): void {
        $stmt = $this->wire->database->prepare("SELECT * FROM vk_notes WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $note = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$note) { header('Location: ' . $this->module->page->url . '?view=kb'); exit; }

        $slug     = $this->wire->sanitizer->pageName($note['title'] ?: 'note');
        $sections = [['heading1', $note['title']]];
        if ($note['category']) $sections[] = ['para', __('Category:').' '.$note['category']];
        $sections[] = ['para', __('Updated:').' '.$note['updated_at']];
        if ($note['body']) $sections[] = ['body', $note['body']];
        $this->sendDocx("kb-{$slug}.docx", $sections);
    }

    public function exportKbCatDocx(string $cat): void {
        $db   = $this->wire->database;
        if ($cat) {
            $stmt = $db->prepare("SELECT * FROM vk_notes WHERE category=:cat ORDER BY title ASC");
            $stmt->execute([':cat' => $cat]);
        } else {
            $stmt = $db->query("SELECT * FROM vk_notes ORDER BY category ASC, title ASC");
        }
        $notes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $title    = $cat ? sprintf(__('Knowledge Base: %s'), $cat) : __('Knowledge Base');
        $sections = [['heading1', $title]];
        foreach ($notes as $note) {
            $sections[] = ['heading2', $note['title']];
            if ($note['body'])     $sections[] = ['body', $note['body']];
            $sections[] = ['para', __('Updated:').' '.$note['updated_at']];
        }

        $slug = $this->wire->sanitizer->pageName($cat ?: 'all');
        $this->sendDocx("kb-{$slug}.docx", $sections);
    }

    public function exportAuditDocx(int $ruleIdx, array $rules): void {
        if (!isset($rules[$ruleIdx])) {
            header('Location: ' . $this->module->page->url . '?view=audit');
            exit;
        }
        $rule = $rules[$ruleIdx];
        $results = $this->module->getAuditExportResults($rule);
        if (isset($results['error']) || isset($results['setup'])) {
            header('Location: ' . $this->module->page->url . '?view=audit');
            exit;
        }
        $rows = [[__('Page Title'),__('Template'),__('URL')]];
        foreach ($results['pages'] ?? [] as $p) $rows[] = [$p['title'], $p['template'], $p['url']];

        $slug     = $this->wire->sanitizer->pageName($rule['label'] ?: 'audit');
        $sections = [
            ['heading1', sprintf(__('Content Audit: %s'), $rule['label'])],
            ['para',     __('Scope:').' '.$rule['selector']],
            ['para',     __('Field path:').' '.($rule['field'] ?? '')],
            ['para',     sprintf(__('%d pages found'), count($rows)-1)],
        ];
        if (count($rows)>1) $sections[] = ['table', $rows];
        $this->sendDocx("audit-{$slug}.docx", $sections);
    }

    private function sendDocx(string $filename, array $sections): void {
        $tmp = $this->createDocxPackage($sections);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="'.addslashes($filename).'"');
        header('Content-Length: '.filesize($tmp));
        header('Cache-Control: no-cache');
        readfile($tmp);
        @unlink($tmp);
    }

    private function buildDocumentXml(array $sections): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\r\n"
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>';

        foreach ($sections as [$type, $content]) {
            $xml .= match($type) {
                'heading1' => $this->docxH($content, 1),
                'heading2' => $this->docxH($content, 2),
                'para'     => $this->docxP($content),
                'body'     => $this->docxBody($content),
                'table'    => $this->docxTbl($content),
                default    => '',
            };
        }
        $xml .= '<w:sectPr>'
            . '<w:pgSz w:w="12240" w:h="15840"/>'
            . '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>'
            . '</w:sectPr></w:body></w:document>';
        return $xml;
    }

    private function createDocxPackage(array $sections): string {
        $tmp = tempnam(sys_get_temp_dir(), 'vk_') . '.docx';
        $xml = $this->buildDocumentXml($sections);
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE|\ZipArchive::OVERWRITE) !== true) {
            header('Location: ' . $this->module->page->url);
            exit;
        }
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml"  ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
        );
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
        return $tmp;
    }

    private function docxH(string $text, int $lvl): string {
        $esc  = htmlspecialchars($text, ENT_XML1);
        $sz   = $lvl===1 ? 40 : 30;
        $bold = $lvl===1 ? '<w:b/>' : '';
        $sp   = $lvl===1 ? '<w:spacing w:before="240" w:after="120"/>' : '<w:spacing w:before="180" w:after="80"/>';
        return "<w:p><w:pPr><w:pStyle w:val=\"Heading{$lvl}\"/>{$sp}</w:pPr>"
            ."<w:r><w:rPr>{$bold}<w:sz w:val=\"{$sz}\"/><w:szCs w:val=\"{$sz}\"/></w:rPr>"
            ."<w:t xml:space=\"preserve\">{$esc}</w:t></w:r></w:p>";
    }

    private function docxP(string $text, bool $bold=false): string {
        $esc = htmlspecialchars($text, ENT_XML1);
        $b   = $bold ? '<w:b/>' : '';
        return "<w:p><w:r><w:rPr>{$b}<w:sz w:val=\"22\"/><w:szCs w:val=\"22\"/></w:rPr>"
            ."<w:t xml:space=\"preserve\">{$esc}</w:t></w:r></w:p>";
    }

    private function docxBody(string $text): string {
        $text = $this->plainText($text);
        $out = '';
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) $out .= $this->docxP($line);
        return $out ?: $this->docxP('');
    }

    private function plainText(string $text): string {
        $text = preg_replace('/<(br|\/p|\/li|\/h[1-6])\b[^>]*>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\u{00A0}", ' ', $text));
    }

    private function docxTbl(array $rows): string {
        $cols  = count($rows[0]);
        $colW  = (int)(9360 / max($cols, 1));
        $bdr   = '<w:top w:val="single" w:sz="4" w:color="CCCCCC"/>'
               . '<w:left w:val="single" w:sz="4" w:color="CCCCCC"/>'
               . '<w:bottom w:val="single" w:sz="4" w:color="CCCCCC"/>'
               . '<w:right w:val="single" w:sz="4" w:color="CCCCCC"/>'
               . '<w:insideH w:val="single" w:sz="4" w:color="CCCCCC"/>'
               . '<w:insideV w:val="single" w:sz="4" w:color="CCCCCC"/>';

        $xml  = '<w:tbl><w:tblPr>'
              . '<w:tblW w:w="9360" w:type="dxa"/>'
              . '<w:tblBorders>'.$bdr.'</w:tblBorders>'
              . '</w:tblPr><w:tblGrid>';
        for ($i=0; $i<$cols; $i++) $xml .= "<w:gridCol w:w=\"{$colW}\"/>";
        $xml .= '</w:tblGrid>';

        foreach ($rows as $ri => $row) {
            $isHdr = ($ri===0);
            $xml  .= '<w:tr>';
            if ($isHdr) $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
            foreach ($row as $cell) {
                $esc  = htmlspecialchars((string)$cell, ENT_XML1);
                $fill = $isHdr ? '<w:shd w:val="clear" w:color="auto" w:fill="E8EEF4"/>' : '';
                $b    = $isHdr ? '<w:b/>' : '';
                $xml .= "<w:tc><w:tcPr><w:tcW w:w=\"{$colW}\" w:type=\"dxa\"/>{$fill}"
                      . "<w:tcMar><w:top w:w=\"80\" w:type=\"dxa\"/><w:bottom w:w=\"80\" w:type=\"dxa\"/>"
                      . "<w:left w:w=\"120\" w:type=\"dxa\"/><w:right w:w=\"120\" w:type=\"dxa\"/></w:tcMar>"
                      . "</w:tcPr>"
                      . "<w:p><w:r><w:rPr>{$b}<w:sz w:val=\"18\"/><w:szCs w:val=\"18\"/></w:rPr>"
                      . "<w:t xml:space=\"preserve\">{$esc}</w:t></w:r></w:p></w:tc>";
            }
            $xml .= '</w:tr>';
        }
        return $xml.'</w:tbl><w:p/>';
    }
}
