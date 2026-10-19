<?php
require_once dirname(__DIR__, 2) . '/staff_access.php';
sanctuario_staff_guard('crops');
header('Location: ../../crops/crops.php', true, 302);
exit;
