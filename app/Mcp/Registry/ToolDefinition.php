<?php

namespace App\Mcp\Registry;

use Closure;

/**
 * The single declaration of an MCP tool (spec §4.1): name, LLM-oriented
 * description, strict input schema, required permission, annotations and the
 * handler that calls a domain service. The handler returns
 * `['summary' => string, 'data' => mixed]`.
 */
final class ToolDefinition
{
    /** @var list<Param> */
    public array $params = [];

    public ?string $permission = null;

    public bool $readOnly = true;

    public bool $destructive = false;

    public bool $idempotent = false;

    public bool $acceptsIdempotencyKey = false;

    public ?Closure $preview = null;

    /** @var list<string> */
    public array $covers = [];

    public ?Closure $handler = null;

    public string $domain = '';

    private function __construct(public string $name, public string $title, public string $description) {}

    public static function make(string $name, string $title, string $description): self
    {
        return new self($name, $title, $description);
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function params(Param ...$params): self
    {
        $this->params = [...$this->params, ...$params];

        return $this;
    }

    /**
     * A tool that changes data.
     */
    public function write(bool $idempotent = false): self
    {
        $this->readOnly = false;
        $this->idempotent = $idempotent;

        return $this;
    }

    /**
     * A create tool: accepts the optional `idempotencyKey` so a retry never
     * creates twice.
     */
    public function creates(): self
    {
        $this->readOnly = false;
        $this->acceptsIdempotencyKey = true;
        $this->params[] = Param::string('idempotencyKey', 'Optional unique key (max 120 chars). Repeating the same call with the same key returns the first result instead of creating again.');

        return $this;
    }

    /**
     * A destructive or bulk tool: needs `confirm: true`; without it the tool
     * only returns a dry-run preview and changes nothing.
     *
     * @param  (Closure(\App\Models\User, array<string, mixed>): array<string, mixed>)|null  $preview
     */
    public function destructive(?Closure $preview = null): self
    {
        $this->readOnly = false;
        $this->destructive = true;
        $this->preview = $preview;
        $this->params[] = Param::boolean('confirm', 'Must be true to actually perform the change. When false or missing, the tool only returns a preview of what would change.');

        return $this;
    }

    /**
     * @param  Closure(\App\Models\User, array<string, mixed>): array{summary: string, data?: mixed, entity?: array{0: string, 1: int|string|null}}  $handler
     */
    public function handler(Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Service methods ("Class::method") this tool exposes, for the MCP parity test.
     */
    public function covers(string ...$methods): self
    {
        $this->covers = [...$this->covers, ...$methods];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->params as $param) {
            $properties[$param->name] = $param->toSchema();

            if ($param->required) {
                $required[] = $param->name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties === [] ? (object) [] : $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string', 'description' => 'Short human-readable result.'],
                'data' => ['description' => 'The structured result.'],
                'dry_run' => ['type' => 'boolean', 'description' => 'True when nothing was changed because confirm was not true.'],
                'confirm_required' => ['type' => 'boolean'],
                'replayed' => ['type' => 'boolean', 'description' => 'True when an earlier result for the same idempotencyKey was returned.'],
            ],
            'required' => ['summary'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => $this->readOnly,
            'destructiveHint' => $this->destructive,
            'idempotentHint' => $this->readOnly || $this->idempotent,
            'openWorldHint' => false,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->params as $param) {
            $rules[$param->name] = $param->rules();
        }

        return $rules;
    }
}
