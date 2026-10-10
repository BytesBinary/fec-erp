<?php

namespace Tests\Mcp;

use Illuminate\Testing\TestResponse;

/**
 * A minimal MCP client speaking JSON-RPC over the real Streamable HTTP route
 * (`POST /mcp`) with a bearer integration token — the same path a real client uses.
 */
class McpClient
{
    protected int $id = 0;

    public function __construct(protected object $test, public ?string $token) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function request(string $method, array $params = []): TestResponse
    {
        $headers = ['Accept' => 'application/json, text/event-stream'];

        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        return $this->test->flushSession()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => $method, 'params' => (object) $params], $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function rpc(string $method, array $params = []): array
    {
        return $this->request($method, $params)->json();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tools(): array
    {
        return $this->rpc('tools/list')['result']['tools'];
    }

    /**
     * @return list<string>
     */
    public function toolNames(): array
    {
        $names = array_column($this->tools(), 'name');
        sort($names);

        return $names;
    }

    /**
     * Calls a tool and returns `[isError, payload]` where payload is the
     * structured result or the decoded error.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{isError: bool, payload: array<string, mixed>, raw: array<string, mixed>}
     */
    public function call(string $tool, array $arguments = []): array
    {
        $response = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
        $result = $response['result'] ?? [];
        $isError = (bool) ($result['isError'] ?? true);
        $payload = $result['structuredContent'] ?? json_decode($result['content'][0]['text'] ?? '[]', true) ?? [];

        return ['isError' => $isError, 'payload' => $payload, 'raw' => $response];
    }

    public function errorCode(string $tool, array $arguments = []): ?string
    {
        $result = $this->call($tool, $arguments);

        return $result['isError'] ? ($result['payload']['error']['code'] ?? null) : null;
    }
}
