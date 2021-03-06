<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Increment\Messenger\Models\MessengerGroup;
use Increment\Messenger\Models\MessengerMember;
use Increment\Messenger\Models\MessengerMessage;
use Carbon\Carbon;
use Increment\Account\Models\Account;
use Illuminate\Support\Facades\DB;
use App\Events\Message;
use App\Jobs\Notifications;
class MessengerGroupController extends APIController
{
    public $notificationClass = 'Increment\Common\Notification\Http\NotificationController';
    public $messengerMessagesClass = 'Increment\Messenger\Http\MessengerMessageController';

    function __construct(){
      if($this->checkAuthenticatedUser() == false){
        return $this->response();
      }
      $this->model = new MessengerGroup();
      $this->localization();
    }

    public function create(Request $request){
      $data = $request->all();

      $creator = intval($data['creator']);
      $memberData = intval($data['member']);
      $this->model = new MessengerGroup();
      $insertData = array(
        'account_id'  => $creator,
        'title'       => $data['title'],
        'payload'     => $data['payload'] 
      );

      $this->insertDB($insertData);
      $id = intval($this->response['data']);
      if($this->response['data'] > 0){
        $member = new MessengerMember();
        $member->messenger_group_id = $id;
        $member->account_id = $creator;
        $member->status = 'admin';
        $member->created_at = Carbon::now();
        $member->save();

        $member = new MessengerMember();
        $member->messenger_group_id = $id;
        $member->account_id = $memberData;
        $member->status = 'member';
        $member->created_at = Carbon::now();
        $member->save();

        $message = new MessengerMessage();
        $message->messenger_group_id = $id;
        $message->account_id = $creator;
        $message->payload = 'text';
        $message->payload_value = null;
        $message->message = 'Greetings!';
        $message->status = 0;
        $message->created_at = Carbon::now();
        $message->save();

        $parameter = array(
          'to' => $memberData,
          'from' => $creator,
          'payload' => 'thread',
          'payload_value' => $id,
          'route' => '/thread/'.$data['title'],
          'created_at' => Carbon::now()
        );
        app($this->notificationClass)->createByParams($parameter);
      }
      return $this->response();
    }

}
