<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds one admin user idempotently', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = User::sole();

    expect($admin->email)->toBe('admin@example.com')
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});
