<?php

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\RuleContextBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

function makeSqlRuleContext(array $body = [], int $authId = 21): array
{
    $authCollection = new Collection;
    $authCollection->name = 'users';
    $authCollection->type = CollectionType::Base;
    $authCollection->is_system = false;
    $authCollection->fields = [];
    $authCollection->api_rules = [];

    $user = Record::of($authCollection);
    $user->setAttribute('id', $authId);

    Auth::setUser($user);

    $request = Request::create('/api/collections/posts/records', 'POST', $body);
    app()->instance('request', $request);

    return (new RuleContextBuilder)->build($request, $user);
}

it('resolves @request.auth reference in run mode to query bindings', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id = @request.auth.id', $context);

    expect($query->getBindings())->toBe([21]);
});

it('normalizes @request.auth on the left to a field comparison in run mode', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id = id', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('= ?')
        ->and($query->getBindings())->toBe([21]);
});

it('normalizes literal-on-left ordered comparisons by inverting the operator', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('5 > id');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('< ?')
        ->and($query->getBindings())->toBe([5]);
});

it('uses column comparison when both operands are fields', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['id', 'updated_at'])->run('id = updated_at');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('id')
        ->and($sql)->toContain('updated_at')
        ->and($sql)->not->toContain('?')
        ->and($query->getBindings())->toBe([]);
});

it('compiles two @request operands into a bound literal SQL comparison', function () {
    $context = makeSqlRuleContext(['user' => 21]);
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.body.user = @request.auth.id', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? = ?')
        ->and($query->getBindings())->toBe([21, 21]);
});

it('compiles sysvar on the left with a literal value into bound SQL comparison', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id = "value"', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? = ?')
        ->and($sql)->not->toContain('__sysvar__')
        ->and($query->getBindings())->toBe([21, 'value']);
});

it('compiles sysvar on the left with a literal value and not-equals into bound SQL comparison', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id != "value"', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? != ?')
        ->and($sql)->not->toContain('__sysvar__')
        ->and($query->getBindings())->toBe([21, 'value']);
});

it('compiles sysvar is not null into a bound SQL null comparison', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id IS NOT NULL', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? is not null')
        ->and($sql)->not->toContain('__sysvar__')
        ->and($query->getBindings())->toBe([21]);
});

it('compiles sysvar is null into a bound SQL null comparison', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('@request.auth.id IS NULL', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? is null')
        ->and($sql)->not->toContain('__sysvar__')
        ->and($query->getBindings())->toBe([21]);
});

it('compiles grouped sysvar null comparison using bound SQL in logical expressions', function () {
    $context = makeSqlRuleContext();
    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id = 1 OR @request.auth.id IS NOT NULL', $context);

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('? is not null')
        ->and($sql)->not->toContain('__sysvar__')
        ->and($query->getBindings())->toContain(1)
        ->and($query->getBindings())->toContain(21);
});

it('compiles pocketbase contains ~ and !~ operators into LIKE and NOT LIKE with wildcards', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['exercise'])->run('exercise ~ "cable"');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('exercise')
        ->and($sql)->toContain('like ?')
        ->and($query->getBindings())->toBe(['%cable%']);
});

it('compiles pocketbase contains !~ operator into NOT LIKE with wildcards', function () {
    $query = Collection::query();

    QueryFilter::for($query, ['exercise'])->run('exercise !~ "cable"');

    $sql = strtolower($query->toSql());

    expect($sql)->toContain('exercise')
        ->and($sql)->toContain('not like ?')
        ->and($query->getBindings())->toBe(['%cable%']);
});
