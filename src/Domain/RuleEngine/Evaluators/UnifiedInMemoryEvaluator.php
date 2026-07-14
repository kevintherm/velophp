<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine\Evaluators;

use RuntimeException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Evaluators\InMemoryEvaluator;
use Veloquent\Core\Domain\RuleEngine\VeloquentParser;
use Veloquent\Core\Domain\Collections\Models\Collection;

/**
 * Extends exprc's InMemoryEvaluator to support Veloquent-specific features:
 *  - Sysvar resolution from a context array (@user.id, @request.body.foo)
 *  - Cross-collection EXISTS queries (@collection.name.field)
 *  - Date-suffix fields (field__date, field__year, etc.) processed via Carbon
 *  - HASKEY operator (array_key_exists)
 *
 * Note: Cross-collection lookups perform real database queries. This is intentional
 * as evaluate() is primarily used for API rule evaluation in the application layer.
 */
class UnifiedInMemoryEvaluator extends InMemoryEvaluator
{
    public function __construct(private readonly array $context)
    {
        parent::__construct($context);
    }

    // ── Overrides ──────────────────────────────────────────────────────────────

    protected function evaluateComparison(ComparisonNode $node): bool
    {
        $field = $node->field;
        $op = strtoupper($node->operator);
        $val = $node->value;

        $leftIsCollection = $this->isCollectionSysvar($field);

        $rightIsCollection = false;
        $rightPath = null;
        if ($val instanceof IdentifierNode) {
            $rightIsCollection = $this->isCollectionSysvar($val->name);
            $rightPath = $rightIsCollection ? $this->collectionPath($val->name) : null;
            $rightValue = $this->isSysvar($val->name) ? $this->sysvarValue($val->name) : data_get($this->context, $val->name);
        } else {
            $rightValue = $val;
        }

        if ($op === '~' || $op === '!~') {
            $op = $op === '~' ? 'LIKE' : 'NOT LIKE';
            if (is_string($rightValue)) {
                $rightValue = '%' . $rightValue . '%';
            }
        }

        // Cross-collection
        if ($leftIsCollection) {
            return $this->evaluateCrossCollectionExists($this->collectionPath($field), $op, $rightValue);
        }

        if ($rightIsCollection) {
            $leftValue = $this->isSysvar($field) ? $this->sysvarValue($field) : data_get($this->context, $field);

            return $this->evaluateCrossCollectionExists($rightPath, $this->invertOrdered($op), $leftValue);
        }

        $left = $this->getContextValue($field);

        $expected = $rightValue;
        if (is_array($expected)) {
            $expected = array_map(function ($val) {
                if ($val instanceof IdentifierNode) {
                    return $this->isSysvar($val->name) ? $this->sysvarValue($val->name) : data_get($this->context, $val->name);
                }

                return $val;
            }, $expected);
        } elseif ($expected instanceof IdentifierNode) {
            $expected = $this->isSysvar($expected->name) ? $this->sysvarValue($expected->name) : data_get($this->context, $expected->name);
        }

        return $this->applyOperator($left, $op, $expected);
    }

    protected function evaluateNullComparison(NullComparisonNode $node): bool
    {
        if ($this->isCollectionSysvar($node->field)) {
            $parsed = $this->parseCrossCollectionPath($this->collectionPath($node->field));

            $collection = Collection::findByNameCached($parsed['name']);
            if (! $collection) {
                return false;
            }

            $query = DB::table($collection->getPhysicalTableName());

            if ($parsed['filter'] !== null && $parsed['filter'] !== '') {
                $this->applyFilterToQuery($query, $parsed['filter']);
            }

            return $query
                ->where($parsed['field'], $node->isNot ? '!=' : '=', null)
                ->exists();
        }

        $val = $this->getContextValue($node->field);

        return $node->isNot ? ! is_null($val) : is_null($val);
    }

    // ── Context / sysvar helpers ───────────────────────────────────────────────

    private function getContextValue(string $field): mixed
    {
        if (preg_match('/^(.+)__(date|year|month|day|time)$/i', $field, $m)) {
            $baseField = $m[1];
            $function = strtolower($m[2]);
            $value = $this->isSysvar($baseField) ? $this->sysvarValue($baseField) : data_get($this->context, $baseField);

            if ($value === null) {
                return null;
            }

            try {
                $carbon = Carbon::parse($value);

                return match ($function) {
                    'date'  => $carbon->toDateString(),
                    'year'  => $carbon->year,
                    'month' => $carbon->month,
                    'day'   => $carbon->day,
                    'time'  => $carbon->toTimeString(),
                    default => $value,
                };
            } catch (\Throwable) {
                return $value;
            }
        }

        return $this->isSysvar($field) ? $this->sysvarValue($field) : data_get($this->context, $field);
    }

    private function isSysvar(string $field): bool
    {
        return str_starts_with($field, '@') || str_starts_with($field, '__numeric__');
    }

    private function isCollectionSysvar(mixed $field): bool
    {
        return is_string($field) && str_starts_with($field, '@collection.');
    }

    private function collectionPath(string $field): string
    {
        return substr($field, strlen('@collection.'));
    }

    private function sysvarValue(mixed $field): mixed
    {
        if (! is_string($field)) {
            return $field;
        }

        if (str_starts_with($field, '__numeric__')) {
            $val = substr($field, strlen('__numeric__'));

            return is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
        }

        $path = ltrim($field, '@');

        return data_get($this->context, $path);
    }

    /**
     * Parse a cross-collection path into its components.
     *
     * Supports:
     *  - `name.field`          → plain cross-collection field lookup
     *  - `name[filter].field`  → filtered cross-collection lookup
     *
     * @return array{name: string, filter: string|null, field: string}
     */
    private function parseCrossCollectionPath(string $path): array
    {
        if (preg_match('/^([^\[.]+)\[([^\]]*)\]\.(.+)$/', $path, $m)) {
            return ['name' => $m[1], 'filter' => $m[2], 'field' => $m[3]];
        }

        $parts = explode('.', $path, 2);
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid collection sysvar. Use @collection.name.field or @collection.name[filter].field');
        }

        return ['name' => $parts[0], 'filter' => null, 'field' => $parts[1]];
    }

    /**
     * Apply a filter expression string to an existing DB query builder.
     * Sysvars in the filter are resolved against $this->context.
     */
    private function applyFilterToQuery(\Illuminate\Database\Query\Builder $query, string $filterExpr): void
    {
        $filterAst = (new VeloquentParser)->parse($filterExpr);
        $sysvars   = $this->context;

        $this->applyAstToQueryBuilder($query, $filterAst);
    }

    /**
     * Walk a simple AST and apply conditions to a plain DB query builder.
     * Supports ComparisonNode with sysvar or literal values (the common filter case).
     */
    private function applyAstToQueryBuilder(\Illuminate\Database\Query\Builder $q, \Kevintherm\Exprc\Ast\Node $node): void
    {
        if ($node instanceof LogicalNode) {
            $op = strtoupper($node->operator);
            if ($op === 'AND') {
                $this->applyAstToQueryBuilder($q, $node->left);
                $this->applyAstToQueryBuilder($q, $node->right);
            } elseif ($op === 'OR') {
                $q->where(function ($sub) use ($node) {
                    $this->applyAstToQueryBuilder($sub, $node->left);
                    $sub->orWhere(function ($sub2) use ($node) {
                        $this->applyAstToQueryBuilder($sub2, $node->right);
                    });
                });
            }

            return;
        }

        if ($node instanceof ComparisonNode) {
            $field = $node->field;
            $op    = strtoupper($node->operator);
            $val   = $node->value instanceof IdentifierNode
                ? ($this->isSysvar($node->value->name)
                    ? $this->sysvarValue($node->value->name)
                    : data_get($this->context, $node->value->name))
                : $node->value;

            if ($op === '~' || $op === '!~') {
                $op = $op === '~' ? 'LIKE' : 'NOT LIKE';
                if (is_string($val)) {
                    $val = '%' . $val . '%';
                }
            }

            $q->where($field, $op, $val);

            return;
        }

        if ($node instanceof NullComparisonNode) {
            $node->isNot
                ? $q->whereNotNull($node->field)
                : $q->whereNull($node->field);
        }
    }

    private function evaluateCrossCollectionExists(string $path, string $operator, mixed $otherValue): bool
    {
        $parsed = $this->parseCrossCollectionPath($path);

        $collection = Collection::findByNameCached($parsed['name']);
        if (! $collection) {
            throw new RuntimeException(sprintf('Collection "%s" not found for cross-collection lookup.', $parsed['name']));
        }

        $query = DB::table($collection->getPhysicalTableName());

        if ($parsed['filter'] !== null && $parsed['filter'] !== '') {
            $this->applyFilterToQuery($query, $parsed['filter']);
        }

        if ($operator === 'IN') {
            $query->whereIn($parsed['field'], is_array($otherValue) ? $otherValue : [$otherValue]);
        } elseif ($operator === 'NOT IN') {
            $query->whereNotIn($parsed['field'], is_array($otherValue) ? $otherValue : [$otherValue]);
        } elseif ($operator === 'CONTAINS') {
            $query->whereJsonContains($parsed['field'], $otherValue);
        } elseif ($operator === 'NOT CONTAINS') {
            $query->whereJsonContains($parsed['field'], $otherValue, 'and', true);
        } elseif ($operator === 'HASKEY') {
            $query->whereJsonContainsKey($parsed['field'] . '->' . $otherValue);
        } elseif ($operator === 'NOT HASKEY') {
            $query->whereJsonContainsKey($parsed['field'] . '->' . $otherValue, 'and', true);
        } else {
            $query->where($parsed['field'], $operator, $otherValue);
        }

        return $query->exists();
    }

    private function invertOrdered(string $op): string
    {
        return match (strtoupper($op)) {
            '>' => '<', '<' => '>', '>=' => '<=', '<=' => '>=',
            default => $op,
        };
    }

    /**
     * Extend parent's applyOperator to handle:
     *  - HASKEY (Veloquent-specific operator)
     *  - String comparisons for date/datetime values (parent only handles numeric for >, <, etc.)
     *
     * @param string|int|float|bool|null|array<int, mixed>|\Kevintherm\Exprc\Ast\Node $expected
     */
    protected function applyOperator(mixed $actual, string $operator, string|int|float|bool|null|array|\Kevintherm\Exprc\Ast\Node $expected): bool
    {
        if ($operator === 'HASKEY') {
            return is_array($actual) && array_key_exists((string) $expected, $actual);
        }
        
        if ($operator === 'CONTAINS' && is_string($actual) && is_string($expected) && (str_contains($expected, '%') || str_contains($expected, '_'))) {
            return $this->likeMatch($actual, $expected);
        }

        if (
            in_array($operator, ['>', '>=', '<', '<='], true)
            && is_string($actual)
            && is_string($expected)
            && ! is_numeric($actual)
            && ! is_numeric($expected)
        ) {
            $cmp = strcmp($actual, $expected);

            return match ($operator) {
                '>'  => $cmp > 0,
                '>=' => $cmp >= 0,
                '<'  => $cmp < 0,
                '<=' => $cmp <= 0,
            };
        }

        return parent::applyOperator($actual, $operator, $expected);
    }
}
