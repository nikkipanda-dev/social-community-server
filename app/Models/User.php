<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // 'name',
        // 'email',
        // 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function teamMembers() {
        return $this->hasMany(TeamMember::class);
    }

    public function blogEntries() {
        return $this->hasMany(BlogEntry::class);
    }

    public function blogEntrySupporters() {
        return $this->hasMany(BlogEntrySupporter::class);
    }

    public function microblogEntries() {
        return $this->hasMany(MicroblogEntry::class);
    }
    
    public function journalEntries() {
        return $this->hasMany(JournalEntry::class);
    }

    public function discussionPosts() {
        return $this->hasMany(DiscussionPost::class);
    }

    public function events() {
        return $this->hasMany(Event::class);
    }

    public function reports() {
        return $this->hasMany(Report::class);
    }

    public function microblogEntryComments() {
        return $this->hasMany(MicroblogEntryComment::class);
    }

    public function microblogEntryHearts() {
        return $this->hasMany(MicroblogEntryHeart::class);
    }

    public function microblogEntryCommentHearts() {
        return $this->hasMany(MicroblogEntryCommentHeart::class);
    }

    public function friends() {
        return $this->hasMany(Friend::class);
    }

    public function discussionPostReplies() {
        return $this->hasMany(DiscussionPostReply::class);
    }

    public function discussionPostReplyHearts() {
        return $this->hasMany(DiscussionPostReplyHeart::class);
    }

    public function discussionPostSupporters() {
        return $this->hasMany(DiscussionPostSupporter::class);
    }
}
