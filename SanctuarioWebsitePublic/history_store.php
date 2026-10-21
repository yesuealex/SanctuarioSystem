<?php
/** Private CMS version snapshots, outside the htdocs document root on standard XAMPP. */
function cms_history_dir(): string {
    $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sanctuario_cms_history';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create private CMS history folder');
    }
    return $dir;
}
function cms_history_actor(): string {
    $candidate = $_SESSION['username'] ?? $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Admin';
    return substr(is_scalar($candidate) ? (string)$candidate : 'Admin', 0, 100);
}
function cms_history_record(string $type, string $html): array {
    if (!in_array($type, ['draft', 'published', 'before_restore'], true)) {
        throw new RuntimeException('Invalid version type');
    }
    $dir = cms_history_dir();
    $id = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(6));
    $meta = [
        'id' => $id,
        'type' => $type,
        'timestamp' => gmdate('c'),
        'actor' => cms_history_actor(),
        'sha256' => hash('sha256', $html)
    ];
    $htmlFile = $dir . DIRECTORY_SEPARATOR . $id . '.html';
    $jsonFile = $dir . DIRECTORY_SEPARATOR . $id . '.json';
    if (file_put_contents($htmlFile, $html, LOCK_EX) === false) {
        throw new RuntimeException('Could not save history snapshot');
    }
    if (file_put_contents($jsonFile, json_encode($meta, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        @unlink($htmlFile);
        throw new RuntimeException('Could not save history metadata');
    }
    return $meta;
}
function cms_history_valid_id(string $id): bool {
    return (bool)preg_match('/^\d{8}_\d{6}_[a-f0-9]{12}$/D', $id);
}
function cms_history_read(string $id): array {
    if (!cms_history_valid_id($id)) throw new RuntimeException('Invalid version identifier');
    $dir = cms_history_dir();
    $json = $dir . DIRECTORY_SEPARATOR . $id . '.json';
    $html = $dir . DIRECTORY_SEPARATOR . $id . '.html';
    if (!is_file($json) || !is_file($html)) throw new RuntimeException('Version not found');
    $meta = json_decode((string)file_get_contents($json), true);
    $content = file_get_contents($html);
    if (!is_array($meta) || $content === false || !hash_equals((string)($meta['sha256'] ?? ''), hash('sha256', $content))) {
        throw new RuntimeException('History snapshot failed integrity check');
    }
    return ['meta' => $meta, 'html' => $content];
}
function cms_history_list(): array {
    $files = glob(cms_history_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
    rsort($files, SORT_STRING);
    $entries = [];
    foreach (array_slice($files, 0, 100) as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data) && isset($data['id'], $data['timestamp'], $data['type'])) $entries[] = $data;
    }
    return $entries;
}
