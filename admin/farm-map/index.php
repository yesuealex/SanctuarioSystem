<?php
require_once dirname(__DIR__, 2) . '/security.php';
secure_session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','super admin'], true)) { http_response_code(403); exit('Admin access only'); }
header('Location: ../../farmMap/farmMap.php', true, 302);
exit;
