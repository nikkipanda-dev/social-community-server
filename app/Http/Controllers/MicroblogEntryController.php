<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
}
