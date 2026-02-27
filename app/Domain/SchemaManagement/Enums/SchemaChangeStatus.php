<?php

namespace App\Domain\SchemaManagement\Enums;

enum SchemaChangeStatus: string
{
    case Pending = 'PENDING';
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
