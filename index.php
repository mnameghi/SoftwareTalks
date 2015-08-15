<?php

require_once 'vendor/autoload.php';
require_once 'config/settings.php';

$data = json_decode(file_get_contents('php://input'), true);
$jsondata = file_get_contents('php://input');

if ($data == null || $data == "" || !isset($data['message'])) die();

$db = new MysqliDb(HOST, DB_USER, DB_PASSWORD, DATABASE);
$db->setPrefix('soft_');
$text = '';
$chatid = '';

try {
    $chatid = $data['message']['chat']['id'];
    $first_name = isset($data['message']['chat']['first_name']) ? $data['message']['chat']['first_name'] : '';
    $last_name = isset($data['message']['chat']['last_name']) ? $data['message']['chat']['last_name'] : '';
    $username = isset($data['message']['chat']['username']) ? $data['message']['chat']['username'] : '';

    $db->insert('users', array('ID'=>$chatid, 'first_name' => $first_name, 'last_name' => $last_name, 'username' => $username));

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
        case '/start@softwaretalk' :
            $message = "سلام\nبه ربات جلسات باز نرم افزاری خوش آمدید.\nجهت اطلاع از جلسه آتی عبارت next را ارسال کنید.";
            $bot->sendMessage($chatid, $message);
            break;

        case '/next':
        case '/next@softwaretalk':
        case 'next':
            $db->orderBy('ID', 'DESC');
            $q = $db->getOne('nextMessages');
            $message = $q['text'];
            $bot->sendMessage($chatid, $message);
            break;

        case COMMAND1:
        case '/'.COMMAND1:
            if ($chatid != ADMIN) return;
            if ($db->update('admin', array('next_status' => '1')))
                $bot->sendMessage($chatid, 'متن مورد نظر را وارد کنید');
            break;

        case COMMAND2:
        case '/'.COMMAND2:
            if ($chatid != ADMIN) return;
            $db->update('admin', array('send_status' => 0));
            $msg = "پیام زیر برای همه کاربران ارسال خواهد شد. آیا برای ارسال پیامها اطمینان دارید؟\n\n";
            $db->orderBy('ID', 'DESC');
            $q = $db->getOne('nextMessages');
            $msg .= $q['text'];
            $db->update('admin', array('send_status' => 1));
            $bot->sendMessage($chatid, $msg);
            break;

        case COMMAND3:
        case '/'.COMMAND3:
            if ($chatid != ADMIN) return;
            $db->update('admin', array('next_status' => 0, 'send_status' => 0));
            $bot->sendMessage($chatid, 'عملیات جاری لغو شد');
            break;

        default:
            if ($chatid != ADMIN) return;
            $q = $db->getOne('admin');

            //send message to all
            if ($text == 'بله' || $text == 'yes') {
                if ($q['send_status'] != '1') return;
                $users = $db->get('users');
                $db->orderBy('ID', 'DESC');
                $next = $db->getOne('nextMessages', array('text'));
                foreach ($users as $user) {
                    try {
                        $bot->sendMessage($user['ID'], $next['text']);
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                    }
                }
				$db->update('admin', array('next_status' => '0'));
                $bot->sendMessage($chatid, 'پیام مورد نظر ارسال شد');
                return;
            }

            //register next message
            if ($q['next_status'] != '1') return;
            if ($db->insert('nextMessages', array('text' => $text))) {
                $bot->sendMessage($chatid, 'پیام مورد نظر ثبت شد');
                $status = '0';
            }
            $db->update('admin', array('next_status' => $status));
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}
