<?php

use App\Ai\Tools\SearchDocsKnowledgeBase;
use App\Models\DocEmbedding;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

test('search docs returns relevant results', function () {
    DocEmbedding::create([
        'source' => 'OWASP',
        'title' => 'SQL Injection Prevention',
        'content' => 'Always use parameterized queries to prevent injection.',
        'category' => 'security',
        'embedding' => array_fill(0, 768, 0.1),
    ]);

    Embeddings::fake([
        [array_fill(0, 768, 0.1)],
    ]);

    $tool = new SearchDocsKnowledgeBase;
    $result = $tool->handle(new Request([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]));

    expect($result)->toBeString();
    expect($result)->toContain('OWASP');
});

test('search docs returns message when no results found', function () {
    Embeddings::fake([
        [array_fill(0, 768, 0.9)],
    ]);

    $tool = new SearchDocsKnowledgeBase;
    $result = $tool->handle(new Request([
        'query' => 'nonexistent topic',
    ]));

    expect($result)->toBeString();
});
