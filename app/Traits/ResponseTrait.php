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

    public function getPredefinedResponse($type) {
        $response = null;

        if ($type === 'default') {
            $response = "Something went wrong. Please try again in a few seconds or contact us directly for assistance."; 
        }

        return $response;
    }
}