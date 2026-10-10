<?php

namespace App\Notifications;

/**
 * Notices about AI integrations: created, revoked, expiring, new IP.
 */
class McpNotification extends InAppNotification
{
    public function __construct(
        protected string $heading,
        protected string $message,
        protected string $tone = 'info',
    ) {}

    public function title(): string
    {
        return $this->heading;
    }

    public function body(): string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return url('/settings/ai-integrations');
    }

    public function actionLabel(): string
    {
        return __('erp.mcp.open_integrations');
    }

    public function color(): string
    {
        return $this->tone;
    }
}
