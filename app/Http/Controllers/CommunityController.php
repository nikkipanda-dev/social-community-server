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

    public function getTeamMembers() {
        Log::info("Entering CommunityController getTeamMembers...");

        try {
            $team = TeamMember::with('user:id,first_name,last_name,username')->get();

            if (count($team) > 0) {
                Log::info("Successfully retrieved team members. Leaving CommunityController getTeamMembers...");

                return $this->successResponse('details', $team);
            } else {
                Log::error("Failed to retrieve team members. No data yet.\n");

                return $this->errorResponse("No data to show.");
            }
        } catch (\Exception $e) {
            Log::error("Failed to retrieve team members. " . $e->getMessage() . ".\n");

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

                        $team = TeamMember::with('user:id,first_name,last_name,username')->get();

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
                                $communityDetails->name = $request->name;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('name')) {
                                    Log::info("Successfully updated community name. Leaving CommunityController updateName...");

                                    return $this->successResponse('details', $communityDetails->refresh()->name);
                                } else {
                                    Log::error("Community name was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', 'community name'));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->name = $request->name;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community name. Leaving CommunityController updateName...");

                                    return $this->successResponse('details', $communityDetails->name);
                                } else {
                                    Log::error("Failed to store community name to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update community name. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community name. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community name. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateDescription(Request $request) {
        Log::info("Entering CommunityController updateDescription...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'description' => 'bail|required|string|between:10,10000',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $communityDetails->description = $request->description;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('description')) {
                                    Log::info("Successfully updated community description. Leaving CommunityController updateDescription...");

                                    return $this->successResponse('details', $communityDetails->refresh()->description);
                                } else {
                                    Log::error("Community description was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', 'community description'));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->description = $request->description;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community description. Leaving CommunityController updateDescription...");

                                    return $this->successResponse('details', $communityDetails->description);
                                } else {
                                    Log::error("Failed to store community description to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update community description. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community description. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community description. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function destroyTeamMember(Request $request) {
        Log::info("Entering CommunityController destroyTeamMember...");

        $this->validate($request, [
            'auth_username' => 'bail|required|exists:users,username',
            'id' => 'bail|required|exists:team_members',
            'username' => 'bail|required|alpha_num',
        ]);

        $isSuccess = false;
        $errorText = '';
        $teamResponse = null;

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->auth_username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {
                            $teamMember = TeamMember::with('user')->find($request->id);

                            if ($teamMember && ($teamMember->user->username === $request->username)) {
                                $teamResponse = DB::transaction(function () use ($isSuccess, $errorText, $teamMember) {
                                    $originalId = $teamMember->getOriginal('id');

                                    $teamMember->delete();

                                    if (!(TeamMember::find($originalId))) {
                                        $isSuccess = true;
                                    } else {
                                        $errorText = "Failed to soft delete team member.\n";

                                        throw new Exception("Failed to soft delete team member.\n");
                                    }

                                    return [
                                        'isSuccess' => $isSuccess,
                                        'errorText' => $errorText,
                                        'id' => $originalId,
                                    ];
                                }, 3);
                            } else {
                                Log::info("username". $teamMember->user->username);
                                Log::info("reqauest".$request->username);
                                Log::error("Failed to soft delete team member. Team member ID does not exist or might be deleted and/or username does not match record.\n");

                                return $this->errorResponse($this->getPredefinedResponse('default', null));
                            }
                            break;
                        }   
                    }

                    if ($teamResponse['isSuccess']) {
                        Log::info("Successfully soft deleted team member ID ". $teamResponse['id']. ". Leaving CommunityController destroyTeamMember...\n");

                        $team = TeamMember::with('user:id,first_name,last_name,username')->get();

                        return $this->successResponse("details", $team);
                    } else {
                        Log::error($teamResponse['errorText']);

                        return $this->errorResponse($this->getPredefinedResponse('default', null));
                    }
                } else {
                    Log::error("Failed to soft delete team member. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to soft delete team member. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to soft delete team member. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateWebsite(Request $request) {
        Log::info("Entering CommunityController storeWebsite...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'website' => 'bail|required|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $communityDetails->website = "https://".$request->website;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('website')) {
                                    Log::info("Successfully updated community website. Leaving CommunityController updateWebsite...");

                                    return $this->successResponse('details', $communityDetails->refresh());
                                } else {
                                    Log::error("Community website was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', 'community website'));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->website = "https://".$request->website;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community website. Leaving CommunityController updateWebsite...");

                                    return $this->successResponse('details', $communityDetails);
                                } else {
                                    Log::error("Failed to store community website to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }    
                    }
                } else {
                    Log::error("Failed to update community website. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community website. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store website. ".$e->getMessage().".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateFacebookAccount(Request $request) {
        Log::info("Entering CommunityController updateFacebookAccount...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'facebook_account' => 'bail|required|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $communityDetails->facebook_account = "https://facebook.com/".$request->facebook_account;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('facebook_account')) {
                                    Log::info("Successfully updated community's Facebook account. Leaving CommunityController updateFacebookAccount...");

                                    return $this->successResponse('details', $communityDetails->refresh());
                                } else {
                                    Log::error("Community's Facebook account was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', "community's Facebook account"));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->facebook_account = "https://facebook.com/".$request->facebook_account;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community's Facebook account. Leaving CommunityController updateFacebookAccount...");

                                    return $this->successResponse('details', $communityDetails);
                                } else {
                                    Log::error("Failed to store community's Facebook account to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update community's Facebook account. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community's Facebook account. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community's Facebook account. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateInstagramAccount(Request $request) {
        Log::info("Entering CommunityController updateInstagramAccount...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'instagram_account' => 'bail|required|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $communityDetails->instagram_account = "https://instagram.com/".$request->instagram_account;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('instagram_account')) {
                                    Log::info("Successfully updated community's Instagram account. Leaving CommunityController updateInstagramAccount...");

                                    return $this->successResponse('details', $communityDetails->refresh());
                                } else {
                                    Log::error("Community's Instagram account was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', "community's Instagram account"));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->instagram_account = "https://instagram.com/".$request->instagram_account;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community's Instagram account. Leaving CommunityController updateInstagramAccount...");

                                    return $this->successResponse('details', $communityDetails);
                                } else {
                                    Log::error("Failed to store community's Instagram account to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update community's Instagram account. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community's Instagram account. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community's Instagram account. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }

    public function updateTwitterAccount(Request $request) {
        Log::info("Entering CommunityController updateTwitterAccount...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'twitter_account' => 'bail|required|between:2,100',
        ]);

        try {
            if ($this->hasAuthHeader($request->header('authorization'))) {
                $user = User::where('username', $request->username)->first();

                if ($user) {
                    foreach ($user->tokens as $token) {
                        if ($token->tokenable_id === $user->id) {

                            $communityDetails = CommunityDetail::first();

                            if ($communityDetails) {
                                $communityDetails->twitter_account = "https://twitter.com/".$request->twitter_account;

                                $communityDetails->save();

                                if ($communityDetails->wasChanged('twitter_account')) {
                                    Log::info("Successfully updated community's Twitter account. Leaving CommunityController updateTwitterAccount...");

                                    return $this->successResponse('details', $communityDetails->refresh());
                                } else {
                                    Log::error("Community's Twitter account was not changed. No action needed.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('not changed', "community's Twitter account"));
                                }
                            } else {
                                $communityDetails = new CommunityDetail();

                                $communityDetails->twitter_account = "https://twitter.com/".$request->twitter_account;

                                $communityDetails->save();

                                if ($communityDetails) {
                                    Log::info("Successfully updated community's Twitter account. Leaving CommunityController updateTwitterAccount...");

                                    return $this->successResponse('details', $communityDetails);
                                } else {
                                    Log::error("Failed to store community's Twitter account to database.\n");

                                    return $this->errorResponse($this->getPredefinedResponse('default', null));
                                }
                            }
                            break;
                        }
                    }
                } else {
                    Log::error("Failed to update community's Twitter account. User does not exist or might be deleted.\n");

                    return $this->errorResponse($this->getPredefinedResponse('user not found', null));
                }
            } else {
                Log::error("Failed to update community's Twitter account. Missing authorization header or does not match regex.\n");

                return $this->errorResponse($this->getPredefinedResponse('default', null));
            }
        } catch (\Exception $e) {
            Log::error("Failed to store community's Twitter account. " . $e->getMessage() . ".\n");

            return $this->errorResponse($this->getPredefinedResponse('default', null));
        }
    }
}
