<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
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
            'reported_id' => $this->faker->numberBetween(1, 300),
            'is_microblog' => false,
            'is_community_blog' => false,
            'is_discussion' => false,
            'is_event' => false,
            'is_message' => false,
            'category' => $this->faker->words(2, true),
            'status' => $this->faker->word(2, true),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
