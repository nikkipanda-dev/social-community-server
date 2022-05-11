<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class JournalEntryController extends Controller
{
    use ResponseTrait, AuthTrait;

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
}
