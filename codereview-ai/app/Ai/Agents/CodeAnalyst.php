<?php

namespace App\Ai\Agents;

use App\Models\CodeReview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class CodeAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public CodeReview $codeReview,
    ) {}

    /**
     * System prompt — define o papel do Agent.
     * Usa template Blade para interpolar dados do projeto.
     */
    public function instructions(): string
    {
        return view('prompts.code-review-system-prompt', [
            'codeReview' => $this->codeReview,
        ])->render();
    }

    /**
     * Schema da resposta — forca o LLM a responder neste formato.
     * O SDK converte para JSON Schema e envia ao provider.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('Analise completa em markdown do code review')
                ->required(),
            'score' => $schema->integer()
                ->min(0)->max(100)
                ->description('Score geral do codigo (0-100)')
                ->required(),
            'priority_finding_ids' => $schema->array()
                ->items($schema->integer())
                ->description('IDs dos 3 findings mais criticos')
                ->required(),
        ];
    }
}
