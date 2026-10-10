<?php

namespace App\Filament\Pages\Settings;

use App\Exceptions\Domain\DomainException;
use App\Models\ClearanceStage;
use App\Models\User;
use App\Services\Clearance\StaffSignatureService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Approvers upload the signature image printed on cleared documents
 * (PNG with a transparent background, up to 512 KB).
 *
 * @property-read Schema $form
 */
class MySignature extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'profile/signature';

    protected static ?string $navigationLabel = 'My signature';

    protected string $view = 'filament.pages.settings.my-signature';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $roleIds = ClearanceStage::query()->pluck('approver_role_id');

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FileUpload::make('signature')
                ->label('Signature image (PNG, transparent background)')
                ->acceptedFileTypes(['image/png'])
                ->maxSize(512)
                ->storeFiles(false)
                ->required(),
        ]);
    }

    public function currentDataUri(): ?string
    {
        $png = app(StaffSignatureService::class)->contents($this->user());

        return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
    }

    public function save(): void
    {
        $file = collect((array) $this->form->getState()['signature'])->first();

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            app(StaffSignatureService::class)->store($this->user(), $file->get());
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->form->fill();
        Notification::make()->success()->title('Signature saved.')->send();
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [Action::make('save')->label('Save signature')->submit('save')];
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
