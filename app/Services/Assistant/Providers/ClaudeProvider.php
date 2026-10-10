<?php

namespace App\Services\Assistant\Providers;

use App\Exceptions\Assistant\ProviderUnavailableException;
use App\Services\Assistant\Contracts\AssistantProvider;
use App\Services\Assistant\ProviderReply;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API through Laravel's HTTP client. The key and model come
 * from ANTHROPIC_API_KEY / ASSISTANT_MODEL and never reach the browser.
 */
class ClaudeProvider implements AssistantProvider
{
    public function available(): bool
    {
        return filled(config('assistant.api_key'));
    }

    public function respond(string $system, array $messages, array $tools): ProviderReply
    {
        if (! $this->available()) {
            throw new ProviderUnavailableException('No ANTHROPIC_API_KEY is configured.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('assistant.api_key'),
                'anthropic-version' => (string) config('assistant.api_version'),
            ])->timeout((int) config('assistant.timeout_seconds'))->acceptJson()->post((string) config('assistant.api_url'), array_filter([
                'model' => config('assistant.model'),
                'max_tokens' => (int) config('assistant.max_tokens'),
                'system' => $system,
                'messages' => $messages,
                'tools' => $tools === [] ? null : $tools,
            ]));
        } catch (ConnectionException $exception) {
            throw new ProviderUnavailableException('The language model could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new ProviderUnavailableException('The language model returned HTTP '.$response->status().'.');
        }

        $text = '';
        $calls = [];

        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $calls[] = ['id' => (string) $block['id'], 'name' => (string) $block['name'], 'input' => (array) ($block['input'] ?? [])];
            }
        }

        return new ProviderReply(trim($text), $calls);
    }
}
