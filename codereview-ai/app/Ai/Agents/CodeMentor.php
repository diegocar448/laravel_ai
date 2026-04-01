<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AnalyzeArchitecture;
use App\Ai\Tools\AnalyzePerformance;
use App\Ai\Tools\AnalyzeSecurity;
use App\Ai\Tools\SearchDocsKnowledgeBase;
use App\Ai\Tools\StoreImprovements;
use App\Models\Project;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class CodeMentor implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public Project $project,
    ) {}

    public function instructions(): string
    {
        return view('prompts.code-mentor', [
            'project' => $this->project,
            'codeReview' => $this->project->codeReview,
        ])->render();
    }

    public function tools(): iterable
    {
        return [
            new AnalyzeArchitecture,
            new AnalyzePerformance,
            new AnalyzeSecurity,
            new SearchDocsKnowledgeBase,
            new StoreImprovements($this->project),
        ];
    }
}
