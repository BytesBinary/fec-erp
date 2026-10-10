<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Security\UserSecurityService;
use App\Support\Authorization\Authorizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Active login sessions of a user; super admin can revoke them (audited).
 */
class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginSessions';

    protected static ?string $title = 'Login sessions';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'session:view_any');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->active())
            ->columns([
                TextColumn::make('device_label')->label('Device'),
                TextColumn::make('ip')->label('IP'),
                IconColumn::make('trusted_until')->label('Trusted')->boolean()->state(fn (UserSession $record): bool => $record->isTrusted()),
                TextColumn::make('last_active_at')->label('Last active')->since()->sortable(),
                TextColumn::make('created_at')->label('Signed in')->dateTime(),
            ])
            ->defaultSort('last_active_at', 'desc')
            ->recordActions([
                Action::make('revoke')
                    ->label('Log out')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(function (UserSession $record, array $data): void {
                        try {
                            app(UserSecurityService::class)->revokeSession(Auth::user(), $record, $data['reason']);
                        } catch (DomainException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Session logged out.')->send();
                    }),
            ]);
    }
}
