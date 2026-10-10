<?php

namespace App\Support\Grading;

/**
 * Result of a GPA / CGPA computation at full precision. `display` is the
 * value rounded half up to the configured decimals (default 2).
 */
final readonly class GpaSummary
{
    public function __construct(
        public ?float $gpa,
        public float $countedCredits,
        public float $earnedCredits,
        public float $qualityPoints,
    ) {}

    public function display(): string
    {
        return GpaCalculator::format($this->gpa);
    }
}
