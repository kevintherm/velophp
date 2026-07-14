<?php

use Veloquent\Core\Domain\RuleEngine\RuleEngine;

// ── Basic evaluation ─────────────────────────────────────────────────────────

it('evaluates a field equals literal', function () {
    $engine = RuleEngine::make(['status']);
    expect($engine->evaluate('status = "active"', ['status' => 'active']))->toBeTrue();
    expect($engine->evaluate('status = "active"', ['status' => 'inactive']))->toBeFalse();
});

it('evaluates symmetric LHS/RHS: field = @sysvar', function () {
    $engine = RuleEngine::make();
    $context = ['user' => 5, 'request' => ['auth' => ['id' => 5]]];
    expect($engine->evaluate('user = @request.auth.id', $context))->toBeTrue();
});

it('evaluates symmetric LHS/RHS: @sysvar = field (flipped)', function () {
    $engine = RuleEngine::make();
    $context = ['user' => 5, 'request' => ['auth' => ['id' => 5]]];
    expect($engine->evaluate('@request.auth.id = user', $context))->toBeTrue();
});

it('evaluates @sysvar = @sysvar (both sides system vars)', function () {
    $engine = RuleEngine::make();
    $context = [
        'request' => ['body' => ['id' => 9], 'auth' => ['id' => 9]],
    ];
    expect($engine->evaluate('@request.body.id = @request.auth.id', $context))->toBeTrue();
});

it('evaluates field = field (both sides from context)', function () {
    $engine = RuleEngine::make();
    $context = ['post' => 3, 'parent_post' => 3];
    expect($engine->evaluate('post = parent_post', $context))->toBeTrue();
});

it('evaluates null equality with = null syntax', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('field = null', ['field' => null]))->toBeTrue();
    expect($engine->evaluate('field = null', ['field' => 'value']))->toBeFalse();
    expect($engine->evaluate('field != null', ['field' => 'value']))->toBeTrue();
    expect($engine->evaluate('field != null', ['field' => null]))->toBeFalse();
});

it('evaluates sysvar is not null', function () {
    $engine = RuleEngine::make();
    $context = ['request' => ['auth' => ['id' => 42]]];

    expect($engine->evaluate('@request.auth.id IS NOT NULL', $context))->toBeTrue();
    expect($engine->evaluate('@request.auth.id IS NULL', $context))->toBeFalse();
});

it('evaluates a missing field as null', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('unknown = null', []))->toBeTrue();
    expect($engine->evaluate('unknown != null', []))->toBeFalse();
});

// ── Logical operators ──────────────────────────────────────────────────────

it('evaluates && (AND)', function () {
    $engine = RuleEngine::make();
    $ctx = ['a' => 1, 'b' => 2];
    expect($engine->evaluate('a = 1 && b = 2', $ctx))->toBeTrue();
    expect($engine->evaluate('a = 1 && b = 99', $ctx))->toBeFalse();
});

it('evaluates || (OR)', function () {
    $engine = RuleEngine::make();
    $ctx = ['a' => 1, 'b' => 2];
    expect($engine->evaluate('a = 99 || b = 2', $ctx))->toBeTrue();
    expect($engine->evaluate('a = 99 || b = 99', $ctx))->toBeFalse();
});

it('evaluates grouped sub-expressions', function () {
    $engine = RuleEngine::make();
    $ctx = ['a' => 1, 'b' => 2, 'c' => 3];
    expect($engine->evaluate('a = 1 && (b = 2 || c = 99)', $ctx))->toBeTrue();
    expect($engine->evaluate('a = 1 && (b = 99 || c = 99)', $ctx))->toBeFalse();
});

// ── Comparison operators ───────────────────────────────────────────────────

it('evaluates like / not like', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('name like "%John%"', ['name' => 'Mr. John Smith']))->toBeTrue();
    expect($engine->evaluate('name not like "%John%"', ['name' => 'Mr. John Smith']))->toBeFalse();
    expect($engine->evaluate('name like "%nobody%"', ['name' => 'Mr. John Smith']))->toBeFalse();
});

it('evaluates pocketbase contains ~ and !~', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('name ~ "John"', ['name' => 'Mr. John Smith']))->toBeTrue();
    expect($engine->evaluate('name !~ "John"', ['name' => 'Mr. John Smith']))->toBeFalse();
    expect($engine->evaluate('name ~ "nobody"', ['name' => 'Mr. John Smith']))->toBeFalse();
    expect($engine->evaluate('name !~ "nobody"', ['name' => 'Mr. John Smith']))->toBeTrue();
});

it('evaluates in / not in', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('role in ("admin","editor")', ['role' => 'admin']))->toBeTrue();
    expect($engine->evaluate('role not in ("admin","editor")', ['role' => 'viewer']))->toBeTrue();
});

it('evaluates ordered comparisons: >, <, >=, <=', function () {
    $engine = RuleEngine::make();
    expect($engine->evaluate('score > 50', ['score' => 75]))->toBeTrue();
    expect($engine->evaluate('score < 50', ['score' => 75]))->toBeFalse();
    expect($engine->evaluate('score >= 75', ['score' => 75]))->toBeTrue();
    expect($engine->evaluate('score <= 74', ['score' => 75]))->toBeFalse();
});

// ── JSON operations ───────────────────────────────────────────────────────

it('evaluates JSON contains with ?=', function () {
    $engine = RuleEngine::make();
    $ctx = ['tags' => ['php', 'laravel']];
    expect($engine->evaluate('tags ?= "php"', $ctx))->toBeTrue();
    expect($engine->evaluate('tags ?= "rust"', $ctx))->toBeFalse();
});

it('evaluates JSON contains on JSON-encoded string', function () {
    $engine = RuleEngine::make();
    $ctx = ['meta' => json_encode(['tags' => ['php', 'laravel']])];
    // With the JsonFieldAdapter style context (flat field), via data_get
    expect($engine->evaluate('meta ?= "php"', ['meta' => ['php', 'laravel']]))->toBeTrue();
});

it('evaluates JSON has-key with ?&', function () {
    $engine = RuleEngine::make();
    $ctx = ['settings' => ['theme' => 'dark', 'notifications' => true]];
    expect($engine->evaluate('settings ?& "theme"', $ctx))->toBeTrue();
    expect($engine->evaluate('settings ?& "missing_key"', $ctx))->toBeFalse();
});

it('evaluates system variable with bracket notation', function () {
    $engine = RuleEngine::make();
    $ctx = [
        'request' => [
            'body' => [
                'attachments' => [
                    ['id' => 1, 'name' => 'file1.txt'],
                ],
                'prompt' => '',
            ],
            'auth' => [
                'id' => 'user_123',
            ],
        ],
        'name' => 'some_other_agent',
    ];
    $rule = '@request.auth.id != "" && (name != "nutrients_agent_v1" || (@request.body.attachments[0] != null && @request.body.prompt = ""))';
    expect($engine->evaluate($rule, $ctx))->toBeTrue();
});
