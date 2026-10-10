<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * A pull could not be completed. `transient` failures (portal down, timeout)
 * are retried by the queue; the others are final and shown as-is to staff.
 */
class ResultPortalException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $transient = false)
    {
        parent::__construct($message);
    }

    public static function unavailable(string $detail): self
    {
        return new self("The result portal could not be reached: {$detail}", true);
    }

    public static function layoutUnknown(): self
    {
        return new self('The portal answered, but its result page layout is not recognised yet. The parser needs a saved sample of a real result page (regular and improvement exam).');
    }
}
