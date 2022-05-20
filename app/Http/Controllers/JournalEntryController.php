<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\User;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use Illuminate\Support\Facades\Log;

class JournalEntryController extends Controller
{
    use ResponseTrait, PostTrait, AuthTrait;

    public function getJournalEntries() {
        Log::info("Entering JournalEntryController getJournalEntries...");

        try {
            $journalEntries = JournalEntry::with('user:id,first_name,last_name,username')->get();

            if (count($journalEntries) > 0) {
                Log::info("Successfully retrieved journal entries. Leaving MicroblogEntryController getMicroblogEntries...");

                foreach ($journalEntries as $microblogEntry) {
                    $microblogEntry->body = substr($microblogEntry->body, 0, 100);
                }

                return $this->successResponse('details', $journalEntries);
            } else {
                Log::error("No journal entries yet. No action needed.\n");

                return $this->errorResponse("No journal entries yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve journal entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getUserJournalEntries(Request $request) {
        Log::info("Entering JournalEntryController getUserJournalEntries...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
        ]);

        try {
            $user = User::where('username', $request->username)->first();

            if ($user) {
                $journalEntries = JournalEntry::latest()
                                                ->with('user:id,first_name,last_name,username')
                                                ->where('user_id', $user->id)
                                                ->get();

                if (count($journalEntries) > 0) {
                    Log::info("Successfully retrieved user's journal entries. Leaving JournalEntryController getUserJournalEntries...");

                    foreach ($journalEntries as $entry) {
                        unset($entry->id);
                        unset($entry->updated_at);
                        unset($entry->deleted_at);
                        unset($entry->user->id);
                    }

                    return $this->successResponse('details', $journalEntries);
                } else {
                    Log::error("No journal entries yet. No action needed.\n");

                    return $this->errorResponse("No journal entries yet.");
                }
            } else {
                Log::error("Failed to retrieve user's journal entries. Both users do not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user's journal entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedUserJournalEntries(Request $request) {
        Log::info('request '.$request->offset);
        Log::info("Entering JournalEntryController getPaginatedUserJournalEntries...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            $user = User::where('username', $request->username)->first();

            if ($user) {
                $journalEntries = JournalEntry::latest()
                                              ->with('user:id,first_name,last_name,username')
                                              ->where('user_id', $user->id)
                                              ->offset(intval($request->offset, 10))
                                              ->limit(intval($request->limit, 10))
                                              ->get();

                if (count($journalEntries) > 0) {
                    Log::info("Successfully retrieved user's paginated journal entries. Leaving JournalEntryController getPaginatedUserJournalEntries...");

                    foreach ($journalEntries as $entry) {
                        unset($entry->id);
                        unset($entry->updated_at);
                        unset($entry->deleted_at);
                        unset($entry->user->id);
                    }

                    return $this->successResponse('details', $journalEntries);
                } else {
                    Log::error("No more additional journal entries. No action needed.\n");

                    return $this->errorResponse("No more journal entries to show.");
                }
            } else {
                Log::error("Failed to retrieve user's paginated journal entries. Both users do not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user's paginated journal entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeJournalEntry(Request $request) {
        Log::info($request->body[0] === '{' || $request->body[0] === '[');
        Log::info("Entering JournalEntryController storeJournalEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'title' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|between:2,10000',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {

                    if (!(($request->body[0] === '{') || ($request->body[0] === '['))) {
                        Log::error("Failed to store journal entry. Body is not a JSON string.");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $isUnique = false;
                            $journalEntrySlug = null;

                            $journalEntry = new JournalEntry();

                            $journalEntry->user_id = $user->id;
                            $journalEntry->title = $request->title;
                            $journalEntry->body = $request->body;

                            do {
                                $journalEntrySlug = $this->generateSlug();

                                if (!(JournalEntry::where('slug', $journalEntrySlug)->first())) {
                                    $isUnique = true;
                                }
                            } while (!($isUnique));

                            $journalEntry->slug = $journalEntrySlug;

                            $journalEntry->save();

                            if (!$journalEntry) {
                                Log::error("Failed to store journal entry.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }

                            if ($journalEntry) {
                                Log::info("Successfully stored new journal entry ID ".$journalEntry->id. ". Leaving JournalEntryController storeJournalEntry....\n");

                                return $this->successResponse("details", $journalEntry->only(['title', 'body', 'slug', 'created_at']));
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store journal entry. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store journal entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store journal entry. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getJournalEntry(Request $request) {
        Log::info("Entering JournalEntryController getJournalEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|exists:journal_entries',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $journalEntry = $this->getJournalEntryRecord($request->slug);

                            if (!$journalEntry) {
                                Log::error("Failed to retrieve journal entry. Journal entry does not exist or might be deleted.\n");

                                return $this->errorResponse("Journal entry does not exist.");
                            }

                            if (!($journalEntry->user_id === $user->id)) {
                                Log::error("Failed to retrieve journal entry. Journal entry exists but author is not the authenticated user.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }

                            if ($journalEntry) {
                                Log::info("Successfully retrieved journal entry ID ".$journalEntry->id. ". Leaving JournalEntryController getJournalEntry...\n");

                                return $this->successResponse("details", $journalEntry->only(['title', 'body', 'slug', 'created_at']));
                            }

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to retrieve journal entry. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to retrieve journal entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve journal entry. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
