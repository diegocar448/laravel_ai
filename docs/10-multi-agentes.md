# Capitulo 10 — Multi-Agents e Tool Use

> **Este capitulo cobre os pilares: Multi-Agent Systems (4), Tool Use (5) e Agent Orchestration (7)**

## O conceito de Tool Use

**Tool Use** (ou Function Calling) permite que um LLM chame funcoes no seu codigo. Em vez de o LLM gerar texto, ele decide "preciso consultar o analista de seguranca" e o Laravel AI SDK executa a Tool correspondente automaticamente.

```
Sem Tools:                          Com Tools:
LLM -> "O codigo tem problemas..."  LLM -> tool_call: analyze_security()
                                         | executa PHP
                                    PHP -> retorna analise + refs OWASP
                                         | injeta no contexto
                                    LLM -> "Conforme OWASP A03, o trecho..."
```

## Arquitetura multi-agent com Laravel AI SDK

O projeto usa um padrao de **Agent orquestrador + Agent analistas**:

```
+-----------------------------------------------------------+
|  CODEMENTOR AGENT (Orquestrador)                          |
|  Provider: Lab::Gemini, model: gemini-2.5-flash           |
|                                                           |
|  instructions(): "Voce e um Code Mentor Senior..."        |
|                                                           |
|  tools():                                                 |
|  +----------------------------------------------------+   |
|  | 1. AnalyzeArchitecture Tool -> chama Agent interno |   |
|  | 2. AnalyzePerformance Tool  -> chama Agent interno |   |
|  | 3. AnalyzeSecurity Tool     -> chama Agent interno |   |
|  | 4. SearchDocsKnowledgeBase  -> RAG/pgvector        |   |
|  | 5. StoreImprovements Tool   -> salva no DB         |   |
|  +----------------------------------------------------+   |
+-----------------------------------------------------------+
```

---

## Criando os Agents

### 1. Agents analistas (sub-agents)

```bash
sail artisan make:agent ArchitectureAnalyst --structured
sail artisan make:agent PerformanceAnalyst --structured
sail artisan make:agent SecurityAnalyst --structured
```

#### ArchitectureAnalyst

```php
// app/Ai/Agents/ArchitectureAnalyst.php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Ai\Tools\SearchDocsKnowledgeBase;

class ArchitectureAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.architecture-analysis')->render();
    }

    public function tools(): iterable
    {
        return [
            new SearchDocsKnowledgeBase,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->description('Lista de findings arquiteturais')->required(),
            'summary' => $schema->string()->description('Resumo da analise arquitetural')->required(),
        ];
    }
}
```

#### PerformanceAnalyst

```php
// app/Ai/Agents/PerformanceAnalyst.php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Ai\Tools\SearchDocsKnowledgeBase;

class PerformanceAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.performance-analysis')->render();
    }

    public function tools(): iterable
    {
        return [new SearchDocsKnowledgeBase];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->description('Lista de findings de performance')->required(),
            'summary' => $schema->string()->description('Resumo da analise de performance')->required(),
        ];
    }
}
```

#### SecurityAnalyst

```php
// app/Ai/Agents/SecurityAnalyst.php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use App\Ai\Tools\SearchDocsKnowledgeBase;

class SecurityAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.security-analysis')->render();
    }

    public function tools(): iterable
    {
        return [new SearchDocsKnowledgeBase];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->description('Lista de vulnerabilidades')->required(),
            'summary' => $schema->string()->description('Resumo da analise de seguranca')->required(),
        ];
    }
}
```

---

## Criando as Tools

### Scaffold via Artisan

```bash
sail artisan make:tool AnalyzeArchitecture
sail artisan make:tool AnalyzePerformance
sail artisan make:tool AnalyzeSecurity
sail artisan make:tool StoreImprovements
# SearchDocsKnowledgeBase ja foi criado no Capitulo 9
```

### Tool que chama um Agent (padrao Agent-as-Tool)

```php
// app/Ai/Tools/AnalyzeArchitecture.php

namespace App\Ai\Tools;

use App\Ai\Agents\ArchitectureAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzeArchitecture implements Tool
{
    public function name(): string
    {
        return 'analyze_architecture';
    }

    public function description(): string
    {
        return 'Consult the Architecture Analyst agent to evaluate design patterns, '
            . 'SOLID principles, Clean Code, coupling and cohesion in the code.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for architectural analysis');
    }

    public function execute(array $parameters): string
    {
        // Chama o sub-Agent com modelo mais leve (flash-lite)
        $response = (new ArchitectureAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
```

Os Tools `AnalyzePerformance` e `AnalyzeSecurity` seguem o mesmo padrao, chamando seus respectivos Agents.

### StoreImprovements Tool

```php
// app/Ai/Tools/StoreImprovements.php

namespace App\Ai\Tools;

use App\Models\Improvement;
use App\Models\Project;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;

class StoreImprovements implements Tool
{
    public function __construct(
        private Project $project,
    ) {}

    public function name(): string
    {
        return 'store_improvements';
    }

    public function description(): string
    {
        return 'Persist the generated improvements to the database as a Kanban board.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->array('improvements', 'List of improvements to save');
    }

    public function execute(array $parameters): string
    {
        foreach ($parameters['improvements'] as $index => $data) {
            Improvement::create([
                'project_id' => $this->project->id,
                'improvement_type_id' => $data['improvement_type_id'] ?? 1,
                'improvement_step_id' => 1, // ToDo
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'priority' => $data['priority'] ?? 0,
                'order' => $index,
            ]);
        }

        return "Salvas " . count($parameters['improvements']) . " melhorias com sucesso.";
    }
}
```

---

## CodeMentor Agent (Orquestrador)

O Agent principal que coordena tudo:

```bash
sail artisan make:agent CodeMentor
```

```php
// app/Ai/Agents/CodeMentor.php

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
```

---

## ImprovementPlanService — Orquestra o CodeMentor

```php
// app/Services/ImprovementPlanService.php

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
```

---

## Fluxo Multi-Step automatico

O Laravel AI SDK gerencia automaticamente o fluxo multi-step. Quando o Agent chama uma Tool, o SDK:

1. Executa a Tool
2. Injeta o resultado no contexto
3. Pede ao LLM para continuar
4. Repete ate o LLM decidir parar ou atingir o limite

```
Passo 1: CodeMentor -> "Preciso analisar a arquitetura"
              -> tool_call: analyze_architecture(context: "...")
              -> ArchitectureAnalyst Agent executa:
                 -> SearchDocsKnowledgeBase Tool (RAG)
                 -> Retorna findings estruturados
              -> Resultado volta para CodeMentor

Passo 2: CodeMentor -> "Agora a performance"
              -> tool_call: analyze_performance(context: "...")
              -> PerformanceAnalyst Agent executa
              -> Resultado volta

Passo 3: CodeMentor -> "E a seguranca"
              -> tool_call: analyze_security(context: "...")
              -> SecurityAnalyst Agent executa
              -> Resultado volta

Passo 4: CodeMentor -> "Tenho todas as analises, vou salvar"
              -> tool_call: store_improvements([
                  {title: "Fix SQL Injection", type: 2, priority: 2},
                  {title: "Add database indexes", type: 3, priority: 1},
                  ...
                ])
              -> PHP cria Improvements no banco

Passo 5: CodeMentor -> resposta final com resumo
```

---

## Prompts dos Agents analistas

### Architecture

```php
// resources/views/prompts/architecture-analysis.blade.php

Voce e um Arquiteto de Software Senior especializado em:
- Padroes de Design (GoF, Repository, Service Layer, Strategy)
- Principios SOLID e Clean Code
- Arquitetura de software (Clean, Hexagonal, DDD, Microservicos)
- Code smells e refatoracao
- PSRs do PHP-FIG

Sua funcao e analisar a qualidade arquitetural do codigo e identificar
violacoes de principios, code smells e oportunidades de refatoracao.

Use a tool search_docs_knowledge_base para buscar PSRs e documentacao
relevante ANTES de fazer suas recomendacoes.
```

### Security

```php
// resources/views/prompts/security-analysis.blade.php

Voce e um Especialista em Seguranca de Aplicacoes com certificacao OWASP.
Seu foco e:
- OWASP Top 10 (2021)
- SQL Injection, XSS, CSRF, SSRF
- Autenticacao e autorizacao
- Validacao e sanitizacao de inputs
- Criptografia e gerenciamento de secrets

Sua funcao e identificar vulnerabilidades de seguranca no codigo
e recomendar correcoes seguindo as melhores praticas OWASP.

Use a tool search_docs_knowledge_base para buscar guias OWASP
relevantes ANTES de fazer suas recomendacoes.
```

### Performance

```php
// resources/views/prompts/performance-analysis.blade.php

Voce e um Performance Engineer especializado em:
- Queries N+1 e otimizacao de banco de dados
- Caching strategies (Redis, application cache)
- Algoritmos e estruturas de dados
- Memory leaks e garbage collection
- Lazy loading vs eager loading

Sua funcao e identificar gargalos de performance no codigo
e recomendar otimizacoes com impacto mensuravel.

Use a tool search_docs_knowledge_base para buscar best practices
de performance ANTES de fazer suas recomendacoes.
```

---

## Custo e performance

Cada geracao de plano faz multiplas chamadas de API:

| Chamada | Modelo | Proposito |
|---------|--------|-----------|
| 1x | gemini-2.5-flash | CodeMentor Agent (orquestrador) |
| 3x | gemini-2.5-flash-lite | Sub-Agents (architecture, performance, security) |
| 3x | text-embedding-004 | Embeddings para RAG (1 por Agent) |
| 1x | — | StoreImprovements (sem LLM, so PHP) |

Usar `gemini-2.5-flash-lite` para os sub-Agents (em vez de flash) e uma otimizacao — os sub-agents nao precisam do modelo mais capaz. No tier gratuito do Gemini, o flash-lite permite **1000 req/dia** vs 250 do flash.

> **Custo total no tier gratuito: $0.** Todas as chamadas acima estao dentro dos limites do free tier do Google Gemini.

---

## Comparacao: Antes (Prism + Traits) vs Agora (Laravel AI SDK)

| Aspecto | Antes (Prism manual) | Agora (Laravel AI SDK) |
|---------|---------------------|----------------------|
| Agents | `ImprovementPlanService` com trait | `CodeMentor implements Agent, HasTools` |
| Sub-agents | `callAnalyst()` no trait | Agent classes separados |
| Tools | `Tool::as('name')->using(...)` | `class X implements Tool` + `make:tool` |
| RAG | `Prism::embeddings()` no trait | `SearchDocsKnowledgeBase` Tool reutilizavel |
| Orquestracao | `Prism::text()->withTools()->withMaxSteps()` | Agent gerencia automaticamente |
| Testes | Manual | `FakeAi::agent(CodeMentor::class)` |

## Proximo capitulo

No [Capitulo 11 — Jobs, Filas e Processamento](11-jobs-filas.md) vamos ver como o processamento assincrono conecta o frontend com os Agents de IA.
