<?php

namespace App\Traits;

use Illuminate\Support\Str;

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

    public function getPredefinedResponse($type, $data) {
        $response = null;

        if ($type === 'default') {
            $response = "Something went wrong. Please try again in a few seconds or contact us directly for assistance."; 
        } else if ($type === 'user not found') {
            $response = "User does not exist.";
        } else if ($type === 'not changed') {
            $response = Str::ucfirst($data)." was not changed.";
        }

        return $response;
    }
}