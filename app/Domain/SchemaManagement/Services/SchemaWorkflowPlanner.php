<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\SchemaManagement\Models\SchemaChange;
use App\Domain\SchemaManagement\ValueObjects\SchemaChangePayload;
use App\Domain\SchemaManagement\ValueObjects\AddFieldPayload;
use App\Domain\SchemaManagement\ValueObjects\RenameFieldPayload;
use App\Domain\SchemaManagement\ValueObjects\ChangeFieldTypePayload;
use App\Domain\SchemaManagement\Enums\SchemaChangeType;
use App\Domain\SchemaManagement\Steps\SchemaChangeStep;
use App\Domain\SchemaManagement\Steps\AddColumnStep;
use App\Domain\SchemaManagement\Steps\BackfillStep;
use App\Domain\SchemaManagement\Steps\SwitchReadModelStep;
use App\Domain\SchemaManagement\Steps\MarkDeprecatedStep;
use InvalidArgumentException;

class SchemaWorkflowPlanner
{
    /**
     * @return SchemaChangeStep[] Ordered list of instantiated steps
     */
    public function plan(SchemaChange $schemaChange): array
    {
        $payloadObj = SchemaChangePayload::fromArray($schemaChange->type, $schemaChange->payload);

        return match ($schemaChange->type) {
            SchemaChangeType::AddField => $this->planAddField($schemaChange, $payloadObj),
            SchemaChangeType::RenameField => $this->planRenameField($schemaChange, $payloadObj),
            SchemaChangeType::ChangeFieldType => $this->planChangeFieldType($schemaChange, $payloadObj),
        };
    }

    /**
     * @param AddFieldPayload $payload
     * @return SchemaChangeStep[]
     */
    private function planAddField(SchemaChange $schemaChange, AddFieldPayload $payload): array
    {
        return [
            new AddColumnStep($schemaChange, $payload->name, $payload->type),
            // For simple Add Field, updating logical metadata to say "it exists" happens implicitly or explicitly
            new SwitchReadModelStep($schemaChange, $payload->name, $payload->name),
        ];
    }

    /**
     * @param RenameFieldPayload $payload
     * @return SchemaChangeStep[]
     */
    private function planRenameField(SchemaChange $schemaChange, RenameFieldPayload $payload): array
    {
        return [
            new AddColumnStep($schemaChange, $payload->to, $payload->type),
            new BackfillStep($schemaChange, $payload->from, $payload->to, $payload->type),
            new SwitchReadModelStep($schemaChange, $payload->from, $payload->to),
            new MarkDeprecatedStep($schemaChange, $payload->from),
        ];
    }

    /**
     * @param ChangeFieldTypePayload $payload
     * @return SchemaChangeStep[]
     */
    private function planChangeFieldType(SchemaChange $schemaChange, ChangeFieldTypePayload $payload): array
    {
        // For type changes, we create a new column with a physical suffix because we can't rename
        // the logical field. For example: `age` becomes physically `age_int`.
        $newPhysicalName = new \App\Domain\SchemaManagement\ValueObjects\FieldName($payload->name->value . '_' . $payload->toType->value);
        
        return [
            new AddColumnStep($schemaChange, $newPhysicalName, $payload->toType),
            new BackfillStep($schemaChange, $payload->name, $newPhysicalName, $payload->toType, $payload->fromType),
            new SwitchReadModelStep($schemaChange, $payload->name, $newPhysicalName),
            // The old physical column associated with the logical $payload->name is now deprecated
            new MarkDeprecatedStep($schemaChange, $payload->name),
        ];
    }
}
