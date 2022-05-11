<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BlogEntry;

class BlogEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        BlogEntry::factory()
                 ->count(300)
                 ->create();
    }
}
