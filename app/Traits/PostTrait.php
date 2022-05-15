<?php

namespace App\Traits;

use App\Models\MicroblogEntry;
use App\Models\MicroblogEntryComment;
use App\Models\MicroblogEntryCommentHeart;
use App\Models\MicroblogEntryHeart;
use Illuminate\Support\Facades\Log;

trait PostTrait {
    public function generateSlug() {
        $rand = bin2hex(random_bytes(30));

        return $rand;
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
}