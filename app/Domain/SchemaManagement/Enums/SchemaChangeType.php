<?php

namespace App\Domain\SchemaManagement\Enums;

enum SchemaChangeType: string
{
    case AddField = 'ADD_FIELD';
    case RenameField = 'RENAME_FIELD';
    case ChangeFieldType = 'CHANGE_FIELD_TYPE';
}
