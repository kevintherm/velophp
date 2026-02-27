<?php

namespace App\Domain\SchemaManagement\ValueObjects;

use App\Domain\SchemaManagement\Enums\FieldType;

class RenameFieldPayload extends SchemaChangePayload
{
    public function __construct(
        public readonly FieldName $from,
        public readonly FieldName $to,
        public readonly FieldType $type // Current type needs to be retained for back-filling
    ) {
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
            'type' => $this->type->value,
        ];
    }
}
