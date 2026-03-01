<?php

use App\Domain\SchemaManagement\Services\SchemaChangePlan;

$field = fn (string $name, string $type = 'string') => [
    'name' => $name,
    'type' => $type,
];

test('detects added fields', function () use ($field) {
    $plan = SchemaChangePlan::buildPlan(
        before: [],
        after: [$field('title')],
    );

    expect($plan->adds)->toHaveCount(1)
        ->and($plan->adds[0]['name'])->toBe('title');

    expect($plan->renames)->toBeEmpty();
    expect($plan->modifies)->toBeEmpty();
    expect($plan->drops)->toBeEmpty();
});

test('detects dropped fields', function () use ($field) {
    $plan = SchemaChangePlan::buildPlan(
        before: [$field('title')],
        after: [],
    );

    expect($plan->drops)->toHaveCount(1)
        ->and($plan->drops[0]['name'])->toBe('title');

    expect($plan->adds)->toBeEmpty();
    expect($plan->renames)->toBeEmpty();
    expect($plan->modifies)->toBeEmpty();
});

test('a name change appears as drop + add', function () use ($field) {
    $plan = SchemaChangePlan::buildPlan(
        before: [$field('title')],
        after: [$field('headline')],
    );

    expect($plan->drops)->toHaveCount(1)
        ->and($plan->drops[0]['name'])->toBe('title');
    expect($plan->adds)->toHaveCount(1)
        ->and($plan->adds[0]['name'])->toBe('headline');
    expect($plan->renames)->toBeEmpty();
});

test('detects modified field attributes', function () use ($field) {
    $plan = SchemaChangePlan::buildPlan(
        before: [$field('title', 'string')],
        after: [$field('title', 'text')],
    );

    expect($plan->modifies)->toHaveCount(1);
    expect($plan->renames)->toBeEmpty();
    expect($plan->adds)->toBeEmpty();
    expect($plan->drops)->toBeEmpty();
});

test('handles mixed changes in one plan', function () use ($field) {
    $before = [
        $field('title'),
        $field('slug'),
        $field('excerpt'),
    ];
    $after = [
        $field('headline'),              // new (title is dropped)
        $field('excerpt', 'text'),        // modify type
        $field('views', 'integer'),       // add
    ];

    $plan = SchemaChangePlan::buildPlan($before, $after);

    expect($plan->adds)->toHaveCount(2)
        ->and(collect($plan->adds)->pluck('name')->sort()->values()->all())
        ->toBe(['headline', 'views']);

    expect($plan->modifies)->toHaveCount(1)
        ->and($plan->modifies[0][1]['type'])->toBe('text');

    expect($plan->drops)->toHaveCount(2)
        ->and(collect($plan->drops)->pluck('name')->sort()->values()->all())
        ->toBe(['slug', 'title']);
});

test('throws on incompatible type change', function () use ($field) {
    SchemaChangePlan::buildPlan(
        before: [$field('active', 'text')],
        after: [$field('active', 'boolean')],
    );
})->throws(\LogicException::class, 'Incompatible type change');

test('throws when dropping a required field', function () {
    $before = [['name' => 'email', 'type' => 'string', 'required' => true]];

    SchemaChangePlan::buildPlan(before: $before, after: []);
})->throws(\LogicException::class, 'Cannot drop required field');
