<?php

namespace App\Services\Assistant\Contracts;

use App\Services\Assistant\ProviderReply;

/**
 * The language model behind the assistant. `messages` use the Anthropic
 * Messages shape: role user|assistant, content string or content blocks
 * (text / tool_use / tool_result). `tools` are name, description, input_schema.
 */
interface AssistantProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array{name: string, description: string, input_schema: array<string, mixed>}>  $tools
     *
     * @throws \App\Exceptions\Assistant\ProviderUnavailableException
     */
    public function respond(string $system, array $messages, array $tools): ProviderReply;

    public function available(): bool;
}
