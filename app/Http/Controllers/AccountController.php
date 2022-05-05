<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Mail\InvitationMail;
use Illuminate\Support\Facades\Mail;
use App\Models\InvitationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Traits\ResponseTrait;
use Exception;

class AccountController extends Controller
{
    use ResponseTrait;

    public function invite(Request $request) {
        Log::info("Entering AccountController invite...");

        $this->validate($request, [
            'emails.*' => 'bail|required|array:list',
            'emails.list.*' => 'bail|distinct|array|min:1',
        ]);

        $existingEmails = [];
        $errorText = null;
        $isSuccess = false;

        foreach ($request->emails_list as $email) {
            // Check if user already has a record

            $user = User::withTrashed()->where('email', $email)->first();

            if (!($user)) {
                Log::info("New user");
                $tokenResponse = DB::transaction(function () use($email, $isSuccess, $errorText) {
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
                    
                    return [
                        'isSuccess' => $isSuccess,
                        'errorText' => $errorText,
                    ];
                }, 3);

                if ($tokenResponse['isSuccess']) {
                    Log::info("Success");
                } else {
                    Log::info("Not successful");
                }
            } else {
                Log::info("Existing");
                $existingEmails[] = $email;
            }
        }
    }

    public function store(Request $request, $token) {
        Log::info("Entering AccountController invite...");

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
                            $invitationToken = InvitationToken::where('token', $token)->first();

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

                    return $this->successResponse('user', [
                        'token' => $userResponse['token'],
                        'details' => $userResponse['user'],
                    ]);
                } else {
                    Log::error($userResponse['errorText']);

                    return $this->errorResponse("Something went wrong. Please contact us directly for assistance.");
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

        $invitationToken = InvitationToken::where('token', $token)->first();

        if ($invitationToken) {
            if ($invitationToken->is_valid) {
                $isValid = true;
            }
        }
        
        return $isValid;
    }
}
