<?php

namespace App\Domain\SchemaManagement\ValueObjects;

use App\Domain\SchemaManagement\Enums\FieldType;
use App\Domain\SchemaManagement\Enums\SchemaChangeType;

abstract class SchemaChangePayload
{
    abstract public function toArray(): array;

    public static function fromArray(SchemaChangeType $type, array $payload): self
    {
        return match ($type) {
            SchemaChangeType::AddField => new AddFieldPayload(
                new FieldName($payload['name']),
                FieldType::from($payload['type'])
            ),
            SchemaChangeType::RenameField => new RenameFieldPayload(
                new FieldName($payload['from']),
                new FieldName($payload['to']),
                FieldType::from($payload['type'])
            ),
            SchemaChangeType::ChangeFieldType => new ChangeFieldTypePayload(
                new FieldName($payload['name']),
                FieldType::from($payload['fromType']),
                FieldType::from($payload['toType'])
            ),
        };
    }
}
