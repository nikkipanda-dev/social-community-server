<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Mail\InvitationMail;
use Illuminate\Support\Facades\Mail;
use App\Models\InvitationToken;
use App\Models\User;
use App\Models\FirebaseCredential;
use App\Models\UserDisplayPhoto;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use App\Traits\FileTrait;
use Exception;

class AccountController extends Controller
{
    use ResponseTrait, AuthTrait, FileTrait;

    public function searchUser(Request $request) {
        Log::info("Entering AccountController searchUser...");
        
        $this->validate($request, [
            'username' => 'bail|required|alpha_num',
        ]);

        try {
            $users = User::where('username', 'like', "%".$request->username."%")
                         ->select('username', 'first_name', 'last_name')
                         ->get();

            Log::info($users);

            if (count($users) > 0) {
                Log::info("Successfully retrieved matched usernames. Leaving AccountController searchUser...");

                return $this->successResponse("details", $users);
            } else {
                Log::error("None matched.\n");

                return $this->errorResponse("None matched.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to search user. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function invite(Request $request) {
        Log::info("Entering AccountController invite...");

        $this->validate($request, [
            'emails.*' => 'bail|required|array:list',
            'emails.list.*' => 'bail|distinct|array|min:1',
        ]);

        $existingEmails = [];
        $errorText = null;
        $isSuccess = false;

        try {
            $tokenResponse = DB::transaction(function () use ($request, $isSuccess, $errorText, $existingEmails) {
                foreach ($request->emails_list as $email) {
                    // Check if user already has a record

                    $invitationTokens = InvitationToken::where('email', $email)->get();

                    if (count($invitationTokens) > 0) {
                        foreach ($invitationTokens as $invitationToken) {
                            $invitationToken->delete();
                        }
                    }

                    $user = User::withTrashed()->where('email', $email)->first();

                    if (!($user)) {
                        $randBytes = random_bytes(30);

                        $token = new InvitationToken();

                        $token->email = $email;
                        $token->token = bin2hex($randBytes);
                        $token->is_valid = true;

                        $token->save();

                        if ($token) {
                            $isSuccess = true;
                            Mail::to($email)->send(new InvitationMail($token->token));
                        } else {
                            $isSuccess = false;
                            $errorText = "Failed to send invitation link. Please try again in a few seconds or contact the developers directly for assistance.";

                            throw new Exception("Failed to persist token to database for email " . $email . ".");
                        }
                    } else {
                        $existingEmails[] = $email;
                    }
                }

                return [
                    'isSuccess' => $isSuccess,
                    'errorText' => $errorText,
                    'existingEmails' => $existingEmails,
                ];
            }, 3);

            if ($tokenResponse['isSuccess']) {
                Log::info("Successfully sent member invitations. Leaving AccountController invite...");
                
                return $this->successResponse("details", "Successully sent member invitation".((count($request->emails_list) > 1) ? "s" : '').".");
            } else {
                Log::error($tokenResponse['errorText']);

                return $this->errorResponse([
                    'message' => '',
                    'existing_emails' => $tokenResponse['isSuccess']['existingEmails'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send member invitations. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function store(Request $request, $token) {
        Log::info("Entering AccountController store...");

        $this->validate($request, [
            'email' => 'bail|required|email',
            'first_name' => 'bail|required|min:2',
            'last_name' => 'bail|required|min:2',
            'username' => 'bail|required|min:3',
            'password' => 'bail|required|between:8,20|confirmed',
            'password_confirmation' => 'required',
        ]);

        try {
            $isSuccess = false;
            $errorText = null;

            $user = new User();

            if ($this->isTokenValid($token)) {
                $userResponse = DB::transaction(function () use ($user, $request, $token, $isSuccess, $errorText) {
                    $user->first_name = $request->first_name;
                    $user->last_name = $request->last_name;
                    $user->username = $request->username;
                    $user->email = $request->email;
                    $user->password = Hash::make($request->password);
                    $user->is_super_admin = false;
                    $user->is_admin = false;

                    $user->save();

                    if ($user) {
                        $generatedToken = $user->createToken('auth_user')->plainTextToken;

                        if ($generatedToken) {
                            // delete invitation token
                            $invitationToken = InvitationToken::where('token', base64_decode($token))->first();

                            if ($invitationToken) {
                                $originalToken = $invitationToken->getOriginal('token');

                                $invitationToken->delete();

                                if (!(InvitationToken::where('token', $originalToken)->first())) {
                                    $isSuccess = true;
                                } else {
                                    $errorText = "Failed to delete invitation token from database.";

                                    throw new Exception("Failed to delete invitation token from database. \n");

                                }
                            } else {
                                $errorText = "Invitation is no longer valid or might be deleted.";

                                throw new Exception("Invitation is no longer valid or might be deleted. \n");
                            }
                        } else {
                            $errorText = "Failed to generate personal access token for account registration with email ." . $user->email . ".";

                            throw new Exception("Failed to generate personal access token for account registration with email ." . $user->email . ". \n");
                        }
                    } else {
                        $errorText = "Failed to store new user account on database of email ." . $request->email.".";

                        throw new Exception("Failed to store new user account on database of email ." . $request->email . ". \n");
                    }

                    return [
                        'isSuccess' => $isSuccess,
                        'errorText' => $errorText,
                        'user' => $user,
                        'token' => $generatedToken,
                    ];
                }, 3);

                if ($userResponse['isSuccess']) {
                    Log::info("Successfully registered user ID ".$userResponse['user']['id'].". Leaving AccountController store...");

                    $isUnique = false;
                    $secret = null;

                    $credential = new FirebaseCredential();

                    $credential->user_id = $user->id;

                    do {
                        $secret = $this->generateSecretKey();

                        if (!(FirebaseCredential::where('secret', $secret)->first())) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    $credential->secret = $secret;

                    $credential->save();

                    return $this->successResponse('details', [
                        'token' => $userResponse['token'],
                        'user' => $userResponse['user'],
                        'firebase' => [
                            'secret' => $credential->secret,
                        ]
                    ]);
                } else {
                    Log::error($userResponse['errorText']);

                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                }
            } else {
                Log::error("Invitation link is invalid or might be deleted.");

                return $this->errorResponse("Invitation link is no longer valid.");
            }

        } catch (\Exception $e) {
            Log::error("Failed to register. ".$e->getMessage().".\n");

            return $this->errorResponse("Something went wrong. Please try again in a few minutes or contact us directly for assistance.");
        }
    }

    public function validateToken($token) {
        if ($this->isTokenValid($token)) {
            return $this->successResponse(null, null);
        } else {
            Log::error("Invalid invitation token.\n");

            return $this->errorResponse("Invalid invitation link.");
        }
    }

    public function isTokenValid($token) {
        $isValid = false;

        $invitationToken = InvitationToken::where('token', base64_decode($token))->first();

        if ($invitationToken) {
            if ($invitationToken->is_valid) {
                $isValid = true;
            }
        }
        
        return $isValid;
    }

    public function getUsers() {
        Log::info("Entering AccountController getUsers...");

        try {
            $users = User::get();

            if (count($users) > 0) {
                Log::info("Successfully retrieved the users. Leaving AccountController getUsers...");

                return $this->successResponse('details', $users);
            } else {
                Log::error("No users yet. No action needed.\n");

                return $this->errorResponse("No users yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve users. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getUser(Request $request) {
        Log::info("Entering AccountController getUser...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
        ]);

        try {
            $user = User::where('username', $request->username)->first();

            if ($user) {
                Log::info("Successfully retrieved user ID ".$user->id.". Leaving AccountController getUser...");

                return $this->successResponse('details', $user->only(['first_name', 'last_name', 'username']));
            } else {
                Log::error("Failed to retrieve user details. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('not found', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user details. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function getAdministrators() {
        Log::info("Entering AccountController getAdministrators...");

        try {
            $administrators = User::where('is_admin', true)->get();

            if (count($administrators) > 0) {
                Log::info("Successfully retrieved the administrators. Leaving AccountController getAdministrators...");

                return $this->successResponse('details', $administrators);
            } else {
                Log::error("No administrators yet. No action needed.\n");

                return $this->errorResponse("No administrators yet.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve administrators. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyAdministrator(Request $request) {
        Log::info("Entering AccountController destroyAdministrator...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->auth_username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $admin = User::where('username', $request->username)->first();

                            if ($admin) {
                                $admin->is_admin = false;

                                $admin->save();

                                if ($admin->wasChanged('is_admin')) {
                                    Log::info("Successfully removed user ID ".$admin->username."'s admin privileges.\n");

                                    return $this->successResponse('details', $admin->only(['first_name', 'last_name', 'username']));
                                } else {
                                    Log::error("User ID " . $admin->username . "'s admin privileges was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', $admin->username."'s admininstrator status"));
                                }
                            } else {
                                Log::error("Failed to remove admin privileges. Admin does not exist or might be deleted.\n");

                                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to remove admin privileges. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to remove admin privileges. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to remove admin privileges. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateUserFullName(Request $request) {
        Log::info("Entering AccountController updateUserFullName...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'first_name' => 'bail|required|min:2|max:100',
            'last_name' => 'bail|required|min:2|max:100',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update user's first and last name. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update user's first and last name. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $user->first_name = $request->first_name;
                    $user->last_name = $request->last_name;

                    $user->save();

                    Log::info("Successfully updated first and last name of user ID " . $user->id . ". Leaving AccountController updateUserFullName...\n");

                    return $this->successResponse("details", $user->only(['first_name', 'last_name']));

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update user's first and last name. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateUserEmail(Request $request) {
        Log::info("Entering AccountController updateUserEmail...");

        $user = User::where('username', $request->username)->first();

        if (!($user)) {
            Log::error("Failed to update user's email address. User does not exist or might be deleted.\n");

            return $this->errorResponse($this->getPredefinedResponse('user not found', null));
        }

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'email' => [
                'bail',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ]
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update user email. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $user->email = $request->email;

                    $user->save();

                    if (!($user->wasChanged('email'))) {
                        Log::notice("Email address of user ID " . $user->id . " was not changed. No action needed.\n");

                        return $this->errorResponse($this->getPredefinedResponse('not changed', 'email address'));
                    }

                    Log::info("Successfully updated email address of user ID " . $user->id . ". Leaving AccountController updateUserEmail...\n");

                    return $this->successResponse("details", $user->email);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update user email. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateUserDisplayPhoto(Request $request) {
        Log::info("Entering AccountController updateUserDisplayPhoto...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'image' => 'bail|required|image',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update user's display photo. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update user's display photo. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    if (!($request->hasFile('image'))) {
                        Log::error("Failed to update user's display photo. No image found.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    if (!($request->image->isValid())) {
                        Log::error("Failed to update user's display photo. Image is invalid.\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    $displayPhoto = $this->getDisplayPhoto($user->id);

                    $isUnique = false;
                    $slug = null;

                    do {
                        $slug = $this->generateFilename();
                        if (!(Storage::disk('do_space')->exists("user/" . $slug . "." . $request->image->extension()))) {
                            $isUnique = true;
                        }
                    } while (!($isUnique));

                    Storage::disk('do_space')->putFileAs(
                        "user",
                        $request->image,
                        $slug . "." . $request->image->extension()
                    );

                    if (Storage::disk('do_space')->exists("user/" . $slug . "." . $request->image->extension())) {
                        Storage::disk("do_space")->setVisibility("user/" . $slug . "." . $request->image->extension(), 'public');
                    }

                    if ($displayPhoto) {
                        $originalId = $displayPhoto->getOriginal('id');

                        $displayPhoto->delete();

                        if (UserDisplayPhoto::find($originalId)) {
                            Log::error("Failed to soft delete display photo ID " . $originalId . ".");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        if (!(UserDisplayPhoto::find($originalId))) {
                            Log::notice("Soft deleted display photo ID ".$originalId.".");
                        }
                    }

                    $displayPhoto = new UserDisplayPhoto();

                    $displayPhoto->user_id = $user->id;
                    $displayPhoto->disk = "do_space";
                    $displayPhoto->path = $slug . "." . $request->image->extension();
                    $displayPhoto->extension = $request->image->extension();

                    $displayPhoto->save();

                    if (!($displayPhoto->id)) {
                        Log::error("Failed to update display photo of user ID " . $user->id . ".\n");

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }

                    Log::info("Successfully updated display photo of user ID " . $user->id . ". Leaving AccountController updateUserDisplayPhoto...\n");

                    return $this->successResponse("details", Storage::disk($displayPhoto->disk)->url("user/" . $displayPhoto->path));

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update user's display photo. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateUserPassword(Request $request) {
        Log::info("Entering AccountController updateUserPassword...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'password' => 'bail|required|between:8,20|confirmed',
            'password_confirmation' => 'required',
        ]);

        try {
            if (!($this->hasAuthHeader($request->header('authorization')))) {
                Log::error("Failed to update user's password. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }

            $user = User::where('username', $request->username)->first();

            if (!($user)) {
                Log::error("Failed to update user's password. User does not exist or might be deleted.\n");

                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
            }

            foreach ($user->tokens as $token) {
                if ($token->tokenable_id === $user->id) {
                    $user->password = Hash::make($request->password);

                    $user->save();

                    if (!($user->wasChanged('password'))) {
                        Log::notice("Password of user ID " . $user->id . " was not changed. No action needed.\n");

                        return $this->errorResponse($this->getPredefinedResponse('not changed', 'password'));
                    }

                    Log::info("Successfully updated password of user ID " . $user->id . ". Leaving AccountController updateUserPassword...\n");

                    return $this->successResponse("details", $user);

                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update user's password. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyUser(Request $request) {
        Log::info("Entering AccountController destroyUser...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'username' => 'bail|required|exists:users',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->auth_username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $targetUser = User::where('username', $request->username)->first();

                            if ($targetUser) {
                                $originalUsername = $targetUser->getOriginal('username');

                                $targetUser->delete();

                                if (!(User::where('username', $originalUsername)->first())) {
                                    Log::info("Successfully soft deleted user " . $originalUsername . ".\n");

                                    return $this->successResponse('details', $originalUsername);
                                } else {
                                    Log::error("Failed to soft delete user ID " . $targetUser->id . ".\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            } else {
                                Log::error("Failed to soft delete user. User to be removed does not exist or might be deleted.\n");

                                return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to soft delete user. Authenticated user does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to soft delete user. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete user. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
