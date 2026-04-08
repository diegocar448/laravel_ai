# Capitulo 8 — Agents e Structured Output (Laravel AI SDK)

> **Este capitulo cobre os pilares: Prompt Engineering (1) e Structured Output (2)**

Neste capitulo vamos criar o **Agent CodeAnalyst**, o **prompt Blade**, o **service de orquestracao** e configurar **streaming** e **failover** entre providers. Ao final, voce tera o pipeline completo: formulario -> Agent -> Structured Output -> banco de dados.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap08-agents
cd codereview-ai
```

---

## Visao geral da arquitetura

Antes de comecar, entenda o fluxo completo que vamos construir:

```
CodeReviewForm (Livewire)
       |
       | dispatch(AnalyzeCodeJob)
       v
CodeAnalysisService
       |
       | new CodeAnalyst($codeReview)
       v
CodeAnalyst Agent
  +-- implements Agent, HasStructuredOutput
  +-- use Promptable
  +-- instructions() -> Blade template
  +-- schema() -> JsonSchema
       |
       | ->prompt($context, provider: Lab::Gemini)
       v
Gemini API (failover: OpenAI -> Anthropic)
       |
       | Structured Output (JSON)
       v
{ summary: "...", score: 72, priority_finding_ids: [2, 5, 6] }
       |
       | $codeReview->update(...)
       v
Banco de dados (code_reviews, review_findings)
```

### Structured Output — o conceito

Normalmente, quando pedimos algo a um LLM, recebemos texto livre. Com **Structured Output**, definimos um schema e a IA e forcada a responder nesse formato exato:

```
Sem Structured Output:
"O codigo tem alguns problemas de seguranca, como SQL injection..."

Com Structured Output:
{
    "summary": "Analise detalhada em markdown...",
    "score": 72,
    "priority_finding_ids": [2, 5, 6]
}
```

No Laravel AI SDK, usamos a interface `HasStructuredOutput` com `JsonSchema`.

---

## Passo 1 — Verificar a configuracao do Laravel AI SDK

O pacote `laravel/ai` ja foi instalado no Capitulo 2. Verifique se o arquivo `config/ai.php` existe:

```bash
cat config/ai.php
```

O conteudo deve ser:

```php
// config/ai.php (publicado pelo Laravel AI SDK)
return [
    'default' => env('AI_PROVIDER', 'gemini'),
    'providers' => [
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
        ],
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],
    ],
];
```

Se o arquivo nao existir, publique:

```bash
sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

> **Atencao:** O `vendor:publish` cria uma nova migration `create_agent_conversations_table`. Se voce ja executou o cap 07, essa tabela ja existe no banco — **delete o arquivo de migration duplicado** antes de rodar o migrate:
> ```bash
> # Verifique se ja existe uma migration anterior
> ls database/migrations/ | grep agent_conversations
> # Se houver dois arquivos, delete o mais recente (data maior)
> rm database/migrations/YYYY_MM_DD_HHMMSS_create_agent_conversations_table.php
> ```

```bash
sail artisan migrate
```

> **Por que Gemini?** O Google oferece tier gratuito (sem cartao de credito): 250 req/dia no `gemini-2.5-flash` e 1000 req/dia no `flash-lite`. Gere sua chave em [aistudio.google.com/apikey](https://aistudio.google.com/apikey).

Adicione a chave ao `.env`:

```bash
# No arquivo .env
GEMINI_API_KEY=sua-chave-aqui
```

---

## Passo 2 — Criar o Agent CodeAnalyst via Artisan

O Laravel AI SDK oferece um comando Artisan para gerar Agents. Vamos criar o `CodeAnalyst` com suporte a Structured Output:

```bash
sail artisan make:agent CodeAnalyst --structured
```

Isso cria o arquivo em `app/Ai/Agents/CodeAnalyst.php`. Edite com o conteudo completo:

```php
<?php

namespace App\Ai\Agents;

use App\Models\CodeReview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class CodeAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public CodeReview $codeReview,
    ) {}

    /**
     * System prompt — define o papel do Agent.
     * Usa template Blade para interpolar dados do projeto.
     */
    public function instructions(): string
    {
        return view('prompts.code-review-system-prompt', [
            'codeReview' => $this->codeReview,
        ])->render();
    }

    /**
     * Schema da resposta — forca o LLM a responder neste formato.
     * O SDK converte para JSON Schema e envia ao provider.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('Analise completa em markdown do code review')
                ->required(),
            'score' => $schema->integer()
                ->min(0)->max(100)
                ->description('Score geral do codigo (0-100)')
                ->required(),
            'priority_finding_ids' => $schema->array()
                ->items($schema->integer())
                ->description('IDs dos 3 findings mais criticos')
                ->required(),
        ];
    }
}
```

**Pontos importantes:**
- `Agent` — contrato do SDK que exige `instructions()` (system prompt)
- `HasStructuredOutput` — contrato que exige `schema()` (formato da resposta)
- `Promptable` — trait que adiciona os metodos `prompt()` e `stream()`
- `instructions()` — renderiza um template Blade, permitindo interpolar dados do projeto
- `schema()` — define tipagem forte: `string`, `integer` com min/max, `array`

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeAnalyst agent with structured output"
```

---

## Passo 3 — Criar o template Blade do system prompt

O prompt do sistema define o papel da IA como um **Code Reviewer Senior**. Usamos Blade para interpolar dados dinamicos do projeto.

Crie o diretorio e o arquivo:

```bash
mkdir -p resources/views/prompts
```

Crie `resources/views/prompts/code-review-system-prompt.blade.php`:

```php
Voce e um Code Reviewer Senior com mais de 20 anos de experiencia em
engenharia de software e revisao de codigo em equipes de alta performance.

Seu papel e analisar o codigo submetido e:

1. **Contextualizar** o codigo dentro do ecossistema {{ $codeReview->project->language }}
2. **Analisar** pontos fortes e fracos nos 3 pilares:
   - Arquitetura: padroes de design, SOLID, Clean Code, acoplamento, coesao
   - Performance: queries N+1, cache, algoritmos, lazy loading, memory leaks
   - Seguranca: OWASP Top 10, SQL Injection, XSS, CSRF, validacao de inputs

3. **Priorizar** os 3 findings mais criticos para resolver primeiro,
   considerando impacto em producao e facilidade de correcao

4. **Gerar** um summary em markdown com no maximo 800 palavras contendo:
   - Score geral do codigo (0-100)
   - Analise por pilar (2-3 paragrafos curtos cada)
   - Top 3 melhorias prioritarias (sem code snippets longos)

IMPORTANTE: O summary deve ser conciso e objetivo. Nao inclua blocos de codigo longos no summary — apenas referencias curtas inline.
Responda APENAS no formato JSON especificado.
Linguagem: {{ $codeReview->project->language }}
Projeto: {{ $codeReview->project->name }}
```

**Por que Blade para prompts?**
- `{{ $codeReview->project->language }}` — interpola "php", "javascript", etc.
- `{{ $codeReview->project->name }}` — interpola "API de Pagamentos", etc.
- Permite usar `@if`, `@foreach` para prompts condicionais
- Mantemos o prompt como arquivo versionado, nao hardcoded no PHP

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add Blade system prompt template for CodeAnalyst"
```

---

## Passo 4 — Criar o CodeAnalysisService

O service orquestra o Agent: carrega dados, chama o Agent, salva o resultado no banco.

Crie o diretorio e o arquivo:

```bash
mkdir -p app/Services
```

Crie `app/Services/CodeAnalysisService.php`:

```php
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
        $response = (new CodeAnalyst($codeReview))->prompt(
            $this->buildContext($codeReview),
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash',
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
```

**Fluxo do service:**

```
handle($codeReview)
   |
   +-- 1. load() — carrega project, findings com type e pillar (evita N+1)
   |
   +-- 2. prompt() — envia contexto ao Gemini, recebe JSON tipado
   |       |
   |       +-- buildContext() — monta JSON com dados do projeto e findings
   |
   +-- 3. update() — salva summary e muda status para Completed
   |
   +-- 4. foreach — marca os 3 findings prioritarios com agent_flagged_at
```

**Pontos importantes:**
- `$response['summary']` — acesso direto como array, nunca null, nunca formato errado
- `$response['score']` — integer entre 0-100 (garantido pelo schema)
- `$response['priority_finding_ids']` — array de integers (garantido pelo schema)
- Se a IA retornar formato invalido, o SDK lanca excecao **antes** de chegar ao `update()`

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeAnalysisService to orchestrate agent"
```

---

## Passo 5 — Adicionar streaming de respostas

O Laravel AI SDK suporta streaming nativo — a resposta aparece em tempo real, util para UX.

O Agent `CodeAnalyst` ja possui o metodo `stream()` via trait `Promptable`. Veja como usar:

```php
use App\Ai\Agents\CodeAnalyst;
use Laravel\Ai\Enums\Lab;

// Streaming — resposta aparece em tempo real
$stream = (new CodeAnalyst($codeReview))->stream(
    $context,
    provider: Lab::Gemini,
    model: 'gemini-2.5-flash',
);

foreach ($stream as $chunk) {
    echo $chunk; // cada pedaco da resposta
}
```

**Quando usar streaming:**
- Em endpoints HTTP onde o usuario aguarda a resposta na tela
- Em componentes Livewire com `wire:stream`
- **Nao** use em Jobs (nao tem quem receber os chunks)

---

## Passo 6 — Configurar failover entre providers

Se o Gemini falhar (quota excedida, timeout, erro 500), o SDK pode automaticamente tentar outro provider.

Edite o metodo `handle()` do `CodeAnalysisService` para adicionar failover:

```php
// No metodo handle() do CodeAnalysisService, substitua o prompt() por:
// O failover e configurado passando um array como provider (provider => model)
// O SDK tenta cada provider na ordem ate um ter sucesso
$response = (new CodeAnalyst($codeReview))->prompt(
    $this->buildContext($codeReview),
    provider: [
        Lab::Gemini->value => 'gemini-2.5-flash',
        Lab::OpenAI->value  => 'gpt-4o-mini',
        Lab::Anthropic->value => 'claude-haiku-4-5-20251001',
    ],
);
```

> **Importante:** O SDK NAO tem um parametro `failover:`. O failover e feito passando um array de `[provider_string => model]` como valor do parametro `provider`. Use `Lab::Gemini->value` (string) como chave — enums nao podem ser usados diretamente como chaves de array PHP.

**Fluxo de failover:**

```
1. Tenta Gemini gemini-2.5-flash (gratis)
   |-- Sucesso? -> retorna resposta
   |-- Falhou?
       |
       2. Tenta OpenAI gpt-4o-mini (pago)
          |-- Sucesso? -> retorna resposta
          |-- Falhou?
              |
              3. Tenta Anthropic claude-haiku (pago)
                 |-- Sucesso? -> retorna resposta
                 |-- Falhou? -> lanca excecao
```

> **Nota:** Para usar failover, configure as API keys dos providers alternativos no `.env`. Para uso simples com apenas Gemini (recomendado para comecar), use `provider: Lab::Gemini, model: 'gemini-2.5-flash'`.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add streaming and failover support to CodeAnalysisService"
```

---

## Passo 7 — Criar o CodeReviewForm (trigger da analise)

O formulario Livewire coleta as informacoes do codigo e dispara o job de analise.

Crie `app/Livewire/Forms/CodeReviewForm.php`:

```php
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

    public function rules(): array
    {
        return [
            'architecture_strength'    => 'required|string|max:1000',
            'architecture_improvement' => 'required|string|max:1000',
            'performance_strength'     => 'required|string|max:1000',
            'performance_improvement'  => 'required|string|max:1000',
            'security_strength'        => 'required|string|max:1000',
            'security_improvement'     => 'required|string|max:1000',
        ];
    }

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
```

> **Atencao:** O metodo `rules()` e obrigatorio em Livewire Form Objects. Sem ele, `$this->validate()` lanca `MissingRulesException`.

**Fluxo do formulario:**

```
Usuario preenche 6 campos (3 pilares x 2 tipos)
   |
   +-- store() cria CodeReview com status Pending
   |
   +-- createFindings() cria 6 ReviewFinding
   |
   +-- AnalyzeCodeJob::dispatch() — enfileira job assincrono
   |
   +-- O job chama CodeAnalysisService::handle()
       que chama o Agent CodeAnalyst
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeReviewForm to trigger AI analysis"
```

---

## Passo 8 — Criar a view da pagina do projeto

A pagina `pages/projects/show` exibe o codigo, o formulario de analise, o loading enquanto o queue processa e o resultado final.

### 8.1 — Adicionar highlight.js no layout

Edite `resources/views/layouts/app.blade.php` e adicione o CSS antes de `</head>` e o JS antes de `</body>`:

```html
<!-- antes de </head> -->
@livewireStyles
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
</head>
```

```html
<!-- antes de </body> -->
@livewireScripts
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
    function hljsHighlight() {
        document.querySelectorAll('pre code:not([data-highlighted])').forEach(el => hljs.highlightElement(el));
    }

    document.addEventListener('DOMContentLoaded', hljsHighlight);
    document.addEventListener('livewire:navigated', hljsHighlight);

    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(hljsHighlight, 0));
        });
    });
</script>
</body>
```

> **Por que `Livewire.hook('commit')`?**
> O evento `livewire:updated` não existe no Livewire 3/4. O hook `commit` dispara após cada re-render do componente — inclusive os disparados pelo `wire:poll`. Sem ele, o highlight.js não re-aplica quando o resultado da IA aparece automaticamente na tela.
> A função `hljsHighlight` usa `pre code:not([data-highlighted])` para processar apenas elementos novos no DOM, evitando re-processar blocos já destacados.

### 8.2 — Criar a view show.blade.php

Crie `resources/views/pages/projects/show.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Livewire\Forms\CodeReviewForm;

new class extends Component
{
    public Project $project;
    public CodeReviewForm $form;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function requestReview(): void
    {
        $this->form->store($this->project);
        $this->redirect(route('project', $this->project));
    }

    public function with(): array
    {
        return [
            'project' => $this->project->load('codeReview.findings', 'improvements', 'status'),
        ];
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">{{ $project->name }}</h1>
            <div class="flex items-center gap-2 mt-1">
                <span class="text-xs px-2 py-0.5 rounded bg-indigo-600/30 text-indigo-300">{{ strtoupper($project->language) }}</span>
                <span class="text-xs text-gray-400">{{ $project->status->name }}</span>
            </div>
        </div>
    </div>

    {{-- Codigo com syntax highlight --}}
    <x-card>
        <x-card.header>
            <span class="text-sm font-medium">Codigo</span>
        </x-card.header>
        <x-card.body class="p-0">
            <pre class="rounded-b-lg overflow-x-auto text-sm m-0"><code class="{{ $project->language }}">{{ $project->code_snippet }}</code></pre>
        </x-card.body>
    </x-card>

    {{-- Status: Pending — loading com wire:poll --}}
    @if($project->codeReview && $project->codeReview->review_status_id === 1)
        <div wire:poll.3s class="mt-6 rounded-xl border border-indigo-500/30 bg-indigo-950/40 overflow-hidden">
            <div class="h-1 w-full bg-indigo-900/50 overflow-hidden">
                <div class="h-1 bg-indigo-500"
                     style="animation: progress 2s ease-in-out infinite; width: 40%"></div>
            </div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-600/20 flex items-center justify-center">
                        <svg class="animate-spin h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-indigo-300">Analisando com IA...</p>
                        <p class="text-xs text-gray-400 mt-1">Os agentes estao revisando o codigo. Isso leva entre 10 e 30 segundos.</p>
                        <div class="mt-4 space-y-2">
                            @foreach([
                                'CodeAnalyst — Analisando estrutura geral',
                                'SecurityAnalyst — Verificando vulnerabilidades OWASP',
                                'ArchitectureAnalyst — Avaliando padroes de design',
                                'PerformanceAnalyst — Identificando gargalos',
                            ] as $i => $etapa)
                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></div>
                                    {{ $etapa }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            @keyframes progress {
                0%   { width: 5%;  margin-left: 0; }
                50%  { width: 40%; margin-left: 30%; }
                100% { width: 5%;  margin-left: 100%; }
            }
        </style>
    @endif

    {{-- Status: Failed --}}
    @if($project->codeReview && $project->codeReview->review_status_id === 3)
        <div class="mt-6 p-4 rounded-lg bg-red-900/30 border border-red-500/30 text-red-300 text-sm">
            Falha na analise. Tente novamente mais tarde.
        </div>
    @endif

    {{-- Status: Completed — resultado --}}
    @if($project->codeReview && $project->codeReview->review_status_id === 2)
        <div class="mt-6 space-y-6">
            <h2 class="text-lg font-semibold">Resultado da Analise</h2>

            @if($project->codeReview->summary)
                <x-card>
                    <x-card.header>
                        <span class="text-sm font-medium">Analise Completa</span>
                    </x-card.header>
                    <x-card.body>
                        <div class="text-sm leading-7 text-gray-300 space-y-3
                            [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-indigo-300 [&_h2]:mt-4 [&_h2]:border-b [&_h2]:border-gray-700 [&_h2]:pb-1
                            [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-gray-200 [&_h3]:mt-3
                            [&_strong]:text-white [&_strong]:font-semibold
                            [&_code]:bg-gray-800 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-indigo-300 [&_code]:text-xs
                            [&_pre]:bg-gray-800 [&_pre]:rounded-lg [&_pre]:p-4 [&_pre]:overflow-x-auto [&_pre]:my-3
                            [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1
                            [&_li]:text-gray-300
                        ">{!! \Illuminate\Support\Str::markdown($project->codeReview->summary) !!}</div>
                    </x-card.body>
                </x-card>
            @endif

            @if($project->codeReview->findings->count())
                <h3 class="text-base font-semibold mt-4">Findings</h3>
                @foreach($project->codeReview->findings as $finding)
                    <x-card>
                        <x-card.header>
                            <div class="flex items-center justify-between">
                                <span>{{ $finding->pillar->name }} — {{ $finding->type->name }}</span>
                                <span class="text-xs px-2 py-1 rounded
                                    {{ $finding->severity === 'critical' ? 'bg-red-600' : '' }}
                                    {{ $finding->severity === 'high' ? 'bg-orange-500' : '' }}
                                    {{ $finding->severity === 'medium' ? 'bg-yellow-500 text-black' : '' }}
                                    {{ $finding->severity === 'low' ? 'bg-gray-500' : '' }}
                                ">{{ $finding->severity }}</span>
                            </div>
                        </x-card.header>
                        <x-card.body>{{ $finding->description }}</x-card.body>
                    </x-card>
                @endforeach
            @endif
        </div>

    @else
        {{-- Formulario para solicitar analise --}}
        <form wire:submit="requestReview" class="space-y-6 mt-6">
            <h2 class="text-lg font-semibold">Solicitar Analise de IA</h2>
            <p class="text-sm text-gray-500">Preencha suas observacoes sobre o codigo antes de enviar para analise.</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-form.input wire:model="form.architecture_strength"
                    label="Arquitetura — Ponto forte"
                    placeholder="Ex: Bom uso de interfaces, separacao de responsabilidades" />
                <x-form.input wire:model="form.architecture_improvement"
                    label="Arquitetura — Ponto de melhoria"
                    placeholder="Ex: Alta coesao entre classes, falta de injecao de dependencia" />
                <x-form.input wire:model="form.performance_strength"
                    label="Performance — Ponto forte"
                    placeholder="Ex: Queries otimizadas, uso de cache" />
                <x-form.input wire:model="form.performance_improvement"
                    label="Performance — Ponto de melhoria"
                    placeholder="Ex: N+1 detectado, falta de paginacao" />
                <x-form.input wire:model="form.security_strength"
                    label="Seguranca — Ponto forte"
                    placeholder="Ex: CSRF protegido, validacao de inputs" />
                <x-form.input wire:model="form.security_improvement"
                    label="Seguranca — Ponto de melhoria"
                    placeholder="Ex: SQL injection risk, dados sensiveis expostos" />
            </div>

            <x-button type="submit">
                <span wire:loading.remove>Solicitar Analise IA</span>
                <span wire:loading>Analisando...</span>
            </x-button>
        </form>
    @endif
</div>
```

**Pontos importantes da view:**
- `wire:poll.3s` — enquanto status = Pending, o Livewire consulta o banco a cada 3s e atualiza automaticamente quando o job terminar (sem o usuario recarregar)
- `highlight.js` — syntax highlight do codigo igual a um editor
- `Str::markdown()` — renderiza o summary gerado pelo Gemini como HTML formatado
- Badges coloridos por severidade: `critical`=vermelho, `high`=laranja, `medium`=amarelo, `low`=cinza

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add project show view with AI loading state and result"
```

---

## Passo 10 — Verificar o Agent no Tinker

Vamos testar se o Agent funciona corretamente. Primeiro, certifique-se de que o banco esta atualizado:

```bash
sail artisan migrate:fresh --seed
```

Abra o Tinker:

```bash
sail artisan tinker
```

```php
// Criar usuario e projeto de teste
$user = App\Models\User::create([
    'name' => 'Diego',
    'email' => 'diego@test.com',
    'password' => 'password',
]);

$project = $user->projects()->create([
    'project_status_id' => 1,
    'name' => 'API de Pagamentos',
    'language' => 'php',
    'code_snippet' => '<?php
class PaymentController {
    public function store(Request $request) {
        $amount = $request->input("amount");
        DB::select("SELECT * FROM payments WHERE amount = " . $amount);
        return response()->json(["ok" => true]);
    }
}',
]);

// Criar code review com findings
$review = $project->codeReview()->create([
    'review_status_id' => 1,
]);

$review->findings()->create([
    'review_pillar_id' => 3, // Security
    'finding_type_id' => 2,  // Improvement
    'description' => 'SQL Injection via concatenacao de input no DB::select()',
]);

// Verificar que o Agent instancia corretamente
$agent = new App\Ai\Agents\CodeAnalyst($review);
echo $agent->instructions();
// Deve exibir o prompt com "php" e "API de Pagamentos" interpolados

// Testar chamada real ao Agent (requer GEMINI_API_KEY no .env)
// $service = new App\Services\CodeAnalysisService();
// $service->handle($review);
// $review->refresh();
// echo $review->summary; // Analise em markdown
// echo $review->status->name; // "Completed"

// Sair do Tinker
exit
```

> Se o `instructions()` exibiu o prompt com os dados interpolados, o Agent esta configurado corretamente. Descomente as linhas do `$service` para testar a chamada real ao Gemini (requer API key).

---

## Passo 11 — Providers suportados (referencia)

| Provider | Modelos | Custo | Ideal para |
|----------|---------|-------|-----------|
| **Google Gemini** (padrao) | gemini-2.5-flash, flash-lite | **Gratis** (250-1000 req/dia) | Tutorial, prototipos |
| OpenAI | gpt-4o, gpt-4o-mini | Pre-pago ($5 minimo) | Producao high-volume |
| Anthropic | claude-sonnet-4, claude-haiku | Pre-pago | Analise complexa |
| Ollama | llama3, codellama | Gratis (local) | Offline, privacidade |
| Groq | llama-3.3-70b | Gratis (rate limit) | Fast inference |

Para trocar de provider, basta alterar `AI_PROVIDER` e a API key no `.env`.

---

## Passo 12 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: verify agent setup via tinker"

# Push da branch
git push -u origin feat/cap08-agents

# Criar Pull Request
gh pr create --title "feat: agents e structured output" --body "Capitulo 08 - CodeAnalyst Agent, Blade prompt, CodeAnalysisService, streaming, failover e CodeReviewForm"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `config/ai.php` | Configuracao do Laravel AI SDK (providers e API keys) |
| `app/Ai/Agents/CodeAnalyst.php` | Agent com `HasStructuredOutput` + `Promptable` |
| `resources/views/prompts/code-review-system-prompt.blade.php` | System prompt Blade — max 800 palavras, sem code snippets longos |
| `app/Services/CodeAnalysisService.php` | Orquestra Agent -> Eloquent (com failover) |
| `app/Livewire/Forms/CodeReviewForm.php` | Form Object com `rules()` + dispara job de IA |
| `resources/views/pages/projects/show.blade.php` | Exibe codigo com highlight, loading `wire:poll.3s` e resultado markdown |
| `resources/views/layouts/app.blade.php` | Inclui highlight.js CDN para syntax highlight |

## Proximo capitulo

No [Capitulo 9 — RAG com pgvector](09-rag-pgvector.md) vamos implementar busca semantica em documentacoes para enriquecer as analises dos Agents.
