<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiscussionPost;
use App\Models\User;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use Illuminate\Support\Facades\Log;

class DiscussionPostController extends Controller
{
    use ResponseTrait, AuthTrait, PostTrait;
    
    public function getDiscussionPosts() {
        Log::info("Entering DiscussionPostController getDiscussionPosts...");

        try {
            $discussionPosts = $this->getAllDiscussionPosts();

            if (count($discussionPosts) > 0) {
                Log::info("Successfully retrieved discussion posts. Leaving DiscussionPostController getDiscussionPosts...");

                foreach ($discussionPosts as $discussion) {
                    $discussion->body = substr($discussion->body, 0, 100);
                    unset($discussion->deleted_at);
                    unset($discussion->updated_at);
                    if ($discussion->user && $discussion->user->id) {
                        unset($discussion->user->id);
                    }
                }

                return $this->successResponse('details', $discussionPosts);
            } else {
                Log::notice("No discussion posts yet. No action needed.\n");

                return $this->errorResponse("No discussion posts yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve discussion posts. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedDiscussionPosts(Request $request) {
        Log::info("Entering DiscussionPostController getPaginatedDiscussionPosts...");

        $this->validate($request, [
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            $discussionPosts = $this->getChunkedDiscussionPosts($request->offset, $request->limit);

            if (count($discussionPosts) === 0) {
                Log::notice("No more discussion posts. No action needed.\n");

                return $this->errorResponse("No more discussion posts to show.");
            }

            if (count($discussionPosts) > 0) {
                Log::info("Successfully retrieved paginated discussion posts. Leaving DiscussionPostController getPaginatedDiscussionPosts...");

                foreach ($discussionPosts as $discussion) {
                    $discussion->body = substr($discussion->body, 0, 100);
                    unset($discussion->deleted_at);
                    unset($discussion->updated_at);
                    if ($discussion->user && $discussion->user->id) {
                        unset($discussion->user->id);
                    }
                }

                return $this->successResponse('details', $discussionPosts);
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated discussion posts. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeDiscussionPost(Request $request) {
        Log::info("Entering DiscussionPostController storeDiscussionPost...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'title' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|between:2,10000',
            'category' => 'bail|required|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $isUnique = false;
                            $discussionPostSlug = null;

                            $discussionPost = new DiscussionPost();

                            $discussionPost->user_id = $user->id;
                            $discussionPost->title = $request->title;
                            $discussionPost->body = $request->body;

                            do {
                                $discussionPostSlug = $this->generateSlug();

                                if (!(DiscussionPost::where('slug', $discussionPostSlug)->first())) {
                                    $isUnique = true;
                                }
                            } while (!($isUnique));

                            $discussionPost->slug = $discussionPostSlug;
                            $discussionPost->is_hobby = false;
                            $discussionPost->is_wellbeing = false;
                            $discussionPost->is_career = false;
                            $discussionPost->is_coaching = false;
                            $discussionPost->is_science_and_tech = false;
                            $discussionPost->is_social_cause = false;
                            $discussionPost->{'is_'.$request->category} = true;

                            $discussionPost->save();

                            if (!$discussionPost) {
                                Log::error("Failed to store new discussion post.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }

                            Log::info("Successfully stored new discussion post ID ".$discussionPost->id. ". Leaving DiscussionController storeDiscussionPost...");

                            $discussionPosts = $this->getDiscussionPosts();

                            return $this->successResponse("details", $discussionPosts);

                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store discussion post. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store discussion post. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new discussion post. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
