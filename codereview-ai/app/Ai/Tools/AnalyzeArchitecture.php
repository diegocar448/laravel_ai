<?php

namespace App\Ai\Tools;

use App\Ai\Agents\ArchitectureAnalyst;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Tools\Request;

class AnalyzeArchitecture implements Tool
{
    public function description(): string
    {
        return 'Consult the Architecture Analyst agent to evaluate design patterns, '
            . 'SOLID principles, Clean Code, coupling and cohesion in the code.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'context' => $schema->string()
                ->description('Code and context for architectural analysis')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $response = (new ArchitectureAnalyst)->prompt(
            $request->string('context'),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response);
    }
}
