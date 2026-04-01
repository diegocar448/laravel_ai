<?php

namespace App\Ai\Tools;

use App\Models\DocEmbedding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Pgvector\Laravel\Distance;

class SearchDocsKnowledgeBase implements Tool
{
    public function description(): string
    {
        return 'Search PSRs, OWASP guides and Laravel documentation by semantic similarity. '
            . 'Use this to find relevant documentation before making recommendations.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search query describing what documentation to find')
                ->required(),
            'category' => $schema->string()
                ->description('Filter by category: architecture, performance, or security'),
        ];
    }

    public function handle(Request $request): string
    {
        // 1. Converter a query em vetor com cache
        $result = Embeddings::for([$request->string('query')])
                ->cache()
                ->dimensions(768)
                ->generate(Lab::Gemini, 'gemini-embedding-001');

        $queryVector = $result->first();

        // 2. Buscar os 5 docs mais similares via pgvector
        $docsQuery = DocEmbedding::query()
            ->nearestNeighbors('embedding', $queryVector, Distance::Cosine);

        if ($request->has('category')) {
            $docsQuery->where('category', $request->string('category'));
        }

        $docs = $docsQuery->take(5)->get();

        if ($docs->isEmpty()) {
            return 'Nenhuma documentacao encontrada para a query informada.';
        }

        // 3. Formatar o resultado como contexto para o Agent
        return $docs->map(function ($doc) {
            return "[{$doc->source}] {$doc->title}\n{$doc->content}";
        })->implode("\n\n---\n\n");
    }
}
