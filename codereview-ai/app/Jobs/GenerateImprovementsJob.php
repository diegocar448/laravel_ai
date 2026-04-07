<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ImprovementPlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateImprovementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;       // 5 minutos (multi-agent e mais lento)
    public int $backoff = 90;        // Aguardar 90s antes de cada retry (rate limit Gemini)

    public function __construct(
        public Project $project,
    ) {}

    public function handle(ImprovementPlanService $service): void
    {
        // ImprovementPlanService usa CodeMentor Agent (Capitulo 10)
        $service->handle($this->project);
    }
}
