<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Friend;
use App\Traits\AuthTrait;
use App\Traits\ResponseTrait;
use App\Traits\FriendTrait;
use Illuminate\Support\Facades\Log;

class FriendController extends Controller
{
    use AuthTrait, ResponseTrait, FriendTrait;

    public function storeFriend(Request $request) {
        Log::info("Entering FriendController storeFriend...");

        $this->validate($request, [
            'auth_username' =>'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if (!($authUser && $user)) {
                    Log::error("Failed to add friend. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {
                            $sender = $this->getFriendStatus($authUser->id, $user->id);
                            $recipient = $this->getFriendStatus($user->id, $authUser->id);

                            if ($sender || $recipient) {
                                if ($sender === 'pending') {
                                    Log::error("Failed to add friend. Authenticated user ID ".$authUser->id." already sent a friend invitation to user ID ".$user->id.".\n");

                                    return $this->errorResponse("Please wait for @".$authUser->username." to respond to your invitation.");
                                }

                                if ($recipient === 'pending') {
                                    Log::error("Failed to add friend. User ID " . $user->id . " already sent a friend invitation to authenticated user ID " . $authUser->id . ".\n");

                                    return $this->errorResponse("Please respond to @" . $user->username . "'s friend invitation.");
                                }

                                if (($sender === 'accepted') || ($recipient === 'accepted')) {
                                    Log::error("Authenticated user ID " . $authUser->id . " is already friends with friend ID " . $user->id . ".\n");

                                    return $this->errorResponse("You and " . $user->username . " are already friends.");
                                }
                            }

                            if (!($sender) && !($recipient)) {
                                $friend = new Friend();

                                $friend->user_id = $authUser->id;
                                $friend->friend_id = $user->id;
                                $friend->status = "pending";

                                $friend->save();

                                if (!$friend) {
                                    Log::error("Failed to add friend.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }

                                if ($friend) {
                                    Log::info("Successfully added friend ID " . $friend->id . " from user ID " . $friend->user_id . ". Leaving FriendController storeFriend...\n");

                                    return $this->successResponse("details", [
                                        'is_sender' => true,
                                        'status' => $friend->status,
                                    ]);
                                }
                            }

                            break;
                        }
                    }
                }
            } else {
                Log::error("Failed to add friend. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to add friend. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeAcceptFriend(Request $request) {
        Log::info("Entering FriendController storeAcceptFriend...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if (!($authUser && $user)) {
                    Log::error("Failed to accept friend invitation. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {
                            $friendStatus = $this->getFriendStatus($user->id, $authUser->id);

                            if (!$friendStatus) {
                                Log::error("Authenticated user ID " . $authUser->id . " is neither friends nor have a pending invitation with friend ID " . $user->id . ".\n");

                                return $this->errorResponse(null);
                            }

                            if ($friendStatus === 'accepted') {
                                Log::error("Authenticated user ID " . $authUser->id . " is already friends with friend ID " . $user->id . ".\n");

                                return $this->errorResponse("You and " . $user->username . " are already friends.");
                            }

                            if ($friendStatus === 'pending') {
                                $invitation = $this->getInvitation($user->id, $authUser->id);

                                if (!$invitation) {
                                    Log::error("Failed to accept friend invitation. Invitation does not exist or might be deleted.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }

                                if ($invitation) {
                                    $invitation->status = 'accepted';

                                    $invitation->save();

                                    if (!($invitation->wasChanged('status'))) {
                                        Log::error("Failed to accept friend invitation from authenticated user ID " . $authUser->id . " to friend ID " . $user->id.".\n");

                                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                                    }

                                    if ($invitation->wasChanged('status')) {
                                        Log::info("Successfully accepted friend invitation from authenticated user ID " . $authUser->id . " to friend ID " . $user->id . ". Leaving FriendController storeAcceptFriend...\n");

                                        return $this->successResponse("details", $invitation->status);
                                    }
                                }
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to accept friend invitation. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getFriend(Request $request) {
        Log::info("Entering FriendController getFriend...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if (!($authUser && $user)) {
                    Log::error("Failed to retrieve friend. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {
                            $sender = $this->getFriendStatus($authUser->id, $user->id);
                            $recipient = $this->getFriendStatus($user->id, $authUser->id);

                            if ($sender || $recipient) {
                                if ($sender === 'pending') {
                                    Log::info("Authenticated user ID " . $authUser->id . " sent a friend invitation to user ID " . $user->id . ". Leaving FriendController storeFriend...\n");

                                    return $this->successResponse("details", [
                                        'is_sender' => true,
                                        'status' => $sender,
                                    ]);
                                }

                                if ($recipient === 'pending') {
                                    Log::info("User ID " . $user->id . " received a friend invitiation from user ID " . $authUser->id . ". Leaving FriendController storeFriend...\n");

                                    return $this->successResponse("details", [
                                        'is_sender' => false,
                                        'status' => $recipient,
                                    ]);
                                }

                                if (($sender === 'accepted') || ($recipient === 'accepted')) {
                                    Log::info("Authenticated user ID " . $authUser->id . " is already friends with friend ID " . $user->id . ". Leaving FriendController storeFriend...\n");

                                    return $this->successResponse("details", [
                                        'is_sender' => null,
                                        'status' => $sender ? $sender : $recipient,
                                    ]);
                                }
                            }

                            if (!($sender) && !($recipient)) {
                                Log::info("Authenticated user ID " . $authUser->id . " and friend ID " . $user->id . " are not yet friends. Leaving FriendController storeFriend...\n");

                                return $this->successResponse("details", [
                                    'is_sender' => null,
                                    'status' => null,
                                ]);
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve friend. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getAllFriends(Request $request) {
        Log::info("Entering FriendController getAllFriends...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if (!($authUser) && !($user)) {
                    Log::error("Failed to retrieve friends. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {
                            $friends = $this->getAll($user->id);

                            if (count($friends) === 0) {
                                Log::notice("User ID " . $user->id . " has no friends yet.\n");

                                return $this->errorResponse(null);
                            }

                            if (count($friends) > 0) {
                                Log::info("Successfully retrieved user ID " . $user->id . "'s friends. Leaving FriendController getAllFriends...\n");

                                return $this->successResponse("details", $friends);
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve friend. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedFriends(Request $request) {
        Log::info("Entering FriendController getPaginatedFriends...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if (!($user)) {
                    Log::error("Failed to retrieve paginated friends. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $friends = $this->getFriendsPaginated($user->id, $request->offset, $request->limit);

                            if (count($friends) === 0) {
                                Log::notice("No additional friends fetched. No action needed.\n");

                                return $this->errorResponse(null);
                            }

                            if (count($friends) > 0) {
                                Log::info("Successfully retrieved user ID " . $user->id . "'s paginated friends. Leaving FriendController getPaginatedFriends...\n");

                                return $this->successResponse("details", $friends);
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated friends. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getFriendInvitations(Request $request) {
        Log::info("Entering FriendController getFriendInvitations...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if (!($user)) {
                    Log::error("Failed to retrieve friends. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $friendInvitations = $this->getAllInvitations($user->id);

                            if (count($friendInvitations) === 0) {
                                Log::notice("User ID " . $user->id . " has no friend invitations yet.\n");

                                return $this->errorResponse(null);
                            }

                            if (count($friendInvitations) > 0) {
                                Log::info("Successfully retrieved user ID " . $user->id . "'s friend invitations. Leaving FriendController getFriendInvitations...\n");

                                return $this->successResponse("details", $friendInvitations);
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve friend. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedFriendInvitations(Request $request) {
        Log::info("Entering FriendController getPaginatedFriendInvitations...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if (!($user)) {
                    Log::error("Failed to retrieve paginated friend invitations. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $friendInvitations = $this->getPaginatedInvitations($user->id, $request->offset, $request->limit);

                            if (count($friendInvitations) === 0) {
                                Log::notice("No additional friend invitations fetched. No action needed.\n");

                                return $this->errorResponse(null);
                            }

                            if (count($friendInvitations) > 0) {
                                Log::info("Successfully retrieved user ID " . $user->id . "'s paginated friend invitations. Leaving FriendController getPaginatedFriendInvitations...\n");

                                return $this->successResponse("details", $friendInvitations);
                            }

                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated friend invitations. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyFriend(Request $request) {
        Log::info("Entering FriendController destroyFriend...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if (!($authUser) && !($user)) {
                    Log::error("Failed to remove friend. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {
                            $sender = $this->getFriendStatus($authUser->id, $user->id);
                            $recipient = $this->getFriendStatus($user->id, $authUser->id);

                            if (!($sender) && !($recipient)) {
                                Log::info("Authenticated user ID " . $authUser->id . " is neither friends nor have a pending invitation with friend ID " . $user->id . ".\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }

                            $friend = null;
                            $isSender = false;

                            if ($sender || $recipient) {
                                if ($sender) {
                                    $isSender = true;
                                    $friend = $this->getFriendRecord($authUser->id, $user->id);
                                }

                                if ($recipient) {
                                    $friend = $this->getFriendRecord($user->id, $authUser->id);
                                }

                                if ($friend) {
                                    if ($friend->users && $friend->users->first()) {
                                        $originalId = $friend->getOriginal('id');
                                        $originalStatus = $friend->getOriginal('status');
                                        $originalUsername = $friend->users->first()->username;

                                        $friend->delete();

                                        if (Friend::find($originalId)) {
                                            Log::error("Failed to soft delete friend ID ".$originalId." from authenticated user ID ".$authUser->id.".\n");

                                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                                        }

                                        if (!(Friend::find($originalId))) {
                                            Log::info("Successfully soft delete ID " . $originalId . " from friends table. Leaving FriendController destroyFriend...\n");

                                            if (($originalStatus === 'pending') && $isSender) {
                                                return $this->successResponse("details", "Friend invitation cancelled.");
                                            }

                                            if (($originalStatus === 'pending') && !($isSender)) {
                                                return $this->successResponse("details", "Friend invitation declined.");
                                            }

                                            if (($originalStatus === 'accepted')) {
                                                return $this->successResponse("details", "@". $originalUsername." was removed from your friends.");
                                            }
                                        }
                                    }
                                }
                            }

                            break;
                        }
                    }
                }
            } else {
                Log::error("Failed to remove friend. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to remove friend. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
