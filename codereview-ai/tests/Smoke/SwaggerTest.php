<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('api documentation is accessible', function () {
    Gate::before(fn () => true);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/docs/api')->assertOk();
});

test('openapi json is valid', function () {
    Gate::before(fn () => true);

    $admin = User::factory()->admin()->create();
    $response = $this->actingAs($admin)->getJson('/docs/api.json');
    $response->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('openapi');
    expect($data)->toHaveKey('paths');
});
