<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['username' => 'owner'],
            ['name' => 'Pemilik Cafe', 'email' => 'owner@cafe.test', 'password' => env('OWNER_DEFAULT_PASSWORD', 'GantiPassword123!'), 'is_active' => true]
        );
        $owner->syncRoles(['Owner']);
    }
}
