<?php

/*
|--------------------------------------------------------------------------
| Grading and CGPA rules
|--------------------------------------------------------------------------
|
| Spec §7. `scale` is only the seed for the `grading_scales` table; the
| table is the live source (editable by super admin). Defaults follow the
| Bangladesh UGC uniform scale (docs/DECISIONS.md D-006).
|
*/

return [

    'scale' => [
        ['min' => 80, 'max' => 100, 'letter' => 'A+', 'point' => 4.00],
        ['min' => 75, 'max' => 79.99, 'letter' => 'A', 'point' => 3.75],
        ['min' => 70, 'max' => 74.99, 'letter' => 'A-', 'point' => 3.50],
        ['min' => 65, 'max' => 69.99, 'letter' => 'B+', 'point' => 3.25],
        ['min' => 60, 'max' => 64.99, 'letter' => 'B', 'point' => 3.00],
        ['min' => 55, 'max' => 59.99, 'letter' => 'B-', 'point' => 2.75],
        ['min' => 50, 'max' => 54.99, 'letter' => 'C+', 'point' => 2.50],
        ['min' => 45, 'max' => 49.99, 'letter' => 'C', 'point' => 2.25],
        ['min' => 40, 'max' => 44.99, 'letter' => 'D', 'point' => 2.00],
        ['min' => 0, 'max' => 39.99, 'letter' => 'F', 'point' => 0.00],
    ],

    /*
     | Which attempt of a repeated course counts toward the CGPA:
     | `best` (highest grade point, later attempt wins ties) or `latest`.
     */
    'retake_policy' => env('GRADING_RETAKE_POLICY', 'best'),

    /*
     | A course with no passing attempt counts with 0.00 and its credits.
     | Set to false to leave such courses out of the CGPA entirely.
     */
    'failed_course_counts' => (bool) env('GRADING_FAILED_COUNTS', true),

    /*
     | A grade point above this value is a pass.
     */
    'pass_above' => 0.0,

    'decimals' => 2,

];
