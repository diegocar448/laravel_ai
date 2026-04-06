<?php

use App\Ai\Agents\CodeAnalyst;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use App\Services\CodeAnalysisService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;

test('analyze code job calls service', function () {
    Ai::fakeAgent(CodeAnalyst::class, [
        [
            'summary' => 'Analise completa.',
            'score' => 90,
            'priority_finding_ids' => [],
        ],
    ]);

    $review = CodeReview::factory()->create();
    AnalyzeCodeJob::dispatchSync($review);

    $review->refresh();
    expect($review->review_status_id)->toBe(2); // Completed
});

test('failed job updates status to failed', function () {
    Ai::fakeAgent(CodeAnalyst::class, [
        fn () => throw new \Exception('Timeout'),
    ]);

    $review = CodeReview::factory()->create();
    $job = new AnalyzeCodeJob($review);

    try {
        $job->handle(new CodeAnalysisService);
    } catch (\Exception $e) {
        $job->failed($e);
    }

    $review->refresh();
    expect($review->review_status_id)->toBe(3); // Failed
});

test('job is dispatched to queue', function () {
    Queue::fake();

    $review = CodeReview::factory()->create();
    AnalyzeCodeJob::dispatch($review);

    Queue::assertPushed(AnalyzeCodeJob::class, function ($job) use ($review) {
        return $job->codeReview->id === $review->id;
    });
});
