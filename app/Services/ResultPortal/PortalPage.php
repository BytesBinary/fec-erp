<?php

namespace App\Services\ResultPortal;

/**
 * What one portal answer says: found (with the header, subjects and outcome)
 * or the fixed "not verified" message.
 */
final readonly class PortalPage
{
    /**
     * @param  array<string, string>  $meta  label → value of the header table
     * @param  list<array{code: string, title: string, letter: ?string, point: ?float}>  $subjects
     * @param  list<string>  $backlog
     */
    public function __construct(
        public PortalPageStatus $status,
        public array $meta = [],
        public array $subjects = [],
        public ?string $outcome = null,
        public ?float $gpa = null,
        public ?float $cgpa = null,
        public array $backlog = [],
    ) {}

    public function registration(): ?string
    {
        return $this->meta['Registration'] ?? null;
    }
}
