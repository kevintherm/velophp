<?php

namespace App\Domain\SchemaManagement\ValueObjects;

use App\Domain\SchemaManagement\Enums\FieldType;

class ChangeFieldTypePayload extends SchemaChangePayload
{
    public function __construct(
        public readonly FieldName $name,
        public readonly FieldType $fromType,
        public readonly FieldType $toType
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'fromType' => $this->fromType->value,
            'toType' => $this->toType->value,
        ];
    }
}
