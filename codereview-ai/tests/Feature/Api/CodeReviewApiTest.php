<?php

use App\Jobs\AnalyzeCodeJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('can start code review', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$project->id}/reviews", [
            'architecture_strength' => 'Good patterns',
            'architecture_improvement' => 'Needs DI',
            'performance_strength' => 'Fast queries',
            'performance_improvement' => 'N+1 detected',
            'security_strength' => 'CSRF present',
            'security_improvement' => 'SQL injection risk',
        ])
        ->assertCreated()
        ->assertJsonPath('review_status_id', 1); // Pending

    Queue::assertPushed(AnalyzeCodeJob::class);
    $this->assertDatabaseCount('review_findings', 6);
});

test('cannot create duplicate review', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->codeReview()->create(['review_status_id' => 1]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$project->id}/reviews", [
            'architecture_strength' => 'x',
            'architecture_improvement' => 'x',
            'performance_strength' => 'x',
            'performance_improvement' => 'x',
            'security_strength' => 'x',
            'security_improvement' => 'x',
        ])
        ->assertConflict();
});
