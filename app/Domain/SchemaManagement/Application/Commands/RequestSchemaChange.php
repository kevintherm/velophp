<?php

namespace App\Domain\SchemaManagement\Application\Commands;

use App\Domain\SchemaManagement\ValueObjects\SchemaChangePayload;
use App\Domain\SchemaManagement\Enums\SchemaChangeType;
use Illuminate\Foundation\Bus\Dispatchable;

class RequestSchemaChange
{
    use Dispatchable;

    public function __construct(
        public readonly int $collectionId,
        public readonly SchemaChangeType $type,
        public readonly array $payloadData
    ) {
        // We validate that it can be built
        SchemaChangePayload::fromArray($this->type, $this->payloadData);
    }

    public function handle(\App\Domain\SchemaManagement\Application\SchemaChangeApplicationService $service)
    {
        return $service->request($this->collectionId, $this->type, $this->payloadData);
    }
}
