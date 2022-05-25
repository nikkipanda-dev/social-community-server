<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\MicroblogEntry;
use App\Models\User;
use App\Models\DiscussionPost;
use App\Models\DiscussionPostReply;
use App\Models\DiscussionPostReplyHeart;
use App\Models\DiscussionPostSupporter;
use Illuminate\Support\Str;
use App\Models\MicroblogEntryComment;
use App\Models\MicroblogEntryCommentHeart;
use App\Models\MicroblogEntryHeart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

trait PostTrait {
    public function generateSlug() {
        $rand = bin2hex(random_bytes(30));

        return $rand;
    }

    public function getMicroblogEntry($slug) {
        Log::info("Entering PostTrait getMicroblogEntry...");

        $microblogEntry = MicroblogEntry::where('slug', $slug)->first();

        return $microblogEntry;
    }

    public function getMicroblogEntryHearts($id, $userId) {
        Log::info("Entering PostTrait getMicroblogEntryHearts...");

        $heartDetails = [
            'count' => 0,
            'is_heart' => false,
        ];

        try {
            $microblogEntryHearts = MicroblogEntryHeart::with('user')
                                                       ->where('microblog_entry_id', $id)
                                                       ->where('is_heart', true)
                                                       ->get();

            if (count($microblogEntryHearts) > 0) {
                foreach ($microblogEntryHearts as $heart) {
                    if ($heart->user->id === $userId) {
                        $heartDetails['is_heart'] = true;

                        break;
                    }
                }

                $heartDetails['count'] = count($microblogEntryHearts);
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve heart count. " . $e->getMessage() . ".\n");
        }

        return $heartDetails;
    }

    public function getMicroblogEntryComments($id) {
        Log::info("Entering PostTrait getMicroblogEntryComments...");

        $comments = null;

        try {
            $microblogEntryComments = MicroblogEntryComment::latest()
                                                           ->with('user:id,first_name,last_name,username')
                                                           ->where('microblog_entry_id', $id)
                                                           ->get();

            if (count($microblogEntryComments) > 0) {
                foreach ($microblogEntryComments as $comment) {
                    unset($comment->updated_at);
                    unset($comment->deleted_at);
                    unset($comment->user->id);
                }

                $comments = $microblogEntryComments;
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve microblog entry comments. " . $e->getMessage() . ".\n");
        }

        return $comments;
    }

    public function getMicroblogEntryCommentHearts($id, $userId) {
        Log::info("Entering PostTrait getMicroblogEntryCommentHearts...");

        $heartDetails = [];

        try {
            $microblogEntryCommentHearts = MicroblogEntryCommentHeart::with('user')
                                                                     ->where('comment_id', $id)
                                                                     ->where('is_heart', true)
                                                                     ->get();

            if (count($microblogEntryCommentHearts) > 0) {
                foreach ($microblogEntryCommentHearts as $heart) {
                    if ($heart->user->id === $userId) {
                        $heartDetails['is_heart'] = true;

                        break;
                    }
                }

                $heartDetails['count'] = count($microblogEntryCommentHearts);
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve comment heart count. " . $e->getMessage() . ".\n");
        }

        return $heartDetails;
    }

    public function getMicroblogMostLovedEntry($userId) {
        Log::info("Entering PostTrait getMicroblogMostLovedEntry...");

        $mostLovedMicroblogEntry = null;

        try {
            $microblogEntries = MicroblogEntry::withCount(['microblogEntryHearts' => function (Builder $q) {
                $q->where('is_heart', true);
            }])->where('user_id', $userId)->orderBy('microblog_entry_hearts_count', 'desc')->get();

            if ($microblogEntries) {
                if (count($microblogEntries) > 0) {
                    $mostLovedMicroblogEntry = $microblogEntries->first();

                    if ($mostLovedMicroblogEntry) {
                        $mostLovedMicroblogEntry = $mostLovedMicroblogEntry->only(['body', 'slug', 'created_at', 'microblog_entry_hearts_count']);
                    }
                }
            } else {
                Log::error("Failed to retrieve most loved microblog entry. User does not exist or might be deleted.\n");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to retrieve most loved microblog entry. ".$e->getMessage().".\n");
        }

        return $mostLovedMicroblogEntry;
    }

    public function getMicroblogMostActiveEntry($userId) {
        Log::info("Entering PostTrait getMicroblogMostActiveEntry...");

        $mostActiveMicroblogEntry = null;

        try {
            $microblogEntries = MicroblogEntry::withCount('microblogEntryComments')
                                              ->where('user_id', $userId)
                                              ->orderBy('microblog_entry_comments_count', 'desc')
                                              ->get();

            if ($microblogEntries) {
                if (count($microblogEntries) > 0) {
                    $mostActiveMicroblogEntry = $microblogEntries->first();

                    if ($mostActiveMicroblogEntry) {
                        $mostActiveMicroblogEntry = $mostActiveMicroblogEntry->only(['body', 'slug', 'created_at', 'microblog_entry_comments_count']);
                    }
                }
            } else {
                Log::error("Failed to retrieve most active microblog entry. User does not exist or might be deleted.\n");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve most active microblog entry. " . $e->getMessage() . ".\n");
        }

        return $mostActiveMicroblogEntry;
    }

    // Journal
    public function getAllJournalEntries($userId) {
        Log::info("Entering PostTrait getAllJournalEntries...");

        $journalEntries = JournalEntry::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('user_id', $userId)
                                      ->get();

        return $journalEntries;
    }

    public function getChunkedJournalEntries($userId, $offset, $limit) {
        Log::info("Entering PostTrait getChunkedJournalEntries...");

        $journalEntries = JournalEntry::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('user_id', $userId)
                                      ->offset(intval($offset, 10))
                                      ->limit(intval($limit, 10))
                                      ->get();

        return $journalEntries;
    }

    public function getJournalEntryRecord($slug) {
        Log::info("Entering PostTrait getJournalEntryRecord...");

        $journalEntry = JournalEntry::where('slug', $slug)->first();

        return $journalEntry;
    }

    // Discussions
    public function getAllDiscussionPosts($category) {
        Log::info("Entering PostTrait getAllDiscussionPosts...");
        Log::info($category);

        $discussions = [];

        if ($category) {
            $discussions = DiscussionPost::latest()
                                         ->with('user:id,first_name,last_name,username')
                                         ->where($category, true)
                                         ->get();
        } else {
            $discussions = DiscussionPost::latest()
                                         ->with('user:id,first_name,last_name,username')
                                         ->get();
        }

        return $discussions;
    }

    public function getChunkedDiscussionPosts($category, $offset, $limit) {
        Log::info("Entering PostTrait getChunkedDiscussionPosts...");

        $discussions = [];

        if ($category) {
            $discussions = DiscussionPost::latest()
                                         ->with('user:id,first_name,last_name,username')
                                         ->where($category, true)
                                         ->offset(intval($offset, 10))
                                         ->limit(intval($limit, 10))
                                         ->get();
        } else {
            $discussions = DiscussionPost::latest()
                                         ->with('user:id,first_name,last_name,username')
                                         ->offset(intval($offset, 10))
                                         ->limit(intval($limit, 10))
                                         ->get();
        }

        return $discussions;
    }

    public function getDiscussionPostRecord($slug) {
        Log::info("Entering PostTrait getDiscussionPostRecord...");
        
        $discussion = DiscussionPost::with('user:id,first_name,last_name,username')
                                    ->where('slug', $slug)
                                    ->first();

        $category = null;

        $categoryArr = ['hobby', 'wellbeing', 'career', 'coaching', 'science_and_tech', 'social_cause'];

        foreach ($categoryArr as $type) {
            if ($discussion->{'is_' . $type} == true) {
                $category = ($type === 'science_and_tech') ? "science & tech" : $type;
                break;
            }
        }

        $discussion = [
            'id' => $discussion->id,
            'user' => [
                'id' => $discussion->user->id,
                'first_name' => $discussion->user->first_name,
                'last_name' => $discussion->user->last_name,
                'username' => $discussion->user->username,
            ],
            'title' => $discussion->title,
            'body' => $discussion->body,
            'slug' => $discussion->slug,
            'created_at' => $discussion->created_at,
            'category' => Str::headline($category),
        ];

        return $discussion;
    }

    public function getDiscussionPostReplyRecord($slug) {
        Log::info("Entering PostTrait getDiscussionPostReplyRecord...");

        $reply = DiscussionPostReply::with('user:id,first_name,last_name,username')
                                    ->where('slug', $slug)
                                    ->first();

        return $reply;
    }

    public function getAllDiscussionPostReplies($postId) {
        Log::info("Entering PostTrait getAllDiscussionPostReplies...");

        $replies = DiscussionPostReply::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('discussion_post_id', $postId)
                                      ->get();

        if ($replies && (count($replies) > 0)) {
            foreach ($replies as $reply) {
                unset($reply->discussion_post_id);
                unset($reply->user_id);
                unset($reply->updated_at);
                unset($reply->deleted_at);

                if ($reply->user && $reply->user->id) {
                    unset($reply->user->id);
                }
            }
        }

        return $replies;
    }

    public function getChunkedDiscussionPostReplies($postId, $limit) {
        Log::info("Entering PostTrait getChunkedDiscussionPostReplies...");

        $replies = DiscussionPostReply::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('discussion_post_id', $postId)
                                      ->limit(intval($limit, 10))
                                      ->get();

        if ($replies && (count($replies) > 0)) {
            foreach ($replies as $reply) {
                unset($reply->id);
                unset($reply->discussion_post_id);
                unset($reply->user_id);
                unset($reply->updated_at);
                unset($reply->deleted_at);

                if ($reply->user && $reply->user->id) {
                    unset($reply->user->id);
                }
            }
        }

        return $replies;
    }

    public function getAllDiscussionPostSupporters($postId) {
        Log::info("Entering PostTrait getAllDiscussionPostSupporters...");

        $users = [];

        $supporters = DiscussionPostSupporter::with('user:id,first_name,last_name,username')
                                             ->where('discussion_post_id', $postId)
                                             ->get();

        if ($supporters && (count($supporters) > 0)) {
            foreach($supporters as $supporter) {
                if ($supporter->user && $supporter->user->id) {
                    unset($supporter->user->id);
                    $users[] = $supporter->user;
                }
            }
        }

        return $users;
    }

    public function getDiscussionPostSupporter($postId, $userId) {
        Log::info("Entering PostTrait getDiscussionPostSupporter...");

        $supporter = DiscussionPostSupporter::where('discussion_post_id', $postId)
                                            ->where('user_id', $userId)
                                            ->first();

        return $supporter;
    }

    public function isDiscussionPostSupporter($postId, $userId) {
        Log::info("Entering PostTrait getDiscussionPostSupporter...");

        $isSupporter = false;

        $supporter = DiscussionPostSupporter::where('discussion_post_id', $postId)
                                             ->where('user_id', $userId)
                                             ->first();

        if ($supporter) {
            $isSupporter = true;
        }

        return $isSupporter;
    }

    public function isDiscussionPostReplyHeart($postId, $userId) {
        Log::info("Entering PostTrait isDiscussionPostReplyHeart...");

        $isHeart = false;

        $heart = DiscussionPostReplyHeart::where('discussion_post_reply_id', $postId)
                                         ->where('user_id', $userId)
                                         ->first();

        if ($heart) {
            $isHeart = true;
        }

        return $isHeart;
    }

    public function getDiscussionPostReplyHearts($postId, $userId) {
        Log::info("Entering PostTrait getDiscussionPostReplyHearts...");

        $heartDetails = [
            'count' => 0,
            'hearts' => null,
            'is_heart' => false,
        ];

        $hearts = DiscussionPostReplyHeart::with('user:id,first_name,last_name,username')
                                          ->where('discussion_post_reply_id', $postId)
                                          ->get();

        if (count($hearts) > 0) {
            foreach ($hearts as $heart) {
                if ($heart->user && ($heart->user->first_name && $heart->user->last_name && $heart->user->username)) {
                    $heartDetails['hearts'][] = [
                        'first_name' => $heart->user->first_name,
                        'last_name' => $heart->user->last_name,
                        'username' => $heart->user->username,
                    ];
                }
                if ($heart->user->id === $userId) {
                    $heartDetails['is_heart'] = true;

                    break;
                }
            }

            $heartDetails['count'] = count($hearts);
        }

        return $heartDetails;
    }

    public function getAllTrendingDiscussionPosts() {
        Log::info("Entering PostTrait getAllTrendingPosts...");

        $posts = DiscussionPost::withCount(['discussionPostSupporters', 'discussionPostReplies'])
                               ->orderBy('discussion_post_supporters_count', 'desc')
                               ->orderBy('discussion_post_replies_count', 'desc')
                               ->limit(5)
                               ->get();

        $discussions = [];
        $categoryArr = ['hobby', 'wellbeing', 'career', 'coaching', 'science_and_tech', 'social_cause'];

        if ($posts && (count($posts) > 0)) {
            foreach ($posts as $post) {
                if (($post->discussion_post_replies_count === 0) && ($post->discussion_post_supporters_count === 0)) {
                    break;
                }

                $category = null;
                foreach ($categoryArr as $type) {
                    if ($post->{'is_' . $type} == true) {
                        $category = ($type === 'science_and_tech') ? "science & tech" : $type;
                        break;
                    }
                }

                $discussions[] = [
                    'title' => $post->title,
                    'body' => $post->body,
                    'slug' => $post->slug,
                    'created_at' => $post->created_at,
                    'replies' => $post->discussion_post_replies_count,
                    'supporters' => $post->discussion_post_supporters_count,
                    'category' => $category,
                ];
            }
        }

        return $discussions;
    }
}