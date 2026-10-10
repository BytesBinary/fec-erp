<?php

namespace App\Enums;

/**
 * The channel a request (and therefore an audited write) came through.
 * `system` covers console commands, queued jobs and seeders.
 */
enum Channel: string
{
    case Web = 'web';
    case Mcp = 'mcp';
    case Assistant = 'assistant';
    case System = 'system';

    public function label(): string
    {
        return __("erp.channels.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            Channel::Web => 'info',
            Channel::Mcp => 'warning',
            Channel::Assistant => 'success',
            Channel::System => 'gray',
        };
    }
}
