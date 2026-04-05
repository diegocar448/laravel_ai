<?php

namespace App\Jobs;

use App\Models\CodeReview;
use App\Services\CodeAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;          // Tentar ate 3 vezes
    public int $timeout = 120;       // Timeout de 2 minutos
    public int $backoff = 60;        // Aguardar 60s antes de cada retry (rate limit)

    public function __construct(
        public CodeReview $codeReview,
    ) {}

    public function handle(CodeAnalysisService $service): void
    {
        // CodeAnalysisService usa CodeAnalyst Agent (Capitulo 8)
        $service->handle($this->codeReview);
    }

    public function failed(\Throwable $exception): void
    {
        $this->codeReview->update([
            'review_status_id' => 3, // Failed
            'summary' => 'Erro ao analisar o codigo. Tente novamente.',
        ]);

        Log::error('Code analysis failed', [
            'code_review_id' => $this->codeReview->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
