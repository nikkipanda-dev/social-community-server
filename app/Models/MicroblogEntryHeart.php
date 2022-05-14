<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MicroblogEntryHeart extends Model
{
    use HasFactory, SoftDeletes;

    public function microblogEntry() {
        return $this->belongsTo(MicroblogEntry::class);
    }
}
