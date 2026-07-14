<?php

declare(strict_types=1);

namespace Veloquent\Core\Domain\RuleEngine\Evaluators;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\RuleEngine\VeloquentParser;
use Illuminate\Database\Eloquent\Builder;
use Kevintherm\Exprc\Ast\ComparisonNode;
use Kevintherm\Exprc\Ast\IdentifierNode;
use Kevintherm\Exprc\Ast\LogicalNode;
use Kevintherm\Exprc\Ast\Node;
use Kevintherm\Exprc\Ast\NotNode;
use Kevintherm\Exprc\Ast\NullComparisonNode;
use Kevintherm\Exprc\Ast\VisitorInterface;
use Kevintherm\Exprc\EvaluatorInterface;
use Kevintherm\Exprc\Resolvers\FieldResolverInterface;
use RuntimeException;

class UnifiedEvaluator implements EvaluatorInterface, VisitorInterface
{
    private array $sysvars = [];

    public function __construct(
        private readonly object $builder,
        private readonly FieldResolverInterface $resolver
    ) {}

    public function withSysvars(array $sysvars): self
    {
        $this->sysvars = $sysvars;

        return $this;
    }

    private function isContextVariable(string $field): bool
    {
        return str_starts_with($field, '@');
    }

    private function isNumericLiteral(string $field): bool
    {
        return str_starts_with($field, '__numeric__');
    }

    private function isSysvar(string $field): bool
    {
        return $this->isContextVariable($field) || $this->isNumericLiteral($field);
    }

    private function getSysvarValue(mixed $field): mixed
    {
        if (! is_string($field)) {
            return $field;
        }

        if ($this->isNumericLiteral($field)) {
            $val = str_replace('__numeric__', '', $field);

            return is_numeric($val) ? (str_contains($val, '.') ? (float) $val : (int) $val) : $val;
        }

        if ($this->isContextVariable($field)) {
            $path = ltrim($field, '@');

            return data_get($this->sysvars, $path);
        }

        return $field;
    }

    public function evaluate(Node $node): mixed
    {
        $node->accept($this);

        return $this->builder;
    }

    public function beforeProcessNode(Node $node): void
    {
        // Lifecycle hook
    }

    public function afterProcessNode(Node $node, mixed $result): void
    {
        // Lifecycle hook
    }

    public function visitLogicalNode(LogicalNode $node): mixed
    {
        $operator = strtoupper($node->operator);

        if ($operator === 'AND') {
            $this->applyNestedWhere($this->builder, 'and', function (object $nested) use ($node): void {
                $this->applyNodeInContext($nested, $node->left, 'and');
                $this->applyNodeInContext($nested, $node->right, 'and');
            });

            return null;
        }

        if ($operator === 'OR') {
            $this->applyNestedWhere($this->builder, 'and', function (object $nested) use ($node): void {
                $this->applyNodeInContext($nested, $node->left, 'and');
                $this->applyNodeInContext($nested, $node->right, 'or');
            });

            return null;
        }

        throw new RuntimeException(sprintf('Unsupported logical operator "%s".', $node->operator));
    }

    public function visitNotNode(NotNode $node): mixed
    {
        $this->builder->where(function ($query) use ($node) {
            $this->applyNodeInContext($query, $node->node, 'and');
        }, null, null, 'and not');

        return null;
    }

    public function visitComparisonNode(ComparisonNode $node): mixed
    {
        $this->applyComparisonNode($this->builder, $node, 'and');

        return null;
    }

    public function visitNullComparisonNode(NullComparisonNode $node): mixed
    {
        $this->applyNullComparison($this->builder, $node->field, $node->isNot, 'and');

        return null;
    }

    public function visitIdentifierNode(IdentifierNode $node): mixed
    {
        return $this->isSysvar($node->name) ? $this->getSysvarValue($node->name) : $node->name;
    }

    private function applyNodeInContext(object $builder, Node $node, string $boolean): void
    {
        if ($node instanceof ComparisonNode) {
            $this->applyComparisonNode($builder, $node, $boolean);

            return;
        }

        if ($node instanceof NullComparisonNode) {
            $this->applyNullComparison($builder, $node->field, $node->isNot, $boolean);

            return;
        }

        if ($node instanceof LogicalNode || $node instanceof NotNode) {
            if ($boolean === 'or') {
                $builder->orWhere(function ($query) use ($node) {
                    $node->accept($this->replicate($query));
                });
            } else {
                $node->accept($this->replicate($builder));
            }

            return;
        }
    }

    private function replicate(object $builder): self
    {
        return (new self($builder, $this->resolver))->withSysvars($this->sysvars);
    }

    private function applyComparisonNode(object $builder, ComparisonNode $node, string $boolean): void
    {
        $left = $this->resolveOperand($node->field, true);
        $right = $this->resolveOperand($node->value);
        $operator = strtoupper($node->operator);

        if ($operator === '~' || $operator === '!~') {
            $operator = $operator === '~' ? 'LIKE' : 'NOT LIKE';
            if (is_string($right->value)) {
                $right->value = '%' . $right->value . '%';
            }
        }

        // Cross-collection lookups
        if ($left->isCollection) {
            $collectionPath = str_replace('@collection.', '', $node->field);
            $this->applyCrossCollectionComparison($builder, $collectionPath, $operator, $right->value, $right->isField, $boolean);

            return;
        }

        if ($right->isCollection) {
            $collectionPath = str_replace('@collection.', '', $node->value instanceof IdentifierNode ? $node->value->name : '');
            $this->applyCrossCollectionComparison($builder, $collectionPath, $this->invertOrderedOperator($operator), $left->value, $left->isField, $boolean);

            return;
        }

        // Null comparisons
        if ($right->value === null && in_array($operator, ['=', '!='])) {
            $this->applyNullComparison($builder, (string) $node->field, $operator === '!=', $boolean);

            return;
        }

        // List operators
        if (in_array($operator, ['IN', 'NOT IN'])) {
            $inValues = is_array($right->value) ? $right->value : [$right->value];
            $this->applyInClause($builder, $this->resolver->resolve($node->field), $inValues, $boolean, $operator === 'NOT IN');

            return;
        }

        // JSON operators
        if (in_array($operator, ['CONTAINS', 'NOT CONTAINS'])) {
            $method = $boolean === 'or' ? 'orWhereJsonContains' : 'whereJsonContains';
            $builder->{$method}($this->resolver->resolve($node->field), $right->value, 'and', $operator === 'NOT CONTAINS');

            return;
        }

        if (in_array($operator, ['HASKEY', 'NOT HASKEY'])) {
            $column = $this->resolver->resolve($node->field) . '->' . $right->value;
            $method = $boolean === 'or' ? 'orWhereJsonContainsKey' : 'whereJsonContainsKey';
            $builder->{$method}($column, 'and', $operator === 'NOT HASKEY');

            return;
        }

        // Symmetric comparisons
        if ($left->isSysvar && $right->isField) {
            // Flip: @auth.id = id -> id = @auth.id
            $this->applyScalarComparison($builder, $this->resolver->resolve((string) $right->value), $this->invertOrderedOperator($operator), $left->value, $boolean);

            return;
        }

        if ($left->isSysvar) {
            // Sysvar vs Literal/Sysvar
            $this->applyLiteralComparison($builder, $operator, $left->value, $right->value, $boolean);

            return;
        }

        if ($right->isField) {
            // Field vs Field
            $method = $boolean === 'or' ? 'orWhereColumn' : 'whereColumn';
            $builder->{$method}($this->resolver->resolve($node->field), $this->toSqlOperator($operator), $this->resolver->resolve((string) $right->value));

            return;
        }

        // Default: Field vs Literal
        $this->applyScalarComparison($builder, $this->resolver->resolve($node->field), $operator, $right->value, $boolean);
    }

    private function resolveOperand(mixed $operand, bool $forceDynamic = false): object
    {
        $isCollection = false;
        $isSysvar = false;
        $isField = false;
        $value = null;

        if ($operand instanceof IdentifierNode) {
            $isSysvar = $this->isSysvar($operand->name);
            $isField = ! $isSysvar;
            $isCollection = str_starts_with($operand->name, '@collection.');
            $value = $this->visitIdentifierNode($operand);
        } elseif (is_string($operand) && $forceDynamic) {
            $isSysvar = $this->isSysvar($operand);
            $isField = ! $isSysvar;
            $isCollection = str_starts_with($operand, '@collection.');
            $value = $isSysvar ? $this->getSysvarValue($operand) : $operand;
        } elseif (is_array($operand)) {
            $value = array_map(function ($v) {
                return $v instanceof IdentifierNode ? $this->visitIdentifierNode($v) : $v;
            }, $operand);
        } else {
            $value = $operand;
        }

        return (object) [
            'value' => $value,
            'isSysvar' => $isSysvar,
            'isField' => $isField,
            'isCollection' => $isCollection,
        ];
    }

    /**
     * Parse a cross-collection path into its components.
     *
     * Supports two forms:
     *  - `name.field`               → plain cross-collection field lookup
     *  - `name[filter].field`        → filtered cross-collection lookup
     *
     * @return array{name: string, filter: string|null, field: string}
     */
    private function parseCrossCollectionPath(string $collectionPath): array
    {
        // name[filter].field  (filter may contain dots and sysvars)
        if (preg_match('/^([^\[.]+)\[([^\]]*)\]\.(.+)$/', $collectionPath, $m)) {
            return ['name' => $m[1], 'filter' => $m[2], 'field' => $m[3]];
        }

        $parts = explode('.', $collectionPath, 2);
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid collection sysvar. Use @collection.name.field or @collection.name[filter].field');
        }

        return ['name' => $parts[0], 'filter' => null, 'field' => $parts[1]];
    }

    private function applyCrossCollectionComparison(object $builder, string $collectionPath, string $operator, mixed $otherValue, bool $otherIsField, string $boolean): void
    {
        $parsed = $this->parseCrossCollectionPath($collectionPath);
        $collectionName  = $parsed['name'];
        $collectionField = $parsed['field'];
        $filterExpr      = $parsed['filter'];

        $collection = Collection::findByNameCached($collectionName);
        if (! $collection) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $builder->{$method}('0 = 1');

            return;
        }
        $tableName = $collection->getPhysicalTableName();

        $sysvars    = $this->sysvars;
        $resolver   = $this->resolver;

        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        $builder->{$method}(function ($q) use ($builder, $tableName, $collectionField, $operator, $otherValue, $otherIsField, $filterExpr, $sysvars, $resolver) {
            $q->selectRaw('1')->from($tableName);

            if ($filterExpr !== null && $filterExpr !== '') {
                $filterAst = (new VeloquentParser)->parse($filterExpr);

                $subResolver = new class($tableName, $resolver) implements FieldResolverInterface {
                    public function __construct(
                        private readonly string $table,
                        private readonly FieldResolverInterface $parent
                    ) {}

                    public function resolve(string $field): string
                    {
                        if (str_starts_with($field, '@') || str_starts_with($field, '__numeric__')) {
                            return $this->parent->resolve($field);
                        }
                        if (str_contains($field, '.')) {
                            return $field;
                        }

                        return $this->table . '.' . $field;
                    }
                };

                (new UnifiedEvaluator($q, $subResolver))
                    ->withSysvars($sysvars)
                    ->evaluate($filterAst);
            }

            if ($operator === 'IN' || $operator === 'NOT IN') {
                $inValues = is_array($otherValue) ? $otherValue : [$otherValue];
                if ($operator === 'IN') {
                    $q->whereIn($tableName.'.'.$collectionField, $inValues);
                } else {
                    $q->whereNotIn($tableName.'.'.$collectionField, $inValues);
                }
            } elseif ($operator === 'CONTAINS') {
                $q->whereJsonContains($tableName.'.'.$collectionField, $otherValue);
            } elseif ($operator === 'NOT CONTAINS') {
                $q->whereJsonContains($tableName.'.'.$collectionField, $otherValue, 'and', true);
            } elseif ($operator === 'HASKEY') {
                $q->whereJsonContainsKey($tableName.'.'.$collectionField . '->' . $otherValue);
            } elseif ($operator === 'NOT HASKEY') {
                $q->whereJsonContainsKey($tableName.'.'.$collectionField . '->' . $otherValue, 'and', true);
            } elseif ($otherIsField) {
                $parentTable = $builder instanceof Builder ? $builder->getModel()->getTable() : null;
                $resolvedOtherValue = $resolver->resolve((string) $otherValue);
                if ($parentTable && ! str_contains($resolvedOtherValue, '.')) {
                    $resolvedOtherValue = $parentTable.'.'.$resolvedOtherValue;
                }

                $q->whereColumn($tableName.'.'.$collectionField, $this->toSqlOperator($operator), $resolvedOtherValue);
            } else {
                $q->where($tableName.'.'.$collectionField, $this->toSqlOperator($operator), $otherValue);
            }
        });
    }

    private function applyScalarComparison(object $builder, string $field, string $operator, mixed $value, string $boolean): void
    {
        $sqlOperator = $this->toSqlOperator($operator);

        if (preg_match('/^(.+)__(date|year|month|day|time)$/i', $field, $matches)) {
            $column = $matches[1];
            $function = strtolower($matches[2]);

            $method = ($boolean === 'or' ? 'orWhere' : 'where').ucfirst($function);

            $builder->{$method}($column, $sqlOperator, $value);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $builder->{$method}($field, $sqlOperator, $value);
    }

    private function applyNullComparison(object $builder, string $field, bool $isNot, string $boolean): void
    {
        if (str_starts_with($field, '@collection.')) {
            $collectionPath = str_replace('@collection.', '', $field);
            $parts = explode('.', $collectionPath, 2);
            if (count($parts) < 2) {
                throw new RuntimeException('Invalid collection sysvar.');
            }
            $collectionName = $parts[0];
            $collectionField = $parts[1];

            $collection = Collection::findByNameCached($collectionName);
            if (! $collection) {
                $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
                $builder->{$method}('0 = 1');

                return;
            }
            $tableName = $collection->getPhysicalTableName();

            $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
            $builder->{$method}(function ($q) use ($tableName, $collectionField, $isNot) {
                $q->selectRaw('1')->from($tableName);
                if ($isNot) {
                    $q->whereNotNull($tableName.'.'.$collectionField);
                } else {
                    $q->whereNull($tableName.'.'.$collectionField);
                }
            });

            return;
        }

        if ($this->isSysvar($field)) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $sql = $isNot ? '? IS NOT NULL' : '? IS NULL';
            $builder->{$method}($sql, [$this->getSysvarValue($field)]);

            return;
        }

        $resolvedField = $this->resolver->resolve($field);
        $method = $isNot
            ? ($boolean === 'or' ? 'orWhereNotNull' : 'whereNotNull')
            : ($boolean === 'or' ? 'orWhereNull' : 'whereNull');

        $builder->{$method}($resolvedField);
    }

    private function applyLiteralComparison(object $builder, string $operator, mixed $left, mixed $right, string $boolean): void
    {
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $builder->{$method}('? '.$this->toSqlOperator($operator).' ?', [$left, $right]);
    }

    private function applyInClause(object $builder, string $field, array $values, string $boolean, bool $not): void
    {
        if ($not) {
            $method = $boolean === 'or' ? 'orWhereNotIn' : 'whereNotIn';
            $builder->{$method}($field, $values);

            return;
        }

        $method = $boolean === 'or' ? 'orWhereIn' : 'whereIn';
        $builder->{$method}($field, $values);
    }

    private function applyNestedWhere(object $builder, string $boolean, callable $callback): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $builder->{$method}(function ($nested) use ($callback): void {
            $callback($nested);
        });
    }

    private function negateNode(Node $node): Node
    {
        if ($node instanceof NotNode) {
            return $node->node;
        }

        if ($node instanceof LogicalNode) {
            if (strtoupper($node->operator) === 'AND') {
                return new LogicalNode('OR', $this->negateNode($node->left), $this->negateNode($node->right));
            }

            return new LogicalNode('AND', $this->negateNode($node->left), $this->negateNode($node->right));
        }

        if ($node instanceof ComparisonNode) {
            return new ComparisonNode($node->field, $this->invertOperator($node->operator), $node->value);
        }

        throw new RuntimeException('Unknown AST node received while negating query expression.');
    }

    private function invertOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            '=' => '!=',
            '!=' => '=',
            '>' => '<=',
            '>=' => '<',
            '<' => '>=',
            '<=' => '>',
            'LIKE' => 'NOT LIKE',
            'NOT LIKE' => 'LIKE',
            '~' => '!~',
            '!~' => '~',
            'IN' => 'NOT IN',
            'NOT IN' => 'IN',
            'CONTAINS' => 'NOT CONTAINS',
            'NOT CONTAINS' => 'CONTAINS',
            'HASKEY' => 'NOT HASKEY',
            'NOT HASKEY' => 'HASKEY',
            default => throw new RuntimeException(sprintf('Unsupported operator "%s" for negation.', $operator)),
        };
    }

    private function invertOrderedOperator(string $op): string
    {
        return match (strtoupper($op)) {
            '>' => '<',
            '<' => '>',
            '>=' => '<=',
            '<=' => '>=',
            default => $op,
        };
    }

    private function toSqlOperator(string $operator): string
    {
        return match (strtoupper($operator)) {
            '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'CONTAINS', 'NOT CONTAINS', 'HASKEY', 'NOT HASKEY', 'IN', 'NOT IN' => $operator,
            default => throw new RuntimeException(sprintf('Unsupported operator "%s" for query builder.', $operator)),
        };
    }
}
