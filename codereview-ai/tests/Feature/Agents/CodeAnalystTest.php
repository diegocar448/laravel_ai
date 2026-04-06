<?php

use App\Ai\Agents\CodeAnalyst;
use App\Models\CodeReview;
use App\Models\Project;
use App\Services\CodeAnalysisService;
use Laravel\Ai\Ai;

test('code analyst returns structured output', function () {
    $project = Project::factory()->create(['language' => 'php']);
    $review = CodeReview::factory()->create(['project_id' => $project->id]);

    Ai::fakeAgent(CodeAnalyst::class, [
        [
            'summary' => '## Analise\nCodigo com boa estrutura.',
            'score' => 85,
            'priority_finding_ids' => [],
        ],
    ]);

    $service = new CodeAnalysisService;
    $service->handle($review);

    $review->refresh();
    expect($review->summary)->toContain('Analise');
    expect($review->review_status_id)->toBe(2); // Completed
});

test('code analysis handles agent failure', function () {
    $review = CodeReview::factory()->create();

    Ai::fakeAgent(CodeAnalyst::class, [
        fn () => throw new \Exception('API timeout'),
    ]);

    $service = new CodeAnalysisService;

    expect(fn () => $service->handle($review))->toThrow(\Exception::class);
});
