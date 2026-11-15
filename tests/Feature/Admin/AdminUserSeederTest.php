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

it('does not create the well-known admin login in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
});
