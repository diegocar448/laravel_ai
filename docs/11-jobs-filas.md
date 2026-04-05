# Capitulo 11 — Jobs, Filas e Processamento

> **Este capitulo cobre o pilar: AI Infrastructure (8)**

Neste capitulo vamos criar os **Jobs** que processam chamadas de IA em background, configurar o **queue worker**, adicionar **Events de monitoring** e escrever **testes com FakeAi**. Ao final, o fluxo completo estara funcionando: o usuario submete codigo, o job processa com Agents e o resultado aparece na tela.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap11-jobs
cd codereview-ai
```

---

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

---

## Passo 1 — Configurar a conexao de filas

O Laravel suporta varios drivers de fila. Vamos usar `database` porque ja temos PostgreSQL rodando.

Edite o arquivo `.env` e certifique-se de que a variavel esta assim:

```env
QUEUE_CONNECTION=database
```

A configuracao completa ja existe em `config/queue.php`:

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

Rode as migrations (caso ainda nao tenha rodado):

```bash
sail artisan migrate
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: configure database queue connection"
```

---

## Passo 2 — Criar o AnalyzeCodeJob

Este job recebe um `CodeReview` e delega a analise para o `CodeAnalysisService` (que usa o Agent `CodeAnalyst` do Capitulo 8).

Gere o job:

```bash
sail artisan make:job AnalyzeCodeJob
```

Edite `app/Jobs/AnalyzeCodeJob.php`:

```php
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
```

**Pontos importantes:**
- `$tries = 3` — com retry automatico, o Laravel tenta o job ate 3 vezes antes de mover para `failed_jobs`
- `$timeout = 120` — 2 minutos e suficiente para uma chamada de Agent
- `failed()` — metodo chamado apos todas as tentativas falharem; atualiza o status para `Failed` e loga o erro

### Dispatch do job

Para disparar o job, use em qualquer lugar (controller, Livewire component, etc.):

```php
// No CodeReviewForm::store() ou em um Volt component
AnalyzeCodeJob::dispatch($codeReview);
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add AnalyzeCodeJob with retry and failure handling"
```

---

## Passo 3 — Criar o GenerateImprovementsJob

Este job recebe um `Project` e gera o plano de melhorias usando o `ImprovementPlanService` (que usa o Agent `CodeMentor` do Capitulo 10).

Gere o job:

```bash
sail artisan make:job GenerateImprovementsJob
```

Edite `app/Jobs/GenerateImprovementsJob.php`:

```php
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

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add GenerateImprovementsJob for multi-agent flow"
```

---

## Passo 4 — Criar o Event de monitoring (AgentPrompted)

O Laravel AI SDK dispara events que voce pode ouvir para monitorar chamadas de IA. Vamos criar um Listener para logar cada chamada.

Gere o listener:

```bash
sail artisan make:listener LogAgentPrompt --event=\\Laravel\\Ai\\Events\\AgentPrompted
```

Edite `app/Listeners/LogAgentPrompt.php`:

```php
<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
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

**O que esse listener faz:**
- Registra no log cada vez que um Agent e chamado
- Captura o nome do Agent, total de tokens usados e duracao em milissegundos
- Util para monitoring de custos e performance

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add LogAgentPrompt listener for AI monitoring"
```

---

## Passo 5 — Criar o componente de feedback com polling

Enquanto o job processa em background, a pagina usa **polling** para atualizar automaticamente.

Edite (ou crie) `resources/views/pages/reviews/show.blade.php`:

```php
<?php
// resources/views/pages/reviews/show.blade.php

use App\Models\CodeReview;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

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

**Como funciona:**
- Quando `review_status_id === 1` (Pending), o `wire:poll.5s` faz o Livewire recarregar o componente a cada 5 segundos
- Quando o job termina e atualiza o status para `2` (Completed), o polling para e o resultado aparece

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add review show page with polling feedback"
```

---

## Passo 6 — Escrever testes com FakeAi

O Laravel AI SDK oferece `FakeAi` para testar sem chamar APIs reais. Vamos criar testes para os dois jobs.

Crie o arquivo de teste:

```bash
sail artisan make:test Jobs/AnalyzeCodeJobTest --pest
```

Edite `tests/Feature/Jobs/AnalyzeCodeJobTest.php`:

```php
<?php

use App\Ai\Agents\CodeAnalyst;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use Laravel\Ai\Testing\FakeAi;

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

### Teste de Embeddings

Crie outro arquivo de teste para embeddings:

```bash
sail artisan make:test Jobs/EmbeddingsTest --pest
```

Edite `tests/Feature/Jobs/EmbeddingsTest.php`:

```php
<?php

use Laravel\Ai\Testing\FakeAi;

test('search docs knowledge base returns results', function () {
    FakeAi::fake();

    FakeAi::embeddings()
        ->respondWith([[0.1, 0.2, 0.3, /* ...768 dims */]]);

    $tool = new \App\Ai\Tools\SearchDocsKnowledgeBase;
    $result = $tool->execute([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]);

    expect($result)->toContain('OWASP');
});
```

**Pontos importantes sobre FakeAi:**
- `FakeAi::fake()` — intercepta todas as chamadas de IA, nenhuma API real e chamada
- `FakeAi::agent(Class)->respondWith([...])` — define a resposta que o Agent vai retornar
- `FakeAi::agent(Class)->throwException(...)` — simula falha na API
- `FakeAi::embeddings()->respondWith([...])` — simula resposta de embeddings

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add FakeAi tests for jobs and embeddings"
```

---

## Passo 7 — Rodar o queue worker e verificar

Agora vamos testar tudo junto. Abra **dois terminais**.

### Terminal 1 — Iniciar o queue worker

```bash
cd ~/laravel_ai/codereview-ai
sail artisan queue:work --tries=3 -v
```

O worker fica escutando por novos jobs. Voce vera algo como:

```
[2026-03-26 12:00:00] Processing: App\Jobs\AnalyzeCodeJob
[2026-03-26 12:00:05] Processed:  App\Jobs\AnalyzeCodeJob
```

### Terminal 2 — Disparar um job manualmente via Tinker

```bash
cd ~/laravel_ai/codereview-ai
sail artisan tinker --execute="
\$user = App\Models\User::first() ?? App\Models\User::factory()->create();
\$project = \$user->projects()->first() ?? App\Models\Project::create(['user_id' => \$user->id, 'project_status_id' => 1, 'name' => 'Test Project', 'language' => 'PHP', 'code_snippet' => '<?php echo \"hello\";']);
\$review = \$project->codeReview ?? App\Models\CodeReview::create(['project_id' => \$project->id, 'review_status_id' => 1]);
echo 'User: ' . \$user->email . ' | Project: ' . \$project->name . ' | Review ID: ' . \$review->id . PHP_EOL;
App\Jobs\AnalyzeCodeJob::dispatch(\$review);
echo 'Job disparado! Verifique o Terminal 1.' . PHP_EOL;
"
```

Volte ao Terminal 1 e verifique que o job foi processado.

> **Rate Limit:** O Gemini free tier tem limite de ~15 requisicoes por minuto e cota diaria. Se o job falhar com `RateLimitedException`, aguarde alguns minutos e tente novamente. O `backoff = 60` no job ja garante que os retries aguardem 60 segundos automaticamente.

### Monitorando com Laravel Pail

O projeto inclui `laravel/pail` para monitorar logs em tempo real:

```bash
sail artisan pail

# Filtrar por nivel
sail artisan pail --filter="level:error"
```

### Verificar jobs na fila

```bash
# Ver jobs pendentes
sail artisan queue:monitor default

# Ver jobs que falharam
sail artisan queue:failed

# Retentar jobs que falharam
sail artisan queue:retry all
```

---

## Passo 8 — Configurar Supervisor para producao

Em producao, o `Dockerfile` usa **Supervisor** para manter o worker rodando. Crie (ou edite) `docker/supervisor/supervisord.conf`:

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

**Pontos importantes:**
- `numprocs=2` — roda 2 workers em paralelo
- `--max-time=3600` — reinicia o worker a cada hora (evita memory leaks)
- `autorestart=true` — reinicia automaticamente se o worker cair

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add Supervisor config for queue worker in production"
```

---

## Passo 9 — Rodar os testes

Verifique que todos os testes passam:

```bash
sail artisan test --filter=AnalyzeCodeJobTest
sail artisan test --filter=EmbeddingsTest
```

Deve mostrar todos os testes passando (verde).

---

## Passo 10 — Diagrama do fluxo completo

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

---

## Passo 11 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: complete jobs, queues and processing chapter"

# Push da branch
git push -u origin feat/cap11-jobs

# Criar Pull Request
gh pr create --title "feat: jobs, filas e processamento" --body "Capitulo 11 - AnalyzeCodeJob, GenerateImprovementsJob, FakeAi tests, Events monitoring e Supervisor config"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `.env` | `QUEUE_CONNECTION=database` |
| `app/Jobs/AnalyzeCodeJob.php` | Job que processa code review via CodeAnalyst Agent |
| `app/Jobs/GenerateImprovementsJob.php` | Job que gera plano de melhorias via CodeMentor Agent |
| `app/Listeners/LogAgentPrompt.php` | Listener que loga chamadas de Agents (tokens, duracao) |
| `resources/views/pages/reviews/show.blade.php` | Pagina com polling para feedback em tempo real |
| `tests/Feature/Jobs/AnalyzeCodeJobTest.php` | Testes com FakeAi para AnalyzeCodeJob |
| `tests/Feature/Jobs/EmbeddingsTest.php` | Testes com FakeAi para embeddings |
| `docker/supervisor/supervisord.conf` | Supervisor config para queue worker em producao |

## Proximo capitulo

No [Capitulo 12 — Deploy com Docker](12-deploy-docker.md) vamos ver como empacotar tudo para producao com Docker multi-stage.
