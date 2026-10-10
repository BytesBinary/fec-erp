<?php

namespace App\Filament\Resources\EmailDeliveries;

use App\Enums\EmailDeliveryStatus;
use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\EmailDeliveries\Pages\ListEmailDeliveries;
use App\Filament\Resources\EmailDeliveries\Pages\ViewEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\NotificationEventRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Delivery history of every email: queued, sent, failed (with the error),
 * skipped (no address) and blocked. Failed ones can be retried.
 */
class EmailDeliveryResource extends Resource
{
    protected static ?string $model = EmailDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Security & Access';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'email-deliveries';

    protected static ?string $navigationLabel = 'Email deliveries';

    protected static ?string $modelLabel = 'email';

    public static function getEloquentQuery(): Builder
    {
        return app(EmailDeliveryService::class)->query(self::user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $registry = app(NotificationEventRegistry::class);

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event_key')->label('Event')->formatStateUsing(fn (string $state): string => $registry->has($state) ? $registry->get($state)['label'] : ucfirst($state))->searchable(),
                TextColumn::make('recipient_email')->label('To')->searchable()->placeholder('—'),
                TextColumn::make('subject')->limit(50)->searchable(),
                TextColumn::make('status')->badge()->color(fn (EmailDeliveryStatus $state): string => $state->color())->formatStateUsing(fn (EmailDeliveryStatus $state): string => $state->label()),
                TextColumn::make('attempts'),
                TextColumn::make('last_error')->label('Problem')->wrap()->limit(70)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(EmailDeliveryStatus::cases())->mapWithKeys(fn (EmailDeliveryStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('event_key')->label('Event')->options(collect($registry->all())->map(fn (array $event): string => $event['label'])->all())->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (EmailDelivery $record): bool => $record->status->canRetry())
                    ->action(fn (EmailDelivery $record) => self::guard(function () use ($record): void {
                        app(EmailDeliveryService::class)->retry(self::user(), $record);
                        Notification::make()->title('Queued again')->success()->send();
                    })),
            ])
            ->toolbarActions([
                BulkAction::make('retrySelected')
                    ->label('Retry selected')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::guard(function () use ($records): void {
                        $records->filter(fn (EmailDelivery $delivery): bool => $delivery->status->canRetry())->each(fn (EmailDelivery $delivery) => app(EmailDeliveryService::class)->retry(self::user(), $delivery));
                        Notification::make()->title('Queued again')->success()->send();
                    })),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Email')->columns(3)->schema([
                TextEntry::make('recipient_email')->label('To'),
                TextEntry::make('status')->badge()->color(fn (EmailDeliveryStatus $state): string => $state->color())->formatStateUsing(fn (EmailDeliveryStatus $state): string => $state->label()),
                TextEntry::make('attempts'),
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('body')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-wrap']),
                TextEntry::make('last_error')->label('Problem')->placeholder('—')->columnSpanFull(),
                TextEntry::make('sent_at')->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailDeliveries::route('/'),
            'view' => ViewEmailDelivery::route('/{record}'),
        ];
    }

    protected static function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }

    protected static function guard(callable $action): void
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
