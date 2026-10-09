<?php

namespace App\Support;

use App\Enums\Channel;
use Closure;
use Illuminate\Http\Request;

/**
 * Who/where the current operation comes from: the channel (web, mcp,
 * assistant, system), client IP, user agent and — for MCP — the integration
 * id. Registered as a scoped singleton, so it resets per request / job.
 *
 * The web layer sets nothing explicitly (HTTP requests default to `web`);
 * the MCP server and the assistant call {@see self::runAs()} or set the
 * channel before invoking services.
 */
class RequestContext
{
    public function __construct(
        protected ?Channel $channel = null,
        protected ?string $ip = null,
        protected ?string $userAgent = null,
        protected ?int $integrationId = null,
    ) {}

    public static function current(): self
    {
        return app(self::class);
    }

    public function channel(): Channel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        return app()->runningInConsole() && ! app()->runningUnitTests() ? Channel::System : Channel::Web;
    }

    public function setChannel(Channel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function ip(): ?string
    {
        return $this->ip ?? $this->request()?->ip();
    }

    public function userAgent(): ?string
    {
        return $this->userAgent ?? $this->request()?->userAgent();
    }

    public function integrationId(): ?int
    {
        return $this->integrationId;
    }

    public function setClient(?string $ip, ?string $userAgent): static
    {
        $this->ip = $ip;
        $this->userAgent = $userAgent;

        return $this;
    }

    public function setIntegrationId(?int $integrationId): static
    {
        $this->integrationId = $integrationId;

        return $this;
    }

    /**
     * Run a callback with a temporary channel (and optional integration id),
     * restoring the previous values afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Channel $channel, Closure $callback, ?int $integrationId = null): mixed
    {
        [$previousChannel, $previousIntegration] = [$this->channel, $this->integrationId];

        $this->channel = $channel;
        $this->integrationId = $integrationId ?? $previousIntegration;

        try {
            return $callback();
        } finally {
            $this->channel = $previousChannel;
            $this->integrationId = $previousIntegration;
        }
    }

    protected function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }
}
