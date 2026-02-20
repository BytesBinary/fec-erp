<?php

namespace App\Filament\Resources\Routine\Pages;

use App\Filament\Resources\Routine\RoutineResource;
use App\Services\RoutineGeneratorService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class RoutineIndex extends ListRecords
{
    protected static string $resource = RoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoGenerate')
                ->label('Auto Generate All')
                ->icon(Heroicon::OutlinedBolt)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Auto-Generate Routines for All Active Batches')
                ->modalDescription('This will delete and regenerate routine slots for ALL active batches\' current semesters. Manual edits will be lost. Continue?')
                ->modalSubmitActionLabel('Yes, Generate')
                ->action(function (): void {
                    $result = app(RoutineGeneratorService::class)->generateForAll();

                    Notification::make()
                        ->success()
                        ->title("Generated for {$result['batches_processed']} batches — {$result['scheduled']} slots scheduled")
                        ->body(count($result['skipped']) > 0 ? implode("\n", array_slice($result['skipped'], 0, 5)) : null)
                        ->send();
                }),
        ];
    }
}
