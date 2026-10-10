<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Unit/Authorization');

pest()->extend(Tests\BrowserTestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Browser');

pest()->browser()->timeout(15000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed the deterministic `seed:test` dataset (spec §10.1).
 */
function seedTestDataset(): void
{
    test()->seed(Database\Seeders\Testing\TestSeeder::class);
}

/**
 * A seeded account by e-mail (see Database\Seeders\Testing\TestDataset).
 */
function datasetUser(string $email): App\Models\User
{
    return App\Models\User::query()->where('email', $email)->firstOrFail();
}

function authorizer(): App\Support\Authorization\Authorizer
{
    return app(App\Support\Authorization\Authorizer::class);
}

/**
 * The authenticator code for `$secret`, `$stepOffset` 30-second steps away from now.
 */
function totpCode(string $secret, int $stepOffset = 0): string
{
    $step = intdiv(now()->getTimestamp(), 30) + $stepOffset;

    return app(PragmaRX\Google2FA\Google2FA::class)->oathTotp($secret, $step);
}

/**
 * Turns 2FA on for `$user` with `$secret`, bypassing the setup UI.
 *
 * @return list<string> plain recovery codes
 */
function enableTwoFactorFor(App\Models\User $user, string $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'): array
{
    $service = app(App\Services\Security\TwoFactorService::class);

    App\Models\UserMfa::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['totp_secret_encrypted' => $secret, 'enabled_at' => now(), 'last_used_step' => null, 'failed_attempts' => 0, 'locked_until' => null],
    );

    return app(App\Services\Security\RecoveryCodeService::class)->generate($user);
}

/**
 * Logs in through the real login form in a fresh browser context and returns the page.
 */
function uiLogin(string $email, string $password = 'password'): Pest\Browser\Api\AwaitableWebpage
{
    return visit('/login')
        ->type('[id="form.email"]', $email)
        ->type('[id="form.password"]', $password)
        ->click('button[type=submit]')
        ->wait(2);
}

/**
 * Signs out through the user menu of the current page.
 */
function uiLogout(Pest\Browser\Api\AwaitableWebpage $page): Pest\Browser\Api\AwaitableWebpage
{
    return $page->click('.fi-user-menu-trigger')->wait(1)->click('Sign out')->wait(2);
}

/**
 * The student model behind a seeded account.
 */
function studentFor(string $email): App\Models\Student
{
    return App\Models\Student::query()->where('user_id', datasetUser($email)->id)->firstOrFail();
}

/**
 * A seeded student applies for clearance; returns the request.
 */
function applyForClearance(string $email = Database\Seeders\Testing\TestDataset::STUDENT_ELIGIBLE): App\Models\ClearanceRequest
{
    return app(App\Services\Clearance\ClearanceService::class)->apply(datasetUser($email));
}

/**
 * Walks a request through the given approver accounts, in order.
 *
 * @param  list<string>  $approverEmails
 */
function approveInOrder(App\Models\ClearanceRequest $request, array $approverEmails): App\Models\ClearanceRequest
{
    foreach ($approverEmails as $email) {
        $request = app(App\Services\Clearance\ClearanceService::class)->approve(datasetUser($email), $request->id);
    }

    return $request;
}
