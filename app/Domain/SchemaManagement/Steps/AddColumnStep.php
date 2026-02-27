<?php

namespace App\Domain\SchemaManagement\Steps;

class AddColumnStep implements SchemaChangeStep
{
    private $schemaChange;
    private $columnName;
    private $columnType;

    public function __construct($schemaChange, $columnName, $columnType)
    {
        $this->schemaChange = $schemaChange;
        $this->columnName = $columnName;
        $this->columnType = $columnType;
    }

    public function getName(): string
    {
        return 'AddColumnStep';
    }

    public function execute(): void
    {
        /** @var \App\Domain\SchemaManagement\Infrastructure\SchemaDDLExecutor $executor */
        $executor = app(\App\Domain\SchemaManagement\Infrastructure\SchemaDDLExecutor::class);
        $executor->addColumn(
            $this->schemaChange->collection_id,
            $this->columnName,
            $this->columnType
        );
    }

    public function isIdempotent(): bool
    {
        // If the column exists in information_schema, it's safe to skip
        return true; 
    }

    public function toArray(): array
    {
        return [
            'columnName' => $this->columnName,
            'columnType' => $this->columnType,
        ];
    }
}
