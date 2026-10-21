
<?php
require __DIR__ . '/guard.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$websiteDir = dirname(__DIR__)
    . '/SanctuarioWebsite/SanctuarioWebsite';

$live = $websiteDir . '/index.php';
$draft = __DIR__ . '/drafts/index.html';

$source = is_file($draft) ? $draft : $live;

if (!is_file($source)) {
    http_response_code(404);
    exit('Website preview not found.');
}

$html = file_get_contents($source);

if ($html === false) {
    http_response_code(500);
    exit('Cannot load website preview.');
}

// Build the actual public URL from the CMS location.
$systemUrl = rtrim(
    str_replace(
        '\\',
        '/',
        dirname(dirname($_SERVER['SCRIPT_NAME']))
    ),
    '/'
);

$websiteUrl = $systemUrl
    . '/SanctuarioWebsite/SanctuarioWebsite/';

// Remove any previously inserted CMS base element.
$html = preg_replace(
    '~<base\b[^>]*data-cms-preview-base\s*=\s*(["\'])1\1[^>]*>~i',
    '',
    $html
);

// Insert the correct base URL into the HTML head.
$base = '<base href="'
    . htmlspecialchars(
        $websiteUrl,
        ENT_QUOTES,
        'UTF-8'
    )
    . '" data-cms-preview-base="1">';

$html = preg_replace(
    '/(<head\b[^>]*>)/i',
    '$1' . $base,
    $html,
    1
);

echo $html;
