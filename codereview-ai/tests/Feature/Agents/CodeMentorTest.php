<?php

use App\Ai\Agents\CodeMentor;
use App\Models\CodeReview;
use App\Models\Project;
use App\Models\ReviewFinding;
use App\Services\ImprovementPlanService;
use Laravel\Ai\Ai;

test('code mentor creates improvement plan', function () {
    $project = Project::factory()->create();
    $review = CodeReview::factory()->create(['project_id' => $project->id]);
    ReviewFinding::factory()->count(3)->flaggedByAgent()->create(['code_review_id' => $review->id]);

    Ai::fakeAgent(CodeMentor::class, ['Plano gerado com sucesso.']);

    $service = new ImprovementPlanService;
    $service->handle($project);

    $project->user->refresh();
    expect($project->user->first_plan_at)->not->toBeNull();
});
