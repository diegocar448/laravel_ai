<?php

namespace App\Services;

use App\Ai\Agents\CodeMentor;
use App\Models\Project;
use Laravel\Ai\Enums\Lab;

class ImprovementPlanService
{
    public function handle(Project $project): void
    {
        $project->load(['codeReview.findings' => function ($query) {
            $query->whereNotNull('agent_flagged_at')
                  ->orWhereNotNull('user_flagged_at');
        }]);

        // Uma unica chamada — o Agent orquestra tudo via Tools
        $response = (new CodeMentor($project))->prompt(
            $this->buildPrompt($project),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash',
        );

        // Marcar que o plano foi criado
        $project->user->update(['first_plan_at' => now()]);
    }

    private function buildPrompt(Project $project): string
    {
        $priorityFindings = $project->codeReview->findings
            ->filter(fn ($f) => $f->agent_flagged_at || $f->user_flagged_at);

        return "Gere um plano de melhorias para o projeto:\n"
            . "Projeto: {$project->name}\n"
            . "Linguagem: {$project->language}\n\n"
            . "Codigo:\n```{$project->language}\n{$project->code_snippet}\n```\n\n"
            . "Findings prioritarios do code review:\n"
            . $priorityFindings->map(fn ($f) =>
                "- [{$f->pillar->name}] [{$f->severity}] {$f->description}"
            )->implode("\n");
    }
}
