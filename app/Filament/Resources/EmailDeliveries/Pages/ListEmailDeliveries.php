<?php

namespace App\Filament\Resources\EmailDeliveries\Pages;

use App\Enums\EmailDeliveryStatus;
use App\Filament\Resources\EmailDeliveries\EmailDeliveryResource;
use App\Services\Notifications\EmailDeliveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        foreach ([EmailDeliveryStatus::Sent, EmailDeliveryStatus::Failed, EmailDeliveryStatus::Queued, EmailDeliveryStatus::Held, EmailDeliveryStatus::Skipped, EmailDeliveryStatus::Blocked] as $status) {
            $tabs[$status->value] = Tab::make($status->label())->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
