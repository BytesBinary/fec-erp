<?php

namespace App\Mcp\Registry;

use Illuminate\Validation\Rule;

/**
 * One input parameter of a tool: becomes the JSON schema property and the
 * Laravel validation rule at the same time.
 */
final class Param
{
    /**
     * @param  list<string>|null  $enum
     * @param  list<string>  $extraRules
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly bool $required,
        public readonly ?array $enum = null,
        public readonly mixed $example = null,
        public readonly array $extraRules = [],
        public readonly ?string $itemType = null,
    ) {}

    public static function string(string $name, string $description, bool $required = false, mixed $example = null): self
    {
        return new self($name, 'string', $description, $required, example: $example);
    }

    public static function integer(string $name, string $description, bool $required = false, mixed $example = null): self
    {
        return new self($name, 'integer', $description, $required, example: $example);
    }

    public static function number(string $name, string $description, bool $required = false, mixed $example = null): self
    {
        return new self($name, 'number', $description, $required, example: $example);
    }

    public static function boolean(string $name, string $description, bool $required = false, mixed $example = null): self
    {
        return new self($name, 'boolean', $description, $required, example: $example);
    }

    /**
     * @param  list<string>  $values
     */
    public static function enum(string $name, string $description, array $values, bool $required = false): self
    {
        return new self($name, 'string', $description, $required, $values);
    }

    public static function array(string $name, string $description, string $itemType = 'integer', bool $required = false, mixed $example = null): self
    {
        return new self($name, 'array', $description, $required, example: $example, itemType: $itemType);
    }

    public static function object(string $name, string $description, bool $required = false, mixed $example = null): self
    {
        return new self($name, 'object', $description, $required, example: $example);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $schema = ['type' => $this->type === 'object' ? 'object' : $this->type, 'description' => $this->description];

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->type === 'array') {
            $schema['items'] = ['type' => $this->itemType];
        }

        if ($this->example !== null) {
            $schema['examples'] = [$this->example];
        }

        return $schema;
    }

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        $rules[] = match ($this->type) {
            'integer' => 'integer',
            'number' => 'numeric',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'array',
            default => 'string',
        };

        if ($this->enum !== null) {
            $rules[] = Rule::in($this->enum);
        }

        return [...$rules, ...$this->extraRules];
    }
}
