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

4. **Gerar** uma analise detalhada em markdown com:
   - Score geral do codigo (0-100)
   - Analise por pilar com exemplos especificos do codigo
   - Sugestoes concretas de refatoracao com code snippets
   - Quick wins (melhorias rapidas de alto impacto)

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

## Passo 8 — Verificar o Agent no Tinker

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

## Passo 9 — Providers suportados (referencia)

| Provider | Modelos | Custo | Ideal para |
|----------|---------|-------|-----------|
| **Google Gemini** (padrao) | gemini-2.5-flash, flash-lite | **Gratis** (250-1000 req/dia) | Tutorial, prototipos |
| OpenAI | gpt-4o, gpt-4o-mini | Pre-pago ($5 minimo) | Producao high-volume |
| Anthropic | claude-sonnet-4, claude-haiku | Pre-pago | Analise complexa |
| Ollama | llama3, codellama | Gratis (local) | Offline, privacidade |
| Groq | llama-3.3-70b | Gratis (rate limit) | Fast inference |

Para trocar de provider, basta alterar `AI_PROVIDER` e a API key no `.env`.

---

## Passo 10 — Commitar e criar PR

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
| `resources/views/prompts/code-review-system-prompt.blade.php` | System prompt Blade com dados interpolados do projeto |
| `app/Services/CodeAnalysisService.php` | Orquestra Agent -> Eloquent (com failover) |
| `app/Livewire/Forms/CodeReviewForm.php` | Formulario que cria findings e dispara job de IA |

## Proximo capitulo

No [Capitulo 9 — RAG com pgvector](09-rag-pgvector.md) vamos implementar busca semantica em documentacoes para enriquecer as analises dos Agents.
