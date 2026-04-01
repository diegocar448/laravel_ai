<?php

namespace App\Ai\Tools;

use App\Ai\Agents\ArchitectureAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzeArchitecture implements Tool
{
    public function name(): string
    {
        return 'analyze_architecture';
    }

    public function description(): string
    {
        return 'Consult the Architecture Analyst agent to evaluate design patterns, '
            . 'SOLID principles, Clean Code, coupling and cohesion in the code.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for architectural analysis');
    }

    public function execute(array $parameters): string
    {
        // Padrao Agent-as-Tool: instancia o sub-Agent e chama prompt()
        // Usa modelo mais leve (flash-lite) para economia
        $response = (new ArchitectureAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
