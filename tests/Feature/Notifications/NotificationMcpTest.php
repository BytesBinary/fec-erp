<?php

use App\Models\EmailDelivery;
use App\Services\Notifications\Contracts\ExternalMessenger;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;
use Tests\Support\RecordingMessenger;

beforeEach(function () {
    seedTestDataset();
    config(['notifications.enabled' => true]);
    app()->instance(ExternalMessenger::class, new RecordingMessenger);
});

function notificationClient(string $email): McpClient
{
    ['token' => $token] = mcpIntegrationFor(datasetUser($email));

    return new McpClient(test(), $token);
}

it('lets the super admin manage email rules, templates and deliveries through MCP', function () {
    $client = notificationClient(T::SUPER_ADMIN);

    $list = $client->call('notification_rule_list');
    expect($list['isError'])->toBeFalse()->and($list['payload']['data']['categories'])->toHaveKeys(['security', 'portal', 'clearance']);

    $update = $client->call('notification_rule_update', ['event_key' => 'library.loan_overdue', 'enabled' => false, 'mode' => 'digest', 'recipients' => ['affected_user']]);
    expect($update['isError'])->toBeFalse()->and($update['payload']['data'])->toMatchArray(['enabled' => false, 'mode' => 'digest']);

    $bad = $client->call('notification_rule_update', ['event_key' => 'library.loan_overdue', 'recipients' => ['everyone']]);
    expect($bad['isError'])->toBeTrue();

    $template = $client->call('email_template_create', ['event_key' => 'library.loan_overdue', 'name' => 'Short', 'subject' => 'Overdue: {title}', 'body' => 'Please return {title}.']);
    $id = $template['payload']['data']['id'];

    $preview = $client->call('notification_preview', ['event_key' => 'library.loan_overdue', 'email_template_id' => $id]);
    expect($preview['payload']['data']['subject'])->toBe('Overdue: Introduction to Algorithms');

    $rejected = $client->call('email_template_create', ['event_key' => 'library.loan_overdue', 'name' => 'Bad', 'subject' => 'x', 'body' => 'password: hunter2hunter2']);
    expect($rejected['isError'])->toBeTrue();

    expect($client->call('notification_test_send', ['event_key' => 'library.loan_overdue'])['isError'])->toBeFalse();

    $deliveries = $client->call('email_delivery_list', ['status' => 'sent']);
    expect($deliveries['payload']['data']['counts'])->toHaveKey('failed')->and($deliveries['payload']['data']['deliveries'][0]['subject'])->toStartWith('[TEST]');

    EmailDelivery::factory()->create(['status' => App\Enums\EmailDeliveryStatus::Failed]);
    expect($client->call('email_delivery_retry_failed')['payload']['data']['queued'])->toBe(1);

    $reset = $client->call('notification_rule_reset', ['event_key' => 'library.loan_overdue']);
    expect($reset['payload']['data'])->toMatchArray(['enabled' => true, 'mode' => 'immediate']);
});

it('lets the admin office look and retry but not change rules', function () {
    $client = notificationClient(T::ADMIN_OFFICE);

    expect($client->call('notification_rule_list')['isError'])->toBeFalse()
        ->and($client->call('email_delivery_list')['isError'])->toBeFalse()
        ->and($client->errorCode('notification_rule_update', ['event_key' => 'library.loan_overdue', 'enabled' => false]))->toBe('FORBIDDEN')
        ->and($client->errorCode('email_template_create', ['event_key' => 'library.loan_overdue', 'name' => 'x', 'subject' => 'x', 'body' => 'x']))->toBe('FORBIDDEN');
});

it('keeps teachers and students away from every email tool', function () {
    foreach ([T::TEACHER, T::STUDENT_ELIGIBLE] as $email) {
        $client = notificationClient($email);

        foreach (['notification_rule_list', 'email_delivery_list', 'email_template_list'] as $tool) {
            expect($client->errorCode($tool))->toBe('FORBIDDEN', "{$email} {$tool}");
        }
    }
});
