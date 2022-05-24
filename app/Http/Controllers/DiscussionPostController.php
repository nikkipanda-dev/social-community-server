<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiscussionPost;
use App\Models\DiscussionPostReply;
use App\Models\DiscussionPostReplyHeart;
use App\Models\DiscussionPostSupporter;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use App\Traits\FileTrait;
use Illuminate\Support\Facades\Log;

class DiscussionPostController extends Controller
{
    use ResponseTrait, AuthTrait, PostTrait, FileTrait;
    
    public function getDiscussionPosts(Request $request) {
        Log::info("Entering DiscussionPostController getDiscussionPosts...");

        $this->validate($request, [
            'category' => 'bail|nullable|string|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
        ]);

        try {
            $discussionPosts = $this->getAllDiscussionPosts($request->category ? "is_".$request->category : '');

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
            'category' => 'bail|nullable|string|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            $discussionPosts = $this->getChunkedDiscussionPosts($request->category ? "is_".$request->category : '', $request->offset, $request->limit);

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

    public function getDiscussionPost(Request $request) {
        Log::info("Entering DiscussionPostController getDiscussionPost...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store discussion post. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve discussion post. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {

                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to retrieve discussion post. Post does not exist or might be deleted.\n");
                        
                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    if ($discussionPost['user'] && $discussionPost['user']['id']) {
                        unset($discussionPost['user']['id']);
                    }

                    Log::info("Successfully retrieved discussion post ID ".$discussionPost['id']. ". Leaving DiscussionPostController getDiscussionPost...");

                    unset($discussionPost['id']);

                    return $this->successResponse("details", $discussionPost);

                    break;
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to retrieve discussion post. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getDiscussionPostReplies(Request $request) {
        Log::info("Entering DiscussionPostController getDiscussionPostReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
        ]);
        
        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve discussion post replies. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve discussion post replies. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {

                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to retrieve discussion post replies. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $replies = $this->getAllDiscussionPostReplies($discussionPost['id']);

                    if (count($replies) === 0) {
                        Log::notice("No discussion post replies yet for ID ".$discussionPost['id'].". No action needed.\n");

                        return $this->errorResponse("No replies yet.");
                    }

                    foreach ($replies as $reply) {
                        $reply['heartDetails'] = $this->getDiscussionPostReplyHearts($reply->id, $user->id);
                        unset($reply->id);
                    }

                    Log::info("Successfully retrieved discussion post replies for ID ".$discussionPost['id']. ". Leaving DiscussionPostController getDiscussionPostReplies...\n");

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve discussion post replies. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedDiscussionPostReplies(Request $request) {
        Log::info("Entering DiscussionPostController getPaginatedDiscussionPostReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve paginated discussion post replies. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve paginated discussion post replies. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {

                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to retrieve paginated discussion post replies. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $replies = $this->getChunkedDiscussionPostReplies($discussionPost['id'], $request->limit);

                    if (count($replies) === 0) {
                        Log::notice("No more discussion post replies for ID " . $discussionPost['id'] . ". No action needed.\n");

                        return $this->errorResponse("No additional replies to show.");
                    }

                    foreach ($replies as $reply) {
                        $reply['heartDetails'] = $this->getDiscussionPostReplyHearts($reply->id, $user->id);
                        unset($reply->id);
                    }

                    Log::info("Successfully retrieved paginated discussion post replies for ID " . $discussionPost['id'] . ". Leaving DiscussionPostController getPaginatedDiscussionPostReplies...\n");

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated discussion post replies. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeDiscussionPostReplies(Request $request) {
        Log::info("Entering DiscussionPostController storeDiscussionPostReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
            'body' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store discussion post reply. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store discussion post reply. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to store discussion post reply. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $isUnique = false;
                    $discussionPostReplySlug = null;

                    $reply = new DiscussionPostReply();

                    $reply->discussion_post_id = $discussionPost['id'];
                    $reply->user_id = $user->id;
                    $reply->body = $request->body;

                    do {
                        $discussionPostReplySlug = $this->generateSlug();

                        if (!(DiscussionPostReply::where('slug', $discussionPostReplySlug)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $reply->slug = $discussionPostReplySlug;

                    $reply->save();

                    if (!($reply)) {
                        Log::error("Failed to store discussion post reply.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new discussion post comment ID ".$reply->id. ". Leaving DiscussionPostController getDiscussionPostReplies...\n");

                    $replies = $this->getAllDiscussionPostReplies($discussionPost['id']);

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store discussion post reply. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateDiscussionPostReplies(Request $request) {
        Log::info("Entering DiscussionPostController updateDiscussionPostReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_post_replies',
            'body' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update discussion post reply. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update discussion post reply. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $reply = $this->getDiscussionPostReplyRecord($request->slug);

                    if (!($reply)) {
                        Log::error("Failed to update discussion post reply. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    if ($reply->user_id !== $user->id) {
                        Log::error("Failed to update discussion post reply. Authenticated user is not the author.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $reply->body = $request->body;

                    $reply->save();

                    if (!($reply->wasChanged('body'))) {
                        Log::notice("Discussion post reply body of ID ".$reply->id." was not changed. No action needed.\n");

                        return $this->errorResponse($this->getPredefinedResponse('not changed', "Reply"));
                    }

                    Log::info("Successfully updated discussion post reply ID ".$reply->id. ". Leaving DiscussionPostController updateDiscussionPostReplies...\n");

                    unset($reply->id);
                    unset($reply->updated_at);
                    unset($reply->deleted_at);
                    unset($reply->user_id);
                    if ($reply->user && $reply->user->id) {
                        unset($reply->user->id);
                    }

                    return $this->successResponse("details", $reply);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update discussion post reply. ".$e->getMessage().".\n");
            
            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyDiscussionReplies(Request $request) {
        Log::info("Entering DiscussionPostController destroyDiscussionReplies...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_post_replies',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete discussion post reply. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete discussion post reply. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $reply = $this->getDiscussionPostReplyRecord($request->slug);

                    if (!($reply)) {
                        Log::error("Failed to soft delete discussion post reply. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    if ($reply->user_id !== $user->id) {
                        Log::error("Failed to soft delete discussion post reply. Authenticated user is not the author.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $originalPostId = $reply->getOriginal('discussion_post_id');
                    $originalId = $reply->getOriginal('id');

                    $reply->delete();

                    if (DiscussionPostReply::find($originalId)) {
                        Log::error("Failed to soft delete discussion post reply ID " . $originalId . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted discussion post reply ID " . $originalId . ". Leaving DiscussionPostController destroyDiscussionReplies...\n");

                    $replies = $this->getAllDiscussionPostReplies($originalPostId);

                    return $this->successResponse("details", $replies);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete discussion post reply. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getDiscussionPostSupporters(Request $request) {
        Log::info("Entering DiscussionPostController getDiscussionPostSupporters...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve discussion post supporters. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve discussion post supporters. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {

                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to retrieve discussion post supporters. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $supporters = $this->getAllDiscussionPostSupporters($discussionPost['id']);

                    if (!$supporters) {
                        Log::notice("Discussion post ID ".$discussionPost['id']." has no supporters yet. No action needed.\n");

                        return $this->errorResponse("No supporters yet.");
                    }

                    $isSupporter = $this->isDiscussionPostSupporter($discussionPost['id'], $user->id);

                    Log::info("Successfully retrieved discussion post ID " . $discussionPost['id'] . ". Leaving DiscussionPostController getDiscussionPostSupporters...");

                    return $this->successResponse("details", [
                        'is_supporter' => $isSupporter,
                        'supporters' => $supporters,
                    ]);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve discussion post supporters. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeDiscussionPost(Request $request) {
        Log::info("Entering DiscussionPostController storeDiscussionPost...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'category' => 'bail|nullable|string|in:hobby,wellbeing,career,coaching,science_and_tech,social_cause',
            'title' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|string|between:2,10000',
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
                            $discussionPost->{'is_' . $request->category} = true;

                            $discussionPost->save();

                            if (!$discussionPost) {
                                Log::error("Failed to store new discussion post.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }

                            Log::info("Successfully stored new discussion post ID " . $discussionPost->id . ". Leaving DiscussionController storeDiscussionPost...");

                            $discussionPosts = $this->getAllDiscussionPosts($request->category ? "is_" . $request->category : '');

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
            Log::error("Failed to store new discussion post. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeDiscussionPostSupporter(Request $request) {
        Log::info("Entering DiscussionPostController storeDiscussionPostSupporter...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store discussion post supporter. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store discussion post supporter. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to store discussion post supporter. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $supporter = new DiscussionPostSupporter();

                    $supporter->discussion_post_id = $discussionPost['id'];
                    $supporter->user_id = $user->id;

                    $supporter->save();

                    if (!$supporter) {
                        Log::error("Failed to store discussion post supporter.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new discussion post supporter ID ".$supporter->id. ". Leaving DiscussionPostController storeDiscussionPostSupporter...\n");

                    return $this->successResponse("details", null);
                    
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store discussion post supporter. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyDiscussionPostSupporter(Request $request) {
        Log::info("Entering DiscussionPostController storeDiscussionPost...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_posts',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete discussion post supporter. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete discussion post supporter. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $discussionPost = $this->getDiscussionPostRecord($request->slug);

                    if (!$discussionPost) {
                        Log::error("Failed to soft delete discussion post supporter. Post does not exist or might be deleted.\n");

                        return $this->errorResponse("Discussion post does not exist.");
                    }

                    $supporter = $this->getDiscussionPostSupporter($discussionPost['id'], $user->id);

                    if (!$supporter) {
                        Log::error("Failed to soft delete discussion post supporter. User has not yet supported this post.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $originalId = $supporter->getOriginal('id');

                    $supporter->delete();

                    if ($this->getDiscussionPostSupporter($discussionPost['id'], $user->id)) {
                        Log::error("Failed to soft delete discussion post supporter.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted discussion post supporter ID ".$originalId. ". Leaving DiscussionPostController storeDiscussionPost...\n");

                    return $this->successResponse("details", null);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete discussion post supporter. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateDiscussionPostHearts(Request $request) {
        Log::info("Entering DiscussionPostController updateDiscussionPostHearts...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:discussion_post_replies',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store discussion post reply heart. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update discussion post reply heart. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {

                    $reply = $this->getDiscussionPostReplyRecord($request->slug);

                    if (!($reply)) {
                        Log::error("Failed to update discussion post reply heart. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse("Reply does not exist.");
                    }

                    $heart = DiscussionPostReplyHeart::where('discussion_post_reply_id', $reply->id)
                                                     ->where('user_id', $user->id)
                                                     ->first();

                    $originalId = null;
                    
                    if ($heart) {
                        $originalId = $heart->getOriginal('id');
                        $heart->delete();

                        if (DiscussionPostReplyHeart::find($originalId)) {
                            Log::error("Failed to remove discussion post reply heart.\n");

                            return $this->errorResponse("Reply does not exist.");
                        }

                        Log::info("Successfully removed discussion post reply heart ".$originalId. ". Leaving DiscussionPostController updateDiscussionPostHearts...\n");

                        $hearts = $this->getDiscussionPostReplyHearts($reply->id, $user->id);

                        return $this->successResponse("details", $hearts);
                    }

                    $heart = new DiscussionPostReplyHeart();

                    $heart->discussion_post_reply_id = $reply->id;
                    $heart->user_id = $user->id;

                    $heart->save();

                    if (!($heart)) {
                        Log::error("Failed to update discussion post reply heart.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully updated discussion post reply heart ID ".$heart->id. ". Leaving DiscussionPostController updateDiscussionPostHearts...\n");

                    $hearts = $this->getDiscussionPostReplyHearts($reply->id, $user->id);

                    return $this->successResponse("details", $hearts);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update discussion post reply heart. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));  
        }
    }
}
