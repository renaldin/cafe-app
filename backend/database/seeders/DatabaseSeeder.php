<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(OwnerSeeder::class);
        if (app()->environment('local', 'testing') || env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
