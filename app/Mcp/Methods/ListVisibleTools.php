<?php

namespace App\Mcp\Methods;

use App\Mcp\Methods\Concerns\RevalidatesIntegration;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

/**
 * `tools/list`: only the tools the authenticated user (and integration
 * access level) may call. Same paging as the stock method.
 */
class ListVisibleTools implements Method
{
    use RevalidatesIntegration;

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $this->revalidateIntegration($request);

        $paginator = new CursorPaginator(
            items: $context->tools()->values(),
            perPage: $context->perPage($request->get('per_page') ?? 200),
            cursor: $request->cursor(),
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('tools'));
    }
}
