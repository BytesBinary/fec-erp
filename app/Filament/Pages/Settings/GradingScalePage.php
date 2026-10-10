<?php

namespace App\Filament\Pages\Settings;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Services\Results\GradingScaleService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Super admin: the grading scale (marks range → letter → grade point).
 *
 * @property-read Schema $form
 */
class GradingScalePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 31;

    protected static ?string $slug = 'settings/grading-scale';

    protected static ?string $navigationLabel = 'Grading scale';

    protected string $view = 'filament.pages.settings.grading-scale';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'grading_scale:manage');
    }

    public function mount(): void
    {
        $bands = app(GradingScaleService::class)->bands()->map(fn ($band): array => [
            'min_mark' => $band->min_mark, 'max_mark' => $band->max_mark, 'letter' => $band->letter, 'grade_point' => $band->grade_point,
        ])->values()->all();

        $this->form->fill(['bands' => $bands]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('bands')->columns(4)->schema([
                TextInput::make('min_mark')->numeric()->required()->minValue(0)->maxValue(100),
                TextInput::make('max_mark')->numeric()->required()->minValue(0)->maxValue(100),
                TextInput::make('letter')->required()->maxLength(4),
                TextInput::make('grade_point')->numeric()->required()->minValue(0)->maxValue(4),
            ])->minItems(1),
        ]);
    }

    public function save(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        try {
            app(GradingScaleService::class)->replace($user, array_values($this->form->getState()['bands']));
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Grading scale saved.')->send();
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [Action::make('save')->label('Save')->submit('save')];
    }
}
