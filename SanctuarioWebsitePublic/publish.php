
<?php
require __DIR__ . '/guard.php';

header('Content-Type: application/json; charset=utf-8');

function fail($message, $code = 400)
{
    http_response_code($code);

    echo json_encode([
        'ok' => false,
        'error' => $message
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

if (!hash_equals(
    $_SESSION['cms_csrf'] ?? '',
    $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''
)) {
    fail('Invalid security token.', 403);
}

$websiteDir = dirname(__DIR__) . '/SanctuarioWebsite/SanctuarioWebsite';
$live = $websiteDir . '/index.php';
$draft = __DIR__ . '/drafts/index.html';

if (!is_dir($websiteDir) || !is_file($live)) {
    fail('Actual landing page not found.', 404);
}

if (!is_file($draft)) {
    fail('No saved draft found.', 404);
}

$html = file_get_contents($draft);

if (
    $html === false ||
    stripos($html, '<html') === false ||
    stripos($html, '</html>') === false ||
    strlen($html) > 2500000
) {
    fail('Invalid website draft.');
}

// Prevent executable PHP from being introduced by CMS edits.
if (preg_match('/<\?(?:php|=)/i', $html)) {
    fail('PHP code cannot be published through the editor.');
}

// Remove preview-only base elements before publishing.
$html = preg_replace(
    '/<base\b[^>]*data-cms-preview-base=["\']1["\'][^>]*>/i',
    '',
    $html
);

// Preserve the existing live website before publishing.
$backupDir = __DIR__ . '/drafts/backups';

if (
    !is_dir($backupDir) &&
    !mkdir($backupDir, 0750, true)
) {
    fail('Cannot create backup directory.', 500);
}

$backup = $backupDir
    . '/index_'
    . date('Ymd_His')
    . '_'
    . bin2hex(random_bytes(3))
    . '.php';

if (!copy($live, $backup)) {
    fail('Could not back up the landing page.', 500);
}

// Prepare the new website file.
$tmp = tempnam($websiteDir, 'publish_');

if ($tmp === false) {
    fail('Cannot prepare publication.', 500);
}

if (file_put_contents($tmp, $html, LOCK_EX) === false) {
    @unlink($tmp);
    fail('Cannot write published website.', 500);
}

if (!rename($tmp, $live)) {
    @unlink($tmp);
    fail('Could not update the landing page.', 500);
}

// Remove the published draft.
$cleared = @unlink($draft);

// Keep existing CMS history functionality.
require_once __DIR__ . '/history_store.php';

cms_history_record('published', $html);

echo json_encode([
    'ok' => true,
    'warning' => $cleared
        ? null
        : 'Published, but the draft could not be cleared.'
]);
