<?php

namespace App\Filament\Resources\NotificationRules;

use App\Exceptions\Domain\DomainException;
use App\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationRules;
use App\Services\Notifications\NotificationRuleService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * "Email notifications": every event that can send an email, grouped by
 * category, with the switch, recipients, immediate / digest, template,
 * preview and a test email. Only the super admin changes rules.
 */
class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Security & Access';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'email-notifications';

    protected static ?string $navigationLabel = 'Email notifications';

    protected static ?string $modelLabel = 'email event';

    protected static ?string $pluralModelLabel = 'Email notifications';

    public static function getEloquentQuery(): Builder
    {
        app(NotificationRules::class)->sync();

        return parent::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canManage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'notification_rule:manage');
    }

    public static function table(Table $table): Table
    {
        $registry = app(NotificationEventRegistry::class);

        return $table
            ->defaultGroup('category')
            ->groups([
                Group::make('category')->label('Category')->getTitleFromRecordUsing(fn (NotificationRule $record): string => $registry->categories()[$record->category] ?? $record->category)->collapsible(),
            ])
            ->groupingSettingsHidden()
            ->defaultSort('event_key')
            ->paginated(false)
            ->columns([
                TextColumn::make('event_key')->label('Event')->formatStateUsing(fn (string $state): string => $registry->get($state)['label'])->description(fn (NotificationRule $record): string => $record->event_key)->searchable(),
                IconColumn::make('enabled')->boolean()->label('On'),
                TextColumn::make('mode')->badge()->color(fn (string $state): string => $state === 'digest' ? 'gray' : 'info')->formatStateUsing(fn (string $state): string => $state === 'digest' ? 'Daily digest' : 'Immediate'),
                TextColumn::make('recipients')->label('Recipients')->formatStateUsing(fn (mixed $state): string => collect((array) $state)->map(fn (string $kind): string => self::recipientLabels()[$kind] ?? $kind)->implode(', ')),
                TextColumn::make('template.name')->label('Template')->placeholder('Default text'),
            ])
            ->filters([
                SelectFilter::make('enabled')->options(['1' => 'On', '0' => 'Off']),
                SelectFilter::make('category')->options($registry->categories()),
                SelectFilter::make('mode')->options(['immediate' => 'Immediate', 'digest' => 'Daily digest']),
            ])
            ->recordActions([
                Action::make('editRule')
                    ->label('Configure')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->visible(fn (): bool => self::canManage())
                    ->modalHeading(fn (NotificationRule $record): string => 'Configure: '.app(NotificationEventRegistry::class)->get($record->event_key)['label'])
                    ->fillForm(fn (NotificationRule $record): array => [
                        'enabled' => $record->enabled,
                        'mode' => $record->mode,
                        'recipients' => $record->recipients,
                        'email_template_id' => $record->email_template_id,
                    ])
                    ->schema(fn (NotificationRule $record): array => [
                        Toggle::make('enabled')->label('Send this email'),
                        Select::make('mode')->label('When')->options(['immediate' => 'Immediately', 'digest' => 'In the daily digest'])->required(),
                        CheckboxList::make('recipients')->label('Recipients')->options(self::recipientLabels())->columns(2)->helperText('A department head only receives events of their own department. People without a valid email address are listed as skipped.'),
                        Select::make('email_template_id')->label('Template')->placeholder('Default text')->options(fn (): array => EmailTemplate::query()->where('event_key', $record->event_key)->pluck('name', 'id')->all())->helperText('Create more templates under Email templates.'),
                    ])
                    ->action(fn (NotificationRule $record, array $data) => self::guard(function () use ($record, $data): void {
                        app(NotificationRuleService::class)->update(self::user(), $record->event_key, $data);
                        Notification::make()->title('Saved')->success()->send();
                    })),
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Preview with sample values')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (NotificationRule $record): array => [
                        View::make('filament.notifications.email-preview')->viewData(['preview' => app(EmailDeliveryService::class)->preview(self::user(), $record->event_key, $record->email_template_id), 'placeholders' => app(NotificationEventRegistry::class)->placeholders($record->event_key)]),
                    ]),
                Action::make('sendTest')
                    ->label('Send test to me')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (): bool => self::canManage())
                    ->requiresConfirmation()
                    ->modalDescription('Sends the sample email to your own address only.')
                    ->action(fn (NotificationRule $record) => self::guard(function () use ($record): void {
                        app(EmailDeliveryService::class)->sendTest(self::user(), $record->event_key, $record->email_template_id);
                        Notification::make()->title('Test email queued')->success()->send();
                    })),
                Action::make('toggle')
                    ->label(fn (NotificationRule $record): string => $record->enabled ? 'Turn off' : 'Turn on')
                    ->icon(fn (NotificationRule $record): Heroicon => $record->enabled ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->visible(fn (): bool => self::canManage())
                    ->action(fn (NotificationRule $record) => self::guard(fn () => app(NotificationRuleService::class)->update(self::user(), $record->event_key, ['enabled' => ! $record->enabled]))),
                Action::make('reset')
                    ->label('Reset to default')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (): bool => self::canManage())
                    ->requiresConfirmation()
                    ->action(fn (NotificationRule $record) => self::guard(fn () => app(NotificationRuleService::class)->reset(self::user(), $record->event_key))),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function recipientLabels(): array
    {
        return [
            'affected_user' => 'The person it is about',
            'department_head' => 'Department head (own department)',
            'hall_provost' => 'Hall provost (own hall)',
            'context_emails' => 'Extra addresses named by the event',
        ] + Role::query()->orderBy('name')->pluck('name')->mapWithKeys(fn (string $name): array => ['role:'.$name => 'Everyone with role: '.str_replace('_', ' ', $name)])->all();
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationRules::route('/')];
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
