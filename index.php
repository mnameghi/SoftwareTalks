<?php

require_once 'vendor/autoload.php';
require_once 'config/settings.php';
include('functions.php');

//read input data
$data = json_decode(file_get_contents('php://input'), true);

if ($data == null || $data == "" || !isset($data['message'])) die();

$db = new MysqliDb(HOST, DB_USER, DB_PASSWORD, DATABASE);
$db->setPrefix('soft_');
$text = '';
$chatid = '';

try {
    //catch user info
    $chatid = $data['message']['chat']['id'];
    $first_name = isset($data['message']['chat']['first_name']) ? $data['message']['chat']['first_name'] : '';
    $last_name = isset($data['message']['chat']['last_name']) ? $data['message']['chat']['last_name'] : '';
    $username = isset($data['message']['chat']['username']) ? $data['message']['chat']['username'] : '';

    $db->insert('users', array('ID' => $chatid, 'first_name' => $first_name, 'last_name' => $last_name, 'username' => $username));

    //catch message data
    $text = $data['message']['text'];
    $messageid = $data['message']['message_id'];
    $updateid = $data['update_id'];
    $senderid = $data['message']['from']['id'];
    $date = $data['message']['date'];
    $messageid = $data['message']['message_id'];

    $db->insert('received',
        array('ID' => $username, 'Message_id' => $messageid, 'User_id' => $senderid, 'Date' => $date, 'Text' => $text));

} catch (Exception $e) {
    error_log("خطا در دریافت اطلاعات\n\n" . $e->getMessage());
}

$text = strtolower($text);
$bot = new TelegramBot\Api\BotApi(TOKEN);

try {

    switch ($text) {
        case '/start' :
        case '/start@softwaretalkbot' :
            $message = "سلام\nبه ربات جلسات باز نرم افزاری خوش آمدید.\nجهت اطلاع از جلسه آتی عبارت next را ارسال کنید.";
            $bot->sendMessage($chatid, $message);
            break;

        case '/next':
        case '/next@softwaretalkbot':
        case 'next':
            $db->orderBy('ID', 'DESC');
            $q = $db->getOne('nextMessages');
            $message = $q['text'];
            $bot->sendMessage($chatid, $message);
            break;

        case '/about':
        case '/about@softwaretalkbot':
        case 'about':
            $bot->sendMessage($chatid,"من اطلاعات جلسات باز نرم افزاری مشهد را برایتان ارسال میکنم.\n".
                "سورس من روی گیت هاب قرار دارد. می توانید از طریق لینک زیر آن را مشاهده کنید:\n".
                "https://github.com/mnameghi/SoftwareTalks");
            break;

        //set next message
        case COMMAND1:
        case '/' . COMMAND1:
            if (!isAdmin($chatid, $db)) return;
            updateStatus($db, $bot, $chatid, 1, 0);
            break;

        //send NEXT message to all
        case COMMAND2:
        case '/' . COMMAND2:
            if (!isAdmin($chatid, $db)) return;
            sendMessageToAll(null, $chatid, 1, $db, $bot);
            break;

        //send custom message to all
        case COMMAND3:
        case '/' . COMMAND3:
            if (!isAdmin($chatid, $db)) return;
            updateStatus($db, $bot, $chatid, 0, 1);
            break;

        //cancel current operation
        case COMMAND4:
        case '/' . COMMAND4:
            if (!isAdmin($chatid, $db)) return;
            $db->update('adminOperations', array('next_status' => 0, 'send_status' => 0));
            $bot->sendMessage($chatid, 'عملیات جاری لغو شد');
            break;

        default:
            //received message after send operations commands
            if (!isAdmin($chatid, $db)) return;
            $q = $db->getOne('adminOperations');

            if ($q['next_status'] != 0) {
                setNextMessage($text, $chatid, $db, $bot);
            }
            if ($q['send_status'] != 0) {
                sendMessageToAll($text, $chatid, $q['send_status'], $db, $bot);
            }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}