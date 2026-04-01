<?php

namespace App\Ai\Tools;

use App\Ai\Agents\PerformanceAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzePerformance implements Tool
{
    public function name(): string
    {
        return 'analyze_performance';
    }

    public function description(): string
    {
        return 'Consult the Performance Analyst agent to identify bottlenecks, '
            . 'N+1 queries, caching opportunities and optimization suggestions.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for performance analysis');
    }

    public function execute(array $parameters): string
    {
        $response = (new PerformanceAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
