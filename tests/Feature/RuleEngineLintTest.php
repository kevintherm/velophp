<?php

use Veloquent\Core\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Veloquent\Core\Domain\RuleEngine\RuleEngine;

it('accepts valid @-prefixed system variables in lint', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @request.auth.id'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.body.user'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.param.id'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('user = @request.query.user'))->not->toThrow(InvalidRuleExpressionException::class);
});

it('rejects malformed @-prefixed variables in lint (typo)', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @rquest.auth.id'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('rejects invalid @-prefixed namespace in lint', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('user = @env.secret'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('rejects unknown field in lint', function () {
    $engine = RuleEngine::make(['id', 'title']);
    expect(fn () => $engine->lint('unknown_field = "value"'))
        ->toThrow(InvalidRuleExpressionException::class);
});

it('accepts @-variable on the LHS in lint (RuleEngine allows symmetric)', function () {
    $engine = RuleEngine::make(['user']);
    expect(fn () => $engine->lint('@request.auth.id = user'))->not->toThrow(InvalidRuleExpressionException::class);
});

it('accepts is null operator', function () {
    $engine = RuleEngine::make(['id']);
    expect(fn () => $engine->lint('id is null'))
        ->not->toThrow(InvalidRuleExpressionException::class);
});

it('accepts is not null operator', function () {
    $engine = RuleEngine::make(['id']);
    expect(fn () => $engine->lint('id is not null'))
        ->not->toThrow(InvalidRuleExpressionException::class);
});

it('accepts = null as the null check', function () {
    $engine = RuleEngine::make(['id']);
    expect(fn () => $engine->lint('id = null'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('id != null'))->not->toThrow(InvalidRuleExpressionException::class);
});

it('accepts bracket-notation system variable and complex rule in lint', function () {
    $engine = RuleEngine::make(['name']);
    $rule = '@request.auth.id != ""
&& (
    name != "nutrients_agent_v1"
    || (
        @request.body.attachments[0] != null
        && @request.body.prompt = ""
    )
)';
    expect(fn () => $engine->lint($rule))->not->toThrow(InvalidRuleExpressionException::class);
});

it('accepts pocketbase contains ~ and !~ operators in lint', function () {
    $engine = RuleEngine::make(['exercise']);
    expect(fn () => $engine->lint('exercise ~ "cable"'))->not->toThrow(InvalidRuleExpressionException::class);
    expect(fn () => $engine->lint('exercise !~ "cable"'))->not->toThrow(InvalidRuleExpressionException::class);
});
