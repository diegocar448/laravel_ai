<?php

namespace App\Services;

use App\Ai\Agents\CodeAnalyst;
use App\Models\CodeReview;
use Laravel\Ai\Enums\Lab;

class CodeAnalysisService
{
    public function handle(CodeReview $codeReview): void
    {
        // 1. Carregar relacionamentos
        $codeReview->load(['project', 'findings.type', 'findings.pillar']);

        // 2. Chamar o Agent com Structured Output
        // No metodo handle() do CodeAnalysisService, substitua o prompt() por:
        $response = (new CodeAnalyst($codeReview))->prompt(
            $this->buildContext($codeReview),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash',
            failover: [
                Lab::OpenAI => 'gpt-4o-mini',
                Lab::Anthropic => 'claude-haiku-4-5-20251001',
            ],
        );

        // 3. Resposta sempre tipada — salvar direto no DB
        $codeReview->update([
            'summary' => $response['summary'],
            'review_status_id' => 2, // Completed
        ]);

        // 4. Marcar findings prioritarios
        foreach ($response['priority_finding_ids'] as $id) {
            $codeReview->findings()
                ->where('id', $id)
                ->update(['agent_flagged_at' => now()]);
        }
    }

    private function buildContext(CodeReview $codeReview): string
    {
        return json_encode([
            'project' => [
                'name' => $codeReview->project->name,
                'language' => $codeReview->project->language,
                'code' => $codeReview->project->code_snippet,
                'repository_url' => $codeReview->project->repository_url,
            ],
            'findings' => $codeReview->findings->map(fn ($finding) => [
                'id' => $finding->id,
                'pillar' => $finding->pillar->name,
                'type' => $finding->type->name,
                'description' => $finding->description,
                'severity' => $finding->severity,
            ])->toArray(),
        ]);
    }
}
