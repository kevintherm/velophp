<?php

namespace App\Domain\Collections\Enums;

enum CollectionFieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Float = 'float';
    case Double = 'double';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Datetime = 'datetime';
    case Json = 'json';

}
