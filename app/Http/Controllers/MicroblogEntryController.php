<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MicroblogEntry;
use App\Models\MicroblogEntryHeart;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class MicroblogEntryController extends Controller
{
    use ResponseTrait, AuthTrait;
    
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
        ]);

        try {
            $user = User::where('username', $request->username)->first();

            if ($user) {
                $microblogEntries = MicroblogEntry::latest()
                                                  ->with('user:id,first_name,last_name,username')
                                                  ->where('user_id', $user->id)
                                                  ->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getUserMicroblogEntries...");
                    
                    foreach($microblogEntries as $entry) {
                        $entry['hearts'] = $this->getMicroblogEntryHearts($entry->id);
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

    public function getPaginatedUserMicroblogEntries(Request $request) {
        Log::info("Entering MicroblogEntryController getPaginatedUserMicroblogEntries...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'offset' => 'bail|numeric',
            'limit' => 'bail|numeric',
        ]);

        try {
            $user = User::where('username', $request->username)->first();

            if ($user) {
                $microblogEntries = MicroblogEntry::latest()->with('user:id,first_name,last_name,username')->where('user_id', $user->id)->offset(intval($request->offset, 10))->limit(intval($request->limit, 10))->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getPaginatedUserMicroblogEntries...");

                    foreach ($microblogEntries as $entry) {
                        $entry['hearts'] = $this->getMicroblogEntryHearts($entry->id);
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
                            $microblogEntry = new MicroblogEntry();

                            $microblogEntry->user_id = $user->id;
                            $microblogEntry->body = $request->body;

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

    public function getMicroblogEntryHearts($id) {
        Log::info("Entering MicroblogEntryController getMicroblogEntryHearts...");

        $heartCount = null;

        try {
            $microblogEntryHearts = MicroblogEntryHeart::where('microblog_entry_id', $id)
                                                       ->where('is_heart', true)
                                                       ->get();

            if (count($microblogEntryHearts) > 0) {
                $heartCount = count($microblogEntryHearts);
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve heart count. ".$e->getMessage().".\n");
        }

        Log::info("count ".$heartCount);

        return $heartCount;
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
                                $microblogEntryHeart = MicroblogEntryHeart::where('user_id', $user->id)->first();

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

                                return $this->successResponse('details', $this->getMicroblogEntryHearts($microblogEntry->id));
                            } else {
                                Log::error("Failed to update microblog entry heart. Microblog does not exist or might be deleted.\n");

                                return $this->errorResponse("Microblog post does not exist.");
                            }
                            
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update microblog entry heart. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
