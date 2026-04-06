<?php

use App\Jobs\AnalyzeCodeJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

test('full review flow: create project -> review -> findings', function () {
    Queue::fake();

    // 1. Registrar usuario
    Volt::test('pages/auth/register')
        ->set('form.name', 'Diego')
        ->set('form.email', 'diego@test.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'diego@test.com')->first();
    expect($user)->not->toBeNull();

    // 2. Criar projeto via API
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'E2E Test Project',
            'language' => 'php',
            'code_snippet' => str_repeat('class Test { }', 10),
        ]);

    $response->assertCreated();
    $projectId = $response->json('id');

    // 3. Iniciar code review
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$projectId}/reviews", [
            'architecture_strength' => 'Good',
            'architecture_improvement' => 'Needs refactor',
            'performance_strength' => 'OK',
            'performance_improvement' => 'N+1',
            'security_strength' => 'CSRF',
            'security_improvement' => 'SQL Injection',
        ])
        ->assertCreated();

    // 4. Verificar que job foi enfileirado
    Queue::assertPushed(AnalyzeCodeJob::class);

    // 5. Verificar estado no banco
    $this->assertDatabaseHas('projects', ['name' => 'E2E Test Project']);
    $this->assertDatabaseHas('code_reviews', ['project_id' => $projectId]);
    $this->assertDatabaseCount('review_findings', 6);
});
