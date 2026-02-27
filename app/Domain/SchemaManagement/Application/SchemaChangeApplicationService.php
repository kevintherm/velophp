<?php

namespace App\Domain\SchemaManagement\Application;

use App\Domain\SchemaManagement\Models\SchemaChange;
use App\Domain\SchemaManagement\Models\SchemaChangeStep;
use App\Domain\SchemaManagement\Services\SchemaWorkflowPlanner;
use App\Domain\SchemaManagement\Infrastructure\SchemaLock;
use App\Domain\SchemaManagement\Enums\SchemaChangeStatus;
use App\Domain\SchemaManagement\Enums\StepStatus;
use App\Domain\SchemaManagement\Enums\SchemaChangeType;
use RuntimeException;
use Throwable;

class SchemaChangeApplicationService
{
    public function __construct(
        private SchemaWorkflowPlanner $planner,
        private SchemaLock $schemaLock
    ) {}

    /**
     * Entry point for requesting a schema change. Called via RequestSchemaChange command/job.
     */
    public function request(int $collectionId, SchemaChangeType $type, array $payloadData): SchemaChange
    {
        // One per collection locking is enforced by DB constraints or explicit query here
        $activeCount = SchemaChange::where('collection_id', $collectionId)
            ->whereIn('status', [SchemaChangeStatus::Pending, SchemaChangeStatus::Running])
            ->count();

        if ($activeCount > 0) {
            throw new RuntimeException("Another schema change is already running or pending for this collection.");
        }

        $change = SchemaChange::create([
            'collection_id' => $collectionId,
            'type' => $type,
            'status' => SchemaChangeStatus::Pending,
            'payload' => $payloadData,
        ]);

        return $change;
    }

    /**
     * Actually runs the workflow in a locked context.
     */
    public function execute(int $schemaChangeId): void
    {
        $schemaChange = SchemaChange::findOrFail($schemaChangeId);
        
        if ($schemaChange->status === SchemaChangeStatus::Completed || $schemaChange->status === SchemaChangeStatus::Failed) {
            return;
        }

        $this->schemaLock->executeWithLock($schemaChange->collection_id, function () use ($schemaChange) {
            $schemaChange->update(['status' => SchemaChangeStatus::Running]);
            
            try {
                // 1. Plan or Load Steps
                $steps = $this->loadOrPlanSteps($schemaChange);

                // 2. Execute Sequentially
                foreach ($steps as $index => $stepModel) {
                    if ($stepModel->status === StepStatus::Done) {
                        continue;
                    }

                    $abstractStep = $this->hydrateStep($schemaChange, $stepModel);

                    try {
                        $abstractStep->execute();
                        $stepModel->update(['status' => StepStatus::Done]);
                    } catch (Throwable $e) {
                         $stepModel->update([
                             'status' => StepStatus::Failed,
                             'error_payload' => ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                         ]);
                         throw $e; // Fast fail the whole process. Resumability handles the retry later.
                    }
                }

                $schemaChange->update(['status' => SchemaChangeStatus::Completed]);
            } catch (Throwable $e) {
                // Determine if failure is transient vs structural. 
                // For simplicity, failing anything marks it FAILED. A robust system differentiates Backfill retries vs DDL crashes.
                $schemaChange->update([
                    'status' => SchemaChangeStatus::Failed,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * @return SchemaChangeStep[]
     */
    private function loadOrPlanSteps(SchemaChange $schemaChange)
    {
        $existingSteps = SchemaChangeStep::where('schema_change_id', $schemaChange->id)
            ->orderBy('id', 'asc')
            ->get();

        if ($existingSteps->isNotEmpty()) {
            return $existingSteps; // Resuming
        }

        $plannedWorkflow = $this->planner->plan($schemaChange);
        $stepModels = [];

        foreach ($plannedWorkflow as $plannedStep) {
            $stepModels[] = SchemaChangeStep::create([
                'schema_change_id' => $schemaChange->id,
                'step_name' => $plannedStep->getName(),
                'status' => StepStatus::Pending,
                // We'd store serialized array context here to re-hydrate properly on resume
                // 'error_payload' => $plannedStep->toArray()
            ]);
        }

        return $stepModels;
    }

    private function hydrateStep(SchemaChange $schemaChange, SchemaChangeStep $stepModel)
    {
         // In reality, this requires mapping from SchemaChangePayload data because we haven't serialized 
         // the exact step arguments to DB cleanly yet.
         // Let's rely on planner directly recreating it for this simple iteration:
         $plannedWorkflow = $this->planner->plan($schemaChange);
         
         // Match by index or step_name
         foreach($plannedWorkflow as $plannedStep) {
              if ($plannedStep->getName() === $stepModel->step_name) {
                  return $plannedStep;
              }
         }

         throw new RuntimeException("Could not hydrate step: {$stepModel->step_name}");
    }
}
