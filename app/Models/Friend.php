<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Friend extends Model
{
    use HasFactory, SoftDeletes;

    public function users() {
        return $this->hasMany(User::class, 'id', 'user_id');
    }

    public function friends() {
        return $this->hasMany(User::class, 'id', 'friend_id');
    }
}
