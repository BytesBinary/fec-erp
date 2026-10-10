<?php

namespace App\Events\Portal;

use App\Models\ResultPull;
use Illuminate\Support\Collection;

/**
 * A pull marked grades as retake / improved / declined.
 */
class StudentGradeChanged
{
    public function __construct(public ResultPull $pull, public Collection $changes) {}
}
