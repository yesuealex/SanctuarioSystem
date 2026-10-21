<?php
// Run ONCE in command line: php hash_legacy_passwords_cli.php --confirm
// Never place this script on a public web server.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--confirm') {fwrite(STDERR,"CLI only: php hash_legacy_passwords_cli.php --confirm\n");exit(1);}
require __DIR__.'/db.php';
$result=$conn->query('SELECT user_id,password FROM users');
$changed=0;
while($row=$result->fetch_assoc()) {
 $old=(string)$row['password'];
 if (password_get_info($old)['algo'] !== null) continue;
 $new=password_hash($old,PASSWORD_DEFAULT);
 $s=$conn->prepare('UPDATE users SET password=? WHERE user_id=? AND password=?');
 $id=(int)$row['user_id'];$s->bind_param('sis',$new,$id,$old);$s->execute();$changed+=$s->affected_rows;$s->close();
}
echo "Converted {$changed} legacy passwords to hashes. Require users to change any previously exposed passwords.\n";