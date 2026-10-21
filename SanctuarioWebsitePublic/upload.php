<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

function uploadFail(string $message, int $status = 400): void
{
    http_response_code($status);

    echo json_encode([
        'ok' => false,
        'error' => $message
    ]);

    exit;
}

try {
    require_once __DIR__ . '/guard.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        uploadFail('Invalid request method.', 405);
    }

    $sessionToken = $_SESSION['cms_csrf'] ?? '';
    $requestToken = $_POST['csrf'] ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($requestToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $requestToken)
    ) {
        uploadFail('Invalid security token.', 403);
    }

    if (!isset($_FILES['image'])) {
        uploadFail('No image selected.');
    }

    $file = $_FILES['image'];

    if (
        !is_array($file) ||
        !isset($file['error']) ||
        is_array($file['error'])
    ) {
        uploadFail('Invalid image upload.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'Image was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No image selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write uploaded file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension.'
        ];

        uploadFail(
            $errors[$file['error']] ?? 'Image upload failed.'
        );
    }

    if (
        !isset($file['size'], $file['tmp_name']) ||
        $file['size'] <= 0 ||
        $file['size'] > 5 * 1024 * 1024
    ) {
        uploadFail('Image must be less than 5 MB.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        uploadFail('Invalid uploaded image.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($file['tmp_name']);

    if (
        !isset($allowedTypes[$mime]) ||
        getimagesize($file['tmp_name']) === false
    ) {
        uploadFail('Only JPG, PNG, WebP and GIF are allowed.');
    }

    // Save images directly in the actual public website.
    $websiteDir = dirname(__DIR__)
        . '/SanctuarioWebsite/SanctuarioWebsite';

    if (!is_dir($websiteDir)) {
        uploadFail('Public website folder not found.', 500);
    }

    $uploadDir = $websiteDir . '/images';

    if (!is_dir($uploadDir)) {
        if (
            !mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            uploadFail('Cannot create website images folder.', 500);
        }
    }

    if (!is_writable($uploadDir)) {
        uploadFail('Website images folder is not writable.', 500);
    }

    $filename = 'cms_'
        . bin2hex(random_bytes(12))
        . '.'
        . $allowedTypes[$mime];

    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        uploadFail('Cannot save image to website folder.', 500);
    }

    // This relative path works inside the real website.
    $relativePath = 'images/' . $filename;

    echo json_encode([
        'ok' => true,
        'path' => $relativePath
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('CMS Upload Error: ' . $e->getMessage());

    uploadFail(
        'Server error while uploading image. Check PHP error log.',
        500
    );
}
