<?php

namespace App\Domain\SchemaManagement\Enums;

enum StepStatus: string
{
    case Pending = 'PENDING';
    case Done = 'DONE';
    case Failed = 'FAILED';
}
