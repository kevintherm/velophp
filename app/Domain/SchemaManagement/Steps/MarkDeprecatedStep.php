<?php

namespace App\Domain\SchemaManagement\Steps;

class MarkDeprecatedStep implements SchemaChangeStep
{
    private $schemaChange;
    private $logicalName;

    public function __construct($schemaChange, $logicalName)
    {
        $this->schemaChange = $schemaChange;
        $this->logicalName = $logicalName;
    }

    public function getName(): string
    {
        return 'MarkDeprecatedStep';
    }

    public function execute(): void
    {
        // TODO: Call CollectionFieldRepository
        // CollectionFieldRepository::markDeprecated($this->schemaChange->collection_id, $this->logicalName);
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function toArray(): array
    {
        return [
            'logicalName' => $this->logicalName,
        ];
    }
}
