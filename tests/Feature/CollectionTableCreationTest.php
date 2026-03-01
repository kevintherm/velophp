<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('it creates a physical table when a base collection is created', function () {
    $collection = Collection::create([
        'name' => '_velo_products',
        'type' => CollectionType::Base,
        'description' => 'A list of products',
    ]);

    expect(Schema::hasTable('_velo_products'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_products', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_products', 'created_at'))->toBeTrue();
});

test('it creates a physical table with email password and verified when an auth collection is created', function () {
    $collection = Collection::create([
        'name' => '_velo_admins',
        'type' => CollectionType::Auth,
        'description' => 'Admin users',
    ]);

    expect(Schema::hasTable('_velo_admins'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_admins', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_admins', 'email'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_admins', 'password'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_admins', 'verified'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_admins', 'created_at'))->toBeTrue();
});

test('it dynamically creates fields attached to the collection creation payload', function () {
    $collection = Collection::create([
        'name' => '_velo_posts',
        'type' => CollectionType::Base,
        'description' => 'A list of posts',
        'fields' => [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'views', 'type' => 'integer'],
        ],
    ]);

    expect(Schema::hasTable('_velo_posts'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_posts', 'title'))->toBeTrue()
        ->and(Schema::hasColumn('_velo_posts', 'views'))->toBeTrue();
});
