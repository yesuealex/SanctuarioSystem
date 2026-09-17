<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { header("Location: ../adminDashboard.php"); exit(); }
require_once '../db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $priority = $_POST['priority'] ?? 'Medium';

            $allowedPriorities = ['Low','Medium','High','Urgent'];
            if ($title === '' || $description === '') throw new Exception('Title and description are required.');
            if (!in_array($priority, $allowedPriorities, true)) throw new Exception('Invalid priority.');

            $stmt = $conn->prepare("INSERT INTO maintenance_requests (title, description, location, priority, status, created_by) VALUES (?, ?, ?, ?, 'Pending', ?)");
            $adminId = (int)$_SESSION['user_id'];
            $stmt->bind_param("ssssi", $title, $description, $location, $priority, $adminId);
            $stmt->execute();
            $stmt->close();
            $message = 'Maintenance request created successfully.';
        }

        if ($action === 'status') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $allowed = ['Pending','Ongoing','Completed','Cancelled'];
            if ($requestId <= 0 || !in_array($status, $allowed, true)) throw new Exception('Invalid request or status.');

            if ($status === 'Completed') {
                $stmt = $conn->prepare("UPDATE maintenance_requests SET status=?, completed_at=NOW() WHERE request_id=?");
            } else {
                $stmt = $conn->prepare("UPDATE maintenance_requests SET status=?, completed_at=NULL WHERE request_id=?");
            }
            $stmt->bind_param("si", $status, $requestId);
            $stmt->execute();
            $stmt->close();
            $message = 'Request status updated.';
        }

        if ($action === 'delete') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            if ($requestId <= 0) throw new Exception('Invalid request.');

            $stmt = $conn->prepare("DELETE FROM maintenance_requests WHERE request_id=?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();
            $message = 'Maintenance request deleted.';
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$requests = [];
try {
    $result = $conn->query("
        SELECT mr.*, u.username AS assigned_name
        FROM maintenance_requests mr
        LEFT JOIN users u ON u.user_id = mr.assigned_to
        ORDER BY mr.request_id DESC
    ");
    while ($row = $result->fetch_assoc()) $requests[] = $row;
} catch (Throwable $ex) {
    $error = $error ?: 'Could not load maintenance requests. Make sure maintenance_tables.sql has been imported.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Requests | Sanctuario Nature Farm</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="../sidebar.css"><link rel="stylesheet" href="maintenance.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Material+Symbols+Rounded:wght@400;500;600" rel="stylesheet">
<style>
.page{padding:24px}.box{background:#fff;border-radius:16px;padding:22px;margin-bottom:20px;box-shadow:0 4px 18px rgba(0,0,0,.04)}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.form-grid .full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}input,textarea,select{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #dce4de;border-radius:9px;font:14px Poppins}textarea{min-height:90px;resize:vertical}.btn{padding:9px 13px;border:0;border-radius:8px;background:#2f6f45;color:#fff;font-weight:600;cursor:pointer}.btn.danger{background:#a33a3a}.msg{padding:12px;border-radius:9px;background:#eaf6ed;color:#27623a;margin-bottom:14px}.err{padding:12px;border-radius:9px;background:#fff0ef;color:#9b2c2c;margin-bottom:14px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:11px 9px;border-bottom:1px solid #e8ece9;text-align:left;font-size:13px}th{font-weight:600}.inline{display:flex;gap:7px;align-items:center}.inline select{min-width:120px}.muted{color:#718077;font-size:12px}
@media(max-width:700px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}
</style>
</head>
<body>
<div class="layout"><?php include '../sidebar.php'; ?><main class="dashboard"><?php include '../topRightBar.php'; ?>
<div class="page">
<h1>Maintenance Requests</h1><p>Admin can create, review, update, and remove maintenance requests.</p>
<?php if($message): ?><div class="msg"><?=e($message)?></div><?php endif; ?>
<?php if($error): ?><div class="err"><?=e($error)?></div><?php endif; ?>

<div class="box">
<h2>Create Request</h2>
<form method="post">
<input type="hidden" name="action" value="create">
<div class="form-grid">
<div><label>Title</label><input name="title" required maxlength="150" placeholder="e.g. Repair greenhouse roof"></div>
<div><label>Location</label><input name="location" maxlength="150" placeholder="e.g. Greenhouse A"></div>
<div class="full"><label>Description</label><textarea name="description" required placeholder="Describe the maintenance problem or work needed."></textarea></div>
<div><label>Priority</label><select name="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></div>
<div style="display:flex;align-items:end"><button class="btn" type="submit">Create Request</button></div>
</div>
</form>
</div>

<div class="box">
<h2>All Requests</h2>
<div class="table-wrap"><table>
<thead><tr><th>#</th><th>Request</th><th>Location</th><th>Priority</th><th>Assigned</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php if(!$requests): ?><tr><td colspan="7">No requests found.</td></tr><?php else: foreach($requests as $r): ?>
<tr>
<td><?=e($r['request_id'])?></td>
<td><strong><?=e($r['title'])?></strong><br><span class="muted"><?=e($r['description'])?></span></td>
<td><?=e($r['location'] ?: '—')?></td>
<td><?=e($r['priority'])?></td>
<td><?=e($r['assigned_name'] ?: 'Unassigned')?></td>
<td>
<form method="post" class="inline">
<input type="hidden" name="action" value="status"><input type="hidden" name="request_id" value="<?=e($r['request_id'])?>">
<select name="status">
<?php foreach(['Pending','Ongoing','Completed','Cancelled'] as $s): ?><option <?=($r['status']===$s?'selected':'')?> value="<?=e($s)?>"><?=e($s)?></option><?php endforeach; ?>
</select><button class="btn" type="submit">Save</button>
</form>
</td>
<td>
<form method="post" onsubmit="return confirm('Delete this maintenance request?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="request_id" value="<?=e($r['request_id'])?>"><button class="btn danger" type="submit">Delete</button></form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<p style="margin-top:15px"><a href="assignMaintenance.php">Assign maintenance staff →</a></p>
</div>
</div>
</main></div>
</body></html>
