<?php

namespace App\Services\Mcp;

/**
 * Fills the connection snippets of config/mcp_clients.php.
 */
class ClientSnippets
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function clients(): array
    {
        return collect(config('mcp_clients.clients'))->map(fn (array $client): array => ['label' => $client['label'], 'description' => $client['description']])->all();
    }

    public function label(string $clientType): string
    {
        return (string) config("mcp_clients.clients.{$clientType}.label", $clientType);
    }

    /**
     * @return array{label: string, file: string, language: string, snippet: string, steps: list<string>}
     */
    public function for(string $clientType, string $token): array
    {
        $client = config("mcp_clients.clients.{$clientType}") ?? config('mcp_clients.clients.other');

        return [
            'label' => $client['label'],
            'file' => $client['file'],
            'language' => $client['language'],
            'snippet' => strtr($client['snippet'], ['{server_url}' => $this->serverUrl(), '{token}' => $token, '{server_name}' => config('mcp_clients.server_name')]),
            'steps' => $client['steps'],
        ];
    }

    public function serverUrl(): string
    {
        return url('/mcp');
    }
}
