<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        $this->validate($request, [
            'email' => 'bail|required|exists:users',
            'password' => 'required',
            'remember' => 'nullable|in:true,false',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if ($user) {
                Log::info("User:");
                Log::info($user);
                if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->remember)) {
                    $token = $user->createToken('auth_token')->plainTextToken;

                    if ($token) {
                        Log::info("Successfully authenticated user ID " . $user->id . ". Leaving AuthController authenticate...");

                        return $this->successResponse("details", [
                            'user' => $user,
                            'token' => $token,
                        ]);
                    } else {
                        Log::critical("Failed to authenticate. No token issued.\n");

                        return $this->errorResponse("Please try again in a few seconds or contact us directly for assistance.");
                    }
                }
            } else {
                Log::error("Failed to authenticate. User does not exist or might be deleted.\n");

                return $this->errorResponse("User does not exist.");
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
