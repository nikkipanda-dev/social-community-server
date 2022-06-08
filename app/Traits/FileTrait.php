<?php

namespace App\Traits;

use App\Models\UserDisplayPhoto;
use Illuminate\Support\Facades\Log;

trait FileTrait {
    public function generateFilename() {
        $rand = bin2hex(random_bytes(30));

        return $rand;
    }

    public function getDisplayPhoto($userId) {
        Log::info("Entering FileTrait getDisplayPhoto...");
        
        $displayPhoto = UserDisplayPhoto::latest()->where('user_id', $userId)->first();

        return $displayPhoto;
    }
}