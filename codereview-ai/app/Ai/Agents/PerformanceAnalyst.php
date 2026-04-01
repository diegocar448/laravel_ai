<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Ai\Tools\SearchDocsKnowledgeBase;

class PerformanceAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.performance-analysis')->render();
    }

    public function tools(): iterable
    {
        return [new SearchDocsKnowledgeBase];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->description('Lista de findings de performance')->required(),
            'summary' => $schema->string()->description('Resumo da analise de performance')->required(),
        ];
    }
}
