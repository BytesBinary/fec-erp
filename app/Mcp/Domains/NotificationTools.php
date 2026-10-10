<?php

namespace App\Mcp\Domains;

use App\Exceptions\Domain\NotFoundException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\EmailDelivery;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\EmailTemplateService;
use App\Services\Notifications\NotificationRuleService;

/**
 * The email notification rules, templates and delivery history: the same
 * operations as the "Email notifications" screens, with the same permissions.
 */
final class NotificationTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $deliveryJson = fn (EmailDelivery $delivery): array => [
            'id' => $delivery->id, 'event' => $delivery->event_key, 'to' => $delivery->recipient_email, 'subject' => $delivery->subject,
            'status' => $delivery->status->value, 'attempts' => $delivery->attempts, 'error' => $delivery->last_error,
            'sent_at' => $delivery->sent_at?->toIso8601String(),
        ];

        return [
            ToolDefinition::make('notification_rule_list', 'Email event rules', 'List every email event grouped by category with its switch (enabled), mode (immediate or digest), recipients, template and the placeholders a template may use.')
                ->permission('notification_rule:view')
                ->covers(NotificationRuleService::class.'::list')
                ->handler(function (User $actor): array {
                    $groups = app(NotificationRuleService::class)->list($actor);

                    return ['summary' => $groups->flatten(1)->count().' email event(s) in '.$groups->count().' categories.', 'data' => ['categories' => $groups->map(fn ($rules) => $rules->values()->all())->all()]];
                }),

            ToolDefinition::make('notification_rule_update', 'Change an email event rule', 'Enable or disable an email event, set immediate or digest, change recipients (affected_user, department_head, hall_provost, context_emails, role:<name>) or select a template. Super admin only.')
                ->permission('notification_rule:manage')
                ->params(
                    Param::string('event_key', 'Event key from notification_rule_list, e.g. library.loan_overdue.', required: true),
                    Param::boolean('enabled', 'Send this email.'),
                    Param::enum('mode', 'When it is sent.', ['immediate', 'digest']),
                    Param::array('recipients', 'Recipient kinds.', 'string'),
                    Param::integer('email_template_id', 'Template id of the same event; 0 for the default text.'),
                )
                ->write(idempotent: true)
                ->covers(NotificationRuleService::class.'::update')
                ->handler(function (User $actor, array $args): array {
                    $data = array_intersect_key($args, array_flip(['enabled', 'mode', 'recipients']));

                    if (array_key_exists('email_template_id', $args)) {
                        $data['email_template_id'] = $args['email_template_id'] ?: null;
                    }

                    $rule = app(NotificationRuleService::class)->update($actor, $args['event_key'], $data);

                    return ['summary' => "{$rule->event_key}: ".($rule->enabled ? 'on' : 'off').", {$rule->mode}.", 'data' => $rule->only(['event_key', 'enabled', 'mode', 'recipients', 'email_template_id']), 'entity' => ['NotificationRule', $rule->id]];
                }),

            ToolDefinition::make('notification_rule_reset', 'Reset an email event rule', 'Put an email event back to its default switch, mode, recipients and template.')
                ->permission('notification_rule:manage')
                ->params(Param::string('event_key', 'Event key.', required: true))
                ->write(idempotent: true)
                ->covers(NotificationRuleService::class.'::reset')
                ->handler(function (User $actor, array $args): array {
                    $rule = app(NotificationRuleService::class)->reset($actor, $args['event_key']);

                    return ['summary' => "{$rule->event_key} reset to its defaults.", 'data' => $rule->only(['event_key', 'enabled', 'mode', 'recipients']), 'entity' => ['NotificationRule', $rule->id]];
                }),

            ToolDefinition::make('notification_preview', 'Preview an email', 'Render the email of an event with sample values (optionally a template or a draft subject/body) and show warnings, without sending anything.')
                ->permission('notification_rule:view')
                ->params(Param::string('event_key', 'Event key.', required: true), Param::integer('email_template_id', 'Template id.'), Param::string('subject', 'Draft subject.'), Param::string('body', 'Draft body.'))
                ->covers(EmailDeliveryService::class.'::preview')
                ->handler(function (User $actor, array $args): array {
                    $draft = isset($args['subject'], $args['body']) ? ['subject' => $args['subject'], 'body' => $args['body']] : null;
                    $preview = app(EmailDeliveryService::class)->preview($actor, $args['event_key'], $args['email_template_id'] ?? null, $draft);

                    return ['summary' => 'Preview: '.$preview['subject'], 'data' => $preview];
                }),

            ToolDefinition::make('notification_test_send', 'Send a test email', 'Send the sample email of an event to the signed-in user only.')
                ->permission('notification_rule:manage')
                ->params(Param::string('event_key', 'Event key.', required: true), Param::integer('email_template_id', 'Template id.'))
                ->write()
                ->covers(EmailDeliveryService::class.'::sendTest')
                ->handler(function (User $actor, array $args): array {
                    $delivery = app(EmailDeliveryService::class)->sendTest($actor, $args['event_key'], $args['email_template_id'] ?? null);

                    return ['summary' => "Test email queued to {$delivery->recipient_email}.", 'data' => ['delivery_id' => $delivery->id], 'entity' => ['EmailDelivery', $delivery->id]];
                }),

            ToolDefinition::make('email_delivery_list', 'Email deliveries', 'List recent emails with status (queued, held, sent, failed, skipped, blocked) and the error of failures, plus counts per status.')
                ->permission('email_delivery:view')
                ->params(Param::enum('status', 'Only this status.', ['queued', 'held', 'digested', 'sent', 'failed', 'skipped', 'blocked']), Param::string('event_key', 'Only this event.'), Param::integer('limit', 'Rows (default 50, max 200).'))
                ->covers(EmailDeliveryService::class.'::query', EmailDeliveryService::class.'::counts')
                ->handler(function (User $actor, array $args) use ($deliveryJson): array {
                    $service = app(EmailDeliveryService::class);
                    $rows = $service->query($actor)
                        ->when(isset($args['status']), fn ($query) => $query->where('status', $args['status']))
                        ->when(isset($args['event_key']), fn ($query) => $query->where('event_key', $args['event_key']))
                        ->limit(min((int) ($args['limit'] ?? 50), 200))->get()->map($deliveryJson)->all();

                    return ['summary' => count($rows).' email(s).', 'data' => ['counts' => $service->counts($actor), 'deliveries' => $rows]];
                }),

            ToolDefinition::make('email_delivery_retry', 'Retry an email', 'Queue a failed, skipped or blocked email again.')
                ->permission('email_delivery:retry')
                ->params(Param::integer('delivery_id', 'Delivery id from email_delivery_list.', required: true))
                ->write()
                ->covers(EmailDeliveryService::class.'::retry')
                ->handler(function (User $actor, array $args) use ($deliveryJson): array {
                    $delivery = EmailDelivery::query()->find($args['delivery_id']) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'email delivery']));
                    $retried = app(EmailDeliveryService::class)->retry($actor, $delivery);

                    return ['summary' => "Email {$retried->id} is {$retried->status->value}.", 'data' => $deliveryJson($retried), 'entity' => ['EmailDelivery', $retried->id]];
                }),

            ToolDefinition::make('email_delivery_retry_failed', 'Retry all failed emails', 'Queue every failed email again.')
                ->permission('email_delivery:retry')
                ->write()
                ->covers(EmailDeliveryService::class.'::retryFailed')
                ->handler(function (User $actor): array {
                    $count = app(EmailDeliveryService::class)->retryFailed($actor);

                    return ['summary' => "{$count} email(s) queued again.", 'data' => ['queued' => $count]];
                }),

            ToolDefinition::make('email_template_list', 'Email templates', 'List the custom email templates (subject and body) of one event or all events.')
                ->permission('notification_rule:view')
                ->params(Param::string('event_key', 'Only this event.'))
                ->covers(EmailTemplateService::class.'::list')
                ->handler(function (User $actor, array $args): array {
                    $templates = app(EmailTemplateService::class)->list($actor, $args['event_key'] ?? null)->map(fn (EmailTemplate $template): array => $template->only(['id', 'event_key', 'name', 'subject', 'body']))->all();

                    return ['summary' => count($templates).' template(s).', 'data' => ['templates' => $templates]];
                }),

            ToolDefinition::make('email_template_create', 'Create an email template', 'Create a template for an event. Only that event\'s placeholders are allowed and text that looks like a credential is rejected.')
                ->permission('email_template:manage')
                ->params(Param::string('event_key', 'Event key.', required: true), Param::string('name', 'Template name.', required: true), Param::string('subject', 'Subject with {placeholders}.', required: true), Param::string('body', 'Body with {placeholders}.', required: true))
                ->creates()
                ->covers(EmailTemplateService::class.'::create', EmailTemplateService::class.'::validate')
                ->handler(function (User $actor, array $args): array {
                    $template = app(EmailTemplateService::class)->create($actor, array_intersect_key($args, array_flip(['event_key', 'name', 'subject', 'body'])));

                    return ['summary' => "Template {$template->id} created.", 'data' => $template->only(['id', 'event_key', 'name']), 'entity' => ['EmailTemplate', $template->id]];
                }),

            ToolDefinition::make('email_template_update', 'Update an email template', 'Change the name, subject or body of a template.')
                ->permission('email_template:manage')
                ->params(Param::integer('template_id', 'Template id.', required: true), Param::string('name', 'Name.'), Param::string('subject', 'Subject.'), Param::string('body', 'Body.'))
                ->write(idempotent: true)
                ->covers(EmailTemplateService::class.'::update')
                ->handler(function (User $actor, array $args): array {
                    $template = EmailTemplate::query()->find($args['template_id']) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'email template']));
                    $updated = app(EmailTemplateService::class)->update($actor, $template, array_intersect_key($args, array_flip(['name', 'subject', 'body'])));

                    return ['summary' => "Template {$updated->id} updated.", 'data' => $updated->only(['id', 'event_key', 'name']), 'entity' => ['EmailTemplate', $updated->id]];
                }),

            ToolDefinition::make('email_template_delete', 'Delete an email template', 'Delete a template; rules that used it go back to the default text.')
                ->permission('email_template:manage')
                ->params(Param::integer('template_id', 'Template id.', required: true))
                ->destructive(fn (User $actor, array $args): array => ['template_id' => $args['template_id']])
                ->covers(EmailTemplateService::class.'::delete')
                ->handler(function (User $actor, array $args): array {
                    $template = EmailTemplate::query()->find($args['template_id']) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'email template']));
                    app(EmailTemplateService::class)->delete($actor, $template);

                    return ['summary' => "Template {$args['template_id']} deleted.", 'data' => ['deleted' => true]];
                }),
        ];
    }
}
