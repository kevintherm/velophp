<?php

namespace App\Domain\SchemaManagement\Enums;

enum SchemaOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Drop = 'drop';
}
