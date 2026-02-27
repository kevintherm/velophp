<?php

namespace Tests\Feature;

use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Models\SchemaChange;
use App\Domain\SchemaManagement\Models\SchemaChangeStep;
use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Domain\SchemaManagement\Application\Commands\RequestSchemaChange;
use App\Domain\SchemaManagement\Application\Commands\ExecuteSchemaChange;
use App\Domain\SchemaManagement\Application\SchemaChangeApplicationService;
use App\Domain\SchemaManagement\Infrastructure\SchemaLock;
use App\Domain\SchemaManagement\Infrastructure\SchemaDDLExecutor;
use Mockery;

uses(RefreshDatabase::class);

test('collection add_field creates pending schema change and steps', function () {
    Queue::fake();

    $collection = Collection::create([
        'name' => 'Users',
    ]);

    // Dispatch the intent
    $collection->addField('age', 'integer');
    
    // Process the intent manually as if handled by immediate job bus:
    $schemaChange = SchemaChange::where('collection_id', $collection->id)->first();
    
    expect($schemaChange)->not->toBeNull()
        ->and($schemaChange->type)->toBe(\App\Domain\SchemaManagement\Enums\SchemaChangeType::AddField)
        ->and($schemaChange->status)->toBe(\App\Domain\SchemaManagement\Enums\SchemaChangeStatus::Pending);

    // We can mock the physical executor because we don't want to actually alter physical tables in this pure unit
    $mockDDL = Mockery::mock(SchemaDDLExecutor::class);
    $mockDDL->shouldReceive('addColumn')->once()->andReturn(null);
    $this->app->instance(SchemaDDLExecutor::class, $mockDDL);

    // Mock lock so it doesn't fail on SQLite (GET_LOCK is MySQL specific)
    $mockLock = Mockery::mock(SchemaLock::class);
    $mockLock->shouldReceive('executeWithLock')->once()->andReturnUsing(function ($collectionId, $callback) {
        return $callback();
    });
    $this->app->instance(SchemaLock::class, $mockLock);

    // Call Application Service manually
    $service = $this->app->make(SchemaChangeApplicationService::class);
    $service->execute($schemaChange->id);

    // Check states
    $schemaChange->refresh();
    
    expect($schemaChange->status)->toBe(\App\Domain\SchemaManagement\Enums\SchemaChangeStatus::Completed);

    $steps = SchemaChangeStep::where('schema_change_id', $schemaChange->id)->get();
    expect($steps)->toHaveCount(2)
        ->and($steps[0]->step_name)->toBe('AddColumnStep')
        ->and($steps[0]->status)->toBe(\App\Domain\SchemaManagement\Enums\StepStatus::Done)
        ->and($steps[1]->step_name)->toBe('SwitchReadModelStep')
        ->and($steps[1]->status)->toBe(\App\Domain\SchemaManagement\Enums\StepStatus::Done);
});
