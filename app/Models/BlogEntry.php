<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogEntry extends Model
{
    use HasFactory, SoftDeletes;

    public function blogEntryComments() {
        return $this->hasMany(BlogEntryComment::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function blogEntrySupporters() {
        return $this->hasMany(BlogEntrySupporter::class);
    }

    public function blogEntryImages() {
        return $this->hasMany(BlogEntryImage::class);
    }
}
