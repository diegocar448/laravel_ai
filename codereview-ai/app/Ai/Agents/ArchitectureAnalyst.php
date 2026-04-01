<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Ai\Tools\SearchDocsKnowledgeBase;

class ArchitectureAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.architecture-analysis')->render();
    }

    public function tools(): iterable
    {
        return [
            new SearchDocsKnowledgeBase,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->description('Lista de findings arquiteturais')->required(),
            'summary' => $schema->string()->description('Resumo da analise arquitetural')->required(),
        ];
    }
}
