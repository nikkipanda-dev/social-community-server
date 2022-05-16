<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MicroblogEntry;
use App\Models\MicroblogEntryComment;
use App\Models\MicroblogEntryCommentHeart;
use App\Models\MicroblogEntryHeart;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use Illuminate\Support\Facades\Log;

class MicroblogEntryController extends Controller
{
    use ResponseTrait, AuthTrait, PostTrait;
    
    public function getMicroblogEntries() {
        Log::info("Entering MicroblogEntryController getMicroblogEntries...");

        try {
            $microblogEntries = MicroblogEntry::with('user:id,first_name,last_name,username')->get();

            if (count($microblogEntries) > 0) {
                Log::info("Successfully retrieved microblog entries. Leaving MicroblogEntryController getMicroblogEntries...");

                foreach ($microblogEntries as $microblogEntry) {
                    $microblogEntry->body = substr($microblogEntry->body, 0, 50);
                }

                return $this->successResponse('details', $microblogEntries);
            } else {
                Log::error("No microblog entries yet. No action needed.\n");

                return $this->errorResponse("No microblog entries yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve microblog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getUserMicroblogEntries(Request $request) {
        Log::info("Entering MicroblogEntryController getUserMicroblogEntries...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'auth_username' => 'bail|required|exists:users,username',
        ]);

        try {
            $user = User::where('username', $request->username)->first();
            $authUser = User::where('username', $request->auth_username)->first();

            if ($user && $authUser) {
                $microblogEntries = MicroblogEntry::latest()
                                                  ->with('user:id,first_name,last_name,username')
                                                  ->where('user_id', $user->id)
                                                  ->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getUserMicroblogEntries...");
                    
                    foreach($microblogEntries as $entry) {
                        $entry['hearts'] = $this->getMicroblogEntryHearts($entry->id, $authUser->id);
                        $entry['comments'] = ($this->getMicroblogEntryComments($entry->id) && count($this->getMicroblogEntryComments($entry->id)) > 0) ? count($this->getMicroblogEntryComments($entry->id)) : 0;
                        unset($entry->id);
                        unset($entry->updated_at);
                        unset($entry->deleted_at);
                        unset($entry->user->id);
                    }

                    return $this->successResponse('details', $microblogEntries);
                } else {
                    Log::error("No microblog entries yet. No action needed.\n");

                    return $this->errorResponse("No microblog entries yet.");
                }
            } else {
                Log::error("Failed to retrieve user's microblog entries. Both users do not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user's microblog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedUserMicroblogEntries(Request $request) {
        Log::info("Entering MicroblogEntryController getPaginatedUserMicroblogEntries...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'auth_username' => 'bail|required|exists:users,username',
            'offset' => 'bail|numeric',
            'limit' => 'bail|numeric',
        ]);

        try {
            $user = User::where('username', $request->username)->first();
            $authUser = User::where('username', $request->auth_username)->first();

            if ($user && $authUser) {
                $microblogEntries = MicroblogEntry::latest()
                                                  ->with('user:id,first_name,last_name,username')
                                                  ->where('user_id', $user->id)
                                                  ->offset(intval($request->offset, 10))
                                                  ->limit(intval($request->limit, 10))
                                                  ->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getPaginatedUserMicroblogEntries...");

                    foreach ($microblogEntries as $entry) {
                        $entry['hearts'] = $this->getMicroblogEntryHearts($entry->id, $authUser->id);
                        $entry['comments'] = ($this->getMicroblogEntryComments($entry->id) && count($this->getMicroblogEntryComments($entry->id)) > 0) ? count($this->getMicroblogEntryComments($entry->id)) : 0;
                        unset($entry->id);
                        unset($entry->updated_at);
                        unset($entry->deleted_at);
                        unset($entry->user->id);
                    }

                    return $this->successResponse('details', $microblogEntries);
                } else {
                    Log::error("No microblog entries yet. No action needed.\n");

                    return $this->errorResponse("No microblog entries yet.");
                }
            } else {
                Log::error("Failed to retrieve user's microblog entries. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user's microblog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getUserMicroblogEntry(Request $request, $slug) {
        return response('ok', 200);
    }

    public function getUserMicroblogEntryComments(Request $request) {
        Log::info("Entering MicroblogEntryController getUserMicroblogEntryComments...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:microblog_entries',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $microblogEntry = MicroblogEntry::with(['microblogEntryComments' => function($q) {
                                                                $q->orderBy('created_at', 'desc');
                                                            }, 'microblogEntryComments.user:id,first_name,last_name,username'])
                                                            ->where('slug', $request->slug)
                                                            ->first();
                            
                            if ($microblogEntry) {
                                if (count($microblogEntry->microblogEntryComments) > 0) {
                                    foreach ($microblogEntry->microblogEntryComments as $comment) {
                                        $comment['hearts'] = $this->getMicroblogEntryCommentHearts($comment->id, $user->id);
                                        Log::info($comment);
                                        unset($comment->id);
                                        unset($comment->updated_at);
                                        unset($comment->deleted_at);
                                        unset($comment->user_id);
                                        unset($comment->user->id);
                                    }

                                    if (count($microblogEntry->microblogEntryComments) > 0) {
                                        Log::info("Successfully retrieved comments for microblog ID " . $microblogEntry->id . ". Leaving MicroblogEntryController getUserMicroblogEntryComments...\n");

                                        return $this->successResponse("details", $microblogEntry->microblogEntryComments);
                                    } else {
                                        Log::info("No comments to show for microblog entry ID " . $microblogEntry->id . ". No action needed.\n");

                                        return $this->errorResponse("No comments to show.");
                                    }
                                }
                            } else {
                                Log::error("Failed to retrieve microblog entry comments. Microblog entry does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post does not exist.");
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to retrieve microblog entry comments. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to retrieve microblog entry comments. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve microblog entry comments. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
    
    public function getPaginatedUserMicroblogEntryComments(Request $request) {
        Log::info("Entering MicroblogEntryController getPaginatedUserMicroblogEntryComments...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:microblog_entries',
            'limit' => 'bail|numeric',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $microblogEntry = MicroblogEntry::with(['microblogEntryComments' => function ($q) use($request) {
                                                                $q->orderBy('created_at', 'desc')
                                                                  ->limit($request->limit);
                                                             }, 'microblogEntryComments.user:id,first_name,last_name,username'])
                                                            ->where('slug', $request->slug)
                                                            ->first();

                            if ($microblogEntry) {
                                if (count($microblogEntry->microblogEntryComments) > 0) {
                                    foreach ($microblogEntry->microblogEntryComments as $comment) {
                                        $comment['hearts'] = $this->getMicroblogEntryCommentHearts($comment->id, $user->id);
                                        unset($comment->id);
                                        unset($comment->updated_at);
                                        unset($comment->deleted_at);
                                        unset($comment->user_id);
                                        unset($comment->user->id);
                                    }

                                    if (count($microblogEntry->microblogEntryComments) > 0) {
                                        Log::info("Successfully retrieved paginated comments for microblog ID " . $microblogEntry->id . ". Leaving MicroblogEntryController getUserMicroblogEntryComments...\n");

                                        return $this->successResponse("details", $microblogEntry->microblogEntryComments);
                                    } else {
                                        Log::info("No comments to show for microblog entry ID " . $microblogEntry->id . ". No action needed.\n");

                                        return $this->errorResponse("No comments to show.");
                                    }
                                }
                            } else {
                                Log::error("Failed to retrieve paginated microblog entry comments. Microblog entry does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post does not exist.");
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to retrieve paginated microblog entry comments. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to retrieve paginated microblog entry comments. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated microblog entry comments. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeMicroblogEntry(Request $request) {
        Log::info("Entering MicroblogEntryController storeMicroblogEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'body' => 'bail|required|between:2,300',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $isUnique = false;
                            $microblogEntrySlug = null;

                            $microblogEntry = new MicroblogEntry();

                            $microblogEntry->user_id = $user->id;
                            $microblogEntry->body = $request->body;

                            do {
                                $microblogEntrySlug = $this->generateSlug();

                                if (!(MicroblogEntry::where('slug', $microblogEntrySlug)->first())) {
                                    $isUnique = true;
                                }
                            } while (!($isUnique));

                            $microblogEntry->slug = $microblogEntrySlug;

                            $microblogEntry->save();

                            if ($microblogEntry) {
                                Log::info("Successfully stored new microblog entry ID ".$microblogEntry->id. ". Leaving MicroblogEntryController storeMicroblogEntry...\n");

                                $microblogEntries = MicroblogEntry::latest()->with('user')->where('user_id', $user->id)->get();

                                return $this->successResponse("details", $microblogEntries);
                            } else {
                                Log::error("Failed to store microblog entry.\n");

                                return $this->errorResponse('default', null);
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store microblog entry. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store microblog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store microblog entry. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateMicroblogEntryHeart(Request $request) {
        Log::info("Entering MicroblogEntryController updateMicroblogEntryHeart...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:microblog_entries',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $microblogEntry = MicroblogEntry::with('user')->where('slug', $request->slug)->first();

                            if ($microblogEntry) {
                                $microblogEntryHeart = MicroblogEntryHeart::where('microblog_entry_id', $microblogEntry->id)
                                                                          ->where('user_id', $user->id)
                                                                          ->first();

                                if ($microblogEntryHeart) {
                                    $microblogEntryHeart->is_heart = !($microblogEntryHeart->is_heart);

                                    $microblogEntryHeart->save();

                                    if ($microblogEntryHeart->wasChanged('is_heart')) {
                                        Log::info("Successfully updated microblog entry ID ".$microblogEntry->id."'s 'is_heart' state to ". $microblogEntryHeart->is_heart.". Leaving MicroblogEntryController updateMicroblogEntryHeart...\n");
                                    } else {
                                        Log::error("Failed to update microblog entry ID ".$microblogEntry->id."'s 'is_heart' state.\n");

                                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                                    }
                                } else {
                                    $microblogEntryHeart = new MicroblogEntryHeart();

                                    $microblogEntryHeart->microblog_entry_id = $microblogEntry->id;
                                    $microblogEntryHeart->user_id = $user->id;
                                    $microblogEntryHeart->is_heart = true;

                                    $microblogEntryHeart->save();

                                    if ($microblogEntryHeart) {
                                        Log::info("Successfully stored new microblog entry ID " . $microblogEntry->id . "'s 'is_heart' state to " . $microblogEntryHeart->is_heart . ". Leaving MicroblogEntryController updateMicroblogEntryHeart...\n");
                                    } else {
                                        Log::error("Failed to store new microblog entry's 'is_heart' state.\n");

                                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                                    }
                                }

                                return $this->successResponse('details', $this->getMicroblogEntryHearts($microblogEntry->id, $user->id));
                            } else {
                                Log::error("Failed to update microblog entry heart. Microblog post does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post does not exist.");
                            }
                            
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update microblog entry heart. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update microblog entry heart. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to update microblog entry heart. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeMicroblogEntryComment(Request $request) {
        Log::info("Entering MicroblogEntryController storeMicroblogEntryComment...");
     
        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:microblog_entries',
            'body' => 'bail|required|string|min:2,10000',
        ]);
        
        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $microblogEntry = MicroblogEntry::where('slug', $request->slug)->first();

                            if ($microblogEntry) {
                                $isUnique = false;
                                $microblogEntryCommentSlug = null;

                                $microblogEntryComment = new MicroblogEntryComment();

                                $microblogEntryComment->microblog_entry_id = $microblogEntry->id;
                                $microblogEntryComment->user_id = $user->id;
                                $microblogEntryComment->body = $request->body;

                                do {
                                    $microblogEntryCommentSlug = $this->generateSlug();

                                    if (!(MicroblogEntryComment::where('slug', $microblogEntryCommentSlug)->first())) {
                                        $isUnique = true;
                                    }
                                } while (!($isUnique));

                                $microblogEntryComment->slug = $microblogEntryCommentSlug;

                                $microblogEntryComment->save();

                                if ($microblogEntryComment) {
                                    Log::info("Successfully stored new microblog entry comment ID ".$microblogEntryComment->id. ". Leaving MicroblogEntryController storeMicroblogEntryComment...\n");

                                    return $this->successResponse('details', $this->getMicroblogEntryComments($microblogEntry->id));
                                } else {
                                    Log::error("Failed to store new microblog entry comment.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }

                            } else {
                                Log::error("Failed to store new microblog entry comment. Microblog post does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post does not exist.");
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store new microblog entry comment. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store new microblog entry comment. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new microblog entry comment. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateMicroblogEntryCommentHeart(Request $request) {
        Log::info("Entering MicroblogEntryController updateMicroblogEntryCommentHeart...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:microblog_entry_comments',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $microblogEntryComment = MicroblogEntryComment::where('slug', $request->slug)->first();

                            if ($microblogEntryComment) {
                                $microblogEntryCommentHeart = MicroblogEntryCommentHeart::where('comment_id', $microblogEntryComment->id)
                                                                                        ->where('user_id', $user->id)
                                                                                        ->first();

                                if ($microblogEntryCommentHeart) {
                                    $microblogEntryCommentHeart->is_heart = !($microblogEntryCommentHeart->is_heart);

                                    $microblogEntryCommentHeart->save();

                                    if ($microblogEntryCommentHeart->wasChanged('is_heart')) {
                                        Log::info("Successfully updated microblog entry comment ID " . $microblogEntryComment->id . "'s 'is_heart' state to " . $microblogEntryCommentHeart->is_heart . ". Leaving MicroblogEntryController updateMicroblogEntryCommentHeart...\n");
                                    } else {
                                        Log::error("Failed to update microblog entry comment ID " . $microblogEntryComment->id . "'s 'is_heart' state.\n");

                                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                                    }
                                } else {
                                    Log::info("New ");
                                    $microblogEntryCommentHeart = new MicroblogEntryCommentHeart();

                                    $microblogEntryCommentHeart->comment_id = $microblogEntryComment->id;
                                    $microblogEntryCommentHeart->user_id = $user->id;
                                    $microblogEntryCommentHeart->is_heart = true;

                                    $microblogEntryCommentHeart->save();

                                    if ($microblogEntryCommentHeart) {
                                        Log::info("Successfully stored new microblog entry comment ID " . $microblogEntryComment->id . "'s 'is_heart' state to " . $microblogEntryCommentHeart->is_heart . ". Leaving MicroblogEntryController updateMicroblogEntryCommentHeart...\n");
                                    } else {
                                        Log::error("Failed to store new microblog entry comment's 'is_heart' state.\n");

                                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                                    }
                                }

                                return $this->successResponse('details', $this->getMicroblogEntryCommentHearts($microblogEntryComment->id, $user->id));
                            } else {
                                Log::error("Failed to update microblog entry comment's heart state. Microblog post does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post's comment does not exist.");
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update microblog entry comment's heart state. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update microblog entry comment's heart state. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to update microblog entry comment's heart state. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getMostLovedEntry(Request $request) {
        Log::info("Entering MicroblogEntryController getMicroblogMostLovedEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'auth_username' => 'bail|required|exists:users,username',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {

                            $microblogMostLovedEntry = $this->getMicroblogMostLovedEntry($user->id);

                            if ($microblogMostLovedEntry) {
                                Log::info("Successfully retrieved most loved microblog entry with slug ". $microblogMostLovedEntry['slug'].". MicroblogEntryController getMicroblogMostLovedEntry...\n");

                                return $this->successResponse("details", $microblogMostLovedEntry);
                            } else {
                                Log::notice("No loved microblog entry yet. No action needed.\n");

                                return $this->errorResponse("No loved microblog post yet.");
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to retrieve most loved microblog entry. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to retrieve most loved microblog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve most loved microblog entry. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        } 
    }

    public function getMostActiveEntry(Request $request) {
        Log::info("Entering MicroblogEntryController getMostActiveEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'auth_username' => 'bail|required|exists:users,username',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $authUser = User::where('username', $request->auth_username)->first();
                $user = User::where('username', $request->username)->first();

                if ($authUser && $user) {
                    foreach ($authUser->tokens as $token) {
                        if ($token->tokenable_id === $authUser->id) {

                            $microblogMostActiveEntry = $this->getMicroblogMostActiveEntry($user->id);

                            if ($microblogMostActiveEntry) {
                                Log::info("Successfully retrieved most active microblog entry with slug " . $microblogMostActiveEntry['slug'] . ". MicroblogEntryController getMostActiveEntry...\n");

                                return $this->successResponse("details", $microblogMostActiveEntry);
                            } else {
                                Log::notice("No active microblog entry yet. No action needed.\n");

                                return $this->errorResponse("No active microblog post yet.");
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to retrieve most active microblog entry. Authenticated user and/or author does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to retrieve most active microblog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve most active microblog entry. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
