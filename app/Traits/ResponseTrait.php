<?php

namespace App\Traits;

trait ResponseTrait {
    public function successResponse($label, $data) {
        return [
            'isSuccess' => true,
            'data' => [
                $label => $data,
            ]
        ];
    }

    public function errorResponse($errorText) {
        return [
            'isSuccess' => false,
            'data' => [
                'errorText' => $errorText,
            ]
        ];
    }
}