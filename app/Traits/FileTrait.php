<?php

namespace App\Traits;

trait FileTrait {
    public function generateFilename() {
        $rand = bin2hex(random_bytes(30));

        return $rand;
    }
}