
<?php

require_once __DIR__ . '/security.php';

header('Content-Type: text/plain; charset=UTF-8');

// Replace with an email inbox YOU can access.
$recipient = 'alecxafrancine@gmail.com';

$sent = send_security_email(
    $recipient,
    'Sanctuario SMTP Test',
    'Hello! This is a test email from Sanctuario System.'
);

if ($sent) {
    echo 'SUCCESS: SMTP accepted the email.';
} else {
    echo 'FAILED: Email could not be sent. Check PHP error logs.';
}
