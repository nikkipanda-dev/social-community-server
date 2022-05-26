<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPostSupporter extends Model
{
    use HasFactory, SoftDeletes;

    public function discussionPost() {
        return $this->belongsTo(DiscussionPost::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
