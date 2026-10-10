<?php

namespace App\Events\Portal;

use Illuminate\Support\Collection;

/**
 * New exams appeared on the portal's exam list.
 */
class ExamCatalogUpdated
{
    public function __construct(public Collection $exams) {}
}
