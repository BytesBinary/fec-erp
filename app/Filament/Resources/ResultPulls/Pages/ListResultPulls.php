<?php

namespace App\Filament\Resources\ResultPulls\Pages;

use App\Enums\ResultPullStatus;
use App\Filament\Resources\ResultPulls\ResultPullResource;
use App\Services\ResultPortal\ResultPullService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        foreach ([ResultPullStatus::Success, ResultPullStatus::Failed, ResultPullStatus::Queued, ResultPullStatus::Skipped] as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
