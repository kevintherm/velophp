<?php

namespace App\Domain\SchemaManagement\Steps;

class BackfillStep implements SchemaChangeStep
{
    private $schemaChange;
    private $fromName;
    private $toName;
    private $toType;
    private $fromType;

    public function __construct($schemaChange, $fromName, $toName, $toType, $fromType = null)
    {
        $this->schemaChange = $schemaChange;
        $this->fromName = $fromName;
        $this->toName = $toName;
        $this->toType = $toType;
        $this->fromType = $fromType;
    }

    public function getName(): string
    {
        return 'BackfillStep';
    }

    public function execute(): void
    {
        /** @var \App\Domain\SchemaManagement\Infrastructure\SchemaDMLExecutor $executor */
        $executor = app(\App\Domain\SchemaManagement\Infrastructure\SchemaDMLExecutor::class);
        $executor->backfillChunked($this->schemaChange->collection_id, $this->fromName, $this->toName, $this->toType, $this->fromType);
    }

    public function isIdempotent(): bool
    {
        // Chunked based on cursors, resumé logic handled inside DML Executor
        return true;
    }

    public function toArray(): array
    {
        return [
            'fromName' => $this->fromName,
            'toName' => $this->toName,
            'toType' => $this->toType,
            'fromType' => $this->fromType,
        ];
    }
}
