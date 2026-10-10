<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class PublishSemesterResults extends Prompt
{
    protected string $name = 'publish_semester_results';

    protected string $title = 'Publish semester results';

    protected string $description = 'Guided workflow to preview and publish a semester\'s approved results.';

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [new Argument('semester_code', 'Semester code, e.g. FA2025.', true)];
    }

    public function handle(Request $request): Response
    {
        $code = $request->get('semester_code', '<code>');

        return Response::text(<<<TEXT
            Publish the results of semester {$code}:
            1. Call semester_list to find the semester id for code {$code}.
            2. Call result_publish with confirm=false to get the dry-run preview (how many results and students).
            3. Show the preview to the user and ask for explicit confirmation.
            4. Only after a clear yes, call result_publish again with confirm=true.
            5. Report the number of published results.
            TEXT);
    }
}
