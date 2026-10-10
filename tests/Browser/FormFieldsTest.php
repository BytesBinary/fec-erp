<?php

use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

dataset('campus form pages', [
    'hall residents and dues' => [T::PROVOST_A, '/campus/hall-residents'],
    'library loans' => [T::LIBRARIAN, '/campus/library-loans'],
    'clearance desk filters' => [T::ADMIN_OFFICE, '/clearance/desk'],
]);

it('draws a visible border on every hand-written form field', function (string $email, string $path) {
    uiLogin($email)->navigate($path)->wait(2)
        ->assertScript(<<<'JS'
            (() => {
                const fields = [...document.querySelectorAll('main input:not([type=hidden]):not([type=checkbox]):not([type=radio]), main select, main textarea')];
                return fields.length > 0 && fields.every((el) => parseFloat(getComputedStyle(el).borderTopWidth) >= 1 && getComputedStyle(el).borderTopStyle === 'solid');
            })()
            JS);
})->with('campus form pages');
