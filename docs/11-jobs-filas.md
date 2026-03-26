# Capitulo 11 — Jobs, Filas e Processamento

> **Este capitulo cobre o pilar: AI Infrastructure (8)**

## Por que usar filas?

Chamadas de IA sao **lentas** (5-30 segundos cada). O fluxo multi-agent pode levar mais de 1 minuto. Se fosse sincrono, o usuario ficaria olhando uma tela branca esperando.

```
Sem fila (sincrono):
Clique -> [~60s aguardando] -> Resultado
Timeout, UX ruim

Com fila (assincrono):
Clique -> "Analisando codigo..." -> [background] -> Resultado aparece
UX responsiva
```

## Configuracao de filas

```env
# .env
QUEUE_CONNECTION=database
```

```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => env('DB_QUEUE_TABLE', 'jobs'),
        'queue' => env('DB_QUEUE', 'default'),
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

A migration `create_jobs_table` ja cria as tabelas necessarias:
- `jobs` — jobs pendentes
- `job_batches` — batches de jobs
- `failed_jobs` — jobs que falharam

## AnalyzeCodeJob

```php
// app/Jobs/AnalyzeCodeJob.php

namespace App\Jobs;

use App\Models\CodeReview;
use App\Services\CodeAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;          // Tentar ate 3 vezes
    public int $timeout = 120;       // Timeout de 2 minutos

    public function __construct(
        public CodeReview $codeReview,
    ) {}

    public function handle(CodeAnalysisService $service): void
    {
        // CodeAnalysisService usa CodeAnalyst Agent (Capitulo 8)
        $service->handle($this->codeReview);
    }
}
```

### Dispatch do job

```php
// No CodeReviewForm::store() ou em um Volt component
AnalyzeCodeJob::dispatch($codeReview);
```

## GenerateImprovementsJob

```php
// app/Jobs/GenerateImprovementsJob.php

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

    public function __construct(
        public Project $project,
    ) {}

    public function handle(ImprovementPlanService $service): void
    {
        // ImprovementPlanService usa CodeMentor Agent (Capitulo 10)
        $service->handle($this->project);
    }
}
```

O `GenerateImprovementsJob` tem timeout maior (300s) porque o fluxo multi-agent faz ~7 chamadas de API sequenciais.

## Worker de filas

Para processar os jobs, o worker precisa estar rodando:

```bash
# Desenvolvimento
sail artisan queue:work --tries=3

# Com mais detalhes
sail artisan queue:work --tries=3 -v

# Processar apenas jobs especificos
sail artisan queue:work --queue=default
```

### Monitorando com Laravel Pail

O projeto inclui `laravel/pail` para monitorar logs em tempo real:

```bash
sail artisan pail

# Filtrar por nivel
sail artisan pail --filter="level:error"
```

## Feedback ao usuario com Livewire

Enquanto o job processa em background, a pagina usa **polling** para atualizar:

```php
<?php
// resources/views/pages/reviews/show.blade.php (conceito)

new class extends Component
{
    public CodeReview $codeReview;

    public function with(): array
    {
        return [
            'codeReview' => $this->codeReview->fresh(),
            'isPending' => $this->codeReview->review_status_id === 1,
        ];
    }
}
?>

{{-- Se pendente, faz polling a cada 5 segundos --}}
@if($isPending)
    <div wire:poll.5s>
        <x-card>
            <x-card.body class="text-center">
                <div class="animate-spin h-8 w-8 border-4 border-indigo-600
                            border-t-transparent rounded-full mx-auto"></div>
                <p class="mt-4 text-gray-600">Analisando seu codigo...</p>
                <p class="text-sm text-gray-400">
                    3 Agents de IA estao revisando arquitetura, performance e seguranca
                </p>
            </x-card.body>
        </x-card>
    </div>
@else
    {{-- Resultado do code review --}}
    <x-card>
        <x-card.body>
            {!! Str::markdown($codeReview->summary) !!}
        </x-card.body>
    </x-card>

    {{-- Findings por pilar --}}
    @foreach($codeReview->findings as $finding)
        <x-card class="mt-4">
            <x-card.header class="flex justify-between">
                <span>{{ $finding->pillar->name }}</span>
                <x-severity-badge :severity="$finding->severity" />
            </x-card.header>
            <x-card.body>{{ $finding->description }}</x-card.body>
        </x-card>
    @endforeach
@endif
```

## Tratamento de falhas

### Retry automatico

Com `$tries = 3`, o Laravel tenta o job ate 3 vezes antes de mover para `failed_jobs`.

### Notificando o usuario sobre falha

```php
class AnalyzeCodeJob implements ShouldQueue
{
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
```

---

## Testando com FakeAi

O Laravel AI SDK oferece `FakeAi` para testar sem chamar APIs reais:

```php
use Laravel\Ai\Testing\FakeAi;
use App\Ai\Agents\CodeAnalyst;
use App\Jobs\AnalyzeCodeJob;

test('analyze code job processes successfully', function () {
    // Fake todas as chamadas de IA
    FakeAi::fake();

    // Configurar resposta esperada do Agent
    FakeAi::agent(CodeAnalyst::class)
        ->respondWith([
            'summary' => 'Codigo analisado com sucesso.',
            'score' => 85,
            'priority_finding_ids' => [1, 2, 3],
        ]);

    $codeReview = CodeReview::factory()->create();

    // Executar o job
    AnalyzeCodeJob::dispatch($codeReview);

    // Assertions
    $codeReview->refresh();
    expect($codeReview->summary)->toBe('Codigo analisado com sucesso.');
    expect($codeReview->review_status_id)->toBe(2); // Completed
});

test('analyze code job handles failure', function () {
    FakeAi::fake();

    FakeAi::agent(CodeAnalyst::class)
        ->throwException(new \Exception('API timeout'));

    $codeReview = CodeReview::factory()->create();

    AnalyzeCodeJob::dispatch($codeReview);

    $codeReview->refresh();
    expect($codeReview->review_status_id)->toBe(3); // Failed
});
```

### Testando Embeddings

```php
test('search docs knowledge base returns results', function () {
    FakeAi::fake();

    FakeAi::embeddings()
        ->respondWith([[0.1, 0.2, 0.3, /* ...768 dims */]]);

    $tool = new SearchDocsKnowledgeBase;
    $result = $tool->execute([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]);

    expect($result)->toContain('OWASP');
});
```

---

## Events para monitoring

O Laravel AI SDK dispara events que voce pode ouvir:

```php
// app/Listeners/LogAgentPrompt.php
use Laravel\Ai\Events\AgentPrompted;

class LogAgentPrompt
{
    public function handle(AgentPrompted $event): void
    {
        Log::info('Agent prompted', [
            'agent' => get_class($event->agent),
            'tokens' => $event->usage->totalTokens,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
```

---

## Diagrama do fluxo completo

```
+-------------+     +-----------+     +----------------+
|   Browser   |---->| Livewire  |---->|CodeReviewForm  |
|  (submit)   |     |  (Volt)   |     |   ::store()    |
+-------------+     +-----------+     +-------+--------+
                                              |
                         dispatch             |
                    +-------------------------+
                    v
            +---------------+
            |   jobs table  |  <- database queue
            +-------+-------+
                    |
                    v  queue:work
            +-------------------+
            |  AnalyzeCodeJob   |
            +-------+-----------+
                    |
                    v
            +-------------------+
            |  CodeAnalysis     |
            |    Service        |
            |                   |
            | CodeAnalyst Agent |
            | (Structured Out.) |
            | Lab::Gemini       |
            +-------+-----------+
                    |
                    v  update
            +---------------+
            | code_reviews  |  <- status = completed
            |  table        |  <- summary = markdown
            +---------------+
                    |
                    |  wire:poll.5s detecta mudanca
                    v
            +-------------+
            |   Browser   |  <- resultado aparece!
            +-------------+
```

## Supervisor em producao

Em producao, o `Dockerfile` usa **Supervisor** para manter o worker rodando:

```ini
; docker/supervisor/supervisord.conf

[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

## Proximo capitulo

No [Capitulo 12 — Deploy com Docker](12-deploy-docker.md) vamos ver como empacotar tudo para producao com Docker multi-stage.
