<?php

namespace App\Traits;

trait AuthTrait {
    public function hasAuthHeader($header) {
        $hasAuthHeader = false;

        if ($header && preg_match('/(.*?\|)/', $header)) {
            $hasAuthHeader = true;
        }

        return $hasAuthHeader;
    }
}