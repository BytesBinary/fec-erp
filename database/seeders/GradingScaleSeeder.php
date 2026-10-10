<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use Illuminate\Database\Seeder;

/**
 * Seeds the grading scale from config('grading.scale') when the table is empty
 * (an admin-edited scale is never overwritten).
 */
class GradingScaleSeeder extends Seeder
{
    public function run(): void
    {
        if (GradingScale::query()->exists()) {
            return;
        }

        foreach (config('grading.scale') as $band) {
            GradingScale::query()->create([
                'min_mark' => $band['min'],
                'max_mark' => $band['max'],
                'letter' => $band['letter'],
                'grade_point' => $band['point'],
                'active_from' => '2000-01-01',
            ]);
        }
    }
}
