<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FirebaseCredential;

class SuperAdminFirebaseCredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FirebaseCredential::factory()
                          ->count(1)
                          ->create();
    }
}
