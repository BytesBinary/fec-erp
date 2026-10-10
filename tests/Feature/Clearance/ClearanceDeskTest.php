<?php

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Filament\Pages\Clearance\ClearanceDesk;
use App\Filament\Pages\Clearance\ViewClearance;
use App\Models\ClearanceApproval;
use App\Models\ClearancePrint;
use App\Models\ClearanceRequest;
use App\Notifications\ClearanceNotification;
use App\Services\Clearance\ClearanceService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
    $this->service = app(ClearanceService::class);
    $this->office = datasetUser(T::ADMIN_OFFICE);
    $this->chain = [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION];
});

function readyRequest(string $email = T::STUDENT_ELIGIBLE, ?array $chain = null): ClearanceRequest
{
    return approveInOrder(applyForClearance($email), $chain ?? [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);
}

it('finds ready requests by default and by student id, name, request number, department, session and status', function () {
    $ready = readyRequest(T::STUDENT_ELIGIBLE);
    $pending = applyForClearance(T::STUDENT_LIBRARY_LOAN);

    expect($this->service->search($this->office)->pluck('id')->all())->toBe([$ready->id])
        ->and($this->service->search($this->office, ['status' => 'all'])->pluck('id')->sort()->values()->all())->toBe(collect([$ready->id, $pending->id])->sort()->values()->all())
        ->and($this->service->search($this->office, ['q' => 'CSE-21-003'])->pluck('id')->all())->toBe([$ready->id])
        ->and($this->service->search($this->office, ['q' => 'rakib'])->pluck('id')->all())->toBe([$ready->id])
        ->and($this->service->search($this->office, ['q' => $ready->request_no])->pluck('id')->all())->toBe([$ready->id])
        ->and($this->service->search($this->office, ['status' => 'pending', 'department_id' => $pending->student->department_id])->pluck('id')->all())->toBe([$pending->id])
        ->and($this->service->search($this->office, ['status' => 'all', 'session' => '2021-2022'])->count())->toBe(2)
        ->and($this->service->search($this->office, ['status' => 'all', 'session' => '1999-2000'])->count())->toBe(0);
});

it('lets only the administration office search', function () {
    foreach ([T::TEACHER, T::STUDENT_ELIGIBLE, T::LIBRARIAN, T::PROVOST_A] as $email) {
        expect(fn () => $this->service->search(datasetUser($email)))->toThrow(ForbiddenException::class);
    }
});

it('prints only a fully approved request; the first print moves it to PRINTED', function () {
    $pending = applyForClearance();
    expect(fn () => $this->service->recordPrint($this->office, $pending->id))->toThrow(InvalidStateException::class);

    $ready = approveInOrder($pending, $this->chain);
    $print = $this->service->recordPrint($this->office, $ready->id);

    $fresh = $ready->fresh();
    expect($fresh->status)->toBe(ClearanceStatus::Printed)
        ->and($fresh->printed_at)->not->toBeNull()
        ->and($print->is_duplicate)->toBeFalse()
        ->and($print->printed_by)->toBe($this->office->id);
});

it('logs every reprint and marks it DUPLICATE, unless super admin overrides', function () {
    $ready = readyRequest();
    $first = $this->service->recordPrint($this->office, $ready->id);
    $second = $this->service->recordPrint($this->office, $ready->id);
    $third = $this->service->recordPrint(datasetUser(T::SUPER_ADMIN), $ready->id, 'html', asOriginal: true);

    expect([$first->is_duplicate, $second->is_duplicate, $third->is_duplicate])->toBe([false, true, false])
        ->and(ClearancePrint::query()->where('clearance_request_id', $ready->id)->count())->toBe(3)
        ->and($ready->fresh()->printed_at->equalTo($first->printed_at))->toBeTrue();

    expect(fn () => $this->service->recordPrint($this->office, $ready->id, 'html', asOriginal: true))->toThrow(ForbiddenException::class);
});

it('lets only users with the print permission print', function () {
    $ready = readyRequest();

    expect(fn () => $this->service->recordPrint(datasetUser(T::PRINCIPAL), $ready->id))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->recordPrint(datasetUser(T::STUDENT_ELIGIBLE), $ready->id))->toThrow(ForbiddenException::class);
});

it('hands over only after printing, recording who and whether the ID was verified', function () {
    $ready = readyRequest();

    expect(fn () => $this->service->markCollected($this->office, $ready->id, true))->toThrow(InvalidStateException::class);

    $this->service->recordPrint($this->office, $ready->id);
    $collected = $this->service->markCollected($this->office, $ready->id, true);

    expect($collected->status)->toBe(ClearanceStatus::Collected)
        ->and($collected->collected_by)->toBe($this->office->id)
        ->and($collected->id_verified)->toBeTrue()
        ->and($collected->collected_at)->not->toBeNull();

    expect(fn () => $this->service->recordPrint($this->office, $ready->id))->toThrow(InvalidStateException::class);
});

it('records when the student ID was not verified', function () {
    $ready = readyRequest();
    $this->service->recordPrint($this->office, $ready->id);

    expect($this->service->markCollected($this->office, $ready->id, false)->id_verified)->toBeFalse();
});

it('tells the student when the clearance is collected', function () {
    $ready = readyRequest();
    $this->service->recordPrint($this->office, $ready->id);

    Notification::fake();
    $this->service->markCollected($this->office, $ready->id, true);

    Notification::assertSentTo(datasetUser(T::STUDENT_ELIGIBLE), ClearanceNotification::class, fn (ClearanceNotification $n): bool => $n->title() === 'Clearance collected');
});

it('lets the student finish with a new request only after collection', function () {
    $ready = readyRequest();
    $this->service->recordPrint($this->office, $ready->id);
    $this->service->markCollected($this->office, $ready->id, true);

    expect($this->service->mine(datasetUser(T::STUDENT_ELIGIBLE))->status)->toBe(ClearanceStatus::Collected);
});

describe('print document', function () {
    it('shows all four signature images, a QR code, an empty principal box, the seal area and the footer', function () {
        $ready = readyRequest();
        $print = $this->service->recordPrint($this->office, $ready->id);

        $this->actingAs($this->office);
        $response = $this->get(route('clearance.print', ['clearanceRequest' => $ready->id, 'print' => $print->id]))->assertOk();

        $html = $response->getContent();

        expect(substr_count($html, 'data-testid="signature-image"'))->toBe(4)
            ->and($html)->toContain('data-testid="qr-code"')
            ->and($html)->toContain('data:image/svg+xml;base64,')
            ->and($html)->toContain('data-testid="principal-box"')
            ->and($html)->toContain('data-testid="seal-area"')
            ->and($html)->toContain('Valid only with the Principal')
            ->and($html)->toContain($ready->request_no)
            ->and($html)->not->toContain('duplicate-watermark');

        expect($html)->toMatch('/data-testid="principal-box"[^>]*><\/div>/');
    });

    it('embeds the verification link of this clearance in the QR payload', function () {
        $ready = readyRequest();
        $data = app(App\Services\Clearance\ClearancePrintService::class)->documentData($this->office, $ready);

        expect($data['verification_url'])->toBe(route('clearance.verify', ['code' => $ready->verify_code]));
    });

    it('marks reprints DUPLICATE in the document', function () {
        $ready = readyRequest();
        $this->service->recordPrint($this->office, $ready->id);
        $second = $this->service->recordPrint($this->office, $ready->id);

        $this->actingAs($this->office)->get(route('clearance.print', ['clearanceRequest' => $ready->id, 'print' => $second->id]))
            ->assertOk()->assertSee('duplicate-watermark', false)->assertSee('DUPLICATE');
    });

    it('shows a preview banner when no print record is referenced', function () {
        $ready = readyRequest();

        $this->actingAs($this->office)->get(route('clearance.print', $ready->id))->assertOk()->assertSee('Preview', false)->assertDontSee('duplicate-watermark', false);
    });

    it('lists skipped stages as not applicable', function () {
        $ready = readyRequest(T::STUDENT_NON_RESIDENT, [T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

        $this->actingAs($this->office)->get(route('clearance.print', $ready->id))->assertOk()->assertSee('Not applicable');
    });

    it('refuses printing documents that are not fully approved, of other users, or for unauthorized roles', function () {
        $pending = applyForClearance();

        $this->actingAs($this->office)->get(route('clearance.print', $pending->id))->assertStatus(409);

        $ready = approveInOrder($pending, $this->chain);
        $this->flushSession()->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get(route('clearance.print', $ready->id))->assertForbidden();
        $this->flushSession()->actingAs(datasetUser(T::PRINCIPAL))->get(route('clearance.print', $ready->id))->assertForbidden();
        auth()->logout();
        $this->flushSession()->get(route('clearance.print', $ready->id))->assertRedirect();
    });

    it('generates a PDF', function () {
        $ready = readyRequest();
        $print = $this->service->recordPrint($this->office, $ready->id, 'pdf');

        $response = $this->actingAs($this->office)->get(route('clearance.pdf', ['clearanceRequest' => $ready->id, 'print' => $print->id]));

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf')
            ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
    });
});

describe('public verification', function () {
    it('shows a valid clearance to anyone without login, with minimal data', function () {
        $ready = readyRequest();

        $this->get(route('clearance.verify', ['code' => $ready->verify_code]))
            ->assertOk()
            ->assertSee('Valid')
            ->assertSee('Rakib Hasan')
            ->assertSee($ready->request_no)
            ->assertSee('Intact')
            ->assertDontSee('Abdul Karim')
            ->assertDontSee('01700000')
            ->assertDontSee('rakib')
            ->assertDontSee('19990123456789012');
    });

    it('reports a clearance that is still in progress as not yet valid', function () {
        $pending = applyForClearance();

        $this->get(route('clearance.verify', ['code' => $pending->verify_code]))->assertOk()->assertSee('Not yet valid');
    });

    it('reports a cancelled clearance as not valid', function () {
        $request = applyForClearance();
        $this->service->cancel(datasetUser(T::STUDENT_ELIGIBLE), $request->id);

        $this->get(route('clearance.verify', ['code' => $request->verify_code]))->assertOk()->assertSee('Cancelled');
    });

    it('shows the integrity warning after a database row was tampered with', function () {
        $ready = readyRequest();
        ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->first()->update(['approver_name' => 'Impostor']);

        $this->get(route('clearance.verify', ['code' => $ready->verify_code]))->assertOk()->assertSee('WARNING: this record has been altered')->assertDontSee('Valid —');
    });

    it('answers 404 for unknown or guessed codes', function () {
        $ready = readyRequest();

        $this->get(route('clearance.verify', ['code' => str_repeat('a', 32)]))->assertNotFound();
        $this->get('/verify/clearance/'.$ready->request_no)->assertNotFound();
    });

    it('does not use the sequential request number as the code', function () {
        $ready = readyRequest();

        expect($ready->verify_code)->not->toContain(strtolower($ready->request_no));
    });
});

it('lets the desk page filter requests and shows the print actions on the request page', function () {
    $ready = readyRequest();

    $this->actingAs($this->office);
    Livewire::test(ClearanceDesk::class)->assertSee('Rakib Hasan')->set('search', 'nobody')->assertSee('No clearance requests match.');

    Livewire::test(ViewClearance::class, ['record' => $ready->id])
        ->assertActionVisible('print')
        ->assertActionHidden('collect')
        ->callAction('print')
        ->assertRedirect();

    Livewire::test(ViewClearance::class, ['record' => $ready->id])
        ->assertActionVisible('collect')
        ->callAction('collect', ['id_verified' => true]);

    expect($ready->fresh()->status)->toBe(ClearanceStatus::Collected);
});

it('hides the desk from other roles', function () {
    $this->actingAs(datasetUser(T::TEACHER));
    expect(ClearanceDesk::canAccess())->toBeFalse();

    $this->actingAs($this->office);
    expect(ClearanceDesk::canAccess())->toBeTrue();
});
