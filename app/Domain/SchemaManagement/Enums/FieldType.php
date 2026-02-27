<?php

namespace App\Domain\SchemaManagement\Enums;

enum FieldType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
}
