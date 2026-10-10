<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\User;
use App\Services\Notifications\EmailTemplateService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(EmailTemplateService::class)->update(Auth::user() instanceof User ? Auth::user() : throw new DomainException, $record, $data);
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }
}
