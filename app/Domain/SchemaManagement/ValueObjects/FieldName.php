<?php

namespace App\Domain\SchemaManagement\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

class FieldName
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = Str::snake(strtolower(trim($value)));

        if (empty($normalized)) {
            throw new InvalidArgumentException("Field name cannot be empty.");
        }

        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $normalized)) {
            throw new InvalidArgumentException("Invalid field name format. Must be a valid snake_case identifier.");
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
