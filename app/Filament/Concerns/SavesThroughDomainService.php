<?php

namespace App\Filament\Concerns;

use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ValidationException as DomainValidationException;
use App\Models\User;
use App\Services\CrudService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Makes a Filament Create/Edit page persist through the module's domain
 * service, so the web UI shares authorization, validation and audit logging
 * with MCP and the assistant. Domain validation errors are shown on the form;
 * authorization failures (e.g. moving a record out of the user's scope) are
 * shown as a notification and the save is halted.
 */
trait SavesThroughDomainService
{
    /**
     * @return class-string<CrudService<Model>>
     */
    abstract protected static function domainService(): string;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->throughService(fn (CrudService $service): Model => $service->create($this->actingUser(), $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->throughService(fn (CrudService $service): Model => $service->update($this->actingUser(), $record, $data));
    }

    /**
     * @param  \Closure(CrudService<Model>): Model  $callback
     */
    protected function throughService(\Closure $callback): Model
    {
        try {
            return $callback(app(static::domainService()));
        } catch (DomainValidationException $exception) {
            $messages = collect($exception->context['errors'] ?? [])
                ->mapWithKeys(fn (array $errors, string $field): array => ["data.{$field}" => $errors])
                ->all();

            throw ValidationException::withMessages($messages ?: ['data' => [$exception->getMessage()]]);
        } catch (ForbiddenException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $this->halt();
        }
    }

    protected function actingUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
