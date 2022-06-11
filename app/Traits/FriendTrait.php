<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Friend;
use Illuminate\Support\Facades\Log;

trait FriendTrait {
    public function getAll($userId) {
        Log::info("Entering FriendTrait getAll...");

        $friends = [];

        $allFriends = Friend::has('users')
                            ->has('friends')
                            ->where('user_id', $userId)
                            ->orWhere('friend_id', $userId)
                            ->where('status', 'accepted')
                            ->get();


        if ($allFriends && (count($allFriends) > 0)) {
            foreach ($allFriends as $friend) {
                if ($friend->users->first()->id !== $userId) {    
                    $friends[] = [
                        'id' => $friend->users->first()->id,
                        'username' => $friend->users->first()->username,
                        'first_name' => $friend->users->first()->first_name,
                        'last_name' => $friend->users->first()->last_name,
                    ];
                }

                if ($friend->friends->first()->id !== $userId) {
                    $friends[] = [
                        'id' => $friend->friends->first()->id,
                        'username' => $friend->friends->first()->username,
                        'first_name' => $friend->friends->first()->first_name,
                        'last_name' => $friend->friends->first()->last_name,
                    ];
                }
            }
        }

        return $friends;
    }

    public function getFriendsPaginated($userId, $offset, $limit) {
        Log::info("Entering FriendTrait getFriendsPaginated...");

        $friends = [];

        $allFriends = Friend::has('users')
                            ->has('friends')
                            ->where('user_id', $userId)
                            ->orWhere('friend_id', $userId)
                            ->where('status', 'accepted')
                            ->offset(intval($offset, 10))
                            ->limit(intval($limit, 10))
                            ->get();


        if ($allFriends && (count($allFriends) > 0)) {
            foreach ($allFriends as $friend) {
                if ($friend->users->first()->id !== $userId) {
                    $friends[] = [
                        'username' => $friend->users->first()->username,
                        'first_name' => $friend->users->first()->first_name,
                        'last_name' => $friend->users->first()->last_name,
                    ];
                }

                if ($friend->friends->first()->id !== $userId) {
                    $friends[] = [
                        'username' => $friend->friends->first()->username,
                        'first_name' => $friend->friends->first()->first_name,
                        'last_name' => $friend->friends->first()->last_name,
                    ];
                }
            }
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

        $users = Friend::with('users:id,first_name,last_name,username')
                       ->where('friend_id', $userId)
                       ->where('status', 'pending')
                       ->get();

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

    public function getPaginatedInvitations($userId, $offset, $limit) {
        Log::info("Entering FriendTrait getPaginatedInvitations...");

        $invitations = [];

        $friendInvitations = Friend::with('users:id,first_name,last_name,username')
                                   ->where('friend_id', $userId)
                                   ->where('status', 'pending')
                                   ->offset(intval($offset, 10))
                                   ->limit(intval($limit, 10))
                                   ->get();

        if ($friendInvitations && (count($friendInvitations) > 0)) {
            foreach ($friendInvitations as $invitation) {
                if ($invitation->users->first()) {
                    unset($invitation->users->first()->id);
                    $invitations[] = $invitation->users->first();
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