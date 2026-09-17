<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (($_SESSION['role'] ?? '') !== 'maintenance') { header("Location: ../adminDashboard.php"); exit(); }
require_once '../db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Maintenance Staff';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    try {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($requestId <= 0 || !in_array($status, ['Pending','Ongoing','Completed'], true)) {
            throw new Exception('Invalid request or status.');
        }

        if ($status === 'Completed') {
            $stmt = $conn->prepare("UPDATE maintenance_requests SET status=?, completed_at=NOW() WHERE request_id=? AND assigned_to=?");
        } else {
            $stmt = $conn->prepare("UPDATE maintenance_requests SET status=?, completed_at=NULL WHERE request_id=? AND assigned_to=?");
        }
        $stmt->bind_param("sii", $status, $requestId, $userId);
        $stmt->execute();
        $stmt->close();
        $message = 'Task status updated.';
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$totalRequests=$pendingRequests=$ongoingRequests=$completedRequests=0;
$tasks=[];

try {
    $stmt=$conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status='Pending') AS pending,
            SUM(status='Ongoing') AS ongoing,
            SUM(status='Completed') AS completed
        FROM maintenance_requests
        WHERE assigned_to=?
    ");
    $stmt->bind_param("i",$userId);
    $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalRequests=(int)($row['total']??0);
    $pendingRequests=(int)($row['pending']??0);
    $ongoingRequests=(int)($row['ongoing']??0);
    $completedRequests=(int)($row['completed']??0);

    $stmt=$conn->prepare("SELECT * FROM maintenance_requests WHERE assigned_to=? ORDER BY request_id DESC LIMIT 10");
    $stmt->bind_param("i",$userId);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc())$tasks[]=$row;
    $stmt->close();
} catch(Throwable $ex) {
    $error='Maintenance data could not be loaded. Run maintenance_tables.sql first.';
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Maintenance Staff | Sanctuario Nature Farm</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="../sidebar.css"><link rel="stylesheet" href="maintenance.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Material+Symbols+Rounded:wght@400;500;600" rel="stylesheet">
<style>
.page{padding:24px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0}.card,.box{background:#fff;border-radius:15px;padding:18px}.card span{display:block;color:#718077;font-size:13px}.card strong{font-size:27px}.msg,.err{padding:12px;border-radius:9px;margin-bottom:14px}.msg{background:#eaf6ed;color:#27623a}.err{background:#fff0ef;color:#9b2c2c}.task{display:flex;justify-content:space-between;gap:15px;padding:16px 0;border-bottom:1px solid #e8ece9}.task h3{margin:0 0 5px}.task p{margin:0;color:#68756d;font-size:13px}.task form{display:flex;gap:7px;align-items:center}.task select,.btn{padding:8px 10px;border-radius:8px;border:1px solid #dce4de;font:13px Poppins}.btn{background:#2f6f45;color:#fff;border:0;cursor:pointer}.muted{font-size:12px;color:#718077}@media(max-width:800px){.cards{grid-template-columns:repeat(2,1fr)}.task{flex-direction:column}}
</style>
</head><body><div class="layout"><?php include '../sidebar.php';?><main class="dashboard"><?php include '../topRightBar.php';?><div class="page">
<h1>Maintenance</h1><p>Welcome back, <?=e($userName)?>. These are the maintenance tasks assigned to your account.</p>
<?php if($message):?><div class="msg"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="err"><?=e($error)?></div><?php endif;?>
<div class="cards"><div class="card"><span>Total Assigned</span><strong><?=$totalRequests?></strong></div><div class="card"><span>Pending</span><strong><?=$pendingRequests?></strong></div><div class="card"><span>Ongoing</span><strong><?=$ongoingRequests?></strong></div><div class="card"><span>Completed</span><strong><?=$completedRequests?></strong></div></div>
<div class="box"><h2>My Tasks</h2>
<?php if(!$tasks):?><p>No tasks have been assigned to you yet.</p><?php else:foreach($tasks as $task):?>
<div class="task"><div><h3><?=e($task['title'])?></h3><p><?=e($task['description'])?></p><p class="muted"><?=e($task['location']?:'No location')?> · Priority: <?=e($task['priority'])?> · Request #<?=e($task['request_id'])?></p></div>
<form method="post"><input type="hidden" name="action" value="status"><input type="hidden" name="request_id" value="<?=e($task['request_id'])?>"><select name="status"><?php foreach(['Pending','Ongoing','Completed'] as $s):?><option value="<?=e($s)?>" <?=($task['status']===$s?'selected':'')?>><?=e($s)?></option><?php endforeach;?></select><button class="btn" type="submit">Update</button></form>
</div>
<?php endforeach;endif;?>
</div></div></main></div></body></html>