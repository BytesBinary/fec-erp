<?php

namespace App\Mcp\Resources;

use App\Models\Semester;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class AcademicCalendarResource extends Resource
{
    protected string $uri = 'erp://academic-calendar';

    protected string $mimeType = 'application/json';

    protected string $name = 'academic-calendar';

    protected string $title = 'Academic calendar';

    protected string $description = 'All semesters with their dates; the active semester is flagged.';

    public function handle(Request $request): Response
    {
        return Response::json(Semester::query()->orderBy('starts_on')->get(['id', 'name', 'code', 'starts_on', 'ends_on', 'is_active'])->toArray());
    }
}
