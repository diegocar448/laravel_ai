<?php

namespace App\Ai\Tools;

use App\Ai\Agents\SecurityAnalyst;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Tools\Request;

class AnalyzeSecurity implements Tool
{
    public function description(): string
    {
        return 'Consult the Security Analyst agent to identify vulnerabilities, '
            . 'OWASP Top 10 issues and security best practices violations.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'context' => $schema->string()
                ->description('Code and context for security analysis')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $response = (new SecurityAnalyst)->prompt(
            $request->string('context'),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response);
    }
}
