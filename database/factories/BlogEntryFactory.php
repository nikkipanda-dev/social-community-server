<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BlogEntryFactory extends Factory
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
            'title' => $this->faker->realText(50),
            'body' => $this->faker->realText(500),
            'slug' => $this->faker->uuid(),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
