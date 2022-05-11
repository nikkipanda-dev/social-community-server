<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlogEntry;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class BlogEntryController extends Controller
{
    use ResponseTrait, AuthTrait;
    
    public function getBlogEntries() {
        Log::info("Entering BlogEntryController getBlogEntries...");

        try {
            $blogEntries = BlogEntry::with('user:id,first_name,last_name,username')->get();

            if (count($blogEntries) > 0) {
                Log::info("Successfully retrieved blog entries. Leaving BlogEntryController getBlogEntries...");

                foreach ($blogEntries as $blogEntry) {
                    $blogEntry->body = substr($blogEntry->body, 0, 50);
                }

                return $this->successResponse('details', $blogEntries);
            } else {
                Log::error("No blog entries yet. No action needed.\n");

                return $this->errorResponse("No blog entries yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
