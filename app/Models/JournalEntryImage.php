<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryImage extends Model
{
    use HasFactory, SoftDeletes;

    public function journalEntry() {
        return $this->belongsTo(JournalEntry::class);
    }
}
