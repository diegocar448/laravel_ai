<?php

use App\Models\Project;
use App\Models\User;

test('can list own projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['user_id' => $user->id]);
    Project::factory()->count(2)->create(); // outros usuarios

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can create project via api', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'API de Pagamentos',
            'language' => 'php',
            'code_snippet' => str_repeat('x', 50),
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'API de Pagamentos');

    $this->assertDatabaseHas('projects', ['name' => 'API de Pagamentos']);
});

test('cannot create project with invalid language', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'Test',
            'language' => 'brainfuck',
            'code_snippet' => str_repeat('x', 50),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');
});

test('cannot view another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/projects/{$otherProject->id}")
        ->assertForbidden();
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});
