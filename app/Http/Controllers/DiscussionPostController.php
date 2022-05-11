<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiscussionPost;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Illuminate\Support\Facades\Log;

class DiscussionPostController extends Controller
{
    use ResponseTrait, AuthTrait;
    
    public function getDiscussionPosts() {
        Log::info("Entering DiscussionPostController getDiscussionPosts...");

        try {
            $discussionPosts = DiscussionPost::with('user:id,first_name,last_name,username')->get();

            if (count($discussionPosts) > 0) {
                Log::info("Successfully retrieved discussion posts. Leaving DiscussionPostController getDiscussionPosts...");

                foreach ($discussionPosts as $microblogEntry) {
                    $microblogEntry->body = substr($microblogEntry->body, 0, 100);
                }

                return $this->successResponse('details', $discussionPosts);
            } else {
                Log::error("No discussion posts yet. No action needed.\n");

                return $this->errorResponse("No discussion posts yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve discussion posts. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
