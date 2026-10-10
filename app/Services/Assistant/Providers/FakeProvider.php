<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\Contracts\AssistantProvider;
use App\Services\Assistant\ProviderReply;

/**
 * Deterministic provider for tests (spec §10.5): no network, no LLM. Tests may
 * queue exact replies with {@see self::script()}; otherwise a tiny rule engine
 * answers the questions the tests ask ("where do I create a course?", "what
 * is my CGPA?", "create a department called X code Y").
 */
class FakeProvider implements AssistantProvider
{
    /** @var list<ProviderReply> */
    protected static array $script = [];

    /** @var list<array{system: string, messages: list<array<string, mixed>>, tools: list<array<string, mixed>>}> */
    public static array $calls = [];

    public static bool $unavailable = false;

    /**
     * @param  list<ProviderReply>  $replies
     */
    public static function script(array $replies): void
    {
        self::$script = $replies;
    }

    public static function reset(): void
    {
        self::$script = [];
        self::$calls = [];
        self::$unavailable = false;
    }

    public function available(): bool
    {
        return ! self::$unavailable;
    }

    public function respond(string $system, array $messages, array $tools): ProviderReply
    {
        if (self::$unavailable) {
            throw new \App\Exceptions\Assistant\ProviderUnavailableException('The fake provider is switched off.');
        }

        self::$calls[] = ['system' => $system, 'messages' => $messages, 'tools' => $tools];

        if (self::$script !== []) {
            return array_shift(self::$script);
        }

        $last = $messages[array_key_last($messages)];
        $content = $last['content'];

        if (is_array($content) && ($content[0]['type'] ?? null) === 'tool_result') {
            return $this->afterTool($content[0]);
        }

        return $this->forQuestion(is_string($content) ? $content : '');
    }

    protected function forQuestion(string $text): ProviderReply
    {
        if (preg_match('/create a department called (\w+) code (\w+)/i', $text, $match)) {
            return new ProviderReply('', [['id' => 'toolu_fake_1', 'name' => 'department_create', 'input' => ['name' => $match[1], 'code' => $match[2]]]]);
        }

        if (preg_match('/archive course (\d+)/i', $text, $match)) {
            return new ProviderReply('', [['id' => 'toolu_fake_1', 'name' => 'course_archive', 'input' => ['id' => (int) $match[1]]]]);
        }

        if (preg_match('/cgpa|gpa/i', $text)) {
            return new ProviderReply('', [['id' => 'toolu_fake_1', 'name' => 'result_get_cgpa', 'input' => []]]);
        }

        if (preg_match('/\b(where|how do i|how can i|find|open|go to|menu)\b/i', $text)) {
            return new ProviderReply('', [['id' => 'toolu_fake_1', 'name' => 'help_search_features', 'input' => ['query' => $text]]]);
        }

        return new ProviderReply('I can find screens for you, answer questions about your own data and prepare changes for you to confirm. What would you like to do?');
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function afterTool(array $block): ProviderReply
    {
        $content = is_string($block['content'] ?? null) ? $block['content'] : json_encode($block['content'] ?? '');
        $data = json_decode(substr($content, (int) strpos($content, '{')), true) ?? [];
        $payload = $data['data'] ?? $data;

        if (isset($payload['features']) || isset($payload['restricted'])) {
            if (($payload['features'] ?? []) !== []) {
                $top = $payload['features'][0];

                return new ProviderReply("You can find it under {$top['menuPath']}. Use the link below to open it.");
            }

            if (($payload['restricted'] ?? []) !== []) {
                $roles = implode(', ', $payload['restricted'][0]['roles'] ?? []);

                return new ProviderReply("That is not available to your role. It can be done by: {$roles}.");
            }

            return new ProviderReply('I could not find a matching feature.');
        }

        if (isset($payload['cgpa_display'])) {
            return new ProviderReply("Your CGPA is {$payload['cgpa_display']}.");
        }

        if (($data['error']['code'] ?? null) === 'FORBIDDEN') {
            return new ProviderReply('That is not permitted for your role.');
        }

        return new ProviderReply($data['summary'] ?? 'Done.');
    }
}
