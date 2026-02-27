<?php

use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users can register', function () {
    $response = $this->post('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertStatus(200);
});

test('users can login', function () {
    $user = Superuser::factory()->create();
    $response = $this->post('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertStatus(200);
});

test('users cannot login with invalid password', function () {
    $user = Superuser::factory()->create();

    $this->post('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = Superuser::factory()->create();

    $response = $this->actingAs($user, 'web')->post('/api/logout');

    $this->assertGuest('web');
    $response->assertStatus(200);
});

test('can get authenticated user', function () {
    $user = Superuser::factory()->create();

    $response = $this->actingAs($user, 'web')->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
});
