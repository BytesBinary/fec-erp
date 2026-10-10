<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\ChatRequest;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The assistant's server-side endpoints (spec §5.2). The model API key never
 * reaches the browser; the answer is streamed as server-sent events.
 */
class ChatController extends Controller
{
    public function chat(ChatRequest $request, AssistantService $assistant): StreamedResponse|Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $message = (string) $request->validated('message');
        $context = (array) ($request->validated('context') ?? []);

        if (! config('assistant.stream')) {
            $body = '';

            foreach ($assistant->converse($user, $message, $context) as $event) {
                $body .= 'event: '.$event['type']."\ndata: ".json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
            }

            return response($body."event: end\ndata: {}\n\n", 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
        }

        return response()->eventStream(function () use ($assistant, $user, $message, $context) {
            foreach ($assistant->converse($user, $message, $context) as $event) {
                yield new StreamedEvent(event: (string) $event['type'], data: $event);
            }
        }, endStreamWith: new StreamedEvent(event: 'end', data: '{}'));
    }

    public function history(Request $request, AssistantService $assistant): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json(['messages' => $assistant->history($user)->map(fn (AssistantMessage $message): array => [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'status' => $message->tool_calls['status'] ?? null,
            'preview' => $message->tool_calls['preview'] ?? null,
            'created_at' => $message->created_at?->toIso8601String(),
        ])->values()]);
    }

    public function clear(Request $request, AssistantService $assistant): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $assistant->clearHistory($user);

        return response()->json(['cleared' => true]);
    }

    public function confirm(Request $request, AssistantService $assistant, int $action): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $result = $assistant->confirm($user, $action);

        return response()->json(['ok' => $result['ok'], 'summary' => $result['payload']['summary'] ?? '', 'error' => $result['payload']['error'] ?? null], $result['ok'] ? 200 : 422);
    }

    public function cancel(Request $request, AssistantService $assistant, int $action): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $assistant->cancel($user, $action);

        return response()->json(['cancelled' => true]);
    }
}
