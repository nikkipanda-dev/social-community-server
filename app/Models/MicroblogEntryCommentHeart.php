<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MicroblogEntryCommentHeart extends Model
{
    use HasFactory, SoftDeletes;

    public function microblogEntryComment() {
        return $this->belongsTo(MicroblogEntryComment::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
