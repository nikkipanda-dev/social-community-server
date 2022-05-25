<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPost extends Model
{
    use HasFactory, SoftDeletes;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function discussionPostSupporters() {
        return $this->hasMany(DiscussionPostSupporter::class);
    }

    public function discussionPostReplies() {
        return $this->hasMany(DiscussionPostReply::class);
    }
}
