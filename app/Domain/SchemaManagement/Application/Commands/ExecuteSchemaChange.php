<?php

namespace App\Domain\SchemaManagement\Application\Commands;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSchemaChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $schemaChangeId
    ) {
    }

    public function handle(\App\Domain\SchemaManagement\Application\SchemaChangeApplicationService $service): void
    {
        $service->execute($this->schemaChangeId);
    }
}
