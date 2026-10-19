
<?php

/* Sanctuario Farm Management System
   Shared Security Helpers
   PHP 8+ | XAMPP | PHPMailer Gmail SMTP
*/

/* -------------------------------
   SECURE SESSION
-------------------------------- */

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    if (
        isset($_SESSION['last_activity']) &&
        time() - (int) $_SESSION['last_activity'] > 1800
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['last_activity'] = time();
}

/* -------------------------------
   CSRF TOKEN
-------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool
{
    return isset($_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals(
            csrf_token(),
            $_POST['csrf_token']
        );
}

/* -------------------------------
   PASSWORD VALIDATION
-------------------------------- */

function password_is_strong(string $password): bool
{
    return strlen($password) >= 12
        && strlen($password) <= 72;
}

/* -------------------------------
   APPLICATION URL
-------------------------------- */

function app_url(string $file): string
{
    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9.:-]+$/', $host)) {
        throw new RuntimeException('Invalid host');
    }

    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';

    $dir = rtrim(
        str_replace(
            '\\',
            '/',
            dirname($scriptPath)
        ),
        '/'
    );

    return ($https ? 'https://' : 'http://')
        . $host
        . $dir
        . '/'
        . ltrim($file, '/');
}

/* -------------------------------
   GMAIL SMTP EMAIL SENDER
-------------------------------- */

function send_security_email(
    string $to,
    string $subject,
    string $body
): bool {

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    $configFile = __DIR__ . '/smtp_config.php';

    if (!is_file($autoload)) {
        error_log(
            'Sanctuario SMTP: Composer autoload not found.'
        );
        return false;
    }

    if (!is_file($configFile)) {
        error_log(
            'Sanctuario SMTP: smtp_config.php not found.'
        );
        return false;
    }

    try {

        require_once $autoload;

        $config = require $configFile;

        if (!is_array($config)) {
            throw new RuntimeException(
                'Invalid SMTP configuration.'
            );
        }

        $username = trim(
            (string) ($config['username'] ?? '')
        );

        $appPassword = preg_replace(
            '/\s+/',
            '',
            (string) ($config['app_password'] ?? '')
        );

        $fromEmail = trim(
            (string) ($config['from_email'] ?? '')
        );

        if (
            !filter_var($username, FILTER_VALIDATE_EMAIL)
            || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
            || $appPassword === ''
            || $appPassword === 'YOUR_GOOGLE_APP_PASSWORD'
        ) {
            throw new RuntimeException(
                'SMTP credentials are missing or invalid.'
            );
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = $config['host'] ?? 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = $username;

        $mail->Password = $appPassword;

        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = (int) ($config['port'] ?? 587);

        $mail->CharSet = 'UTF-8';

        $mail->Timeout = 20;

        $mail->setFrom(
            $fromEmail,
            $config['from_name']
                ?? 'Sanctuario Farm Management System'
        );

        $mail->addAddress($to);

        $mail->isHTML(false);

        $mail->Subject = $subject;

        $mail->Body = $body;

        $mail->send();

        return true;

    } catch (\Throwable $e) {

        error_log(
            'Sanctuario SMTP Error: ' . $e->getMessage()
        );

        return false;
    }
}
