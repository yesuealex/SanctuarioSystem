<?php
require_once dirname(__DIR__) . '/staff_access.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
if (!in_array(sanctuario_staff_role(), ['crops staff', 'livestock staff'], true)) { http_response_code(403); exit('Staff access only.'); }
header('Location: ../farmMap/farmMap.php', true, 302);
exit;
