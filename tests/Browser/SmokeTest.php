<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

it('lets an admin sign in through the real login form and reach the dashboard', function () {
    $admin = User::factory()->create([
        'name' => 'Smoke Admin',
        'email' => 'smoke-admin@fec.test',
        'password' => 'password',
    ]);
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

    $page = visit('/login');

    $page->assertSee('Sign in')
        ->type('[id="form.email"]', 'smoke-admin@fec.test')
        ->type('[id="form.password"]', 'password')
        ->click('button[type=submit]')
        ->wait(2)
        ->assertPathIs('/')
        ->assertSee('Dashboard')
        ->assertSee('Smoke Admin')
        ->assertNoJavaScriptErrors();
});

it('redirects a guest to the login page', function () {
    visit('/')
        ->assertPathIs('/login')
        ->assertSee('Sign in');
});
