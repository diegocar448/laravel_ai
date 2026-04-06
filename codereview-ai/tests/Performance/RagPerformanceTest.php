<?php

use App\Models\DocEmbedding;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;

test('pgvector query returns in under 500ms with 1000 docs', function () {
    DocEmbedding::factory()->count(1000)->create();

    $queryVector = new Vector(
        array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 768))
    );

    $start = microtime(true);

    $results = DocEmbedding::query()
        ->nearestNeighbors('embedding', $queryVector, Distance::Cosine)
        ->take(5)
        ->get();

    $elapsed = (microtime(true) - $start) * 1000;

    expect($results)->toHaveCount(5);
    expect($elapsed)->toBeLessThan(500, "Query took {$elapsed}ms, expected < 500ms");
});

test('bulk embedding creation is efficient', function () {
    $start = microtime(true);

    DocEmbedding::factory()->count(100)->create();

    $elapsed = (microtime(true) - $start) * 1000;

    expect($elapsed)->toBeLessThan(5000, "Bulk create took {$elapsed}ms");
    expect(DocEmbedding::count())->toBe(100);
});
