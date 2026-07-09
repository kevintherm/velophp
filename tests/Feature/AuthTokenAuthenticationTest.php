<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Support\Models\Tenant;
use function Pest\Laravel\{postJson, getJson, deleteJson};

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createAuthCollection(string $name): Collection
{
    return Collection::create([
        'name' => $name,
        'type' => 'auth',
        'fields' => [
            ['name' => 'email', 'type' => 'email', 'unique' => true],
            ['name' => 'password', 'type' => 'text'],
        ],
        'options' => [
            'auth_methods' => [
                'standard' => [
                    'enabled' => true,
                    'identity_fields' => ['email'],
                ],
            ],
        ],
    ]);
}

function createAuthRecord(Collection $collection, string $email, string $password): Record
{
    return Record::of($collection)->create([
        'email' => $email,
        'password' => $password,
    ]);
}

function bearerHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
    ];
}

beforeEach(function () {
    config()->set('velo.auth.token.max_active_tokens', 0);

    $tenant = Tenant::query()->updateOrCreate(['domain' => 'localhost'], [
        'id' => 1001,
        'name' => 'test-tenant',
        'database' => ':memory:',
    ]);

    $tenant->makeCurrent();

    $landlordConnection = (string) config('multitenancy.landlord_database_connection_name', 'landlord');

    if (! Schema::hasTable('schema_jobs')) {
        Schema::create('schema_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('collection_id');
            $table->string('status');
            $table->json('payload');
            $table->timestamps();
        });
    }

    if (! Schema::connection($landlordConnection)->hasTable('realtime_subscriptions')) {
        Schema::connection($landlordConnection)->create('realtime_subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('collection_id');
            $table->string('record_id')->nullable();
            $table->string('event');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
});

it('returns opaque login payload keys and omits refresh fields', function () {
    $collection = createAuthCollection('auth_users_a');
    $user = createAuthRecord($collection, 'test@example.test', 'password123');

    postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'token',
                'expires_in',
                'collection_name',
            ],
        ])
        ->assertJsonMissingPath('data.refresh_token')
        ->assertJsonMissingPath('data.refresh_expires_in');
});

it('authenticates me endpoint with a valid opaque token', function () {
    $collection = createAuthCollection('auth_users_b');
    $user = createAuthRecord($collection, 'bob@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');

    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects cross-collection me authentication with a token from another collection', function () {
    $collectionA = createAuthCollection('auth_users_c1');
    $collectionB = createAuthCollection('auth_users_c2');

    $userA = createAuthRecord($collectionA, 'user-a@example.test', 'password123');
    $userB = createAuthRecord($collectionB, 'user-b@example.test', 'password123');

    $loginA = postJson("/api/collections/{$collectionA->name}/auth/login", [
        'identity' => $userA->email,
        'password' => 'password123',
    ]);

    $tokenA = $loginA->json('data.token');

    // Try to authenticate with token A on collection B's me endpoint
    $this->withToken($tokenA)
        ->getJson("/api/collections/{$collectionB->name}/auth/me")
        ->assertUnauthorized();
});

it('revokes all active tokens on logout-all', function () {
    $collection = createAuthCollection('auth_users_d');
    $user = createAuthRecord($collection, 'logout@example.test', 'password123');

    $loginA = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenA = $loginA->json('data.token');

    $loginB = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenB = $loginB->json('data.token');

    AuthToken::query()
        ->forRecord($collection->id, $user->id)
        ->update(['expires_at' => now()->addMinute()]);

    $this->withToken($tokenA)
        ->deleteJson("/api/collections/{$collection->name}/auth/logout-all")
        ->assertSuccessful();

    Auth::guard('api')->forgetUser();

    $this->withToken($tokenA)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();

    $this->withToken($tokenB)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

it('rejects expired tokens', function () {
    $collection = createAuthCollection('auth_users_f');
    $user = createAuthRecord($collection, 'expired@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $tokenData = (object) $login->json('data');

    AuthToken::query()
        ->forRecord($collection->id, $user->id)
        ->update(['expires_at' => now()->subMinute()]);

    $this->withToken($tokenData->token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

it('returns not found for removed refresh endpoint', function () {
    $collection = createAuthCollection('auth_users_g');

    postJson("/api/collections/{$collection->name}/auth/refresh")
        ->assertNotFound();
});

it('enforces max active tokens when set to one', function () {
    config()->set('velo.auth.token.max_active_tokens', 1);

    $collection = createAuthCollection('auth_users_h');
    $user = createAuthRecord($collection, 'max@example.test', 'password123');

    $firstToken = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])->json('data.token');

    $secondToken = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ])->json('data.token');

    Auth::guard('api')->forgetUser();

    $this->withToken($firstToken)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();

    $this->withToken($secondToken)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();
});

it('slides token expiration when remaining lifetime is below the threshold', function () {
    Carbon\Carbon::setTestNow('2026-06-11 12:00:00');

    config()->set('velo.auth.token.expiration', 60);
    config()->set('velo.auth.token.slide', true);
    config()->set('velo.auth.token.slide_ratio', 0.5);

    $collection = createAuthCollection('auth_users_sliding');
    $user = createAuthRecord($collection, 'slide@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');

    // 1. Initial request (cache miss).
    // This will write to cache with expires_at = 12:01:00.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    // Verify initial expiration time in DB
    $dbToken = AuthToken::where('token_hash', hash('sha256', $token))->first();
    expect($dbToken->expires_at->toDateTimeString())->toBe('2026-06-11 12:01:00');

    // 2. Request at 12:00:20 (remaining = 40s, which is > 50% threshold of 30s).
    // It should NOT slide.
    Carbon\Carbon::setTestNow('2026-06-11 12:00:20');
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    $dbToken = AuthToken::where('token_hash', hash('sha256', $token))->first();
    expect($dbToken->expires_at->toDateTimeString())->toBe('2026-06-11 12:01:00');

    // 3. Request at 12:00:40 (remaining = 20s, which is < 50% threshold of 30s).
    // It SHOULD slide by 60s to 12:01:40.
    Carbon\Carbon::setTestNow('2026-06-11 12:00:40');
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    $dbToken = AuthToken::where('token_hash', hash('sha256', $token))->first();
    expect($dbToken->expires_at->toDateTimeString())->toBe('2026-06-11 12:01:40');

    // 4. Request at 12:01:10 (past the original 12:01:00 expiration).
    // Because it slid to 12:01:40, this should still be successful!
    Carbon\Carbon::setTestNow('2026-06-11 12:01:10');
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    // 5. Request at 12:02:00 (past the slid 12:01:40 expiration).
    // This should fail (unauthorized).
    Carbon\Carbon::setTestNow('2026-06-11 12:02:00');
    Auth::guard('api')->forgetUser();

    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();

    Carbon\Carbon::setTestNow();
});

it('synchronizes revocation immediately and clears the cache if DB update fails', function () {
    $collection = createAuthCollection('auth_users_sync');
    $user = createAuthRecord($collection, 'sync@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');
    $hashedToken = hash('sha256', $token);
    $cacheKey = "velo:auth:{$hashedToken}";

    // 1. Initial request to ensure the token gets cached.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    // Verify cache hit exists
    expect(Cache::has($cacheKey))->toBeTrue();

    // 2. Simulate revocation directly in the database (e.g. by another process or admin).
    AuthToken::where('token_hash', $hashedToken)->update(['revoked_at' => now()]);

    // 3. Make another request. The request gets a cache hit, but its terminating callback
    // detects that the DB update failed (due to revoked_at not being null) and evicts the cache.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    // The cache must be cleared now
    expect(Cache::has($cacheKey))->toBeFalse();

    // 4. A subsequent request must be rejected instantly because the cache is cleared.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

it('invalidates token cache when user record is updated', function () {
    $collection = createAuthCollection('auth_users_update_test');
    $user = createAuthRecord($collection, 'update-test@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');
    $hashedToken = hash('sha256', $token);
    $cacheKey = "velo:auth:{$hashedToken}";

    // 1. Initial request to ensure the token gets cached.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'update-test@example.test');

    // Verify cache hit exists
    expect(Cache::has($cacheKey))->toBeTrue();

    // 2. Update the user record.
    $user->update(['email' => 'updated-test@example.test']);

    // 3. The cache must be cleared now.
    expect(Cache::has($cacheKey))->toBeFalse();

    // 4. A subsequent request should be successful and retrieve updated data.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'updated-test@example.test');
});

it('invalidates token cache and revokes tokens in DB when user record is deleted', function () {
    $collection = createAuthCollection('auth_users_delete_test');
    $user = createAuthRecord($collection, 'delete-test@example.test', 'password123');

    $login = postJson("/api/collections/{$collection->name}/auth/login", [
        'identity' => $user->email,
        'password' => 'password123',
    ]);

    $token = $login->json('data.token');
    $hashedToken = hash('sha256', $token);
    $cacheKey = "velo:auth:{$hashedToken}";

    // 1. Initial request to ensure the token gets cached.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertSuccessful();

    // Verify cache hit exists
    expect(Cache::has($cacheKey))->toBeTrue();

    // 2. Delete the user record.
    $user->delete();

    // 3. The cache must be cleared now.
    expect(Cache::has($cacheKey))->toBeFalse();

    // 4. DB token must be revoked.
    $dbToken = AuthToken::where('token_hash', $hashedToken)->first();
    expect($dbToken->revoked_at)->not->toBeNull();

    // 5. A subsequent request should be unauthorized.
    Auth::guard('api')->forgetUser();
    $this->withToken($token)
        ->getJson("/api/collections/{$collection->name}/auth/me")
        ->assertUnauthorized();
});

