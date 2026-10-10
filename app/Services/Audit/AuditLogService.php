<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Read access to the audit log (super admin, `audit_log:view`).
 */
class AuditLogService
{
    public function __construct(protected Authorizer $authorizer) {}

    /**
     * @param  array{actor_user_id?: int, channel?: string, action?: string, entity_type?: string, entity_id?: string, integration_id?: int, from?: string, to?: string}  $filters
     * @return CursorPaginator<int, AuditLog>
     */
    public function search(User $actor, array $filters = [], int $limit = 25, ?string $cursor = null): CursorPaginator
    {
        $this->authorizer->authorize($actor, 'audit_log:view');

        return AuditLog::query()
            ->when(isset($filters['actor_user_id']), fn ($query) => $query->where('actor_user_id', $filters['actor_user_id']))
            ->when(isset($filters['channel']), fn ($query) => $query->where('channel', $filters['channel']))
            ->when(isset($filters['action']), fn ($query) => $query->where('action', 'like', $filters['action'].'%'))
            ->when(isset($filters['entity_type']), fn ($query) => $query->where('entity_type', $filters['entity_type']))
            ->when(isset($filters['entity_id']), fn ($query) => $query->where('entity_id', $filters['entity_id']))
            ->when(isset($filters['integration_id']), fn ($query) => $query->where('integration_id', $filters['integration_id']))
            ->when(isset($filters['from']), fn ($query) => $query->where('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->where('created_at', '<=', $filters['to']))
            ->orderByDesc('id')
            ->cursorPaginate(max(1, min(100, $limit)), ['*'], 'cursor', $cursor);
    }
}
