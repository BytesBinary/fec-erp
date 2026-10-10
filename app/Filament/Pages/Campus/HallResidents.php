<?php

namespace App\Filament\Pages\Campus;

use App\Exceptions\Domain\DomainException;
use App\Models\Hall;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\Student;
use App\Models\User;
use App\Services\Halls\HallResidencyService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Hall provost: residents of the hall, room assignment and dues.
 */
class HallResidents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Campus';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'campus/hall-residents';

    protected static ?string $navigationLabel = 'Hall residents';

    protected string $view = 'filament.pages.campus.hall-residents';

    public string $roll = '';

    public ?int $hallId = null;

    public string $room = '';

    public string $dueRoll = '';

    public string $dueDescription = '';

    public ?string $dueAmount = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'hall:assign_student');
    }

    /**
     * @return Collection<int, HallResidency>
     */
    public function residents(): Collection
    {
        return app(HallResidencyService::class)->residents($this->user())->orderBy('hall_id')->get();
    }

    /**
     * @return Collection<int, HallDue>
     */
    public function openDues(): Collection
    {
        $hallIds = $this->residents()->pluck('hall_id')->unique();

        return HallDue::query()->open()->whereIn('hall_id', $hallIds)->with(['student.user'])->get();
    }

    /**
     * @return array<int, string>
     */
    public function halls(): array
    {
        return Hall::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function assign(): void
    {
        $this->run(function (): string {
            $student = Student::query()->where('roll_number', trim($this->roll))->firstOrFail();
            app(HallResidencyService::class)->assign($this->user(), $student, Hall::query()->findOrFail($this->hallId), $this->room ?: null);

            return 'Resident assigned.';
        });
    }

    public function addDue(): void
    {
        $this->run(function (): string {
            $student = Student::query()->where('roll_number', trim($this->dueRoll))->firstOrFail();
            app(HallResidencyService::class)->recordDue($this->user(), $student, $this->dueDescription, (float) $this->dueAmount);
            $this->dueDescription = '';
            $this->dueAmount = null;

            return 'Due recorded.';
        });
    }

    public function settle(int $dueId): void
    {
        $this->run(function () use ($dueId): string {
            app(HallResidencyService::class)->settleDue($this->user(), HallDue::query()->findOrFail($dueId));

            return 'Due settled.';
        });
    }

    /**
     * @param  callable(): string  $callback
     */
    protected function run(callable $callback): void
    {
        try {
            $message = $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            Notification::make()->danger()->title('Student or hall not found.')->send();

            return;
        }

        Notification::make()->success()->title($message)->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
