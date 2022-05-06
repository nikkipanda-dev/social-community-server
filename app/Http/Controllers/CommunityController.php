<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;

class CommunityController extends Controller
{
    use ResponseTrait;

    public function store(Request $request) {        
        Log::info("Entering CommunityController store...");

        $this->validate($request, [
            'username' => 'bail|required|exists:users',
            'name' => 'bail|required|string|between:2,100',
        ]);

        try {

        } catch (\Exception $e) {
            Log::error("Failed to store community name. ".$e->getMessage()."\n");

            return $this->errorResponse($this->getPredefinedResponse('default'));
        }
    }
}
