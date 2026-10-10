<?php

namespace App\Services\Assistant;

/**
 * One answer of the language model: text and/or tool calls.
 */
final readonly class ProviderReply
{
    /**
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(public string $text = '', public array $toolCalls = []) {}
}
