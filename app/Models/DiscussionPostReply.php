<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPostReply extends Model
{
    use HasFactory, SoftDeletes;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function discussionPost() {
        return $this->belongsTo(DiscussionPost::class);
    }

    public function discussionPostReplyHearts() {
        return $this->hasMany(DiscussionPostReplyHeart::class);
    }
}
