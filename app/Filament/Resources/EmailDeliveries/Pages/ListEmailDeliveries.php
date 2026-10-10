<?php

namespace App\Filament\Resources\EmailDeliveries\Pages;

use App\Filament\Resources\EmailDeliveries\EmailDeliveryResource;
use App\Services\Notifications\EmailDeliveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListEmailDeliveries extends ListRecords
{
    protected static string $resource = EmailDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAllFailed')
                ->label('Retry all failed')
                ->requiresConfirmation()
                ->action(function (): void {
                    $count = app(EmailDeliveryService::class)->retryFailed(Auth::user());
                    Notification::make()->title("{$count} email(s) queued again")->success()->send();
                }),
        ];
    }
}
