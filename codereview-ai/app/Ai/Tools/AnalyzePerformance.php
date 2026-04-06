<?php

namespace App\Ai\Tools;

use App\Ai\Agents\PerformanceAnalyst;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Tools\Request;

class AnalyzePerformance implements Tool
{
    public function description(): string
    {
        return 'Consult the Performance Analyst agent to identify bottlenecks, '
            . 'N+1 queries, caching opportunities and optimization suggestions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'context' => $schema->string()
                ->description('Code and context for performance analysis')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $response = (new PerformanceAnalyst)->prompt(
            $request->string('context'),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response);
    }
}
