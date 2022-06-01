<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FirebaseCredential;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ResponseTrait;

    public function authenticate(Request $request) {
        Log::info("Entering AuthController authenticate...");

        Log::info($request->all());

        $this->validate($request, [
            'email' => 'bail|required|email',
            'password' => 'bail|required',
            'remember' => 'nullable|in:true,false',
        ]);

        try {
            Log::info($request->all());
            $user = User::where('email', $request->email)->first();

            if ($user) {
                if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->remember)) {
                    $token = $user->createToken('auth_user')->plainTextToken;

                    if ($token) {
                        Log::info("Successfully authenticated user ID " . $user->id . ". Leaving AuthController authenticate...");

                        $credential = FirebaseCredential::where('user_id', $user->id)->first();

                        if (!($credential)) {
                            Log::error("Failed to retrieve firebase secret.\n");

                            return $this->errorResponse($this->getPredefinedResponse('default', null));
                        }

                        return $this->successResponse("details", [
                            'user' => $user,
                            'token' => $token,
                            'firebase' => [
                                'api_key' => env("FIREBASE_API_KEY"),
                                'auth_domain' => env("FIREBASE_AUTH_DOMAIN"),
                                'database_url' => env("FIREBASE_DATABASE_URL"),
                                'project_id' => env("FIREBASE_PROJECT_ID"),
                                'storage_bucket' => env("FIREBASE_STORAGE_BUCKET"),
                                'messaging_sender_id' => env("FIREBASE_MESSAGING_SENDER_ID"),
                                'app_id' => env("FIREBASE_APP_ID"),
                                'secret' => $credential->secret,
                            ]
                        ]);
                    } else {
                        Log::critical("Failed to authenticate. No token issued.\n");

                        return $this->errorResponse("Please try again in a few seconds or contact us directly for assistance.");
                    }
                } else {
                    Log::error("Failed to authenticate. Incorrect email and/or password.\n");

                    return $this->errorResponse("Incorrect email and/or password. Please try again.");
                }
            } else {
                Log::error("Failed to authenticate. User does not exist or might be deleted.\n");

                return $this->errorResponse("Your email address does not exist in our system.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to authenticate. ".$e->getMessage().".\n");

            return $this->errorResponse("Please try again in a few seconds or contact us directly for assistance.");
        }
    }

    public function logout (Request $request) {
        Log::info("Entering AuthController logout...");

        $this->validate($request, [
            'email' => 'bail|required|exists:users',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                Log::info($user);
                $user->tokens()->delete();

                return $this->successResponse(null, null);
            } else {
                Log::error("Failed to log out. User does not exist or might be deleted.\n");

                return $this->errorResponse("User does not exist.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to log out. ".$e->getMessage().".\n");

            return $this->errorResponse("Please try again in a few seconds or contact us directly for assistance.");
        }
    }
}
