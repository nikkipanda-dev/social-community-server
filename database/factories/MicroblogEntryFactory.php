<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MicroblogEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => $this->faker->numberBetween(6, 19),
            'body' => $this->faker->realText(300),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
