<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Enums\Channel;
use App\Models\AuditLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('actor.name')->label('Actor')->placeholder('System'),
                        TextEntry::make('channel')
                            ->badge()
                            ->color(fn (Channel $state): string => $state->color())
                            ->formatStateUsing(fn (Channel $state): string => $state->label()),
                        TextEntry::make('action')->fontFamily(FontFamily::Mono),
                        TextEntry::make('entity_type')->label('Entity')->placeholder('—'),
                        TextEntry::make('entity_id')->label('Entity ID')->placeholder('—'),
                        TextEntry::make('ip')->label('IP address')->placeholder('—'),
                        TextEntry::make('integration_id')->label('MCP integration')->placeholder('—'),
                        TextEntry::make('user_agent')->label('User agent')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Changes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('before')
                            ->state(fn (AuditLog $record): ?string => self::pretty($record->before))
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder('—'),
                        TextEntry::make('after')
                            ->state(fn (AuditLog $record): ?string => self::pretty($record->after))
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder('—'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    protected static function pretty(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
