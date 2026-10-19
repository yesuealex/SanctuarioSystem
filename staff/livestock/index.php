<?php
require_once dirname(__DIR__, 2) . '/staff_access.php';
sanctuario_staff_guard('livestock');
header('Location: ../../livestock/livestocks.php', true, 302);
exit;
