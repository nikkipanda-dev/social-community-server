<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function eventReplies() {
        return $this->hasMany(EventReply::class);
    }

    public function eventParticipants() {
        return $this->hasMany(EventParticipant::class);
    }
}
