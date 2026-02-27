<?php

namespace App\Domain\SchemaManagement\Models;

use App\Domain\SchemaManagement\Enums\SchemaChangeStatus;
use App\Domain\SchemaManagement\Enums\SchemaChangeType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $collection_id
 * @property SchemaChangeType $type
 * @property SchemaChangeStatus $status
 * @property array $payload
 * @property string|null $error
 */
class SchemaChange extends Model
{
    protected $table = 'schema_changes';

    protected $fillable = [
        'collection_id',
        'type',
        'status',
        'payload',
        'error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SchemaChangeType::class,
            'status' => SchemaChangeStatus::class,
            'payload' => 'array',
        ];
    }
}
