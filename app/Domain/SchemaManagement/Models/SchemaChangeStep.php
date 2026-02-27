<?php

namespace App\Domain\SchemaManagement\Models;

use App\Domain\SchemaManagement\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $schema_change_id
 * @property string $step_name
 * @property StepStatus $status
 * @property array|null $error_payload
 */
class SchemaChangeStep extends Model
{
    protected $table = 'schema_change_steps';

    protected $fillable = [
        'schema_change_id',
        'step_name', // e.g., 'AddColumnStep'
        'status', // PENDING, DONE, FAILED
        'error_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StepStatus::class,
            'error_payload' => 'array',
        ];
    }

    public function schemaChange(): BelongsTo
    {
        return $this->belongsTo(SchemaChange::class);
    }
}
