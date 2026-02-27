<?php

namespace App\Domain\SchemaManagement\Infrastructure;

use App\Domain\SchemaManagement\ValueObjects\FieldName;
use App\Domain\SchemaManagement\Enums\FieldType;
use Illuminate\Support\Facades\DB;

class SchemaDMLExecutor
{
    /**
     * Executes chunked DML updates for backfilling data.
     */
    public function backfillChunked(
        int $collectionId, 
        FieldName $fromName, 
        FieldName $toName, 
        FieldType $toType, 
        ?FieldType $fromType = null
    ): void {
        $tableName = $this->getPhysicalTableName($collectionId);

        // Simple batch processing
        // @TODO: In a real production system, you'd use chunkById to avoid huge locks and memory issues
        // and ideally persist the last processed ID in schema_change_steps table for true resumability
        DB::table($tableName)->orderBy('id')->chunkById(1000, function ($records) use ($tableName, $fromName, $toName, $toType) {
            foreach ($records as $record) {
                $oldValue = $record->{$fromName->value};
                $newValue = $this->castValue($oldValue, $toType);

                DB::table($tableName)
                    ->where('id', $record->id)
                    ->update([$toName->value => $newValue]);
            }
        });
    }

    private function castValue($value, FieldType $toType)
    {
        if ($value === null) {
            return null;
        }

        return match ($toType) {
            FieldType::String => (string) $value,
            FieldType::Integer => (int) $value,
            FieldType::Boolean => (bool) $value,
            FieldType::Json => is_string($value) ? $value : json_encode($value),
        };
    }

    private function getPhysicalTableName(int $collectionId): string
    {
        // Simple mock for now relying on Collection model fetching
        $collection = \App\Domain\Collections\Models\Collection::findOrFail($collectionId);
        return $collection->name;
    }
}
