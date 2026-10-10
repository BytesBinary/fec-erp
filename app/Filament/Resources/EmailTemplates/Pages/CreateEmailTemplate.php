<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\User;
use App\Services\Notifications\EmailTemplateService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(EmailTemplateService::class)->create(Auth::user() instanceof User ? Auth::user() : throw new DomainException, $data);
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }
}
