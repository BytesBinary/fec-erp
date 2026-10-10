<?php

namespace App\Mcp\Registry;

use App\Enums\Channel;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ProfileIncompleteException;
use App\Exceptions\Domain\ValidationException as DomainValidationException;
use App\Models\IdempotencyKey;
use App\Models\McpIntegration;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileService;
use App\Support\Authorization\Authorizer;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs one tool call for a real ERP user. Both the MCP server and the in-app
 * assistant go through here, so authorization, the profile gate, read-only
 * integrations, strict input validation, dry-run confirmation, idempotency and
 * audit logging are identical on every channel (spec §4.1, §5.2).
 */
class ToolExecutor
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit, protected ProfileService $profiles) {}

    /**
     * Whether `$user` should even see the tool in `tools/list`.
     */
    public function visibleTo(User $user, ToolDefinition $definition, ?McpIntegration $integration = null): bool
    {
        if ($integration?->isReadOnly() && ! $definition->readOnly) {
            return false;
        }

        return $this->hasPermission($user, $definition);
    }

    /**
     * A tool's `permission` may list alternatives separated by `|` (any one is enough).
     */
    protected function hasPermission(User $user, ToolDefinition $definition): bool
    {
        if ($definition->permission === null) {
            return true;
        }

        foreach (explode('|', $definition->permission) as $permission) {
            if ($this->authorizer->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{ok: bool, payload: array<string, mixed>}
     */
    public function execute(User $user, ToolDefinition $definition, array $arguments, Channel $channel = Channel::Mcp, ?McpIntegration $integration = null): array
    {
        return RequestContext::current()->runAs($channel, function () use ($user, $definition, $arguments, $channel, $integration): array {
            try {
                $payload = $this->run($user, $definition, $arguments, $channel, $integration);
                $this->log($user, $definition, $channel, $payload['dry_run'] ?? false ? 'dry_run' : 'success', $payload['entity'] ?? null);
                unset($payload['entity']);

                return ['ok' => true, 'payload' => $payload];
            } catch (Throwable $exception) {
                $error = $this->toError($exception);
                $this->log($user, $definition, $channel, in_array($error['code'], ['FORBIDDEN', 'PROFILE_INCOMPLETE'], true) ? 'denied' : 'error', null, $error['code']);

                return ['ok' => false, 'payload' => ['error' => $error, 'summary' => $error['message']]];
            }
        }, $integration?->getKey());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function run(User $user, ToolDefinition $definition, array $arguments, Channel $channel, ?McpIntegration $integration): array
    {
        if ($integration?->isReadOnly() && ! $definition->readOnly) {
            throw new ForbiddenException('This integration is read-only and cannot change data.', ['reason' => 'READ_ONLY_INTEGRATION']);
        }

        if ($definition->permission !== null && ! $this->hasPermission($user, $definition)) {
            $this->authorizer->authorize($user, explode('|', $definition->permission)[0]);
        }

        if (! str_starts_with($definition->name, 'me_') && $this->profiles->isGated($user)) {
            throw new ProfileIncompleteException;
        }

        $arguments = $this->validate($definition, $arguments);

        if ($definition->destructive && ($arguments['confirm'] ?? false) !== true) {
            $preview = $definition->preview !== null ? ($definition->preview)($user, $arguments) : ['tool' => $definition->name, 'arguments' => array_diff_key($arguments, ['confirm' => 1])];

            return [
                'summary' => "Dry run: nothing was changed. Call {$definition->name} again with confirm=true to apply this.",
                'data' => $preview,
                'dry_run' => true,
                'confirm_required' => true,
            ];
        }

        unset($arguments['confirm']);

        $key = $definition->acceptsIdempotencyKey ? ($arguments['idempotencyKey'] ?? null) : null;
        unset($arguments['idempotencyKey']);

        if ($key !== null) {
            $hash = hash('sha256', json_encode($arguments, JSON_UNESCAPED_UNICODE));
            $stored = IdempotencyKey::query()->where(['user_id' => $user->getKey(), 'tool' => $definition->name, 'key' => $key])->first();

            if ($stored !== null) {
                if ($stored->request_hash !== $hash) {
                    throw new ConflictException('This idempotencyKey was already used with different arguments.');
                }

                return [...$stored->response, 'replayed' => true];
            }
        }

        $result = ($definition->handler)($user, $arguments);
        $payload = ['summary' => $result['summary'], 'data' => $result['data'] ?? null];

        if ($key !== null) {
            IdempotencyKey::query()->create(['user_id' => $user->getKey(), 'tool' => $definition->name, 'key' => $key, 'request_hash' => $hash, 'response' => $payload]);
        }

        return $payload + ['entity' => $result['entity'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function validate(ToolDefinition $definition, array $arguments): array
    {
        $known = array_map(fn (Param $param): string => $param->name, $definition->params);
        $unknown = array_diff(array_keys($arguments), $known);

        if ($unknown !== []) {
            throw new DomainValidationException('Unknown argument(s): '.implode(', ', $unknown).'.', ['errors' => array_fill_keys($unknown, ['Unknown argument.'])]);
        }

        $validator = Validator::make($arguments, $definition->rules());

        if ($validator->fails()) {
            throw new DomainValidationException(__('erp.errors.validation'), ['errors' => $validator->errors()->toArray()]);
        }

        return array_filter($validator->validated(), fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{code: string, message: string, context: array<string, mixed>}
     */
    public function toError(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof DomainException => $exception->toArray(),
            $exception instanceof ValidationException => ['code' => 'VALIDATION_ERROR', 'message' => __('erp.errors.validation'), 'context' => ['errors' => $exception->errors()]],
            $exception instanceof ModelNotFoundException => (new NotFoundException(__('erp.errors.not_found', ['entity' => 'record'])))->toArray(),
            default => $this->internal($exception),
        };
    }

    /**
     * @return array{code: string, message: string, context: array<string, mixed>}
     */
    protected function internal(Throwable $exception): array
    {
        report($exception);

        return ['code' => 'INTERNAL_ERROR', 'message' => 'Something went wrong while running the tool.', 'context' => []];
    }

    /**
     * @param  array{0: string, 1: int|string|null}|null  $entity
     */
    protected function log(User $user, ToolDefinition $definition, Channel $channel, string $outcome, ?array $entity, ?string $code = null): void
    {
        $this->audit->record(
            $channel === Channel::Assistant ? 'assistant.tool_call' : 'mcp.tool_call',
            $entity,
            null,
            array_filter(['tool' => $definition->name, 'outcome' => $outcome, 'code' => $code, 'read_only' => $definition->readOnly], fn (mixed $value): bool => $value !== null),
            $user,
        );
    }
}
