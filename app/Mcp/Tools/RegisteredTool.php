<?php

namespace App\Mcp\Tools;

use App\Enums\Channel;
use App\Mcp\Registry\ToolDefinition;
use App\Mcp\Registry\ToolExecutor;
use App\Models\McpIntegration;
use App\Models\User;
use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Adapter between a {@see ToolDefinition} and laravel/mcp. All behaviour
 * lives in {@see ToolExecutor}; this class only formats the result.
 */
class RegisteredTool extends Tool
{
    public function __construct(public readonly ToolDefinition $definition) {}

    public function name(): string
    {
        return $this->definition->name;
    }

    public function title(): string
    {
        return $this->definition->title;
    }

    public function description(): string
    {
        return $this->definition->description;
    }

    /**
     * `tools/list` only shows what the signed-in user may call.
     */
    public function shouldRegister(): bool
    {
        $user = Container::getInstance()->make('auth')->user();

        if (! $user instanceof User) {
            return false;
        }

        return app(ToolExecutor::class)->visibleTo($user, $this->definition, $this->integration());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->definition->name,
            'title' => $this->definition->title,
            'description' => $this->definition->description,
            'inputSchema' => $this->definition->inputSchema(),
            'outputSchema' => $this->definition->outputSchema(),
            'annotations' => $this->definition->annotations(),
        ];
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error(json_encode(['error' => ['code' => 'FORBIDDEN', 'message' => 'Not signed in.', 'context' => []]]));
        }

        $result = app(ToolExecutor::class)->execute($user, $this->definition, $request->all(), Channel::Mcp, $this->integration());

        if (! $result['ok']) {
            return Response::error(json_encode($result['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return Response::structured($result['payload']);
    }

    protected function integration(): ?McpIntegration
    {
        $integration = Container::getInstance()->bound('mcp.integration') ? Container::getInstance()->make('mcp.integration') : null;

        return $integration instanceof McpIntegration ? $integration : null;
    }
}
