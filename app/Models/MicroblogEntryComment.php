<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MicroblogEntryComment extends Model
{
    use HasFactory, SoftDeletes;

    public function microblogEntry() {
        return $this->belongsTo(MicroblogEntry::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function microblogEntryCommentHearts() {
        return $this->hasMany(MicroblogEntryCommentHeart::class);
    }
}
