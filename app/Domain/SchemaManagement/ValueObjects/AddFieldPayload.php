<?php

namespace App\Domain\SchemaManagement\ValueObjects;

use App\Domain\SchemaManagement\Enums\FieldType;

class AddFieldPayload extends SchemaChangePayload
{
    public function __construct(
        public readonly FieldName $name,
        public readonly FieldType $type
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'type' => $this->type->value,
        ];
    }
}
