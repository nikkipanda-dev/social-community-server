<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BlogEntryController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DiscussionPostController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MicroblogEntryController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('test', [AccountController::class, 'test']);

Route::post('login', [AuthController::class, 'authenticate']);

// Account
Route::post('register/{token}', [AccountController::class, 'store']);
Route::get('validate-invitation/{token}', [AccountController::class, 'validateToken']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    // Account
    Route::get('search-user', [AccountController::class, 'searchUser']);
    Route::get('user', [AccountController::class, 'getUser']);
    Route::post('invite', [AccountController::class, 'invite']);
    Route::get('users', [AccountController::class, 'getUsers']);
    Route::get('administrators', [AccountController::class, 'getAdministrators']);
    Route::post('destroy-administrator', [AccountController::class, 'destroyAdministrator']);
    Route::post('destroy-user', [AccountController::class, 'destroyUser']);

    // Community
    Route::get('community/details', [CommunityController::class, 'getDetails']);
    Route::get('community/team', [CommunityController::class, 'getTeamMembers']);
    Route::post('community/store-image', [CommunityController::class, 'storeImage']);
    Route::post('community/store-team', [CommunityController::class, 'storeTeam']);
    Route::post('community/update-name', [CommunityController::class, 'updateName']);
    Route::post('community/update-description', [CommunityController::class, 'updateDescription']);
    Route::post('community/update-team', [CommunityController::class, 'updateTeamMembers']);
    Route::post('community/remove-team-member', [CommunityController::class, 'destroyTeamMember']);
    Route::post('community/update-website', [CommunityController::class, 'updateWebsite']);
    Route::post('community/update-facebook-account', [CommunityController::class, 'updateFacebookAccount']);
    Route::post('community/update-instagram-account', [CommunityController::class, 'updateInstagramAccount']);
    Route::post('community/update-twitter-account', [CommunityController::class, 'updateTwitterAccount']);

    // Blog entries
    // Route::get('blog-entries', [BlogEntryController::class, 'getBlogEntries']);
    Route::get('blog-entries/get', [BlogEntryController::class, 'getBlogEntries']);
    Route::get('blog-entries/get-entry', [BlogEntryController::class, 'getBlogEntry']);
    Route::get('blog-entries/paginate', [BlogEntryController::class, 'getPaginatedBlogEntries']);
    Route::post('blog-entries/store', [BlogEntryController::class, 'storeBlogEntry']);
    Route::post('blog-entries/update', [BlogEntryController::class, 'updateBlogEntry']);
    Route::post('blog-entries/destroy', [BlogEntryController::class, 'destroyBlogEntry']);
    Route::get('blog-entries/support/get', [BlogEntryController::class, 'getBlogEntrySupporters']);
    Route::post('blog-entries/support/store', [BlogEntryController::class, 'storeBlogEntrySupporter']);
    Route::post('blog-entries/support/destroy', [BlogEntryController::class, 'destroyBlogEntrySupporter']);
    Route::get('blog-entries/comments/get', [BlogEntryController::class, 'getBlogEntryComments']);
    Route::get('blog-entries/comments/paginate', [BlogEntryController::class, 'getPaginatedBlogEntryComments']);
    Route::post('blog-entries/comments/store', [BlogEntryController::class, 'storeBlogEntryComment']);
    Route::post('blog-entries/comments/update', [BlogEntryController::class, 'updateBlogEntryComment']);
    Route::post('blog-entries/comments/destroy', [BlogEntryController::class, 'destroyBlogEntryComment']);
    Route::get('blog-entries/wordsmiths/get', [BlogEntryController::class, 'getBlogEntryWordsmiths']);
    Route::post('blog-entries/comments/hearts/update', [BlogEntryController::class, 'updateBlogEntryCommentHeart']);

    // Microblog entries
    Route::get('microblog-entries', [MicroblogEntryController::class, 'getMicroblogEntries']);
    Route::get('microblog-entries/user', [MicroblogEntryController::class, 'getUserMicroblogEntries']);
    Route::get('microblog-entries/user/paginate', [MicroblogEntryController::class, 'getPaginatedUserMicroblogEntries']);
    Route::post('microblog-entries/user/store', [MicroblogEntryController::class, 'storeMicroblogEntry']);
    Route::post('microblog-entries/user/entry/update', [MicroblogEntryController::class, 'updateMicroblogEntry']);
    Route::post('microblog-entries/user/entry/destroy', [MicroblogEntryController::class, 'destroyMicroblogEntry']);
    Route::get('microblog-entries/user/entry/most-loved', [MicroblogEntryController::class, 'getMostLovedEntry']);
    Route::get('microblog-entries/user/entry/most-active', [MicroblogEntryController::class, 'getMostActiveEntry']);
    Route::post('microblog-entries/user/entry/hearts', [MicroblogEntryController::class, 'getMicroblogEntryHearts']);
    Route::post('microblog-entries/user/entry/hearts/update', [MicroblogEntryController::class, 'updateMicroblogEntryHeart']);
    Route::get('microblog-entries/user/entry/comments', [MicroblogEntryController::class, 'getUserMicroblogEntryComments']);
    Route::get('microblog-entries/user/entry/comments/paginate', [MicroblogEntryController::class, 'getPaginatedUserMicroblogEntryComments']);
    Route::get('microblog-entries/user/entry/comment/hearts', [MicroblogEntryController::class, 'getUserMicroblogEntryCommentHearts']);
    Route::post('microblog-entries/user/entry/comment/store', [MicroblogEntryController::class, 'storeMicroblogEntryComment']);
    Route::post('microblog-entries/user/entry/comment/hearts/update', [MicroblogEntryController::class, 'updateMicroblogEntryCommentHeart']);

    // Journal entries
    Route::get('journal-entries', [JournalEntryController::class, 'getJournalEntries']);
    Route::get('journal-entries/user/get-entry', [JournalEntryController::class, 'getJournalEntry']);
    Route::get('journal-entries/user/all', [JournalEntryController::class, 'getUserJournalEntries']);
    Route::get('journal-entries/user/paginate', [JournalEntryController::class, 'getPaginatedUserJournalEntries']);
    Route::post('journal-entries/user/store', [JournalEntryController::class, 'storeJournalEntry']);
    Route::post('journal-entries/user/update', [JournalEntryController::class, 'updateJournalEntry']);
    Route::post('journal-entries/user/destroy', [JournalEntryController::class, 'destroyJournalEntry']);

    // Discussion Posts
    Route::get('discussion-posts', [DiscussionPostController::class, 'getDiscussionPosts']);
    Route::get('discussion-posts/paginate', [DiscussionPostController::class, 'getPaginatedDiscussionPosts']);
    Route::get('discussion-posts/get', [DiscussionPostController::class, 'getDiscussionPost']);
    Route::post('discussion-posts/store', [DiscussionPostController::class, 'storeDiscussionPost']);
    Route::post('discussion-posts/update', [DiscussionPostController::class, 'updateDiscussionPost']);
    Route::post('discussion-posts/destroy', [DiscussionPostController::class, 'destroyDiscussionPost']);
    Route::get('discussion-posts/trending/get', [DiscussionPostController::class, 'getTrendingDiscussionPosts']);
    Route::get('discussion-posts/replies/get', [DiscussionPostController::class, 'getDiscussionPostReplies']);
    Route::get('discussion-posts/replies/paginate', [DiscussionPostController::class, 'getPaginatedDiscussionPostReplies']);
    Route::post('discussion-posts/replies/store', [DiscussionPostController::class, 'storeDiscussionPostReplies']);
    Route::post('discussion-posts/replies/update', [DiscussionPostController::class, 'updateDiscussionPostReplies']);
    Route::post('discussion-posts/replies/destroy', [DiscussionPostController::class, 'destroyDiscussionReplies']);
    Route::get('discussion-posts/support/get', [DiscussionPostController::class, 'getDiscussionPostSupporters']);
    Route::post('discussion-posts/support/store', [DiscussionPostController::class, 'storeDiscussionPostSupporter']);
    Route::post('discussion-posts/support/destroy', [DiscussionPostController::class, 'destroyDiscussionPostSupporter']);
    Route::post('discussion-posts/post/hearts/update', [DiscussionPostController::class, 'updateDiscussionPostHearts']);
    Route::post('discussion-posts/test', [DiscussionPostController::class, 'testApi']);

    // Events
    Route::get('events', [EventController::class, 'getEvents']);
    Route::get('events/paginate', [EventController::class, 'getPaginatedEvents']);
    Route::get('events/get', [EventController::class, 'getEvent']);
    Route::post('events/store', [EventController::class, 'storeEvent']);
    Route::post('events/update', [EventController::class, 'updateEvent']);
    Route::post('events/destroy', [EventController::class, 'destroyEvent']);
    Route::get('events/replies/get', [EventController::class, 'getEventReplies']);
    Route::get('events/replies/paginate', [EventController::class, 'getPaginatedEventReplies']);
    Route::post('events/replies/store', [EventController::class, 'storeEventReply']);
    Route::post('events/replies/update', [EventController::class, 'updateEventReply']);
    Route::post('events/replies/destroy', [EventController::class, 'destroyEventReply']);
    Route::post('events/replies/hearts/update', [EventController::class, 'updateEventHeart']);
    Route::get('events/participants/get', [EventController::class, 'getEventParticipants']);
    Route::post('events/participants/store', [EventController::class, 'storeEventParticipant']);
    Route::post('events/participants/destroy', [EventController::class, 'destroyEventParticipant']);

    // Friends
    Route::get('friends/user/get-friend', [FriendController::class, 'getFriend']);
    Route::get('friends/user/all', [FriendController::class, 'getAllFriends']);
    Route::get('friends/user/paginate', [FriendController::class, 'getPaginatedFriends']);
    Route::get('friends/user/invitations', [FriendController::class, 'getFriendInvitations']);
    Route::get('friends/user/invitations/paginate', [FriendController::class, 'getPaginatedFriendInvitations']);
    Route::post('friends/user/store', [FriendController::class, 'storeFriend']);
    Route::post('friends/user/destroy', [FriendController::class, 'destroyFriend']);
    Route::post('friends/user/accept', [FriendController::class, 'storeAcceptFriend']);

    // Report
    Route::get('reports', [ReportController::class, 'getReports']);
    Route::post('report/store', [CommunityController::class, 'updateTwitterAccount']);
});