<?php

use App\Enums\ClearanceStatus as S;

dataset('valid transitions', [
    [S::Submitted, S::Pending], [S::Submitted, S::ReadyForCollection], [S::Submitted, S::Cancelled],
    [S::Pending, S::Pending], [S::Pending, S::Rejected], [S::Pending, S::ReadyForCollection], [S::Pending, S::Cancelled],
    [S::Rejected, S::Pending], [S::Rejected, S::Cancelled],
    [S::ReadyForCollection, S::Printed], [S::ReadyForCollection, S::Cancelled],
    [S::Printed, S::Printed], [S::Printed, S::Collected], [S::Printed, S::Cancelled],
]);

it('allows exactly the transitions of the state machine', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with('valid transitions');

it('rejects every other transition', function () {
    $valid = collect(dataset_pairs());

    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            $isValid = $valid->contains(fn (array $pair): bool => $pair[0] === $from && $pair[1] === $to);

            expect($from->canTransitionTo($to))->toBe($isValid, "{$from->value} → {$to->value}");
        }
    }
});

it('treats collected and cancelled as terminal', function () {
    expect(S::Collected->isTerminal())->toBeTrue()
        ->and(S::Cancelled->isTerminal())->toBeTrue()
        ->and(S::Collected->allowedNext())->toBe([])
        ->and(S::Pending->isActive())->toBeTrue();
});

/**
 * @return list<array{0: S, 1: S}>
 */
function dataset_pairs(): array
{
    return [
        [S::Submitted, S::Pending], [S::Submitted, S::ReadyForCollection], [S::Submitted, S::Cancelled],
        [S::Pending, S::Pending], [S::Pending, S::Rejected], [S::Pending, S::ReadyForCollection], [S::Pending, S::Cancelled],
        [S::Rejected, S::Pending], [S::Rejected, S::Cancelled],
        [S::ReadyForCollection, S::Printed], [S::ReadyForCollection, S::Cancelled],
        [S::Printed, S::Printed], [S::Printed, S::Collected], [S::Printed, S::Cancelled],
    ];
}
