<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Friend;
use Illuminate\Support\Facades\Log;

trait FriendTrait {
    public function getAll($userId) {
        Log::info("Entering FriendTrait getAll...");

        $friends = [];

        $user = User::with(['friends' => function($q) {
            $q->where('status', 'accepted');
        }])->find($userId);

        if ($user && count($user->friends) > 0) {
            $friends = $user->friends;
        }

        return $friends;
    }

    public function getFriendRecord($userId, $friendId) {
        Log::info("Entering FriendTrait getInvitation...");

        $friend = null;

        $friendRecord = Friend::with('users:id,first_name,last_name,username')
                              ->where('user_id', $userId)
                              ->where('friend_id', $friendId)
                              ->whereNotNull('status')
                              ->first();

        if ($friendRecord) {
            Log::info("record ");
            Log::info($friendRecord);
            $friend = $friendRecord;
        }

        return $friend;
    }

    public function getAllInvitations($userId) {
        Log::info("Entering FriendTrait getAllInvitations...");

        $invitations = [];

        $users = Friend::with('users:id,first_name,last_name,username')->where('friend_id', $userId)->get();

        if ($users && (count($users) > 0)) {
            foreach ($users as $user) {
                if ($user->users->first()) {
                    unset($user->users->first()->id);
                    $invitations[] = $user->users->first();
                }
            }
        }

        return $invitations;
    }

    public function getInvitation($userId, $friendId) {
        Log::info("Entering FriendTrait getInvitation...");

        $invitation = null;

        $friendInvitation = Friend::with('users:id,first_name,last_name,username')
                                  ->where('user_id', $userId)
                                  ->where('friend_id', $friendId)
                                  ->where('status', 'pending')
                                  ->first();

        if ($friendInvitation) {
            $invitation = $friendInvitation;
        }

        return $invitation;
    }

    public function getFriendStatus($userId, $friendId) {
        Log::info("Entering FriendTrait getFriendStatus...");

        $friendStatus = '';

        $friend = User::with(['friends' => function($q) use($friendId) {
            $q->where('friend_id', $friendId)
              ->whereNotNull('status');
        }])->find($userId);

        if ($friend && $friend->friends->first()) {
            $friendStatus= $friend->friends->first()->status;
        }

        return $friendStatus;
    }
}