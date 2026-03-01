<?php

namespace App\Domain\Collections\Observers;

use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Domain\SchemaManagement\Services\SchemaDDLService;
use App\Infrastructure\Exceptions\InvalidArgumentException;

readonly class CollectionObserver
{
    public function __construct(
        private SchemaDDLService $ddlService
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function creating(Collection $collection): void
    {
        $this->startJob($collection, SchemaOperation::Create);
        $this->ddlService->createTable($collection->getPhysicalTableName(), $collection->fields);
        $collection->schema_updated_at = now();
    }

    public function created(Collection $collection): void
    {
        $this->endJob($collection);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updating(Collection $collection): void
    {
        if ($collection->isDirty('type')) {
            throw new InvalidArgumentException('Collection type cannot be changed');
        }

        $this->startJob($collection, SchemaOperation::Update);

        if ($collection->isDirty('name')) {
            $oldTableName = Collection::formatTableName($collection->getOriginal('name'));
            $this->ddlService->renameTable($oldTableName, $collection->getPhysicalTableName());
            $collection->schema_updated_at = now();
        }

        if ($collection->isDirty('fields')) {
            $originalFields = $collection->getOriginal('fields');
            $newFields = $collection->fields;

            $this->ddlService->updateTable(
                $collection->getPhysicalTableName(),
                $originalFields,
                $newFields
            );
        }

        $collection->schema_updated_at = now();
    }

    public function updated(Collection $collection): void
    {
        $this->endJob($collection);
    }

    public function deleting(Collection $collection): void
    {
        $this->startJob($collection, SchemaOperation::Drop);

        $this->ddlService->deleteTable($collection->getPhysicalTableName());
    }

    public function deleted(Collection $collection): void
    {
        $this->endJob($collection);
    }

    private function startJob(Collection $collection, SchemaOperation $op): void
    {
        SchemaJob::create([
            'collection_id' => $collection->id,
            'table_name' => $collection->getPhysicalTableName(),
            'operation' => $op,
            'started_at' => now(),
        ]);
    }

    private function endJob(Collection $collection): void
    {
        SchemaJob::where('table_name', $collection->getPhysicalTableName())->delete();
    }
}
