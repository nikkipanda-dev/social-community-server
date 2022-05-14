<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MicroblogEntry;
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
                $microblogEntries = MicroblogEntry::latest()->with('user')->where('user_id', $user->id)->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getUserMicroblogEntries...");

                    foreach ($microblogEntries as $microblogEntry) {
                        $microblogEntry->body = substr($microblogEntry->body, 0, 50);
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
                $microblogEntries = MicroblogEntry::latest()->with('user')->where('user_id', $user->id)->offset(intval($request->offset, 10))->limit(intval($request->limit, 10))->get();

                if (count($microblogEntries) > 0) {
                    Log::info("Successfully retrieved user's microblog entries. Leaving MicroblogEntryController getPaginatedUserMicroblogEntries...");

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
}
