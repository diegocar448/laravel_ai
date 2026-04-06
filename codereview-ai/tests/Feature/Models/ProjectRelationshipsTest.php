<?php

use App\Models\CodeReview;
use App\Models\Improvement;
use App\Models\Project;
use App\Models\ReviewFinding;
use App\Models\User;

test('user has many projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->projects)->toHaveCount(3);
    expect($user->projects->first())->toBeInstanceOf(Project::class);
});

test('project has one code review', function () {
    $project = Project::factory()->create();
    $review = CodeReview::factory()->create(['project_id' => $project->id]);

    expect($project->codeReview->id)->toBe($review->id);
});

test('project has many improvements', function () {
    $project = Project::factory()->create();
    Improvement::factory()->count(5)->create(['project_id' => $project->id]);

    expect($project->improvements)->toHaveCount(5);
});

test('code review has many findings', function () {
    $review = CodeReview::factory()->create();
    ReviewFinding::factory()->count(6)->create(['code_review_id' => $review->id]);

    expect($review->findings)->toHaveCount(6);
});

test('deleting user cascades to projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(Project::find($project->id))->toBeNull();
});
