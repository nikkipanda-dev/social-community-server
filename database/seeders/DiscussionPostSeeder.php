<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiscussionPost;
use Illuminate\Database\Eloquent\Factories\Sequence;

class DiscussionPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DiscussionPost::factory()
                      ->count(400)
                      ->create();
    }
}
