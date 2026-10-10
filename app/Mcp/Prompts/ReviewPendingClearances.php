<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class ReviewPendingClearances extends Prompt
{
    protected string $name = 'review_pending_clearances';

    protected string $title = 'Review pending clearances';

    protected string $description = 'Walk through the clearance requests waiting for the signed-in approver.';

    public function handle(Request $request): Response
    {
        return Response::text(<<<'TEXT'
            Review the clearance requests waiting for the user:
            1. Call clearance_list_pending_for_me.
            2. For each request call clearance_get and read the dues shown for the student.
            3. Recommend approve or reject for each, with the reason.
            4. Never approve or reject without the user's explicit instruction; use confirm=false first to show the preview.
            TEXT);
    }
}
