<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Infrastructure\Exceptions\InvalidArgumentException;

final class SchemaChangePlan
{
    /**
     * Type changes that are always safe (no data loss, no cast ambiguity).
     *
     * @return array<string, CollectionFieldType[]>
     */
    private static function compatibleTypeChanges(): array
    {
        return [
            CollectionFieldType::String->value => [CollectionFieldType::Text],
            CollectionFieldType::Integer->value => [CollectionFieldType::Float, CollectionFieldType::Double, CollectionFieldType::Decimal, CollectionFieldType::Text],
            CollectionFieldType::Float->value => [CollectionFieldType::Double, CollectionFieldType::Decimal, CollectionFieldType::Text],
            CollectionFieldType::Double->value => [CollectionFieldType::Decimal, CollectionFieldType::Text],
            CollectionFieldType::Decimal->value => [CollectionFieldType::Text],
        ];
    }

    public function __construct(
        public array $renames = [],
        public array $adds = [],
        public array $modifies = [],
        public array $drops = [],
    ) {}

    /**
     * Diff $before and $after field arrays into a change plan.
     *
     * Fields are matched by name. A name change appears as a drop + add.
     * @throws InvalidArgumentException
     */
    public static function buildPlan(array $before, array $after): self
    {
        $plan = new self;

        $beforeByName = collect($before)->keyBy('name');
        $afterByName = collect($after)->keyBy('name');

        foreach ($afterByName as $name => $field) {
            if (! $beforeByName->has($name)) {
                $plan->adds[] = $field;
            } elseif ($field !== $beforeByName[$name]) {
                $plan->modifies[] = [$beforeByName[$name], $field];
            }
        }

        foreach ($beforeByName as $name => $field) {
            if (! $afterByName->has($name)) {
                $plan->drops[] = $field;
            }
        }

        $plan->validatePlan($plan);

        return $plan;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validatePlan(self $plan): void
    {
        foreach ($plan->adds as $field) {
            $this->assertValidField($field);
        }

        foreach ($plan->modifies as [$old, $new]) {
            $this->assertValidField($new);
            $this->assertCompatibleChange($old, $new);
        }

        foreach ($plan->drops as $field) {
            if ($field['required'] ?? false) {
                throw new \LogicException(
                    "Cannot drop required field '{$field['name']}'."
                );
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertValidField(array $field): void
    {
        app(SchemaPolicy::class)->assertValidColumnDefinition($field);
    }

    /**
     * @throws \LogicException
     */
    private function assertCompatibleChange(array $old, array $new): void
    {
        $from = CollectionFieldType::tryFrom($old['type']);
        $to = CollectionFieldType::tryFrom($new['type']);

        if ($from === $to) {
            return;
        }

        $allowed = self::compatibleTypeChanges()[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \LogicException(
                "Incompatible type change on field '{$new['name']}': cannot change '{$from->value}' to '{$to->value}'."
            );
        }
    }
}
