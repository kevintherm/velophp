<?php

test('it can fetch collections', function () {
    $response = $this->getJson('/api/collections');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => ['id', 'name', 'icon'],
            ],
        ])
        ->assertJson([
            'status' => 'success',
            'message' => 'Collections retrieved successfully',
        ]);
});

test('it handles not found routes with json', function () {
    $response = $this->getJson('/api/non-existent-route');

    $response->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Resource not found',
        ]);
});
