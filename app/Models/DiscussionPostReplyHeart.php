<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPostReplyHeart extends Model
{
    use HasFactory, SoftDeletes;

    public function discussionPostReply() {
        return $this->belongsTo(DiscussionPostReply::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
