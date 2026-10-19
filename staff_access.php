<?php
require_once __DIR__ . '/security.php';
secure_session_start();
function sanctuario_staff_role(): string { return (string)($_SESSION['role'] ?? ''); }
function sanctuario_staff_is_admin(): bool { return in_array(sanctuario_staff_role(), ['admin','super admin'], true); }
function sanctuario_staff_can_access(string $section): bool {
    $role = sanctuario_staff_role();
    if (sanctuario_staff_is_admin()) return true;
    if ($role === 'crops staff') return $section === 'crops';
    if ($role === 'livestock staff') return $section === 'livestock';
    return false;
}
function sanctuario_staff_guard(string $section): void {
    if (empty($_SESSION['user_id'])) { header('Location: /SanctuarioSystem/login.php'); exit; }
    if (!sanctuario_staff_can_access($section)) { http_response_code(403); exit('Access denied for your assigned staff role.'); }
}