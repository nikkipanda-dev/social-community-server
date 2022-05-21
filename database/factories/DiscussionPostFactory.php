<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DiscussionPostFactory extends Factory
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
            'body' => $this->faker->realText(300),
            'is_hobby' => false,
            'is_wellbeing' => false,
            'is_career' => false,
            'is_coaching' => false,
            'is_science_and_tech' => false,
            'is_social_cause' => false,
            'slug' => $this->faker->uuid(),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
