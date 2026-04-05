<?php

use App\Ai\Agents\CodeAnalyst;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use Laravel\Ai\Ai;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(fn () => (new Database\Seeders\LookupSeeder)->run());

test('analyze code job processes successfully', function () {
    // Fake chamadas do Agent com resposta estruturada
    Ai::fakeAgent(CodeAnalyst::class, [
        [
            'summary' => 'Codigo analisado com sucesso.',
            'score' => 85,
            'priority_finding_ids' => [1, 2, 3],
        ],
    ]);

    $codeReview = CodeReview::factory()->create();

    // Executar o job (queue=sync em testes)
    AnalyzeCodeJob::dispatch($codeReview);

    // Assertions
    $codeReview->refresh();
    expect($codeReview->summary)->toBe('Codigo analisado com sucesso.');
    expect($codeReview->review_status_id)->toBe(2); // Completed
});

test('analyze code job handles failure', function () {
    // Fake Agent lancando excecao
    Ai::fakeAgent(CodeAnalyst::class, [
        fn () => throw new \Exception('API timeout'),
    ]);

    $codeReview = CodeReview::factory()->create();

    // O job falha mas o failed() atualiza o status
    try {
        AnalyzeCodeJob::dispatch($codeReview);
    } catch (\Throwable) {
        // Em modo sync a excecao propaga — o failed() ja foi chamado
    }

    $codeReview->refresh();
    expect($codeReview->review_status_id)->toBe(3); // Failed
});
