<?php

namespace App\Mcp\Resources;

use App\Services\Results\GradingScaleService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class GradingScaleResource extends Resource
{
    protected string $uri = 'erp://grading-scale';

    protected string $mimeType = 'application/json';

    protected string $name = 'grading-scale';

    protected string $title = 'Grading scale';

    protected string $description = 'Marks range to letter grade and grade point, plus the CGPA rules (best attempt counts, failed courses count as 0.00).';

    public function handle(Request $request): Response
    {
        return Response::json([
            'bands' => app(GradingScaleService::class)->bands()->map(fn ($band): array => ['min_mark' => $band->min_mark, 'max_mark' => $band->max_mark, 'letter' => $band->letter, 'grade_point' => $band->grade_point])->values()->all(),
            'retake_policy' => config('grading.retake_policy'),
            'failed_course_counts' => config('grading.failed_course_counts'),
            'display_decimals' => config('grading.decimals'),
        ]);
    }
}
