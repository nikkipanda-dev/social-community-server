<?php

namespace App\Http\Controllers;

use App\Events\MessageEvent;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function storeMessage(Request $request) {
        // event(new MessageEvent($request->username, $request->message));

        return response(env("PUSHER_APP_ID"), 200);
    }
}
