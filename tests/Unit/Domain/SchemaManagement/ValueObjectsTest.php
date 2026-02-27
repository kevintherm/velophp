<?php

namespace Tests\Unit\Domain\SchemaManagement;

use App\Domain\SchemaManagement\ValueObjects\FieldName;
use App\Domain\SchemaManagement\Enums\FieldType;
use InvalidArgumentException;

test('valid field name is accepted and normalized', function () {
    $field = new FieldName('  Valid_Name_123  ');
    expect($field->value)->toBe('valid_name_123');
});

test('invalid field name throws exception', function () {
    new FieldName('123invalid');
})->throws(InvalidArgumentException::class);

test('empty field name throws exception', function () {
    new FieldName('   ');
})->throws(InvalidArgumentException::class);

test('valid field type is accepted', function () {
    $type = FieldType::from('string');
    expect($type->value)->toBe('string');
});

test('invalid field type throws exception', function () {
    FieldType::from('money');
})->throws(\ValueError::class);
