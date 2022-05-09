<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TeamMember;
use App\Models\CommunityDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Traits\ResponseTrait;
use App\Traits\AuthTrait;
use Exception;

class CommunityController extends Controller
{
    use ResponseTrait, AuthTrait;

    public function getDetails() {
        Log::info("Entering CommunityController getDetails...");

        try {
            $communityDetails = CommunityDetail::latest()->first();

            if ($communityDetails) {
                Log::info("Successfully retrieved community details. Leaving CommunityController getDetails...");

                return $this->successResponse('details', $communityDetails);
            } else {
                Log::error("Failed to retrieve community details. No data yet.\n");

                return $this->errorResponse("No data to show.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve community details. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeName(Request $request) {        
        Log::info("Entering CommunityController storeName...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'name' => 'bail|required|string|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $communityName = new CommunityDetail();

                            $communityName->name = $request->name;

                            $communityName->save();

                            if ($communityName) {
                                Log::info("Successfully stored community name. Leaving CommunityController storeName...\n");

                                return $this->successResponse('details', $communityName);
                            } else {
                                Log::error("Failed to store community name to database.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store community name. User does not exist or might be deleted.\n");
                    
                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store community name. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community name. ".$e->getMessage()."\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeImage(Request $request) {        
        Log::info("Entering CommunityController storeName...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'image' => 'bail|required|image',
        ]);

        $isSuccess = false;
        $errorText = '';

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            // $imageResponse = DB::transaction(function () use($isSuccess, $errorText) {
                                
                            // }, 3);
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store community image. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store community image. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {

        }
    }

    public function storeDescription(Request $request) {
        Log::info("Entering CommunityController storeDescription...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'description' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $communityDescription = new CommunityDetail();

                            $communityDescription->description = $request->description;

                            $communityDescription->save();

                            if ($communityDescription) {
                                Log::info("Successfully stored community description. Leaving CommunityController storeDescription...\n");

                                return $this->successResponse('details', $communityDescription);
                            } else {
                                Log::error("Failed to store community description to database.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to store community description. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store community description. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community description. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function storeTeam(Request $request) {
        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'team_members' => 'bail|required|array|min:1',
        ]);

        $isSuccess = false;
        $errorText = '';

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    $teamResponse = null;

                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            foreach ($request->team_members as $member) {
                                $decoded = json_decode($member, true, 3);

                                $teamResponse = DB::transaction(function () use ($isSuccess, $errorText, $decoded) {
                                    foreach ($decoded as $item) {
                                        $isSuccess = false;

                                        $username = substr($item['username'], 1);
                                        if ($item['username'][0] === "@" && $username) {
                                            $mentionedUser = User::where('username', $username)->first();

                                            if ($mentionedUser) {
                                                $teamMember = new TeamMember();

                                                $teamMember->user_id = $mentionedUser->id;
                                                $teamMember->title = $item['title'];

                                                $teamMember->save();

                                                if ($teamMember) {
                                                    $isSuccess = true;
                                                } else {
                                                    $errorText = "Failed to store team member username " . $mentionedUser->username . ".\n";

                                                    throw new Exception("Failed to store team member username " . $mentionedUser->username . ".\n");
                                                }
                                            } else {
                                                $isSuccess = false;
                                                $errorText = "Failed to store team member. User " . $mentionedUser->username . " does not exist or might be deleted.\n";

                                                throw new Exception("Failed to store team member. User " . $mentionedUser->username . " does not exist or might be deleted.\n");
                                            }
                                        } else {
                                            $errorText = "Failed to store team member. Username does not match regular expression.\n";

                                            throw new Exception("Failed to store team member. Username does not match regular expression.\n");
                                        }
                                    }

                                    return [
                                        'isSuccess' => $isSuccess,
                                        'errorText' => $errorText,
                                    ];
                                }, 3);
                            }

                            break;
                        }
                    }

                    if ($teamResponse['isSuccess']) {
                        Log::info("Successfully stored team members. Leaving CommunityController storeTeam...");

                        $team = TeamMember::all();

                        return $this->successResponse("details", $team);
                    } else {
                        Log::error($teamResponse['errorText']);

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }
                } else {
                    Log::error("Failed to store team members. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to store team members. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store team members. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateName(Request $request) {
        Log::info("Entering CommunityController updateName...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'name' => 'bail|required|string|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $originalName = $communityDetails->getOriginal('name');

                                $communityDetails->name = $request->name;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('name')) {
                                    Log::info("Successfully updated community name from ".$originalName." to ".$communityDetails->name.".\n");

                                    return $this->successResponse('details', $communityDetails->name);
                                } else {
                                    Log::info("Community name not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', 'community name'));
                                }
                            } else {
                                Log::error("Failed to update community name. No existing data.");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update name. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateDescription(Request $request) {
        Log::info("Entering CommunityController updateDescription...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'description' => 'bail|required|string|between:2,10000',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $originalDescription = $communityDetails->getOriginal('description');

                                $communityDetails->description = $request->description;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('description')) {
                                    Log::info("Successfully updated community description from " . $originalDescription . " to " . $communityDetails->description . ". Leaving CommunityController updateDescription...\n");

                                    return $this->successResponse('details', $communityDetails->description);
                                } else {
                                    Log::info("Community description not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', 'community description'));
                                }
                            } else {
                                Log::error("Failed to update community description. No existing data.");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to update description. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
