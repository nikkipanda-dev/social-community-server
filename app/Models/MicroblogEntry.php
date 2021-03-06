<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MicroblogEntry extends Model
{
    use HasFactory, SoftDeletes;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function microblogEntryHearts() {
        return $this->hasMany(MicroblogEntryHeart::class);
    }

    public function microblogEntryComments() {
        return $this->hasMany(MicroblogEntryComment::class);
    }
}
