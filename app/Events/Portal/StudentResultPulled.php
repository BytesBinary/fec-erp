<?php

namespace App\Events\Portal;

use App\Models\ResultPull;

/**
 * A student's result was pulled and saved.
 */
class StudentResultPulled
{
    public function __construct(public ResultPull $pull) {}
}
