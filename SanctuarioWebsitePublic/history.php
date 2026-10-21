<?php
require __DIR__ . '/guard.php';
require_once __DIR__ . '/history_store.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function history_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}
try {
    $action = (string)($_GET['action'] ?? 'list');
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['ok' => true, 'entries' => cms_history_list()]);
        exit;
    }
    if ($action === 'detail' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (string)($_GET['id'] ?? '');
        $entry = cms_history_read($id);
        $previous = '';
        $entries = array_reverse(cms_history_list());
        foreach ($entries as $index => $item) {
            if ($item['id'] === $id && $index > 0) {
                $previous = cms_history_read($entries[$index - 1]['id'])['html'];
                break;
            }
        }
        echo json_encode(['ok' => true, 'entry' => $entry['meta'], 'html' => $entry['html'], 'previous_html' => $previous]);
        exit;
    }
    if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals((string)($_SESSION['cms_csrf'] ?? ''), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            history_fail('Invalid security token', 403);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $id = is_array($data) ? (string)($data['id'] ?? '') : '';
        $selected = cms_history_read($id);
        $draftDir = __DIR__ . '/drafts';
        if (!is_dir($draftDir) && !mkdir($draftDir, 0750, true)) {
            history_fail('Cannot create draft folder', 500);
        }
        $draftFile = $draftDir . '/index.html';
        $current = is_file($draftFile) ? file_get_contents($draftFile) : false;
        if ($current === false) {
            $live = __DIR__ . '/../SanctuarioWebsitePublic/index.html';
            $current = is_file($live) ? file_get_contents($live) : false;
        }
        if ($current !== false) cms_history_record('before_restore', $current);
        $temp = tempnam($draftDir, 'restore_');
        if (!$temp || file_put_contents($temp, $selected['html'], LOCK_EX) === false || !rename($temp, $draftFile)) {
            if ($temp) @unlink($temp);
            history_fail('Could not restore draft', 500);
        }
        cms_history_record('draft', $selected['html']);
        echo json_encode(['ok' => true]);
        exit;
    }
    history_fail('Unknown history action', 405);
} catch (Throwable $e) {
    history_fail($e->getMessage(), 500);
}
