<?php namespace ProcessWire;

class VerkFiles {

    const ENTITIES  = ['task', 'note', 'comment'];
    const MAX_BYTES = 26214400; // 25 MB
    const ALLOWED   = ['jpg','jpeg','png','gif','webp','svg','pdf','docx','xlsx','pptx','txt','csv','zip'];
    const IMAGE_EXT = ['jpg','jpeg','png','gif','webp']; // svg intentionally excluded (inline XSS)
    const THUMB_MAX = 240;

    public function __construct(protected Verk $module) {}

    public function isValidEntity(string $type): bool {
        return in_array($type, self::ENTITIES, true);
    }

    protected function db(): \PDO { return $this->module->wire('database'); }

    public function baseDir(): string {
        return $this->module->wire('config')->paths->assets . 'Verk/';
    }

    public function dirFor(string $type, int $id): string {
        return $this->baseDir() . $type . '/' . $id . '/';
    }

    /** Ensure base dir exists with a deny-all .htaccess, then the entity subdir. */
    public function ensureDir(string $type, int $id): string {
        $files = $this->module->wire('files');
        $base  = $this->baseDir();
        if (!is_dir($base)) {
            $files->mkdir($base, true);
            file_put_contents($base . '.htaccess', "Require all denied\nDeny from all\n");
        }
        $dir = $this->dirFor($type, $id);
        if (!is_dir($dir)) $files->mkdir($dir, true);
        return $dir;
    }

    public function get(int $id): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM vk_files WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->enrich($row) : null;
    }

    public function listFor(string $type, int $id): array {
        $stmt = $this->db()->prepare(
            "SELECT * FROM vk_files WHERE entity_type = :t AND entity_id = :id AND embedded = 0 ORDER BY created_at ASC, id ASC"
        );
        $stmt->execute([':t' => $type, ':id' => $id]);
        return array_map(fn($r) => $this->enrich($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function store(string $type, int $id, bool $embedded): array {
        $dir = $this->ensureDir($type, $id);
        $ul  = $this->module->wire(new WireUpload('files'));
        $ul->setMaxFiles(20);
        $ul->setMaxFileSize(self::MAX_BYTES);
        $ul->setValidExtensions(self::ALLOWED);
        $ul->setOverwrite(false);
        $ul->setLowercase(true);
        $ul->setDestinationPath($dir);
        $names = $ul->execute();

        $out = [];
        foreach ($names as $name) {
            $path = $dir . $name;
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $w = $h = null;
            if (in_array($ext, self::IMAGE_EXT, true)) {
                $size = @getimagesize($path);
                if ($size) { $w = (int) $size[0]; $h = (int) $size[1]; }
            }
            $stmt = $this->db()->prepare(
                "INSERT INTO vk_files (entity_type, entity_id, stored_name, original_name, ext, mime, size, width, height, embedded, uploaded_by, created_at)
                 VALUES (:t, :id, :sn, :on, :ext, :mime, :size, :w, :h, :emb, :uid, NOW())"
            );
            $stmt->execute([
                ':t' => $type, ':id' => $id, ':sn' => $name, ':on' => $name, ':ext' => $ext,
                ':mime' => mime_content_type($path) ?: 'application/octet-stream',
                ':size' => filesize($path) ?: 0, ':w' => $w, ':h' => $h,
                ':emb' => $embedded ? 1 : 0, ':uid' => (int) $this->module->wire('user')->id,
            ]);
            $row = $this->get((int) $this->db()->lastInsertId());
            if ($row) $out[] = $row;
        }
        return $out;
    }

    public function deleteFile(int $id): bool {
        $row = $this->getRaw($id);
        if (!$row) return false;
        $dir = $this->dirFor($row['entity_type'], (int) $row['entity_id']);
        @unlink($dir . $row['stored_name']);
        @unlink($dir . '.thumbs/' . $row['stored_name']);
        $this->db()->prepare("DELETE FROM vk_files WHERE id = :id")->execute([':id' => $id]);
        return true;
    }

    public function deleteForEntity(string $type, int $id): void {
        $stmt = $this->db()->prepare("SELECT id FROM vk_files WHERE entity_type = :t AND entity_id = :id");
        $stmt->execute([':t' => $type, ':id' => $id]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $fid) $this->deleteFile((int) $fid);
        @rmdir($this->dirFor($type, $id) . '.thumbs/');
        @rmdir($this->dirFor($type, $id));
    }

    public function streamPath(array $row): string {
        return $this->dirFor($row['entity_type'], (int) $row['entity_id']) . $row['stored_name'];
    }

    public function isImage(array $row): bool {
        return in_array(strtolower($row['ext']), self::IMAGE_EXT, true);
    }

    public function thumbPathFor(array $row): ?string {
        if (!$this->isImage($row)) return null;
        $dir   = $this->dirFor($row['entity_type'], (int) $row['entity_id']);
        $thumb = $dir . '.thumbs/' . $row['stored_name'];
        if (is_file($thumb)) return $thumb;
        $src = $dir . $row['stored_name'];
        if (!is_file($src)) return null;
        $this->module->wire('files')->mkdir($dir . '.thumbs/', true);
        copy($src, $thumb);
        $sizer = $this->module->wire(new ImageSizer($thumb));
        $sizer->setUpscaling(false);
        $sizer->resize(self::THUMB_MAX, self::THUMB_MAX);
        return is_file($thumb) ? $thumb : null;
    }

    protected function getRaw(int $id): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM vk_files WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    protected function enrich(array $row): array {
        $base = $this->module->page->url . '?view=file&id=' . (int) $row['id'];
        $row['is_image']   = $this->isImage($row);
        $row['url']        = $base;
        $row['thumb']      = $row['is_image'] ? $base . '&size=thumb' : null;
        $row['human_size'] = $this->humanSize((int) $row['size']);
        return $row;
    }

    public function humanSize(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['KB','MB','GB'];
        $n = $bytes / 1024; $i = 0;
        while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
        return round($n, 1) . ' ' . $units[$i];
    }
}
