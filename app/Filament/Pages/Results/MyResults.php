<?php

namespace App\Filament\Pages\Results;

use App\Models\Student;
use App\Models\User;
use App\Services\Results\ResultService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The student's "Result" menu: published semesters, semester GPA and CGPA.
 */
class MyResults extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'results';

    protected string $view = 'filament.pages.results.my-results';

    public static function getNavigationLabel(): string
    {
        return __('erp.results.nav');
    }

    public function getTitle(): string
    {
        return __('erp.results.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && Student::query()->where('user_id', $user->getKey())->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function transcript(): array
    {
        $user = Auth::user();
        assert($user instanceof User);

        $student = Student::query()->where('user_id', $user->getKey())->firstOrFail();

        return app(ResultService::class)->transcript($user, $student);
    }
}
