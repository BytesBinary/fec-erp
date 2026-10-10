<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Services\Notifications\NotificationEventRegistry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Email templates: extra subject / body texts per event, picked per rule on
 * the Email notifications screen. Only the event's own placeholders are allowed.
 */
class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Security & Access';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'email-templates';

    protected static ?string $navigationLabel = 'Email templates';

    public static function form(Schema $schema): Schema
    {
        $registry = app(NotificationEventRegistry::class);

        return $schema->components([
            Select::make('event_key')->label('Event')->options(collect($registry->all())->map(fn (array $event): string => $event['label'])->all())->searchable()->required()->live()->disabledOn('edit'),
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('subject')->required()->maxLength(255)->helperText(fn ($get): string => self::placeholderHelp($get('event_key'))),
            Textarea::make('body')->required()->rows(10)->helperText(fn ($get): string => self::placeholderHelp($get('event_key')).' Never put passwords or tokens in a template; they are blocked.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('event_key')
            ->columns([
                TextColumn::make('event_key')->label('Event')->formatStateUsing(fn (string $state): string => app(NotificationEventRegistry::class)->get($state)['label'])->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('subject')->limit(60),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    protected static function placeholderHelp(?string $eventKey): string
    {
        if ($eventKey === null || ! app(NotificationEventRegistry::class)->has($eventKey)) {
            return 'Choose an event to see its placeholders.';
        }

        return 'Placeholders: '.collect(app(NotificationEventRegistry::class)->placeholders($eventKey))->map(fn (string $placeholder): string => '{'.$placeholder.'}')->implode(' ');
    }
}
