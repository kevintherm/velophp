<?php

namespace App\Domain\SchemaManagement\Steps;

use App\Domain\Collections\Models\Collection;

class SwitchReadModelStep implements SchemaChangeStep
{
    private $schemaChange;
    private $logicalName;
    private $physicalName;

    public function __construct($schemaChange, $logicalName, $physicalName)
    {
        $this->schemaChange = $schemaChange;
        $this->logicalName = $logicalName;
        $this->physicalName = $physicalName;
    }

    public function getName(): string
    {
        return 'SwitchReadModelStep';
    }

    public function execute(): void
    {
        $collection = Collection::findOrFail($this->schemaChange->collection_id);

        $fields = $collection->fields ?? [];
        
        $logicalName = $this->logicalName instanceof \App\Domain\SchemaManagement\ValueObjects\FieldName 
            ? $this->logicalName->value 
            : $this->logicalName;

        // Ensure the logical field exists or initialize it
        if (!isset($fields[$logicalName])) {
            $fields[$logicalName] = [
                'name' => $logicalName,
                // The type should ideally be passed in, but for switching read models during rename/type change, 
                // we are primarily just updating the physical_column reference.
            ];
        }

        $fields[$logicalName]['physical_column'] = $this->physicalName;

        $collection->fields = $fields;
        $collection->save();
    }

    public function isIdempotent(): bool
    {
        // Updating metadata to a specific value is idempotent
        return true;
    }

    public function toArray(): array
    {
        return [
            'logicalName' => $this->logicalName instanceof \App\Domain\SchemaManagement\ValueObjects\FieldName ? $this->logicalName->value : $this->logicalName,
            'physicalName' => $this->physicalName,
        ];
    }
}
