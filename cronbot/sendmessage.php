<?php
chdir(__DIR__);
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
if (!is_file('info')) {
    error_log('[Mirza broadcast] STOP: cronbot/info is missing; no active queue metadata.');
    return;
}
if (!is_file('users.json')) {
    error_log('[Mirza broadcast] STOP: cronbot/users.json is missing; queue list was not created.');
    return;
}

$usersRaw = file_get_contents('users.json');
$infoRaw = file_get_contents('info');
$userid = json_decode((string) $usersRaw);
$info = json_decode((string) $infoRaw, true);
if (!is_array($userid)) {
    error_log('[Mirza broadcast] ERROR: users.json is not a valid JSON array; JSON error=' . json_last_error_msg());
    return;
}
if (!is_array($info)) {
    error_log('[Mirza broadcast] ERROR: info is not valid JSON; JSON error=' . json_last_error_msg());
    return;
}
error_log('[Mirza broadcast] queue loaded; remaining=' . count($userid) . '; type=' . ($info['type'] ?? 'missing') . '; required_fields=' . (isset($info['id_admin'], $info['id_message'], $info['message'], $info['btnmessage']) ? 'yes' : 'no'));
$count = 0;
if (count($userid) === 0) {
    error_log('[Mirza broadcast] queue is empty; sending completion notice and cleaning queue files.');
    if(isset($info['id_admin'])){
    deletemessage($info['id_admin'], $info['id_message']);
    sendmessage($info['id_admin'], $textbotlang['Admin']['messageBulk']['done'], null, 'HTML');
    unlink('info');
    unlink('users.json');
    }
    return;
    
}
$count_remein = count($userid);
$textprocces = sprintf($textbotlang['Admin']['messageBulk']['progress'], $count_remein);
$cancelmessage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['cancelOperation'], 'callback_data' => 'cancel_sendmessage'],
            ],
        ]
    ]);
Editmessagetext($info['id_admin'], $info['id_message'],$textprocces, $cancelmessage);
$keyboardbuy = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['sell'], 'callback_data' => 'buy'],
            ],
        ]
    ]);
$keyboardstart = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['start'], 'callback_data' => 'start'],
            ],
        ]
    ]);
$keyboardusertest = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['userTest'], 'callback_data' => 'usertestbtn'],
            ],
        ]
    ]);
$keyboardhelpbtn = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['help'], 'callback_data' => 'helpbtn'],
            ],
        ]
    ]);
$keyboardaffiliates = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['affiliates'], 'callback_data' => 'affiliatesbtn'],
            ],
        ]
    ]);
$keyboardaddbalance = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['textbot']['addBalance'], 'callback_data' => 'Add_Balance'],
            ],
        ]
    ]);
$batchStartCount = count($userid);
$sentOk = 0;
$sendFailed = 0;
for ($i = 0; $i < 20; $i++) {
    if (!isset($userid[0])) {
        break;
    }
    $iduser = $userid[0];
    unset($userid[0]);
    $userid = array_values($userid);
    if ($info['type'] == "unpinmessage") {
        unpinmessage($iduser->id);
    } elseif ($info['type'] == "sendmessage" or $info['type'] == "xdaynotmessage") {
        if ($info['btnmessage'] == "none") {
            $meesage = sendmessage($iduser->id, $info['message'], null, 'HTML');
        } elseif ($info['btnmessage'] == "buy") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardbuy, 'HTML');
        } elseif ($info['btnmessage'] == "start") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardstart, 'HTML');
        } elseif ($info['btnmessage'] == "usertestbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardusertest, 'HTML');
        } elseif ($info['btnmessage'] == "helpbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardhelpbtn, 'HTML');
        } elseif ($info['btnmessage'] == "affiliatesbtn") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardaffiliates, 'HTML');
        } elseif ($info['btnmessage'] == "addbalance") {
            $meesage = sendmessage($iduser->id, $info['message'], $keyboardaddbalance, 'HTML');
        }

        if (!is_array($meesage) || empty($meesage['ok'])) {
            $sendFailed++;
            $apiDescription = is_array($meesage) ? (string) ($meesage['description'] ?? 'unknown Telegram API error') : 'invalid API response';
            error_log('[Mirza broadcast] Telegram send failed: ' . substr($apiDescription, 0, 180));
        } else {
            $sentOk++;
        }

        if ($meesage['ok'] == false and $meesage['description'] == "Forbidden: bot was blocked by the user") {
            $invoicecount = select("invoice", "*", "id_user", $iduser->id, "count");
            $userinfo = select("user", "Balance", "id", $iduser->id, "select");
            if ($invoicecount == 0 and $userinfo['Balance'] == 0) {
                $Id_user = $iduser->id;
                $stmt = $pdo->prepare("DELETE FROM user WHERE id = :mp1");
                $stmt->execute([':mp1' => $Id_user]);
            }
        }

        if ($meesage['ok'] and $info['pingmessage'] == "yes") {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    } elseif ($info['type'] == "forwardmessage") {
        $meesage = forwardMessage($info['id_admin'], $info['message'], $iduser->id);
        if ($meesage['ok'] and $info['pingmessage'] == "yes") {
            pinmessage($iduser->id, $meesage['result']['message_id']);
        }
    }
}

file_put_contents('users.json', json_encode($userid, true));
error_log('[Mirza broadcast] batch finished; processed=' . ($batchStartCount - count($userid)) . '; sent_ok=' . $sentOk . '; send_failed=' . $sendFailed . '; remaining=' . count($userid));