<?php

namespace App\Exceptions\Domain;

use RuntimeException;
use Throwable;

/**
 * Base class for domain errors with a stable machine-readable code shared by
 * the web layer, the MCP server and the assistant.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message = '', public readonly array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $this->defaultMessage(), 0, $previous);
    }

    abstract public function errorCode(): string;

    abstract public function httpStatus(): int;

    protected function defaultMessage(): string
    {
        return __('erp.errors.'.strtolower($this->errorCode()));
    }

    /**
     * @return array{code: string, message: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode(),
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
