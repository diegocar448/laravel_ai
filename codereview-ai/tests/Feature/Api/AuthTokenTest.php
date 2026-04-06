<?php

use App\Models\User;

test('can generate api token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/token', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'test',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);
});

test('token generation fails with wrong credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/token', [
        'email' => $user->email,
        'password' => 'wrong',
        'device_name' => 'test',
    ])->assertUnprocessable();
});
