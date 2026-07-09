<?php

use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\Collections\Actions\CreateCollectionAction;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function createRecordFileCollection(array $fields, ?array $apiRules = null): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => 'files_'.Str::lower(Str::random(8)),
        'type' => CollectionType::Base->value,
        'fields' => $fields,
        'api_rules' => $apiRules ?? [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
        ],
    ]);
}

function createRecordFileAuthCollection(): Collection
{
    return app(CreateCollectionAction::class)->execute([
        'name' => 'auth_'.Str::lower(Str::random(8)),
        'type' => CollectionType::Auth->value,
        'fields' => [
            ['name' => 'name', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ],
        'api_rules' => [
            'list' => '',
            'create' => '',
            'view' => '',
            'update' => '',
            'delete' => '',
            'manage' => '',
        ],
    ]);
}

function issueRecordFileAuthToken(Collection $authCollection): string
{
    $user = Record::of($authCollection)->create([
        'name' => 'Record File User',
        'email' => 'user_'.Str::lower(Str::random(8)).'@example.test',
        'password' => 'password123',
    ]);

    return app(TokenAuthService::class)->generateToken($user)->token;
}

function appendQueryToken(string $url, string $token): string
{
    $separator = str_contains($url, '?') ? '&' : '?';

    return "{$url}{$separator}token=".rawurlencode($token);
}

function fakeStorage(): void
{
    Storage::fake(config('filesystems.default', 'local'));
}

it('accepts richtext field values as plain strings', function () {
    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ['name' => 'content', 'type' => CollectionFieldType::RichText->value, 'nullable' => false],
    ]);

    $response = postJson("/api/collections/{$collection->id}/records", [
        'title' => 'Richtext Example',
        'content' => '<h1>Hello</h1><p>World</p>',
    ])->assertCreated();

    $response->assertJsonPath('data.content', '<h1>Hello</h1><p>World</p>');
});

it('accepts boolean multipart values when a file field is present', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ['name' => 'is_published', 'type' => CollectionFieldType::Boolean->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Boolean + File Create',
            'is_published' => 'true',
            'resume' => UploadedFile::fake()->create('resume.pdf', 150, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $createResponse->assertJsonPath('data.is_published', true);

    $recordId = (string) $createResponse->json('data.id');

    post(
        "/api/collections/{$collection->id}/records/{$recordId}",
        [
            '_method' => 'PATCH',
            'is_published' => 'false',
            'resume' => UploadedFile::fake()->create('resume-v2.pdf', 150, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )
        ->assertOk()
        ->assertJsonPath('data.is_published', false);

    $storedRecord = Record::of($collection)->findOrFail($recordId);

    expect((bool) $storedRecord->is_published)->toBeFalse();
});

it('drops file default and persists protected configuration in collection metadata', function () {
    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => true,
            'multiple' => false,
            'protected' => true,
            'default' => 'ignored-default-value',
        ],
    ]);

    $field = collect($collection->fields)->firstWhere('name', 'resume');

    expect($field)->not->toBeNull()
        ->and((bool) ($field['protected'] ?? false))->toBeTrue()
        ->and(array_key_exists('default', $field->toArray()))->toBeFalse();
});

it('stores single file fields as json arrays and returns normalized single object', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    $response = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Candidate Profile',
            'resume' => UploadedFile::fake()->create('resume.pdf', 150, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $response->assertJsonPath('data.resume.name', 'resume.pdf');
    $response->assertJsonPath('data.resume.mime', 'application/pdf');

    $stored = Record::of($collection)->firstOrFail();
    $raw = $stored->getRawOriginal('resume');
    $decoded = json_decode((string) $raw, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(1)
        ->and($decoded[0])->toHaveKeys(['name', 'path', 'size', 'extension', 'mime']);

    $storedPath = (string) $response->json('data.resume.path');
    expect((string) $decoded[0]['path'])->toBe($storedPath);

    expect($storedPath)->toContain('uploads/collections/'.$collection->name.'/');

    $disk = Storage::disk(config('filesystems.default', 'local'));
    expect($disk->exists($storedPath))->toBeTrue();
});

it('validates file mime and max size constraints', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 10,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Candidate Profile',
            'resume' => UploadedFile::fake()->create('avatar.png', 64, 'image/png'),
        ],
        ['Accept' => 'application/json']
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['resume']);

    expect(Record::of($collection)->count())->toBe(0);
});

it('validates min and max file count constraints for multiple file fields', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'attachments',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => true,
            'min' => 2,
            'max' => 2,
            'max_size_kb' => 2048,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Not Enough Files',
            'attachments' => [
                UploadedFile::fake()->create('one.pdf', 100, 'application/pdf'),
            ],
        ],
        ['Accept' => 'application/json']
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachments']);

    post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Too Many Files',
            'attachments' => [
                UploadedFile::fake()->create('one.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('three.pdf', 100, 'application/pdf'),
            ],
        ],
        ['Accept' => 'application/json']
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachments']);
});

it('applies append and remove operations for multiple file fields', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'attachments',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => true,
            'min' => 1,
            'max' => 3,
            'max_size_kb' => 2048,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Report Bundle',
            'attachments' => [
                UploadedFile::fake()->create('base.pdf', 100, 'application/pdf'),
            ],
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $recordId = (string) $createResponse->json('data.id');
    $basePath = (string) $createResponse->json('data.attachments.0.path');

    patchJson("/api/collections/{$collection->id}/records/{$recordId}", [
        'attachments+' => [
            [
                'name' => 'external.pdf',
                'path' => 'uploads/collections/'.$collection->name.'/external.pdf',
                'size' => 2048,
                'extension' => 'pdf',
                'mime' => 'application/pdf',
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.attachments');

    patchJson("/api/collections/{$collection->id}/records/{$recordId}", [
        'attachments-' => [
            ['path' => $basePath],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.attachments')
        ->assertJsonPath('data.attachments.0.path', 'uploads/collections/'.$collection->name.'/external.pdf');

    expect(Storage::disk(config('filesystems.default', 'local'))->exists($basePath))->toBeFalse();
});

it('replaces single file fields and removes the previous file', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Candidate Profile',
            'resume' => UploadedFile::fake()->create('resume-v1.pdf', 100, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $recordId = (string) $createResponse->json('data.id');
    $oldPath = (string) $createResponse->json('data.resume.path');

    $updateResponse = post(
        "/api/collections/{$collection->id}/records/{$recordId}",
        [
            '_method' => 'PATCH',
            'resume' => UploadedFile::fake()->create('resume-v2.pdf', 120, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )->assertOk();

    $newPath = (string) $updateResponse->json('data.resume.path');

    expect($newPath)->not->toBe($oldPath);

    expect(Storage::disk(config('filesystems.default', 'local'))->exists($oldPath))->toBeFalse();
    expect(Storage::disk(config('filesystems.default', 'local'))->exists($newPath))->toBeTrue();
});

it('deletes referenced files when deleting a record', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'resume',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['application/pdf'],
        ],
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Candidate Profile',
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $recordId = (string) $createResponse->json('data.id');
    $storedPath = (string) $createResponse->json('data.resume.path');

    deleteJson("/api/collections/{$collection->id}/records/{$recordId}")
        ->assertOk();

    expect(Storage::disk(config('filesystems.default', 'local'))->exists($storedPath))->toBeFalse();
});

it('requires a token to view protected files and allows bearer token access', function () {
    fakeStorage();

    $authCollection = createRecordFileAuthCollection();
    $token = issueRecordFileAuthToken($authCollection);

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ['name' => 'visibility', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'photo',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'protected' => true,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['image/png'],
        ],
    ], [
        'list' => '',
        'view' => 'visibility = "public"',
        'create' => '',
        'update' => '',
        'delete' => '',
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Public Asset',
            'visibility' => 'public',
            'photo' => UploadedFile::fake()->image('public.png', 120, 120),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $fileUrl = (string) $createResponse->json('data.photo.url');

    expect($fileUrl)->toContain('/api/collections/')
        ->and($createResponse->json('data.photo.protected'))->toBeTrue();

    $this->getJson($fileUrl)
        ->assertUnauthorized();

    $this->getJson($fileUrl, ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    // Verify it still works with query token too (graceful fallback)
    $this->getJson(appendQueryToken($fileUrl, $token))
        ->assertOk();
});

it('enforces view rules when opening protected files', function () {
    fakeStorage();

    $authCollection = createRecordFileAuthCollection();
    $token = issueRecordFileAuthToken($authCollection);

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        ['name' => 'visibility', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'photo',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'protected' => true,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['image/png'],
        ],
    ], [
        'list' => '',
        'view' => 'visibility = "public"',
        'create' => '',
        'update' => '',
        'delete' => '',
    ]);

    $createResponse = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Private Asset',
            'visibility' => 'private',
            'photo' => UploadedFile::fake()->image('private.png', 120, 120),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $fileUrl = (string) $createResponse->json('data.photo.url');

    $this->getJson(appendQueryToken($fileUrl, $token))
        ->assertNotFound();
});

it('keeps unprotected file URLs as direct storage URLs', function () {
    fakeStorage();

    $collection = createRecordFileCollection([
        ['name' => 'title', 'type' => CollectionFieldType::Text->value, 'nullable' => false],
        [
            'name' => 'photo',
            'type' => CollectionFieldType::File->value,
            'nullable' => false,
            'multiple' => false,
            'protected' => false,
            'min' => 1,
            'max' => 1,
            'max_size_kb' => 1024,
            'allowed_mime_types' => ['image/png'],
        ],
    ]);

    $response = post(
        "/api/collections/{$collection->id}/records",
        [
            'title' => 'Public Asset',
            'photo' => UploadedFile::fake()->image('public-direct.png', 120, 120),
        ],
        ['Accept' => 'application/json']
    )->assertCreated();

    $fileUrl = (string) $response->json('data.photo.url');

    expect($response->json('data.photo.protected'))->toBeFalse()
        ->and($fileUrl)->not->toContain('/files/');
});
