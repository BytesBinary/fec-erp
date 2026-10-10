<?php

namespace App\Mcp\Methods;

use App\Mcp\Methods\Concerns\RevalidatesIntegration;
use App\Mcp\Registry\ToolRegistry;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

/**
 * `tools/call`: resolves the tool from the registry, NOT from the filtered
 * list, so a guessed name of a tool the user may not call reaches the
 * executor and gets `FORBIDDEN` instead of "not found" (spec §10.4).
 */
class CallRegisteredTool implements Errable, Method
{
    use InteractsWithResponses;
    use RevalidatesIntegration;

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $this->revalidateIntegration($request);

        if (is_null($request->get('name'))) {
            throw new JsonRpcException('Missing [name] parameter.', -32602, $request->id);
        }

        $tool = app(ToolRegistry::class)->tool((string) $request->params['name'])
            ?? throw new JsonRpcException("Tool [{$request->params['name']}] not found.", -32602, $request->id);

        try {
            $response = Container::getInstance()->call([$tool, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * @return callable(ResponseFactory): array<string, mixed>
     */
    protected function serializable(Tool $tool): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
                'isError' => $factory->responses()->contains(fn (Response $response): bool => $response->isError()),
            ])
        );
    }
}
