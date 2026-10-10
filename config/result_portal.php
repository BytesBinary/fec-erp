<?php

/*
| Official result pulls from the university exam portal (cmc.du.ac.bd).
| A pull runs on the queue whenever a student is added (panel or MCP) and on
| demand from the "Student results" screen. Never enumerates registration
| numbers: it only ever looks up students that exist in this ERP.
*/

return [

    'enabled' => (bool) env('RESULT_PORTAL_ENABLED', true),

    'driver' => env('RESULT_PORTAL_DRIVER', 'du'),

    'base_url' => env('RESULT_PORTAL_URL', 'https://cmc.du.ac.bd'),

    'user_agent' => env('RESULT_PORTAL_USER_AGENT', 'FEC-ERP result sync (college administration)'),

    'queue' => env('RESULT_PORTAL_QUEUE', 'default'),

    'timeout_seconds' => 20,

    /*
     | Pause between two portal requests of one pull (be gentle with the
     | university server).
     */
    'request_delay_ms' => (int) env('RESULT_PORTAL_DELAY_MS', 2000),

    'tries' => 3,

    'backoff_seconds' => [60, 300],

    /*
     | What a later attempt (retake / improvement exam) does to the grade a
     | student already has: `always` replaces it (and marks it improved /
     | retake / declined), `better` only replaces it when the new grade point
     | is higher (a lower one is stored but not counted).
     */
    'replace_policy' => env('RESULT_PORTAL_REPLACE_POLICY', 'always'),

    /*
     | Daily publication check. While `shadow_mode` is on, a confirmed
     | publication is only recorded: no student pulls are queued and no
     | emails sent until an admin presses "Run now" on the Portal monitor.
     */
    'shadow_mode' => (bool) env('RESULT_PORTAL_SHADOW', true),

    'check_time' => env('RESULT_PORTAL_CHECK_TIME', '06:00'),

    'probes_per_exam' => 2,

    /*
     | An exam the portal lists but nobody can see yet is re-checked daily for
     | this many days after it was detected, then weekly.
     */
    'awaiting_daily_days' => 14,

    /*
     | A student with no result after a confirmed publication is re-checked
     | after each of these many days, then closed as "not in this exam".
     */
    'pending_recheck_days' => [3, 7],

    /*
     | Which exam years a student is checked against: from the admission year
     | for the programme length plus extra years for retakes, never beyond
     | the current year (2022 → 2022..2026; 2000 → 2000..2006).
     */
    'window' => [
        'program_years' => 4,
        'extra_years' => 2,
    ],

    /*
     | Department code → program id on the portal's result form.
     */
    'department_programs' => [
        'CSE' => 14,
        'EEE' => 13,
        'CE' => 12,
    ],

];
