<?php

namespace App\Mcp\Registry;

use App\Mcp\Tools\RegisteredTool;
use Illuminate\Support\Collection;

/**
 * Every MCP tool, declared once. Domain classes in app/Mcp/Domains register
 * their tools here; {@see RegisteredTool} wraps each definition for the
 * server. The assistant (phase 6) reads the same registry.
 */
class ToolRegistry
{
    /**
     * Domain classes, each with a static `tools(): list<ToolDefinition>`.
     *
     * @var list<class-string>
     */
    public const DOMAINS = [
        \App\Mcp\Domains\MeTools::class,
        \App\Mcp\Domains\UserTools::class,
        \App\Mcp\Domains\AcademicTools::class,
        \App\Mcp\Domains\PeopleTools::class,
        \App\Mcp\Domains\EnrollmentTools::class,
        \App\Mcp\Domains\ResultTools::class,
        \App\Mcp\Domains\CampusTools::class,
        \App\Mcp\Domains\ClearanceTools::class,
        \App\Mcp\Domains\NoticeTools::class,
        \App\Mcp\Domains\AdminTools::class,
    ];

    /** @var Collection<string, ToolDefinition>|null */
    protected ?Collection $definitions = null;

    /**
     * @return Collection<string, ToolDefinition>
     */
    public function definitions(): Collection
    {
        if ($this->definitions === null) {
            $this->definitions = collect();

            foreach (self::DOMAINS as $domain) {
                foreach ($domain::tools() as $definition) {
                    $this->definitions->put($definition->name, $definition->domain($definition->domain ?: class_basename($domain)));
                }
            }

            foreach (config('mcp_access.extra_domains', []) as $domain) {
                foreach ($domain::tools() as $definition) {
                    $this->definitions->put($definition->name, $definition);
                }
            }
        }

        return $this->definitions;
    }

    public function find(string $name): ?ToolDefinition
    {
        return $this->definitions()->get($name);
    }

    /**
     * @return list<RegisteredTool>
     */
    public function tools(): array
    {
        return $this->definitions()->map(fn (ToolDefinition $definition): RegisteredTool => new RegisteredTool($definition))->values()->all();
    }

    public function tool(string $name): ?RegisteredTool
    {
        $definition = $this->find($name);

        return $definition === null ? null : new RegisteredTool($definition);
    }
}
