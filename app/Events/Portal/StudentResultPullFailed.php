<?php

namespace App\Events\Portal;

use App\Models\ResultPull;

/**
 * A student's pull failed for good.
 */
class StudentResultPullFailed
{
    public function __construct(public ResultPull $pull) {}
}
