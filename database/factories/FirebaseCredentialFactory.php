<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Traits\AuthTrait;

class FirebaseCredentialFactory extends Factory
{

    use AuthTrait;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => 1,
            'secret' => $this->generateSecretKey(),
            'created_at' => now(),
        ];
    }
}
