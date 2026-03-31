<?php

namespace App\Livewire\Forms;

use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use App\Models\Project;
use Livewire\Form;

class CodeReviewForm extends Form
{
    public string $architecture_strength = '';
    public string $architecture_improvement = '';
    public string $performance_strength = '';
    public string $performance_improvement = '';
    public string $security_strength = '';
    public string $security_improvement = '';

    public function store(Project $project): CodeReview
    {
        $this->validate();

        $codeReview = $project->codeReview()->create([
            'review_status_id' => 1, // Pending
        ]);

        $this->createFindings($codeReview);

        // Disparar o job de IA (assincrono)
        AnalyzeCodeJob::dispatch($codeReview);

        auth()->user()->update(['first_review_at' => now()]);

        return $codeReview;
    }

    private function createFindings(CodeReview $codeReview): void
    {
        $findings = [
            ['pillar' => 1, 'type' => 1, 'desc' => $this->architecture_strength],
            ['pillar' => 1, 'type' => 2, 'desc' => $this->architecture_improvement],
            ['pillar' => 2, 'type' => 1, 'desc' => $this->performance_strength],
            ['pillar' => 2, 'type' => 2, 'desc' => $this->performance_improvement],
            ['pillar' => 3, 'type' => 1, 'desc' => $this->security_strength],
            ['pillar' => 3, 'type' => 2, 'desc' => $this->security_improvement],
        ];

        foreach ($findings as $finding) {
            $codeReview->findings()->create([
                'review_pillar_id' => $finding['pillar'],
                'finding_type_id' => $finding['type'],
                'description' => $finding['desc'],
            ]);
        }
    }
}
