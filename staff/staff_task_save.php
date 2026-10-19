<?php
require_once dirname(__DIR__) . '/staff_access.php';
header('Content-Type: application/json; charset=utf-8');
function task_response($status, $message) { http_response_code($status); echo json_encode(['ok' => $status === 200, 'error' => $status === 200 ? null : $message]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id']) || !in_array(sanctuario_staff_role(), ['crops staff','livestock staff'], true) || !csrf_verify()) task_response(403, 'Unauthorized request');
require_once dirname(__DIR__) . '/db.php';
$userId = (int)$_SESSION['user_id'];
$section = sanctuario_staff_role() === 'crops staff' ? 'crops' : 'livestock';
$action = (string)($_POST['action'] ?? '');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
try {
 if ($action === 'save') {
  $title = trim((string)($_POST['title'] ?? ''));
  $start = (string)($_POST['start_date'] ?? ''); $due = (string)($_POST['due_date'] ?? '');
  if ($title === '' || mb_strlen($title) > 180) task_response(400, 'Enter a task description (max 180 characters).');
  foreach ([$start,$due] as $d) { $date = DateTimeImmutable::createFromFormat('!Y-m-d', $d); if (!$date || $date->format('Y-m-d') !== $d) task_response(400, 'Invalid date.'); }
  if ($due < $start) task_response(400, 'Due date cannot be before start date.');
  if ($id) {
   $stmt=$conn->prepare('UPDATE staff_custom_tasks SET title=?, start_date=?, due_date=? WHERE id=? AND user_id=? AND section=?');
   $stmt->bind_param('sssiis',$title,$start,$due,$id,$userId,$section);
  } else {
   $stmt=$conn->prepare('INSERT INTO staff_custom_tasks (user_id,section,title,start_date,due_date) VALUES (?,?,?,?,?)');
   $stmt->bind_param('issss',$userId,$section,$title,$start,$due);
  }
 } elseif ($action === 'toggle' && $id && in_array($_POST['completed'] ?? '', ['0','1'],true)) {
  $completed=(int)$_POST['completed'];
  $stmt=$conn->prepare('UPDATE staff_custom_tasks SET completed=?, completed_at=IF(?=1,NOW(),NULL) WHERE id=? AND user_id=? AND section=?');
  $stmt->bind_param('iiiis',$completed,$completed,$id,$userId,$section);
 } elseif ($action === 'delete' && $id) {
  $stmt=$conn->prepare('DELETE FROM staff_custom_tasks WHERE id=? AND user_id=? AND section=?');
  $stmt->bind_param('iis',$id,$userId,$section);
 } else task_response(400, 'Invalid task request.');
 $stmt->execute();$stmt->close();task_response(200,'');
} catch(Throwable $e) { error_log('Staff task save: '.$e->getMessage()); task_response(500, 'Could not save task. Check staff_tasks_update.sql installation.'); }
