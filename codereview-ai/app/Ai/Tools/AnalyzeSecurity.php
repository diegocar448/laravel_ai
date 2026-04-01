<?php

namespace App\Ai\Tools;

use App\Ai\Agents\SecurityAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzeSecurity implements Tool
{
    public function name(): string
    {
        return 'analyze_security';
    }

    public function description(): string
    {
        return 'Consult the Security Analyst agent to identify vulnerabilities, '
            . 'OWASP Top 10 issues and security best practices violations.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for security analysis');
    }

    public function execute(array $parameters): string
    {
        $response = (new SecurityAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
