<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\ToolDefinition;
use App\Services\Notices\NoticeService;

final class NoticeTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $tools = ToolSupport::crud('notice', 'notice', NoticeService::class, 'notice', hint: 'audience is all, department or hall; department and hall notices need the matching scope.');

        foreach ($tools as $tool) {
            if ($tool->name === 'notice_list') {
                $tool->covers(NoticeService::class.'::query');
            }
        }

        return $tools;
    }
}
