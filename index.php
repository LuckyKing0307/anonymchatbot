<?php
/**
 * Example combined event handler bot.
 *
 * Copyright 2016-2020 Daniil Gentili
 * (https://daniil.it)
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use function Amp\async;
use function Amp\delay;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
/*
 * Various ways to load MadelineProto
 */
if (file_exists('vendor/autoload.php')) {
    include 'vendor/autoload.php';
} else {
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
}

/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    public $array_bans = [];
    public $future = [];
    const ADMIN = "looool0307"; // Change this
    /**
     * Get peer(s) where to report errors.
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }
    public function insertBd($bot_id,$user_id,$group_id,$group=null){
        $db = new MysqlConnectionPool(MysqlConfig::fromAuthority('localhost','root','','chat_bot'));
        if ($group) {
            $result = $db->query("INSERT INTO `message`(`bot_id`, `chat_id`, `user_id`,`message`) VALUES ('".$bot_id."','".$group_id."','".$user_id."','group')");
        }else{

            $result = $db->query("INSERT INTO `message`(`bot_id`, `chat_id`, `user_id`) VALUES ('".$bot_id."','".$group_id."','".$user_id."')");
        }
        $db->close();
    }
    public function userban($id,$type){
        $db = new MysqlConnectionPool(MysqlConfig::fromAuthority('localhost','root','','chat_bot'));
        if ($type=='ban') {
         $result = $db->query("INSERT INTO `bans`(`telegram_id`) VALUES ('".$id."')");
        }if ($type=='unban') {
         $result = $db->query("DELETE FROM `bans` WHERE telegram_id=".$id);
        }
        $db->close();
    }
    public function selectBd($id,$type){
        $user_data = array();
        $db = new MysqlConnectionPool(MysqlConfig::fromAuthority('localhost','root','','chat_bot'));
        if ($type=='chat_id') {
            $result = $db->query("SELECT * FROM `message` WHERE chat_id=".$id);
        }else{

            $result = $db->query("SELECT * FROM `message` WHERE chat_id=".$id." and message='group'");
        }
        foreach ($result as $row) {
            $user_data['bot_id'] = $row['bot_id'];
            $user_data['chat_id'] = $row['chat_id'];
            $user_data['user_id'] = $row['user_id'];
        }

        $db->close();
       return $user_data;
    }

    public function selectBan($id){
        $array = array();
        $db = new MysqlConnectionPool(MysqlConfig::fromAuthority('localhost','root','','chat_bot'));
        $result = $db->query("SELECT * FROM `bans` WHERE telegram_id=".$id);
        foreach ($result as $row) {
            $array[] = $row;
        }
        $db->close();
        return $array;
    }
    /**
     * Handle updates from supergroups and channels.
     *
     * @param array $update Update
     */
    public function onUpdateNewChannelMessage(array $update)
    {
        return $this->onUpdateNewMessage($update);
    }
    /**
     * Handle updates from users.
     *
     * @param array $update Update
     */
    public function onUpdateNewMessage(array $update): void
    {
        global $MadelineProtos,$array_bans;
        
        if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
            return;
        }
        else{
            $channel = [
                "_"=> "peerChannel",
                "channel_id"=> 1958557823
            ];
            if (isset($update['message']['to_id']['user_id']) and $update['message']['from_id']['user_id']==$update['message']['to_id']['user_id'] and empty($update['message']['reply_to'])) {
                if (count($this->selectBan($update['message']['peer_id']['user_id']))==0) {
                   $send = $MadelineProtos[0]->messages->sendMessage(['peer'=>$this->getId($channel),'message'=>$update['message']['message']]);
                   $bot_id = $update['message']['id'];
                   $user_id = $update['message']['from_id']['user_id'];
                   $group_id = $send['updates'][0]['id'];

                   $this->logger($send);
                   $this->insertBd($bot_id,$user_id,$group_id);
                }
            }
            if (isset($update['message']['reply_to'])) {
                if ($update['message']['message']!='/ban' and $update['message']['message']!='/unban') {
                    if (isset($update['message']['peer_id']['channel_id']) and $update['message']['peer_id']['channel_id']==1958557823) {
                       $data = $this->selectBd($update['message']['reply_to']['reply_to_msg_id'],'chat_id');
                        $channel = [
                            "_"=> "peerUser",
                            "channel_id"=> $data['user_id']
                        ];
                       $send = $MadelineProtos[0]->messages->sendMessage(['peer'=>$this->getId($data['user_id']),'message'=>$update['message']['message'],'reply_to_msg_id'=>$data['bot_id']]);
                       $bot_id = $update['message']['id'];
                       $user_id = $update['message']['peer_id']['channel_id'];
                       $group_id = $send['id'];
                       $this->logger($send);
                       $this->insertBd($bot_id,$user_id,$group_id,'group');
                    }else{
                        if (count($this->selectBan($update['message']['peer_id']['user_id']))==0) {
                            $data = $this->selectBd($update['message']['reply_to']['reply_to_msg_id'],'channel_id');
                                $this->logger($data);
                                var_dump($data);
                                $channel = [
                                    "_"=> "peerChannel",
                                    "channel_id"=> 1958557823
                                ];
                               $send = $MadelineProtos[0]->messages->sendMessage(['peer'=>$this->getId($channel),'message'=>$update['message']['message'],'reply_to_msg_id'=>$data['bot_id']]);
                               $bot_id = $update['message']['id'];
                               $user_id = $update['message']['peer_id']['user_id'];
                               $group_id = $send['updates'][0]['id'];
                               $this->logger($send);
                               $this->insertBd($bot_id,$user_id,$group_id,'group');
                        }
                    }
                }else{
                           $data = $this->selectBd($update['message']['reply_to']['reply_to_msg_id'],'chat_id');
                           $this->logger($data);
                    if (isset($update['message']['peer_id']['channel_id']) and $update['message']['peer_id']['channel_id']==1958557823) {
                       if ($update['message']['message']=='/ban') {
                           $data = $this->selectBd($update['message']['reply_to']['reply_to_msg_id'],'chat_id');
                           $this->userban($data['user_id'],'ban');
                       }if ($update['message']['message']=='/unban') {
                           $data = $this->selectBd($update['message']['reply_to']['reply_to_msg_id'],'chat_id');
                           $this->userban($data['user_id'],'unban');
                       }
                    }
                }
            }
        }
        $this->logger($update);
    } 
}
 $MadelineProtos = [];
foreach (['bot.madeline'] as $session => $message) {
    $MadelineProtos []= new API($session);
}
API::startAndLoopMulti($MadelineProtos, MyEventHandler::class);