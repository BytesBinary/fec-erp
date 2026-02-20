<?php

namespace App\Filament\Pages;

use App\Models\InstitutionSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InstitutionSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Institution Settings';

    protected static ?string $title = 'Institution Settings';

    protected string $view = 'filament.pages.institution-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(InstitutionSetting::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institution Info')
                    ->columns(2)
                    ->schema([
                        TextInput::make('institution_name')
                            ->label('Institution Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('short_name')
                            ->label('Short Name')
                            ->maxLength(50),
                        Textarea::make('address')
                            ->label('Address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->columns(3)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Phone')
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                    ]),

                Section::make('Branding')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Institution Logo')
                            ->disk('public')
                            ->directory('institution')
                            ->image()
                            ->maxSize(2048)
                            ->preserveFilenames(),
                    ]),

                Section::make('Principal / VC')
                    ->columns(2)
                    ->schema([
                        TextInput::make('principal_name')
                            ->label('Name')
                            ->maxLength(255),
                        TextInput::make('principal_title')
                            ->label('Title')
                            ->placeholder('Principal')
                            ->maxLength(100),
                        FileUpload::make('principal_signature_path')
                            ->label('Signature Image')
                            ->disk('public')
                            ->directory('institution/signatures')
                            ->image()
                            ->maxSize(1024)
                            ->preserveFilenames()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        InstitutionSetting::current()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Settings saved successfully.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
