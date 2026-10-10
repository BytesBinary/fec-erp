<?php

/*
|--------------------------------------------------------------------------
| Clearance reminders (spec §8.8)
|--------------------------------------------------------------------------
*/

return [

    /*
     | A request that has waited this many days at its current stage reminds
     | the stage's approvers (at most once per interval).
     */
    'remind_after_days' => (int) env('CLEARANCE_REMIND_AFTER_DAYS', 3),

    /*
     | After this many days the super admins are told as well.
     */
    'escalate_after_days' => (int) env('CLEARANCE_ESCALATE_AFTER_DAYS', 7),

];
