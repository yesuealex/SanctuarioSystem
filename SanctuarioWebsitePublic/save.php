<?php
require __DIR__ . '/guard.php';
header('Content-Type: application/json; charset=utf-8');
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')fail('Invalid method',405);
if(!hash_equals($_SESSION['cms_csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail('Invalid security token',403);
$d=json_decode(file_get_contents('php://input'),true);$html=$d['html']??null;
if(!is_string($html)||strlen($html)>2500000||stripos($html,'<html')===false||stripos($html,'</html>')===false)fail('Invalid website document');
if(preg_match('/<\?(?:php|=)/i',$html))fail('Server-side code is not allowed');
$dir=__DIR__.'/drafts';if(!is_dir($dir)&&!mkdir($dir,0750,true))fail('Cannot create drafts folder',500);
$tmp=tempnam($dir,'draft_');if(!$tmp)fail('Cannot prepare draft',500);
if(file_put_contents($tmp,$html,LOCK_EX)===false||!rename($tmp,$dir.'/index.html')){@unlink($tmp);fail('Cannot save draft',500);}
require_once __DIR__.'/history_store.php';
cms_history_record('draft', $html);
echo json_encode(['ok'=>true]);
