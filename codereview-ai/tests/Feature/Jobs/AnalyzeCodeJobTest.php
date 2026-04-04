<?php

use App\Ai\Agents\CodeAnalyst;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use Laravel\Ai\Testing\FakeAi;

test('analyze code job processes successfully', function () {
    // Fake todas as chamadas de IA
    FakeAi::fake();

    // Configurar resposta esperada do Agent
    FakeAi::agent(CodeAnalyst::class)
        ->respondWith([
            'summary' => 'Codigo analisado com sucesso.',
            'score' => 85,
            'priority_finding_ids' => [1, 2, 3],
        ]);

    $codeReview = CodeReview::factory()->create();

    // Executar o job
    AnalyzeCodeJob::dispatch($codeReview);

    // Assertions
    $codeReview->refresh();
    expect($codeReview->summary)->toBe('Codigo analisado com sucesso.');
    expect($codeReview->review_status_id)->toBe(2); // Completed
});

test('analyze code job handles failure', function () {
    FakeAi::fake();

    FakeAi::agent(CodeAnalyst::class)
        ->throwException(new \Exception('API timeout'));

    $codeReview = CodeReview::factory()->create();

    AnalyzeCodeJob::dispatch($codeReview);

    $codeReview->refresh();
    expect($codeReview->review_status_id)->toBe(3); // Failed
});
