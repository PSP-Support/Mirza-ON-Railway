<?php
function mirza_broadcast_create(PDO $pdo, array $info, array $users): int {
 $pdo->beginTransaction();
 try {
  $active=$pdo->query("SELECT id FROM broadcast_queue WHERE status='active' LIMIT 1 FOR UPDATE")->fetchColumn();
  if($active) throw new RuntimeException('A broadcast is already active.');
  $q=$pdo->prepare("INSERT INTO broadcast_queue(admin_id,message_id,type,payload,total_count) VALUES(?,?,?,?,?)");
  $q->execute([(int)$info['id_admin'],(int)$info['id_message'],(string)$info['type'],json_encode($info,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),count($users)]);
  $qid=(int)$pdo->lastInsertId(); $ins=$pdo->prepare("INSERT IGNORE INTO broadcast_recipients(queue_id,user_id) VALUES(?,?)");
  foreach($users as $u){$id=is_object($u)?($u->id??null):(is_array($u)?($u['id']??null):null);if($id!==null&&is_numeric($id))$ins->execute([$qid,(int)$id]);}
  $pdo->commit(); return $qid;
 } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function mirza_broadcast_cancel(PDO $pdo,int $adminId):void {
 $s=$pdo->prepare("UPDATE broadcast_queue SET status='canceled' WHERE status='active' AND admin_id=?");$s->execute([$adminId]);
}
