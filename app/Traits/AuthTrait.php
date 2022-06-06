<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait AuthTrait {
    public function hasAuthHeader($header) {
        $hasAuthHeader = false;

        if ($header && preg_match('/(.*?\|)/', $header)) {
            $hasAuthHeader = true;
        }

        return $hasAuthHeader;
    }

    public function generateSecretKey() {
        $secret = bin2hex(random_bytes(10));

        return $secret;
    }
}