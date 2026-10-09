<?php

namespace App\Filament\Pages;

use App\Enums\ThemePreset;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Appearance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Appearance';

    protected static ?string $title = 'Appearance';

    protected string $view = 'filament.pages.appearance';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->form->fill([
            'theme' => $user->theme?->value ?? ThemePreset::ForestOchre->value,
            'theme_primary_color' => $user->theme_primary_color,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Theme')
                    ->description('Pick a color theme for your own account. This only changes how the panel looks for you.')
                    ->schema([
                        Radio::make('theme')
                            ->label('Preset')
                            ->options(array_column(ThemePreset::options(), 'label', 'value'))
                            ->default(ThemePreset::ForestOchre->value)
                            ->required(),
                        ColorPicker::make('theme_primary_color')
                            ->label('Custom accent color')
                            ->helperText('Optional — overrides just the accent color of the preset above. Leave blank to use the preset\'s default accent.')
                            ->nullable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->currentUser()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Appearance saved.')
            ->send();

        // Colors are resolved once per full page render (see
        // ErpPanelProvider), so reload to pick up the new theme now.
        $this->redirect(static::getUrl());
    }

    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Appearance')
                ->submit('save'),
        ];
    }
}
