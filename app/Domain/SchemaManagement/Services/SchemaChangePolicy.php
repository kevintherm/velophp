<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\SchemaManagement\Enums\FieldType;
use InvalidArgumentException;

class SchemaChangePolicy
{
    /**
     * Determine if a type conversion is logically allowed using backfill/casting workflows.
     * Note: This does not mean it is done via `MODIFY COLUMN`. This is about logical safety.
     *
     * @throws InvalidArgumentException
     */
    public function assertTypeConversionAllowed(FieldType $fromType, FieldType $toType): void
    {
        if ($fromType === $toType) {
            throw new InvalidArgumentException("Cannot convert type to itself.");
        }

        // e.g. json to integer is forbidden
        if ($fromType === FieldType::Json && $toType !== FieldType::String) {
             throw new InvalidArgumentException("Cannot convert JSON to {$toType->value}.");
        }

        // Add more logical constraints as business requires
    }

    /**
     * Determine if a collection name is valid based on prefix rules.
     * 
     * @throws InvalidArgumentException
     */
    public function assertCollectionNameValid(string $collectionName): void
    {
        // Internal protected tables cannot be used directly via SchemaChanges
        if ($collectionName === '_velo_users_auth') {
            throw new InvalidArgumentException("The collection '_velo_users_auth' is protected and cannot be modified via user schema changes.");
        }

        // _ prefixes are reserved strictly for system internal tables (e.g. _superusers, _otps)
        // User collections MUST start with _velo_
        if (!str_starts_with($collectionName, '_velo_')) {
            if (str_starts_with($collectionName, '_')) {
                throw new InvalidArgumentException("Collection names starting with '_' are reserved for system tables.");
            }
            // For now, enforcing all collections to start with _velo_
            throw new InvalidArgumentException("User collections must start with the '_velo_' prefix.");
        }
    }
    
    // Add other assertion logic... (e.g., checking reserved words for names)
}
