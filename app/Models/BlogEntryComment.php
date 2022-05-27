<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogEntryComment extends Model
{
    use HasFactory, SoftDeletes;

    public function blogEntry() {
        return $this->belongsTo(BlogEntry::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function blogEntryCommentHearts() {
        return $this->hasMany(BlogEntryCommentHeart::class);
    }
}
