<?php

namespace App\Filament\Resources\ResultPulls\Pages;

use App\Filament\Resources\ResultPulls\ResultPullResource;
use App\Services\ResultPortal\ResultPullService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListResultPulls extends ListRecords
{
    protected static string $resource = ResultPullResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAllFailed')
                ->label('Retry all failed')
                ->requiresConfirmation()
                ->action(function (): void {
                    $count = app(ResultPullService::class)->retryAllFailed(Auth::user());
                    Notification::make()->title("{$count} pull(s) queued again")->success()->send();
                }),
        ];
    }
}
