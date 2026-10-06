<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

function notificationMeta(string $action): array
{
    $action = strtolower(trim($action));
    if (strpos($action, 'delete') !== false || strpos($action, 'permanent') !== false) return ['activity_type' => 'danger', 'icon' => 'delete'];
    if (strpos($action, 'archive') !== false) return ['activity_type' => 'warning', 'icon' => 'inventory'];
    if (strpos($action, 'login') !== false) return ['activity_type' => 'success', 'icon' => 'login'];
    if (strpos($action, 'logout') !== false) return ['activity_type' => 'info', 'icon' => 'logout'];
    if (strpos($action, 'add') !== false || strpos($action, 'transfer') !== false || strpos($action, 'restore') !== false) return ['activity_type' => 'success', 'icon' => 'check_circle'];
    if (strpos($action, 'edit') !== false || strpos($action, 'update') !== false) return ['activity_type' => 'info', 'icon' => 'edit'];
    return ['activity_type' => 'info', 'icon' => 'info'];
}

function relativeTime(?string $datetime): string
{
    if (!$datetime) return '';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '';
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) return 'Just now';
    $minutes = intdiv($seconds, 60);
    if ($minutes < 60) return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    $hours = intdiv($minutes, 60);
    if ($hours < 24) return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    $days = intdiv($hours, 24);
    if ($days === 1) return 'Yesterday';
    if ($days < 7) return $days . ' days ago';
    return date('M d, Y', $timestamp);
}

$since = trim((string)($_GET['since'] ?? ''));
$sinceSql = '';
if ($since !== '') {
    $sinceDate = DateTime::createFromFormat('Y-m-d\TH:i:s', $since)
        ?: DateTime::createFromFormat('Y-m-d H:i:s', $since)
        ?: DateTime::createFromFormat(DateTime::ATOM, $since);
    if ($sinceDate) $sinceSql = " AND al.created_at > '" . $conn->real_escape_string($sinceDate->format('Y-m-d H:i:s')) . "'";
}
if ($sinceSql === '') $sinceSql = ' AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';

$limit = min(50, max(8, (int)($_GET['limit'] ?? 8)));
$activities = [];
$sql = "SELECT al.log_id AS activity_id, al.user_id, al.action, al.details AS description, al.created_at, u.name, u.username, u.role
        FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC LIMIT {$limit}";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $meta = notificationMeta((string)($row['action'] ?? ''));
        $row = array_merge($row, $meta);
        $row['staff_name'] = trim((string)($row['name'] ?? '')) ?: ((string)($row['username'] ?? 'Staff'));
        $row['role_label'] = ucwords(str_replace('_', ' ', trim((string)($row['role'] ?? 'staff'))));
        $row['relative_time'] = relativeTime($row['created_at'] ?? null);
        $activities[] = $row;
    }
}

$count = 0;
$countSql = "SELECT COUNT(*) AS total FROM activity_logs al WHERE 1=1 {$sinceSql}";
if ($result = $conn->query($countSql)) $count = (int)($result->fetch_assoc()['total'] ?? 0);

$latest = $activities[0]['created_at'] ?? null;
echo json_encode(['ok' => true, 'activities' => $activities, 'unread_count' => min($count, 99), 'latest_created_at' => $latest], JSON_UNESCAPED_UNICODE);
