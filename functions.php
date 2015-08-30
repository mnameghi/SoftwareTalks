<?php
/**
 * @param MysqliDb $db
 * @param TelegramBot\Api\BotApi $bot
 * @param $chatid
 * @param $next
 * @param $send
 */
function updateStatus($db, $bot, $chatid, $next, $send)
{
    //send a text and wait for reply
    //status determinate which command is in process
    if ($db->update('adminOperations', array('next_status' => $next, 'send_status' => $send)))
        $bot->sendMessage($chatid, 'متن مورد نظر را وارد کنید');
}

/**
 * send message to all users
 * @param $text
 * @param $chatid
 * @param $status
 * @param MysqliDb $db
 * @param TelegramBot\Api\BotApi $bot
 */
function sendMessageToAll($text = null, $chatid, $status, $db, $bot)
{
    //this is use for hide confirm keyboard
    $hideKeys=new \TelegramBot\Api\Types\ReplyKeyboardHide(true);

    if ($status == 1) {
        //confirm keyboard
        $keys = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array("بله", "خیر")), false, true, true);

        if ($text==null) {
            //admin is going to send next message and next message stored
            $db->orderBy('ID', 'DESC');
            $q = $db->getOne('nextMessages', array('text'));
            $text=$q['text'];
        }
        $db->update('adminOperations', array('message' => $text));
        $status = 2;

        //admin get confirm
        $msg = "پیام زیر برای همه کاربران ارسال خواهد شد. آیا برای ارسال پیامها اطمینان دارید؟\n\n";
        $msg .= $text;
        $bot->sendMessage($chatid, $msg, true, null, $keys);
    } elseif ($status == 2 && $text == 'بله') {
        //get all user and send message for them
        $users = $db->get('users');
        $db->orderBy('ID', 'DESC');

        //custom message and next message temporary stored in adminOperations table
        $q = $db->getOne('adminOperations', array('message'));
        $message=$q['message'];
        foreach ($users as $user) {
            try {
                $bot->sendMessage($user['ID'], $message);
				usleep(50000);
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }
        $bot->sendMessage($chatid, 'پیام مورد نظر ارسال شد',true,null,$hideKeys);
        $status = 0;
    } else {
        $bot->sendMessage($chatid, 'ارسال پیام لغو شد',true,null,$hideKeys);
        $status = 0;
    }
    $db->update('adminOperations', array('send_status' => $status));
}

/**
 * save next message
 * @param $text
 * @param $chatid
 * @param MysqliDb $db
 * @param TelegramBot\Api\BotApi $bot
 */
function setNextMessage($text, $chatid, $db, $bot)
{
    if ($db->insert('nextMessages', array('text' => $text))) {
        $bot->sendMessage($chatid, 'پیام مورد نظر ثبت شد');
        $db->update('adminOperations', array('next_status' => 0));
    }
}

/**
 * check if user is in admin group
 * @param $chatid
 * @param MysqliDb $db
 * @return bool
 */
function isAdmin($chatid, $db)
{
    $db->where('chatid', $chatid, '=');
    $isadmin = $db->getValue('admins', "count(*)");
    return $isadmin > 0;
}