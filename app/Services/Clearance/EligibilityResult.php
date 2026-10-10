<?php

namespace App\Services\Clearance;

/**
 * @phpstan-type Reason array{code: string, message: string}
 */
final readonly class EligibilityResult
{
    /**
     * @param  list<array{code: string, message: string}>  $reasons
     */
    public function __construct(public array $reasons) {}

    public function eligible(): bool
    {
        return $this->reasons === [];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_column($this->reasons, 'code');
    }

    /**
     * @return array{eligible: bool, reasons: list<array{code: string, message: string}>}
     */
    public function toArray(): array
    {
        return ['eligible' => $this->eligible(), 'reasons' => $this->reasons];
    }
}
