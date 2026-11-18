<?php
chdir(__DIR__); date_default_timezone_set('Asia/Tehran');
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../botapi.php'; require_once __DIR__.'/../function.php'; require_once __DIR__.'/broadcast_queue.php';
$textbotlang=languagechange();
$queue=$pdo->query("SELECT * FROM broadcast_queue WHERE status='active' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$queue){error_log('[Mirza broadcast] no active MySQL queue.');return;}
$info=json_decode($queue['payload'],true);$qid=(int)$queue['id'];$adminId=(int)$queue['admin_id'];$messageId=(int)$queue['message_id'];
if(!is_array($info)){$pdo->prepare("UPDATE broadcast_queue SET status='failed' WHERE id=?")->execute([$qid]);error_log('[Mirza broadcast] invalid queue payload.');return;}
$p=$pdo->prepare("SELECT id,user_id FROM broadcast_recipients WHERE queue_id=? AND status='pending' ORDER BY id LIMIT 20");$p->execute([$qid]);$rows=$p->fetchAll(PDO::FETCH_ASSOC);
if(!$rows){deletemessage($adminId,$messageId);sendmessage($adminId,$textbotlang['Admin']['messageBulk']['done'],null,'HTML');$pdo->prepare("UPDATE broadcast_queue SET status='completed' WHERE id=?")->execute([$qid]);return;}
$remaining=(int)$pdo->query("SELECT COUNT(*) FROM broadcast_recipients WHERE queue_id=$qid AND status='pending'")->fetchColumn();
$cancel=json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['keyboard']['cancelOperation'],'callback_data'=>'cancel_sendmessage']]]]);
Editmessagetext($adminId,$messageId,sprintf($textbotlang['Admin']['messageBulk']['progress'],$remaining),$cancel);
$buttons=[
'buy'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['textbot']['sell'],'callback_data'=>'buy']]]]),
'start'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['keyboard']['start'],'callback_data'=>'start']]]]),
'usertestbtn'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['textbot']['userTest'],'callback_data'=>'usertestbtn']]]]),
'helpbtn'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['textbot']['help'],'callback_data'=>'helpbtn']]]]),
'affiliatesbtn'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['textbot']['affiliates'],'callback_data'=>'affiliatesbtn']]]]),
'addbalance'=>json_encode(['inline_keyboard'=>[[['text'=>$textbotlang['textbot']['addBalance'],'callback_data'=>'Add_Balance']]]])
];
$sent=0;$failed=0;
foreach($rows as $row){
 $uid=(int)$row['user_id'];$ok=false;$err='';
 try{
  if($info['type']==='unpinmessage'){unpinmessage($uid);$ok=true;}
  elseif(in_array($info['type'],['sendmessage','xdaynotmessage'],true)){
   $btn=(string)($info['btnmessage']??'none');$res=sendmessage($uid,(string)$info['message'],$btn==='none'?null:($buttons[$btn]??null),'HTML');
   $ok=is_array($res)&&!empty($res['ok']);if(!$ok)$err=(string)($res['description']??'Telegram API error');
   if($ok&&($info['pingmessage']??'no')==='yes')pinmessage($uid,$res['result']['message_id']);
  }elseif($info['type']==='forwardmessage'){
   $res=forwardMessage($adminId,$info['message'],$uid);$ok=is_array($res)&&!empty($res['ok']);if(!$ok)$err=(string)($res['description']??'Telegram API error');
   if($ok&&($info['pingmessage']??'no')==='yes')pinmessage($uid,$res['result']['message_id']);
  }else $err='Unsupported broadcast type';
 }catch(Throwable $e){$err=$e->getMessage();}
 $st=$ok?'sent':'failed';$u=$pdo->prepare("UPDATE broadcast_recipients SET status=?,attempts=attempts+1,last_error=? WHERE id=? AND status='pending'");$u->execute([$st,substr($err,0,500)?:null,$row['id']]);
 if($ok)$sent++;else $failed++;
}
$pdo->prepare("UPDATE broadcast_queue SET sent_count=sent_count+?,failed_count=failed_count+? WHERE id=?")->execute([$sent,$failed,$qid]);
error_log('[Mirza broadcast] MySQL batch finished; sent='.$sent.'; failed='.$failed.'; queue='.$qid);
