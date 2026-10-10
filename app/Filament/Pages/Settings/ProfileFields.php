<?php

namespace App\Filament\Pages\Settings;

use App\Models\ProfileRequiredField;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Super admin: which student profile fields are required (spec §6).
 */
class ProfileFields extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'settings/profile-fields';

    protected static ?string $navigationLabel = 'Required profile fields';

    protected string $view = 'filament.pages.settings.profile-fields';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'profile_field:manage');
    }

    /**
     * @return Collection<int, ProfileRequiredField>
     */
    public function fields(): Collection
    {
        return app(ProfileService::class)->requiredFields($this->user());
    }

    public function toggleRequired(string $fieldKey): void
    {
        $field = ProfileRequiredField::query()->where('field_key', $fieldKey)->firstOrFail();
        app(ProfileService::class)->configureField($this->user(), $fieldKey, ! $field->required, $field->active);
        Notification::make()->success()->title('Saved.')->send();
    }

    public function toggleActive(string $fieldKey): void
    {
        $field = ProfileRequiredField::query()->where('field_key', $fieldKey)->firstOrFail();
        app(ProfileService::class)->configureField($this->user(), $fieldKey, $field->required, ! $field->active);
        Notification::make()->success()->title('Saved.')->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
