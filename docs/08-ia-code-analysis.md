# Capitulo 8 — Agents e Structured Output (Laravel AI SDK)

> **Este capitulo cobre os pilares: Prompt Engineering (1) e Structured Output (2)**

## Laravel AI SDK — Agents

O **Laravel AI SDK** (`laravel/ai`) e o toolkit first-party oficial do Laravel para IA. O conceito central sao **Agents** — classes PHP que encapsulam instructions, tools e structured output.

```bash
# Instalacao (ja feita no Capitulo 2)
sail composer require laravel/ai

# Publicar config e migrations
sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
sail artisan migrate
```

### Configuracao

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

> **Por que Gemini?** O Google oferece tier gratuito (sem cartao de credito): 250 req/dia no `gemini-2.5-flash` e 1000 req/dia no `flash-lite`. Gere sua chave em [aistudio.google.com/apikey](https://aistudio.google.com/apikey).

---

## Structured Output — o conceito

Normalmente, quando pedimos algo a um LLM, recebemos texto livre. Com **Structured Output**, definimos um schema e a IA e forcada a responder nesse formato exato.

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

## Criando o CodeAnalyst Agent

### Scaffold via Artisan

```bash
sail artisan make:agent CodeAnalyst --structured
```

Isso cria o arquivo em `app/Ai/Agents/CodeAnalyst.php`.

### Implementacao completa

```php
// app/Ai/Agents/CodeAnalyst.php

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
                ->description('IDs dos 3 findings mais criticos')
                ->required(),
        ];
    }
}
```

### Uso do Agent

```php
use App\Ai\Agents\CodeAnalyst;
use Laravel\Ai\Enums\Lab;

// Criar o agent com o code review
$agent = new CodeAnalyst($codeReview);

// Prompt com o contexto do codigo
$response = $agent->prompt(
    $this->buildContext($codeReview),
    provider: Lab::Gemini,
    model: 'gemini-2.5-flash',
);

// Resposta SEMPRE tipada (nunca null, nunca formato errado)
$summary = $response['summary'];             // string
$score = $response['score'];                 // integer 0-100
$priorityIds = $response['priority_finding_ids']; // array de integers
```

---

## System Prompt (Blade template)

O prompt do sistema define o papel da IA como um **Code Reviewer Senior**:

```php
// resources/views/prompts/code-review-system-prompt.blade.php

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

4. **Gerar** uma analise detalhada em markdown com:
   - Score geral do codigo (0-100)
   - Analise por pilar com exemplos especificos do codigo
   - Sugestoes concretas de refatoracao com code snippets
   - Quick wins (melhorias rapidas de alto impacto)

Responda APENAS no formato JSON especificado.
Linguagem: {{ $codeReview->project->language }}
Projeto: {{ $codeReview->project->name }}
```

Note como o Blade permite interpolar dados do projeto diretamente no prompt:
- `{{ $codeReview->project->language }}` — "php"
- `{{ $codeReview->project->name }}` — "API de Pagamentos"

---

## CodeAnalysisService — Orquestra o Agent

O service conecta o Agent com o Eloquent:

```php
// app/Services/CodeAnalysisService.php

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

---

## Streaming de respostas

O Laravel AI SDK suporta streaming nativo:

```php
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

---

## Failover entre providers

Se o Gemini falhar, o SDK pode automaticamente tentar outro provider:

```php
$response = (new CodeAnalyst($codeReview))->prompt(
    $context,
    provider: Lab::Gemini,
    model: 'gemini-2.5-flash',
    failover: [
        Lab::OpenAI => 'gpt-4o-mini',
        Lab::Anthropic => 'claude-haiku-4-5-20251001',
    ],
);
```

---

## CodeReviewForm — trigger da analise

O formulario coleta as informacoes do codigo e dispara o job:

```php
// app/Livewire/Forms/CodeReviewForm.php

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
```

---

## Providers suportados

| Provider | Modelos | Custo | Ideal para |
|----------|---------|-------|-----------|
| **Google Gemini** (padrao) | gemini-2.5-flash, flash-lite | **Gratis** (250-1000 req/dia) | Tutorial, prototipos |
| OpenAI | gpt-4o, gpt-4o-mini | Pre-pago ($5 minimo) | Producao high-volume |
| Anthropic | claude-sonnet-4, claude-haiku | Pre-pago | Analise complexa |
| Ollama | llama3, codellama | Gratis (local) | Offline, privacidade |
| Groq | llama-3.3-70b | Gratis (rate limit) | Fast inference |

Para trocar de provider, basta alterar `AI_PROVIDER` e a API key no `.env`.

---

## Comparacao: Antes (Prism direto) vs Agora (Laravel AI SDK)

| Aspecto | Prism PHP (direto) | Laravel AI SDK (Agent) |
|---------|-------------------|----------------------|
| Criacao | Manual | `sail artisan make:agent` |
| System prompt | `->withSystemPrompt(...)` | `instructions()` metodo |
| Structured Output | `->withStructuredOutput(schema: [...])` | `HasStructuredOutput` + `schema()` |
| Acesso resposta | `json_decode($response->text)` | `$response['field']` direto |
| Streaming | Manual | `->stream()` nativo |
| Failover | Manual | `failover: [...]` parametro |
| Testes | Manual | `FakeAi::agent(...)` |
| Conversas | Manual | `RemembersConversations` trait |

## Proximo capitulo

No [Capitulo 9 — RAG com pgvector](09-rag-pgvector.md) vamos implementar busca semantica em documentacoes para enriquecer as analises dos Agents.
