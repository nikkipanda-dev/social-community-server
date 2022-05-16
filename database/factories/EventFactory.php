<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
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
            'name' => $this->faker->realText(50),
            'details' => $this->faker->realText(300),
            'start_date' => $this->faker->dateTimeBetween('-1 year', '-5 months'),
            'end_date' => $this->faker->dateTimeBetween('-5 months', 'now'),
            'rsvp_date' => $this->faker->dateTimeBetween('-1 year', '-4 months'),
            'details' => $this->faker->realText(300),
            'is_hobby' => false,
            'is_wellbeing' => false,
            'is_career' => false,
            'is_coaching' => false,
            'is_science_and_tech' => false,
            'is_social_cause' => false,
            'slug' => $this->faker->uuid(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', '-6 months'),
        ];
    }
}
