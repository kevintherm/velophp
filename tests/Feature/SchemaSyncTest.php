<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\SchemaManagement\Models\SchemaChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('it syncs table renaming via workflow schema change', function () {
    $collection = Collection::create([
        'name' => 'posts',
        'type' => CollectionType::Base,
        'description' => 'A list of posts',
    ]);

    $collection->update(['name' => 'articles']);

    expect(Schema::hasTable(config('velo.collection_prefix').'posts'))->toBeFalse()
        ->and(Schema::hasTable(config('velo.collection_prefix').'articles'))->toBeTrue();

    // Verify a SchemaChange was recorded
    expect(SchemaChange::where('collection_id', $collection->id)->where('type', 'RENAME_TABLE')->count())->toBe(1);
});

test('it syncs field updates via workflow schema change', function () {
    $collection = Collection::create([
        'name' => 'pages',
        'type' => CollectionType::Base,
        'description' => 'Pages',
        'fields' => [
            ['id' => '1', 'name' => 'title', 'type' => 'string'],
        ],
    ]);

    $collection->update([
        'fields' => [
            ['id' => '1', 'name' => 'headline', 'type' => 'string'],
            ['id' => '2', 'name' => 'views', 'type' => 'integer'],
        ],
    ]);

    $table = config('velo.collection_prefix').'pages';

    // A zero-downtime rename actually LEAVES the physical `title` column in place, but conceptually it is deprecated.
    // Wait, wait... AddColumnStep adds 'headline'
    expect(Schema::hasColumn($table, 'title'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'headline'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'views'))->toBeTrue();

    expect(SchemaChange::where('collection_id', $collection->id)->where('type', 'RENAME_FIELD')->count())->toBe(1)
        ->and(SchemaChange::where('collection_id', $collection->id)->where('type', 'ADD_FIELD')->count())->toBe(1);
});
