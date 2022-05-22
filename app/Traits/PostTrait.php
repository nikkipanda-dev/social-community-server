<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\MicroblogEntry;
use App\Models\User;
use App\Models\DiscussionPost;
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

    public function getAllJournalEntries($userId) {
        $journalEntries = JournalEntry::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('user_id', $userId)
                                      ->get();

        return $journalEntries;
    }

    public function getChunkedJournalEntries($userId, $offset, $limit) {
        $journalEntries = JournalEntry::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->where('user_id', $userId)
                                      ->offset(intval($offset, 10))
                                      ->limit(intval($limit, 10))
                                      ->get();

        return $journalEntries;
    }

    public function getJournalEntryRecord($slug) {
        log::info("Entering PostTrait getJournalEntryRecord...");

        $journalEntry = JournalEntry::where('slug', $slug)->first();

        return $journalEntry;
    }

    public function getAllDiscussionPosts() {
        $discussions = DiscussionPost::latest()
                                     ->with('user:id,first_name,last_name,username')
                                     ->get();

        return $discussions;
    }

    public function getChunkedDiscussionPosts($offset, $limit) {
        $discussions = DiscussionPost::latest()
                                      ->with('user:id,first_name,last_name,username')
                                      ->offset(intval($offset, 10))
                                      ->limit(intval($limit, 10))
                                      ->get();

        return $discussions;
    }
}