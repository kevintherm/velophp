<?php

namespace App\Domain\SchemaManagement\Infrastructure;

use App\Domain\SchemaManagement\ValueObjects\FieldName;
use App\Domain\SchemaManagement\Enums\FieldType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

class SchemaDDLExecutor
{
    /**
     * Executes raw physical ALTER TABLE to add a column.
     * We prefer ALGORITHM=INSTANT to avoid table locks.
     */
    public function addColumn(int $collectionId, FieldName $columnName, FieldType $columnType): void
    {
        $tableName = $this->getPhysicalTableName($collectionId);

        if (!$this->tableExists($tableName)) {
             throw new InvalidArgumentException("Table {$tableName} does not exist.");
        }

        if ($this->columnExists($tableName, $columnName->value)) {
             // Idempotency: Column already exists, safe to return
             return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $columnType) {
            // Map logical FieldType to Laravel Blueprint types
            $column = match ($columnType) {
                FieldType::String => $table->string($columnName->value),
                FieldType::Integer => $table->integer($columnName->value),
                FieldType::Boolean => $table->boolean($columnName->value),
                FieldType::Json => $table->json($columnName->value),
            };

            $column->nullable(); // Always nullable, backfill data later
        });

        // Note: Laravel handles ALGORITHM=INSTANT well enough on MySQL 8 for adding nullable columns at the end, 
        // but we can execute raw DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName->value}` ... ALGORITHM=INSTANT") if needed strictly.
    }

    private function getPhysicalTableName(int $collectionId): string
    {
        // Simple mock for now relying on Collection model fetching
        $collection = \App\Domain\Collections\Models\Collection::findOrFail($collectionId);
        return $collection->name;
    }
    
    private function tableExists(string $tableName): bool
    {
        return Schema::hasTable($tableName);
    }
    
    private function columnExists(string $tableName, string $columnName): bool
    {
        return Schema::hasColumn($tableName, $columnName);
    }
}
