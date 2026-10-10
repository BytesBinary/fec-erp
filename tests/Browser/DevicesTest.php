<?php

use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

it('E2E 11: lists both devices, logs one out, and the logged-out browser lands on the login page', function () {
    $a = uiLogin(T::SUPER_ADMIN);
    $b = uiLogin(T::SUPER_ADMIN);

    $a->navigate('/security/devices')
        ->assertSee('Where you are logged in')
        ->assertCount('[data-testid=device-row]', 2)
        ->assertSee('This device');
    expect($a->script("document.querySelectorAll('[data-testid=device-row] .fi-badge').length"))->toBeGreaterThanOrEqual(1);

    $b->navigate('/security/devices')->assertSee('This device')->assertPathIs('/security/devices');

    $a->click('Log out')->wait(1)->click('Confirm')->wait(2);
    $a->assertCount('[data-testid=device-row]', 1);

    $b->navigate('/')->assertPathIs('/login')->assertSee('Sign in');
});

it('E2E 11: "Log out all other devices" signs out two other browsers', function () {
    $a = uiLogin(T::SUPER_ADMIN);
    $b = uiLogin(T::SUPER_ADMIN);
    $c = uiLogin(T::SUPER_ADMIN);

    $a->navigate('/security/devices')->assertCount('[data-testid=device-row]', 3);

    $a->click('Log out all other devices')->wait(1)->click('Confirm')->wait(2);
    $a->assertCount('[data-testid=device-row]', 1);

    $b->navigate('/')->assertPathIs('/login');
    $c->navigate('/')->assertPathIs('/login');
    $a->navigate('/')->assertPathIs('/')->assertSee('Dashboard');
});

it('shows a notification for a login from a new browser with a link to the devices page', function () {
    $a = uiLogin(T::SUPER_ADMIN);

    expect(datasetUser(T::SUPER_ADMIN)->notifications()->count())->toBeGreaterThanOrEqual(1);

    $a->navigate('/security/devices')->assertSee('Where you are logged in');
});

it('a logged-out browser cannot keep acting through a Livewire page it already had open', function () {
    $a = uiLogin(T::SUPER_ADMIN);
    $b = uiLogin(T::SUPER_ADMIN);

    $b->navigate('/security/devices')->assertSee('Where you are logged in');
    $a->navigate('/security/devices')->assertCount('[data-testid=device-row]', 2);

    $a->click('Log out')->wait(1)->click('Confirm')->wait(2);
    $a->assertCount('[data-testid=device-row]', 1);

    $b->script('window.confirm = () => false; window.alert = () => {}; document.querySelector("[data-testid=device-row]") && document.querySelector("button")?.click()');
    $b->wait(2);

    $a->navigate('/')->assertPathIs('/')->assertSee('Dashboard');
    expect(App\Models\UserSession::query()->active()->count())->toBe(1);
});
