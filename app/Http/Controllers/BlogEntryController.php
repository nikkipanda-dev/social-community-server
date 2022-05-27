<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BlogEntry;
use App\Models\BlogEntryComment;
use App\Models\BlogEntryCommentHeart;
use App\Models\BlogEntryImage;
use App\Models\BlogEntrySupporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\PostTrait;
use Exception;
use Illuminate\Support\Facades\Log;

class BlogEntryController extends Controller
{
    use ResponseTrait, AuthTrait, PostTrait;
    
    public function getBlogEntries(Request $request) {
        Log::info("Entering BlogEntryController getBlogEntries...");

        $this->validate($request, [
            'category' => 'bail|nullable|string|in:newest,oldest',
        ]);

        try {
            $entries = $this->getAllBlogEntries($request->category ? $request->category : '');

            if (count($entries) === 0) {
                Log::notice("No blog entries yet. No action needed.\n");

                return $this->errorResponse("No blog entries yet.");
            }

            foreach ($entries as $entry) {
                unset($entry->user_id);
                unset($entry->id);
                unset($entry->updated_at);
                unset($entry->deleted_at);
                if ($entry->user && $entry->user->id) {
                    unset($entry->user->id);
                }
            }

            Log::info("Successfully retrieved blog entries. Leaving BlogEntryController getBlogEntries...");

            return $this->successResponse('details', $entries);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedBlogEntries(Request $request) {
        Log::info("Entering BlogEntryController getPaginatedBlogEntries...");

        $this->validate($request, [
            'category' => 'bail|nullable|string|in:newest,oldest',
            'offset' => 'bail|required|numeric',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            $entries = $this->getChunkedBlogEntries($request->category ? $request->category : '', $request->offset, $request->limit);

            if (count($entries) === 0) {
                Log::notice("No additional blog entries fetched. No action needed.\n");

                return $this->errorResponse("No additional blog entries to show.");
            }

            foreach ($entries as $entry) {
                unset($entry->user_id);
                unset($entry->id);
                unset($entry->updated_at);
                unset($entry->deleted_at);
                if ($entry->user && $entry->user->id) {
                    unset($entry->user->id);
                }
            }

            Log::info("Successfully retrieved paginated blog entries. Leaving BlogEntryController getBlogEntries...");

            return $this->successResponse('details', $entries);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated blog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getBlogEntry(Request $request) {
        Log::info("Entering BlogEntryController getBlogEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
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
                    $entry = $this->getBlogEntryRecord($request->slug);

                    if (!($entry)) {
                        Log::error("Failed to retrieve blog entry. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    unset($entry->id);
                    unset($entry->user_id);
                    unset($entry->deleted_at);
                    unset($entry->updated_at);

                    if ($entry->user && $entry->user->id) {
                        unset($entry->user->id);
                    }

                    if ($entry->blogEntryImages && (count($entry->blogEntryImages) > 0)) {
                        foreach ($entry->blogEntryImages as $image) {
                            $image['path'] = Storage::disk($image->disk)->url("blog-entries/".$image->path);
                            unset($image->disk);
                        }
                    }

                    Log::info("Successfully retrieved blog entry. Leaving BlogEntryController getBlogEntry...");

                    return $this->successResponse('details', $entry);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entries. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeBlogEntry(Request $request) {
        Log::info("Entering BlogEntryController storeBlogEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'title' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|string|between:2,10000',
            'images' => 'bail|nullable|array|min:1',
            'images.*.*' => 'image',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store new blog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store new blog entry. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    if (!(($request->body[0] === '{') || ($request->body[0] === '['))) {
                        Log::error("Failed to store new blog entry. Body is not a JSON string.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $isUnique = false;
                    $blogEntrySlug = null;

                    $post = new BlogEntry();

                    $post->user_id = $user->id;
                    $post->title = $request->title;
                    $post->body = $request->body;

                    do {
                        $blogEntrySlug = $this->generateSlug();

                        if (!(BlogEntry::where('slug', $blogEntrySlug)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $post->slug = $blogEntrySlug;

                    $post->save();

                    if (!($post)) {
                        Log::error("Failed to store new blog entry.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }
                    
                    $isSuccess = false;
                    $errorText = '';

                    if (is_array($request->images)) {
                        $imagesResponse = DB::transaction(function() use($request, $isSuccess, $errorText, $post) {
                            foreach($request->images as $image) {
                                Log::info($image);
                                if (!($image->isValid())) {
                                    $errorText = "Failed to store blog entry images. Image is invalid.";

                                    throw new Exception($errorText);
                                }

                                $isUnique = false;
                                $slug = null;

                                do {
                                    $slug = $this->generateSlug();
                                    if (!(Storage::disk('do_space')->exists("blog-entries/".$slug . "." . $image->extension()))) {
                                        $isUnique = true;
                                    }
                                } while (!($isUnique));

                                Storage::disk('do_space')->putFileAs(
                                    "blog-entries",
                                    $image,
                                    $slug.".".$image->extension()
                                );

                                if (Storage::disk('do_space')->exists("blog-entries/" . $slug . "." . $image->extension())) {
                                    Storage::disk("do_space")->setVisibility("blog-entries/" . $slug . "." . $image->extension(), 'public');
                                }

                                $newImage = new BlogEntryImage();

                                $newImage->blog_entry_id = $post->id;
                                $newImage->disk = "do_space";
                                $newImage->path = $slug.".".$image->extension();
                                $newImage->extension = $image->extension();

                                $newImage->save();

                                if (!$newImage) {
                                    $isSuccess = false;
                                    $errorText = "Failed to store a blog entry image.";

                                    throw new Exception($errorText);
                                }

                                $isSuccess = true;
                            }

                            return [
                                'isSuccess' => $isSuccess,
                                'errorText' => $errorText,
                            ];
                        }, 3);

                        if (!$imagesResponse['isSuccess']) {
                            Log::error("Failed to store new blog entry images for ID " . $post->id . ".\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }
                    }
                    
                    Log::info("Successfully stored new blog entry ID ".$post->id. ". Leaving BlogEntryController storeBlogEntry...\n");

                    return $this->successResponse("details", $post->slug);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new blog entry. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateBlogEntry(Request $request) {
        Log::info("Entering BlogEntryController updateBlogEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
            'title' => 'bail|required|string|between:2,50',
            'body' => 'bail|required|string|between:2,10000',
            'images' => 'bail|nullable|array|min:1',
            'images.*.*' => 'image',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update blog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update blog entry. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    if (!(($request->body[0] === '{') || ($request->body[0] === '['))) {
                        Log::error("Failed to update blog entry. Body is not a JSON string.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!($post)) {
                        Log::error("Failed to update blog entry. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    $post->title = $request->title;
                    $post->body = $request->body;

                    $post->save();

                    $isSuccess = false;
                    $errorText = '';

                    if (is_array($request->images)) {
                        $imagesResponse = DB::transaction(function () use ($request, $isSuccess, $errorText, $post) {
                            // Soft delete existing images first
                            if ($post->blogEntryImages && (count($post->blogEntryImages) > 0)) {
                                foreach ($post->blogEntryImages as $image) {
                                    $image->delete();
                                }
                            }

                            // Loop through each uploaded image
                            foreach ($request->images as $image) {
                                Log::info($image);
                                if (!($image->isValid())) {
                                    $errorText = "Failed to update blog entry images. Image is invalid.";

                                    throw new Exception($errorText);
                                }

                                $isUnique = false;
                                $slug = null;

                                do {
                                    $slug = $this->generateSlug();
                                    if (!(Storage::disk('do_space')->exists("blog-entries/" . $slug . "." . $image->extension()))) {
                                        $isUnique = true;
                                    }
                                } while (!($isUnique));

                                Storage::disk('do_space')->putFileAs(
                                    "blog-entries",
                                    $image,
                                    $slug . "." . $image->extension()
                                );

                                if (Storage::disk('do_space')->exists("blog-entries/" . $slug . "." . $image->extension())) {
                                    Storage::disk("do_space")->setVisibility("blog-entries/" . $slug . "." . $image->extension(), 'public');
                                }

                                $newImage = new BlogEntryImage();

                                $newImage->blog_entry_id = $post->id;
                                $newImage->disk = "do_space";
                                $newImage->path = $slug . "." . $image->extension();
                                $newImage->extension = $image->extension();

                                $newImage->save();

                                if (!$newImage) {
                                    $isSuccess = false;
                                    $errorText = "Failed to update a blog entry image.";

                                    throw new Exception($errorText);
                                }

                                $isSuccess = true;
                            }

                            return [
                                'isSuccess' => $isSuccess,
                                'errorText' => $errorText,
                            ];
                        }, 3);

                        if (!$imagesResponse['isSuccess']) {
                            Log::error("Failed to update blog entry images for ID " . $post->id . ".\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }
                    }

                    Log::info("Successfully updated blog entry ID " . $post->id . ". Leaving BlogEntryController updateBlogEntry...\n");

                    $post = $this->getBlogEntryRecord($request->slug);

                    unset($post->id);
                    unset($post->user_id);
                    unset($post->updated_at);
                    unset($post->deleted_at);

                    if ($post->user && $post->user->id) {
                        unset($post->user->id);
                    }

                    if ($post->blogEntryImages && (count($post->blogEntryImages) > 0)) {
                        foreach ($post->blogEntryImages as $image) {
                            $image['path'] = Storage::disk($image->disk)->url("blog-entries/" . $image->path);
                            unset($image->disk);
                        }
                    }

                    return $this->successResponse("details", $post);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new blog entry. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyBlogEntry(Request $request) {
        Log::info("Entering BlogEntryController destroyBlogEntry...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete blog entry. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete blog entry. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!($post)) {
                        Log::error("Failed to soft delete blog entry. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    $originalId = $post->getOriginal('id');

                    $post->delete();

                    if (BlogEntry::find($originalId)) {
                        Log::error("Failed to soft delete blog entry ID " . $originalId . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted blog entry ID " . $originalId . ". Leaving BlogEntryController destroyBlogEntry...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new blog entry. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getBlogEntrySupporters(Request $request) {
        Log::info("Entering BlogEntryController getBlogEntrySupporters...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve blog entry supporters. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve blog entry supporters. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!$post) {
                        Log::error("Failed to retrieve blog entry supporters. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Blog entry does not exist.");
                    }

                    $supporters = $this->getAllBlogEntrySupporters($post->id);

                    if (!$supporters) {
                        Log::notice("Blog entry ID " . $post->id." has no supporters yet. No action needed.\n");

                        return $this->errorResponse("No supporters yet.");
                    }

                    $isSupporter = $this->isBlogEntrySupporter($post->id, $user->id);

                    Log::info("Successfully retrieved blog entry ID " . $post->id. ". Leaving BlogEntryController getBlogEntrySupporters...");

                    return $this->successResponse("details", [
                        'is_supporter' => $isSupporter,
                        'supporters' => $supporters,
                    ]);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entry supporters. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeBlogEntrySupporter(Request $request) {
        Log::info("Entering BlogEntryController storeBlogEntrySupporter...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store blog entry supporter. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store blog entry supporter. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!$post) {
                        Log::error("Failed to store blog entry supporter. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Blog entry does not exist.");
                    }

                    $supporter = new BlogEntrySupporter();

                    $supporter->blog_entry_id = $post->id;
                    $supporter->user_id = $user->id;

                    $supporter->save();

                    if (!$supporter) {
                        Log::error("Failed to store blog entry supporter.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new blog entry supporter ID " . $supporter->id . ". Leaving BlogEntryController storeBlogEntrySupporter...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store blog entry supporter. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyBlogEntrySupporter(Request $request) {
        Log::info("Entering BlogEntryController destroyBlogEntrySupporter...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete blog entry supporter. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete blog entry supporter. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!$post) {
                        Log::error("Failed to soft delete blog entry supporter. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Blog entry post does not exist.");
                    }

                    $supporter = BlogEntrySupporter::where('blog_entry_id', $post->id)
                                                   ->where('user_id', $user->id)
                                                   ->first();

                    if (!$supporter) {
                        Log::error("Failed to soft delete blog entry supporter. Supporter does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $originalId = $supporter->getOriginal('id');

                    $supporter->delete();

                    if (BlogEntrySupporter::find($originalId)) {
                        Log::error("Failed to soft delete blog entry supporter ID ".$originalId.".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted new blog entry supporter ID " . $originalId . ". Leaving BlogEntryController destroyBlogEntrySupporter...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete blog entry supporter. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getBlogEntryComments(Request $request) {
        Log::info("Entering BlogEntryController getBlogEntryComments...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve blog entry comments. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve blog entry comments. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!($post)) {
                        Log::error("Failed to retrieve blog entry comments. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    $comments = $this->getAllBlogEntryComments($post->id);

                    if (count($comments) === 0) {
                        Log::notice("No comments for community blog entry ID ".$post->id." yet.\n");

                        return $this->errorResponse("No comments yet.");
                    }

                    foreach ($comments as $comment) {
                        $comment['heartDetails'] = $this->getBlogEntryCommentHearts($comment->id, $user->id);
                        unset($comment->id);
                        unset($comment->blog_entry_id);
                        unset($comment->created_at);
                        unset($comment->updated_at);
                        unset($comment->deleted_at);
                        unset($comment->user_id);

                        if ($comment->user && $comment->user->id) {
                            unset($comment->user->id);
                        }
                    }

                    Log::info("Successfully retrieved blog entry comments for community blog entry ID ".$post->id. ". Leaving BlogEntryController getBlogEntryComments...\n");

                    return $this->successResponse("details", $comments);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entry comments. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getPaginatedBlogEntryComments(Request $request) {
        Log::info("Entering BlogEntryController getPaginatedBlogEntryComments...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
            'limit' => 'bail|required|numeric',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to retrieve paginated blog entry comments. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to retrieve paginated blog entry comments. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!($post)) {
                        Log::error("Failed to retrieve paginated blog entry comments. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    $comments = $this->getChunkedBlogEntryComments($post->id, $request->limit);

                    if (count($comments) === 0) {
                        Log::notice("No additional comments for community blog entry ID " . $post->id . ". No action needed.\n");

                        return $this->errorResponse("No additional comments to show.");
                    }

                    foreach ($comments as $comment) {
                        $comment['heartDetails'] = $this->getBlogEntryCommentHearts($comment->id, $user->id);
                        unset($comment->id);
                        unset($comment->blog_entry_id);
                        unset($comment->created_at);
                        unset($comment->updated_at);
                        unset($comment->deleted_at);
                        unset($comment->user_id);

                        if ($comment->user && $comment->user->id) {
                            unset($comment->user->id);
                        }
                    }

                    Log::info("Successfully retrieved paginated blog entry comments for community blog entry ID " . $post->id . ". Leaving BlogEntryController getPaginatedBlogEntryComments...\n");

                    return $this->successResponse("details", $comments);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve paginated blog entry comments. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeBlogEntryComment(Request $request) {
        Log::info("Entering BlogEntryController storeBlogEntryComment...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entries',
            'body' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to store new blog entry comment. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to store new blog entry comment. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $post = $this->getBlogEntryRecord($request->slug);

                    if (!$post) {
                        Log::error("Failed to store new blog entry comment. Entry does not exist or might be deleted.\n");

                        return $this->errorResponse("Community blog entry does not exist.");
                    }

                    $isUnique = false;
                    $blogEntryCommentSlug = null;

                    $comment = new BlogEntryComment();

                    $comment->blog_entry_id = $post->id;
                    $comment->user_id = $user->id;
                    $comment->body = $request->body;

                    do {
                        $blogEntryCommentSlug = $this->generateSlug();

                        if (!(BlogEntryComment::where('slug', $blogEntryCommentSlug)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $comment->slug = $blogEntryCommentSlug;

                    $comment->save();

                    if (!($comment)) {
                        Log::error("Failed to store new blog entry comment for ID ".$post->id.".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully stored new blog entry comment for ID " . $post->id . ". Leaving BlogEntryController storeBlogEntryComment...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to store new blog entry comment. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateBlogEntryComment(Request $request) {
        Log::info("Entering BlogEntryController updateBlogEntryComment...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entry_comments',
            'body' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update blog entry comment. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update blog entry comment. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $comment = BlogEntryComment::with('user:id,first_name,last_name,username')->where('slug', $request->slug)->first();

                    if (!($comment)) {
                        Log::error("Failed to update blog entry comment. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    if ($comment->user_id !== $user->id) {
                        Log::error("Failed to update blog entry comment. Authenticated user is not the author.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $comment->body = $request->body;

                    $comment->save();

                    if (!($comment->wasChanged('body'))) {
                        Log::notice("Blog entry comment body of ID " . $comment->id . " was not changed. No action needed.\n");

                        return $this->errorResponse($this->getPredefinedResponse('not changed', "Comment"));
                    }

                    Log::info("Successfully updated blog entry comment ID " . $comment->id . ". Leaving DiscussionPostController updateDiscussionPostReplies...\n");

                    unset($comment->id);
                    unset($comment->updated_at);
                    unset($comment->deleted_at);

                    if ($comment->user && $comment->user->id) {
                        unset($comment->user->id);
                    }

                    return $this->successResponse("details", $comment);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update blog entry comment. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyBlogEntryComment(Request $request) {
        Log::info("Entering BlogEntryController destroyBlogEntryComment...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entry_comments',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to soft delete blog entry comment. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to soft delete blog entry comment. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $comment = BlogEntryComment::with('user:id,first_name,last_name,username')->where('slug', $request->slug)->first();

                    if (!($comment)) {
                        Log::error("Failed to soft delete blog entry comment. Reply does not exist or might be deleted.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    if ($comment->user_id !== $user->id) {
                        Log::error("Failed to soft delete blog entry comment. Authenticated user is not the author.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $originalId = $comment->getOriginal('id');

                    $comment->delete();

                    if (BlogEntryComment::find($originalId)) {
                        Log::error("Failed to delete " . $comment->id . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully soft deleted blog entry comment ID " . $originalId . ". Leaving DiscussionPostController updateDiscussionPostReplies...\n");

                    return $this->successResponse("details", null);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete blog entry comment. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateBlogEntryCommentHeart(Request $request) {
        Log::info("Entering BlogEntryController updateBlogEntryCommentHeart...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'slug' => 'bail|required|string|exists:blog_entry_comments',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update community blog entry comment heart. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update community blog entry comment heart. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $comment = $this->getBlogEntryCommentRecord($request->slug);

                    if (!($comment)) {
                        Log::error("Failed to update community blog entry comment heart. Comment does not exist or might be deleted.\n");

                        return $this->errorResponse("Comment does not exist.");
                    }

                    $heart = BlogEntryCommentHeart::where('blog_entry_comment_id', $comment->id)
                                                  ->where('user_id', $user->id)
                                                  ->first();

                    if ($heart) {
                        $originalId = $heart->getOriginal('id');

                        $heart->delete();

                        if (BlogEntryCommentHeart::find($originalId)) {
                            Log::error("Failed to soft delete community blog entry comment heart.\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        Log::info("Successfully soft deleted community blog entry comment heart ID ".$originalId. ". Leaving BlogEntryController updateBlogEntryCommentHeart...\n");
                    }

                    if (!($heart)) {
                        $heart = new BlogEntryCommentHeart();

                        $heart->blog_entry_comment_id = $comment->id;
                        $heart->user_id = $user->id;

                        $heart->save();

                        if (!($heart)) {
                            Log::error("Failed to store community blog entry comment heart for ID " . $comment->id . ".\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        Log::info("Successfully stored community blog entry comment heart ID " . $heart->id . ". Leaving BlogEntryController updateBlogEntryCommentHeart...\n");
                    }

                    $heartDetails = $this->getBlogEntryCommentHearts($comment->id, $user->id);

                    return $this->successResponse("details", $heartDetails);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update community blog entry comment heart. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getBlogEntryWordsmiths(Request $request) {
        Log::info("Entering BlogEntryController getBlogEntryWordsmiths...");

        try {
            $users = $this->getAllBlogEntryWordsmiths();

            if (count($users) === 0) {
                Log::notice("No wordsmiths yet. No action needed.\n");

                return $this->errorResponse("No wordsmiths yet.");
            }

            foreach ($users as $user) {
                unset($user->id);
                unset($user->email);
                unset($user->email_verified_at);
                unset($user->created_at);
                unset($user->updated_at);
                unset($user->deleted_at);
                unset($user->is_super_admin);
                unset($user->is_admin);
            }

            Log::info("Successfully retrieved wordsmiths. Leaving BlogEntryController getBlogEntryWordsmiths...\n");

            return $this->successResponse("details", $users);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve blog entry wordsmiths. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
