<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MicroblogEntry;

class MicroblogEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MicroblogEntry::factory()
            ->count(300)
            ->create();
    }
}
