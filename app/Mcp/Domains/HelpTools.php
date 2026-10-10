<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\User;
use App\Services\Assistant\FeatureIndex;

final class HelpTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        return [
            ToolDefinition::make('help_search_features', 'Find a feature', 'Search the application\'s screens and features by keywords ("create a course", "my results", "clearance desk"). Returns the screens the user may open, each with title, menu path, URL (deep link), required permission, keywords and how-to steps, plus matches the user cannot use together with the roles that can. Never invent a feature that this tool does not return.')
                ->params(Param::string('query', 'What the user wants to do or find, in their own words.', true, 'where do I create a course'), Param::integer('limit', 'Max accessible results, default 5.'))
                ->covers(FeatureIndex::class.'::search')
                ->handler(function (User $actor, array $args): array {
                    $result = app(FeatureIndex::class)->search($actor, $args['query'], min(10, (int) ($args['limit'] ?? 5)));

                    return ['summary' => count($result['features']).' feature(s) you can open, '.count($result['restricted']).' restricted.', 'data' => $result];
                }),
        ];
    }
}
