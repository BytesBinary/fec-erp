<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Enums\Channel;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->color(fn (Channel $state): string => $state->color())
                    ->formatStateUsing(fn (Channel $state): string => $state->label()),
                TextColumn::make('action')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('entity_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (AuditLog $record): string => trim("{$record->entity_type} #{$record->entity_id}", ' #'))
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('actor_user_id')
                    ->label('Actor')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('channel')
                    ->options(collect(Channel::cases())->mapWithKeys(fn (Channel $channel): array => [$channel->value => $channel->label()])->all()),
                SelectFilter::make('entity_type')
                    ->label('Entity')
                    ->options(fn (): array => AuditLog::query()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type', 'entity_type')->all()),
                Filter::make('action')
                    ->schema([TextInput::make('action')->label('Action contains')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['action'] ?? null),
                        fn (Builder $query): Builder => $query->where('action', 'like', '%'.$data['action'].'%'),
                    )),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
