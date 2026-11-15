<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Development-only credentials, documented in the README.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping the demo admin login in production; create a user with your own password.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );
    }
}
