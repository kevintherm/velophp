<?php

namespace App\Domain\SchemaManagement\Policies;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Infrastructure\Exceptions\InvalidArgumentException;

final class SchemaPolicy
{
    private const FORBIDDEN_COLUMNS = [
        'id',
        'created',
        'updated',
    ];

    public function assertValidTableName(string $table): void
    {
        if (! preg_match('/^[a-zA-Z_]+$/', $table)) {
            throw new InvalidArgumentException(
                "Invalid table name '{$table}'. Only letters and underscore allowed."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertValidColumnName(string $column): void
    {
        if (! preg_match('/^[a-zA-Z_]+$/', $column)) {
            throw new InvalidArgumentException(
                "Invalid column name '{$column}'. Only letters and underscore allowed."
            );
        }

        if (in_array($column, self::FORBIDDEN_COLUMNS, true)) {
            throw new InvalidArgumentException(
                "Column name '{$column}' is reserved and cannot be used."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertValidColumnDefinition(array $column): void
    {
        if (! isset($column['name'], $column['type'])) {
            throw new InvalidArgumentException('Column must have name and type');
        }

        $type = CollectionFieldType::tryFrom($column['type']);
        if ($type === null) {
            throw new InvalidArgumentException('Invalid column type: '.$column['type']);
        }

        $this->assertValidColumnName($column['name']);
    }
}
