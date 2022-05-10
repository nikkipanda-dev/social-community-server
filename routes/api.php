<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CommunityController;

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

Route::post('login', [AuthController::class, 'authenticate']);

// Account
Route::post('register/{token}', [AccountController::class, 'store']);
Route::get('validate-invitation/{token}', [AccountController::class, 'validateToken']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    // Account
    Route::get('search-user', [AccountController::class, 'searchUser']);
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
});