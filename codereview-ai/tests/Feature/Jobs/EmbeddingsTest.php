<?php

use Laravel\Ai\Testing\FakeAi;

test('search docs knowledge base returns results', function () {
    FakeAi::fake();

    FakeAi::embeddings()
        ->respondWith([[0.1, 0.2, 0.3, /* ...768 dims */]]);

    $tool = new \App\Ai\Tools\SearchDocsKnowledgeBase;
    $result = $tool->execute([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]);

    expect($result)->toContain('OWASP');
});
