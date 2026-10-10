<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Profile\ProfileService;

final class AdminTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        return [
            ToolDefinition::make('audit_log_search', 'Search audit log', 'Search the audit log (who changed what, from which channel: web, mcp or assistant). Filters are optional; newest first, cursor paging.')
                ->permission('audit_log:view')
                ->params(
                    Param::integer('actor_user_id', 'Only this actor.'),
                    Param::enum('channel', 'web, mcp, assistant or system.', ['web', 'mcp', 'assistant', 'system']),
                    Param::string('action', 'Action prefix, e.g. clearance. or department.created.'),
                    Param::string('entity_type', 'Entity type, e.g. Course.'),
                    Param::string('entity_id', 'Entity id.'),
                    Param::integer('integration_id', 'Only calls made by this AI integration.'),
                    Param::string('from', 'From date-time (ISO 8601).'),
                    Param::string('to', 'Until date-time (ISO 8601).'),
                    ...ToolSupport::paging(),
                )
                ->covers(AuditLogService::class.'::search')
                ->handler(function (User $actor, array $args): array {
                    $filters = array_diff_key($args, ['limit' => 1, 'cursor' => 1]);
                    $page = ToolSupport::page(app(AuditLogService::class)->search($actor, $filters, (int) ($args['limit'] ?? 25), $args['cursor'] ?? null));

                    return ['summary' => count($page['items']).' audit entr'.(count($page['items']) === 1 ? 'y' : 'ies').'.', 'data' => $page];
                }),

            ToolDefinition::make('profile_field_list', 'Required profile fields', 'List which student profile fields are required and active; students missing a required field cannot use the system until they complete their profile.')
                ->permission('profile_field:manage')
                ->covers(ProfileService::class.'::requiredFields')
                ->handler(fn (User $actor): array => ['summary' => 'Required profile fields.', 'data' => app(ProfileService::class)->requiredFields($actor)->map(fn ($field): array => $field->only(['field_key', 'required', 'active']))->all()]),

            ToolDefinition::make('profile_field_configure', 'Configure profile field', 'Make a student profile field required/optional or switch it on/off. A newly required field gates students whose profile no longer satisfies the set. Needs confirm=true.')
                ->permission('profile_field:manage')
                ->params(Param::string('field_key', 'Field key, e.g. blood_group.', true), Param::boolean('required', 'Whether students must fill it.', true), Param::boolean('active', 'Whether the field is used at all (default true).'))
                ->destructive(fn (User $actor, array $args): array => ['field' => $args['field_key'], 'required' => $args['required'], 'effect' => 'Students whose profile no longer satisfies the required set are gated on their next request.'])
                ->covers(ProfileService::class.'::configureField')
                ->handler(function (User $actor, array $args): array {
                    app(ProfileService::class)->configureField($actor, $args['field_key'], (bool) $args['required'], (bool) ($args['active'] ?? true));

                    return ['summary' => "Updated profile field {$args['field_key']}.", 'data' => null];
                }),
        ];
    }
}
