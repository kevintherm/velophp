<?php

namespace Veloquent\Core\Domain\Records\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Veloquent\Core\Support\Database\SchemaCache;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Records\Resources\RecordResource;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName;
use Veloquent\Core\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Veloquent\Core\Domain\QueryCompiler\Exceptions\UnsupportedQueryFeatureException;

class RecordExpansionService
{
    public function expandMany(Collection $sourceCollection, array $records, ?string $expand): void
    {
        $relationFields = $this->parse($sourceCollection, $expand);

        if ($relationFields === []) {
            return;
        }

        $recordIds = collect($records)->map(fn ($r) => (string) $r->getKey())->all();
        if (empty($recordIds)) {
            return;
        }

        $targetCollectionIds = [];
        foreach ($relationFields as $field) {
            if ($id = ($field['target_collection_id'] ?? null)) {
                $targetCollectionIds[] = $id;
            }
        }

        $targetCollectionsById = Collection::query()
            ->whereIn('id', array_unique($targetCollectionIds))
            ->get()
            ->keyBy('id');

        $pivotDataByField = []; // fieldName -> sourceId -> targetIds
        $relationIdsByField = []; // fieldName -> [targetIds]

        foreach ($relationFields as $fieldName => $field) {
            $fieldType = $field['type'] ?? '';
            /** @var Collection|null $targetCollection */
            $targetCollection = $targetCollectionsById->get($field['target_collection_id'] ?? '');

            if (! $targetCollection) {
                Log::warning('EXPANSION_TARGET_COLLECTION_MISSING', [
                    'source_collection' => $sourceCollection->id,
                    'field' => $fieldName,
                    'target_collection_id' => $field['target_collection_id'] ?? null,
                ]);
                continue;
            }

            if ($fieldType === CollectionFieldType::RelationMany->value) {
                $pivotTable = PivotTableName::for($sourceCollection->getPhysicalTableName(), $targetCollection->getPhysicalTableName(), $fieldName);

                if (! SchemaCache::hasTable($pivotTable)) {
                    continue;
                }

                $pivotRows = DB::table($pivotTable)
                    ->whereIn('source_id', $recordIds)
                    ->get();

                foreach ($pivotRows as $row) {
                    $pivotDataByField[$fieldName][$row->source_id][] = (array) $row;
                    $relationIdsByField[$fieldName][] = $row->target_id;
                }
            } else {
                foreach ($records as $record) {
                    $relationId = $record->getAttribute($fieldName);
                    if (is_string($relationId) && $relationId !== '') {
                        $relationIdsByField[$fieldName][] = $relationId;
                    }
                }
            }
        }

        $resolvedByField = [];
        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        foreach ($relationFields as $fieldName => $field) {
            $targetCollection = $targetCollectionsById->get($field['target_collection_id'] ?? '');
            $relationIds = array_values(array_unique($relationIdsByField[$fieldName] ?? []));

            if ($targetCollection === null || empty($relationIds)) {
                $resolvedByField[$fieldName] = [];
                continue;
            }

            $query = Record::of($targetCollection)->newQuery();
            if (! $bypassApiRules) {
                $query->applyRule('view');
            }

            $resolvedByField[$fieldName] = $query
                ->whereIn($query->getModel()->getQualifiedKeyName(), $relationIds)
                ->get()
                ->mapWithKeys(function (Record $targetRecord): array {
                    return [(string) $targetRecord->getAttribute('id') => $targetRecord];
                })
                ->all();
        }

        foreach ($records as $record) {
            if (! $record instanceof Record) {
                continue;
            }

            $sourceId = (string) $record->getKey();

            foreach ($relationFields as $fieldName => $field) {
                $fieldType = $field['type'] ?? '';
                $resolved = $resolvedByField[$fieldName] ?? [];

                if ($fieldType === CollectionFieldType::RelationMany->value) {
                    $pivots = $pivotDataByField[$fieldName][$sourceId] ?? [];
                    $expandedItems = [];
                    $ids = [];

                    foreach ($pivots as $pivot) {
                        $targetId = $pivot['target_id'];
                        if (isset($resolved[$targetId])) {
                            $targetRecord = $resolved[$targetId];
                            $ids[] = $targetId;

                            $resource = new RecordResource($targetRecord);
                            $data = $resource->resolve();

                            // Inject pivot data
                            $pivotFields = $field['pivot_fields'] ?? [];
                            $pivotFieldNames = collect($pivotFields)->map(function ($f) {
                                return is_array($f) ? ($f['name'] ?? null) : $f;
                            })->filter()->all();
                            
                            $data['pivot'] = collect($pivot)->only($pivotFieldNames)->all();

                            $expandedItems[] = $data;
                        }
                    }

                    $record->relationManyIds[$fieldName] = $ids;
                    $record->expandedRelations[$fieldName] = $expandedItems;
                } else {
                    $relationId = $record->getAttribute($fieldName);
                    if (is_string($relationId) && isset($resolved[$relationId])) {
                        $targetRecord = $resolved[$relationId];
                        $resource = new RecordResource($targetRecord);
                        $record->expandedRelations[$fieldName] = $resource->resolve();
                    } else {
                        $record->expandedRelations[$fieldName] = null;
                    }
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>|Field>
     */
    public function parse(Collection $sourceCollection, ?string $expand): array
    {
        $expand = trim((string) ($expand ?? ''));

        if ($expand === '') {
            return [];
        }

        /** @var array<string, array<string, mixed>|Field> $fieldsByName */
        $fieldsByName = collect($sourceCollection->fields ?? [])
            ->keyBy(fn ($field): string => (string) $field['name'])
            ->all();

        $expandFields = collect(explode(',', $expand))
            ->map(fn (string $fieldName): string => trim($fieldName))
            ->filter(fn (string $fieldName): bool => $fieldName !== '')
            ->unique()
            ->values();

        $maxExpansions = config('velo.records_expand_max', 10);

        if ($expandFields->count() > $maxExpansions) {
            throw new UnsupportedQueryFeatureException("A maximum of {$maxExpansions} relation expansions are allowed per request.");
        }

        $relationFields = [];

        foreach ($expandFields as $fieldName) {
            if (str_contains($fieldName, '.')) {
                throw new UnsupportedQueryFeatureException('Nested relation expansion is not implemented.');
            }

            if (! isset($fieldsByName[$fieldName])) {
                throw new InvalidRuleExpressionException("Unknown expand field: {$fieldName}");
            }

            $field = $fieldsByName[$fieldName];
            $fieldType = $field['type'] ?? null;

            if ($fieldType !== CollectionFieldType::Relation->value && $fieldType !== CollectionFieldType::RelationMany->value) {
                throw new InvalidRuleExpressionException("Field '{$fieldName}' is not a relation field.");
            }

            $relationFields[$fieldName] = $field;
        }

        return $relationFields;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function resolveTargetCollection(array $field): ?Collection
    {
        // This method is now shadowed by batch resolution in expandMany but kept for internal parse-time validation if needed.
        $targetCollectionId = $field['target_collection_id'] ?? null;

        if (! is_string($targetCollectionId) || $targetCollectionId === '') {
            return null;
        }

        return Collection::query()->find($targetCollectionId);
    }
}
