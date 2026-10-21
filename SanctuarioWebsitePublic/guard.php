<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator only.');
}
if (empty($_SESSION['cms_csrf'])) { $_SESSION['cms_csrf'] = bin2hex(random_bytes(32)); }
