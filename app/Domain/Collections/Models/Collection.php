<?php

namespace App\Domain\Collections\Models;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Observers\CollectionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CollectionObserver::class)]
class Collection extends Model
{
    use HasUlids;

    protected $fillable = ['type', 'name', 'description', 'fields', 'api_rules', 'schema_updated_at'];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'fields' => 'array',
            'api_rules' => 'array',
            'schema_updated_at' => 'datetime',
        ];
    }

    /**
     * Get the physical database table name for this collection
     */
    public function getPhysicalTableName(): string
    {
        return self::formatTableName($this->name);
    }

    public static function formatTableName($collectionName): string
    {
        $prefix = config('velo.collection_prefix');
        return $prefix . $collectionName;
    }
}
