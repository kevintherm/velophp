<?php

namespace App\Domain\Collections\Models;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\SchemaManagement\Application\Commands\RequestSchemaChange;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = ['type', 'name', 'description'];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'fields' => 'array',
            'api_rules' => 'array',
        ];
    }

    // Example integration method showing how the Collections Domain interacts with SchemaManagement:
    public function addField(string $fieldName, string $fieldType): void
    {
        // 1. Validate domain logic for Collection (e.g. max 50 fields per collection)
        
        // 2. Dispatch the requested intent to the SchemaManagement Bounded Context
        RequestSchemaChange::dispatch(
            $this->id,      // collection
            \App\Domain\SchemaManagement\Enums\SchemaChangeType::AddField,
            [
                'name' => $fieldName,
                'type' => $fieldType
            ]
        );
    }
}
