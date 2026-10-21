<?php
/** Shared database-backed throttling, using hashed client IP and identifier. */
function login_limit_key(string $identifier): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip . '|' . strtolower(trim($identifier)));
}
function login_ip_key(): string {
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}
function login_rate_check(mysqli $conn, string $identifier): bool {
    $keys = [login_limit_key($identifier), login_ip_key()];
    foreach ($keys as $key) {
        $s = $conn->prepare('SELECT attempts, window_start, blocked_until FROM login_rate_limits WHERE rate_key = ?');
        $s->bind_param('s', $key); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close();
        if ($r && $r['blocked_until'] && strtotime($r['blocked_until']) > time()) return false;
    }
    return true;
}
function login_rate_failure(mysqli $conn, string $identifier): void {
    foreach ([[login_limit_key($identifier), 5], [login_ip_key(), 25]] as [$key,$max]) {
        $s=$conn->prepare('INSERT INTO login_rate_limits (rate_key, attempts, window_start, blocked_until) VALUES (?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE attempts=IF(window_start < NOW()-INTERVAL 15 MINUTE,1,attempts+1), window_start=IF(window_start < NOW()-INTERVAL 15 MINUTE,NOW(),window_start), blocked_until=IF(IF(window_start < NOW()-INTERVAL 15 MINUTE,1,attempts+1) >= ?, NOW()+INTERVAL 15 MINUTE,blocked_until)');
        $s->bind_param('si',$key,$max); $s->execute(); $s->close();
    }
}
function login_rate_success(mysqli $conn, string $identifier): void {
    $key=login_limit_key($identifier);
    $s=$conn->prepare('DELETE FROM login_rate_limits WHERE rate_key = ?'); $s->bind_param('s',$key);$s->execute();$s->close();
}