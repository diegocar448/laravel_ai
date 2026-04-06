<?php

use App\Ai\Tools\SearchDocsKnowledgeBase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;


test('search docs knowledge base returns results', function () {
    // Importar um doc de teste no banco
    \App\Models\DocEmbedding::create([
        'source' => 'owasp',
        'title' => 'OWASP A03:2021 - Injection',
        'content' => 'SQL injection prevention techniques.',
        'category' => 'security',
        'embedding' => array_fill(0, 768, 0.1),
    ]);

    // Fake embeddings — retorna vetor identico ao do doc para garantir similaridade maxima
    Embeddings::fake([
        [array_fill(0, 768, 0.1)],
    ]);

    $tool = new SearchDocsKnowledgeBase;
    $result = $tool->handle(new Request([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]));

    expect($result)->toContain('OWASP');
});
