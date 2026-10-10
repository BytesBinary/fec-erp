<?php

namespace App\Filament\Pages\Profile;

use App\Exceptions\Domain\DomainException;
use App\Models\Hall;
use App\Models\Student;
use App\Models\User;
use App\Services\Profile\ProfileService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Student profile completion (spec §6). Every other page redirects here
 * until all required fields are valid.
 *
 * @property-read Schema $form
 */
class CompleteProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'profile/complete';

    protected string $view = 'filament.pages.profile.complete-profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('erp.profile.nav');
    }

    public function getTitle(): string
    {
        return __('erp.profile.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && Student::query()->where('user_id', $user->getKey())->exists();
    }

    public function mount(): void
    {
        $described = app(ProfileService::class)->mine($this->user());
        $profile = $described['profile'];

        $this->form->fill([
            ...$profile->only([
                'full_name_certificate', 'father_name', 'mother_name', 'email', 'present_address', 'permanent_address',
                'guardian_name', 'guardian_phone', 'blood_group', 'nid_or_birth_reg', 'is_residential', 'hall_id',
                'emergency_contact_name', 'emergency_contact_phone',
            ]),
            'date_of_birth' => $profile->date_of_birth?->toDateString(),
            'phone' => $described['student']->phone,
            'photo_path' => $profile->photo_path ? [$profile->photo_path] : [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $locked = fn (string $field): bool => in_array($field, $this->described()['profile']->locked_fields ?? [], true);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('erp.profile.section_identity'))->columns(2)->schema([
                    TextInput::make('full_name_certificate')->label(config('profile.fields.full_name_certificate.label'))->maxLength(255)->disabled(fn () => $locked('full_name_certificate')),
                    DatePicker::make('date_of_birth')->native()->label(config('profile.fields.date_of_birth.label'))->maxDate(now()->subDay())->disabled(fn () => $locked('date_of_birth')),
                    TextInput::make('father_name')->label(config('profile.fields.father_name.label'))->maxLength(255)->disabled(fn () => $locked('father_name')),
                    TextInput::make('mother_name')->label(config('profile.fields.mother_name.label'))->maxLength(255)->disabled(fn () => $locked('mother_name')),
                    TextInput::make('nid_or_birth_reg')->label(config('profile.fields.nid_or_birth_reg.label'))->helperText(__('erp.profile.nid_help')),
                    Select::make('blood_group')->native()->label(config('profile.fields.blood_group.label'))->options(array_combine(config('profile.blood_groups'), config('profile.blood_groups'))),
                    FileUpload::make('photo_path')
                        ->label(config('profile.fields.photo.label'))
                        ->helperText(__('erp.profile.photo_help'))
                        ->image()
                        ->imageEditor()
                        ->imageCropAspectRatio(config('profile.photo.aspect_ratio'))
                        ->imageResizeTargetWidth('350')
                        ->imageResizeTargetHeight('450')
                        ->disk(config('profile.photo.disk'))
                        ->directory(config('profile.photo.directory'))
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->maxSize(config('profile.photo.max_kb'))
                        ->columnSpanFull(),
                ]),
                Section::make(__('erp.profile.section_contact'))->columns(2)->schema([
                    TextInput::make('phone')->label(config('profile.fields.phone.label'))->tel(),
                    TextInput::make('email')->label(config('profile.fields.email.label'))->email(),
                    Textarea::make('present_address')->label(config('profile.fields.present_address.label'))->rows(2),
                    Textarea::make('permanent_address')->label(config('profile.fields.permanent_address.label'))->rows(2),
                    Toggle::make('is_residential')->label(__('erp.profile.is_residential'))->live(),
                    Select::make('hall_id')->label(config('profile.fields.hall.label'))
                        ->options(fn (): array => Hall::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => (bool) $get('is_residential')),
                ]),
                Section::make(__('erp.profile.section_guardian'))->columns(2)->schema([
                    TextInput::make('guardian_name')->label(config('profile.fields.guardian_name.label')),
                    TextInput::make('guardian_phone')->label(config('profile.fields.guardian_phone.label'))->tel(),
                    TextInput::make('emergency_contact_name')->label(config('profile.fields.emergency_contact_name.label')),
                    TextInput::make('emergency_contact_phone')->label(config('profile.fields.emergency_contact_phone.label'))->tel(),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['photo_path'] = is_array($state['photo_path'] ?? null) ? (array_values($state['photo_path'])[0] ?? null) : ($state['photo_path'] ?? null);
        $state['is_residential'] = array_key_exists('is_residential', $state) ? (bool) $state['is_residential'] : null;

        try {
            app(ProfileService::class)->save($this->user(), $this->student(), $state);
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->body(collect($exception->context['errors'] ?? [])->flatten()->implode(' ') ?: null)->send();

            return;
        }

        $described = $this->described();

        if ($described['problems'] === []) {
            Notification::make()->success()->title(__('erp.profile.saved_complete'))->send();
            $this->redirect(filament()->getUrl());

            return;
        }

        Notification::make()->warning()->title(__('erp.profile.saved_incomplete'))->send();
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [Action::make('save')->label(__('erp.profile.save'))->submit('save')];
    }

    /**
     * @return array{profile: \App\Models\StudentProfile, student: Student, progress: array{done: int, total: int, percent: int}, problems: array<string, string>}
     */
    public function described(): array
    {
        return app(ProfileService::class)->mine($this->user());
    }

    protected function student(): Student
    {
        return Student::query()->where('user_id', $this->user()->getKey())->firstOrFail();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
