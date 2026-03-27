# Capitulo 10 — Multi-Agents e Tool Use

> **Este capitulo cobre os pilares: Multi-Agent Systems (4), Tool Use (5) e Agent Orchestration (7)**

Neste capitulo vamos criar o **sistema multi-agent** do projeto: 3 sub-Agents analistas, 4 Tools (incluindo o padrao Agent-as-Tool), o Agent orquestrador **CodeMentor** e o **ImprovementPlanService** que dispara tudo. Ao final, o LLM sera capaz de delegar analises para agentes especialistas e persistir melhorias no banco automaticamente.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap10-multi-agents
cd codereview-ai
```

---

## Conceitos-chave antes de codar

### Tool Use (Function Calling)

**Tool Use** permite que um LLM chame funcoes no seu codigo. Em vez de gerar apenas texto, o LLM decide "preciso consultar o analista de seguranca" e o Laravel AI SDK executa a Tool correspondente automaticamente.

```
Sem Tools:                          Com Tools:
LLM -> "O codigo tem problemas..."  LLM -> tool_call: analyze_security()
                                         | executa PHP
                                    PHP -> retorna analise + refs OWASP
                                         | injeta no contexto
                                    LLM -> "Conforme OWASP A03, o trecho..."
```

### Padrao Agent-as-Tool

O padrao central deste capitulo e o **Agent-as-Tool**: uma Tool que, ao ser executada, instancia e chama outro Agent. Isso permite que o Agent orquestrador **delegue** tarefas para sub-Agents especialistas sem saber dos detalhes internos.

```
CodeMentor Agent (orquestrador)
  |
  +-- tool_call: analyze_architecture(context)
  |     |
  |     +-- AnalyzeArchitecture Tool -> instancia ArchitectureAnalyst Agent
  |                                      -> ArchitectureAnalyst usa SearchDocsKnowledgeBase Tool (RAG)
  |                                      -> retorna JSON estruturado
  |
  +-- tool_call: analyze_performance(context)
  |     +-- AnalyzePerformance Tool -> instancia PerformanceAnalyst Agent
  |
  +-- tool_call: analyze_security(context)
  |     +-- AnalyzeSecurity Tool -> instancia SecurityAnalyst Agent
  |
  +-- tool_call: store_improvements([...])
        +-- StoreImprovements Tool -> salva no banco via Eloquent
```

### Arquitetura geral

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

## Passo 1 — Criar os 3 sub-Agents analistas

Cada sub-Agent e especialista em um pilar (arquitetura, performance, seguranca). Todos implementam `HasStructuredOutput` para retornar JSON tipado.

### 1.1 — Scaffold via Artisan

```bash
sail artisan make:agent ArchitectureAnalyst --structured
sail artisan make:agent PerformanceAnalyst --structured
sail artisan make:agent SecurityAnalyst --structured
```

### 1.2 — ArchitectureAnalyst

Edite `app/Ai/Agents/ArchitectureAnalyst.php`:

```php
<?php

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

**O que cada interface faz:**
- `Agent` — contrato base do Laravel AI SDK (obriga `instructions()`)
- `HasTools` — permite que este Agent use Tools (no caso, `SearchDocsKnowledgeBase` para RAG)
- `HasStructuredOutput` — forca a resposta em JSON tipado via `schema()`
- `Promptable` — trait que adiciona o metodo `prompt()` para chamar o LLM

### 1.3 — PerformanceAnalyst

Edite `app/Ai/Agents/PerformanceAnalyst.php`:

```php
<?php

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

### 1.4 — SecurityAnalyst

Edite `app/Ai/Agents/SecurityAnalyst.php`:

```php
<?php

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

```bash
# Commitar sub-agents
cd ~/laravel_ai
git add .
git commit -m "feat: add ArchitectureAnalyst, PerformanceAnalyst and SecurityAnalyst agents"
```

---

## Passo 2 — Criar os prompts Blade dos sub-Agents

Cada sub-Agent carrega suas instrucoes de um arquivo Blade. Esses prompts definem a **persona** e o **escopo** de cada analista.

### 2.1 — Prompt de arquitetura

Crie `resources/views/prompts/architecture-analysis.blade.php`:

```php
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

### 2.2 — Prompt de performance

Crie `resources/views/prompts/performance-analysis.blade.php`:

```php
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

### 2.3 — Prompt de seguranca

Crie `resources/views/prompts/security-analysis.blade.php`:

```php
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

```bash
# Commitar prompts
cd ~/laravel_ai
git add .
git commit -m "feat: add Blade prompts for analyst sub-agents"
```

---

## Passo 3 — Criar as Tools Agent-as-Tool

Agora vamos criar as Tools que fazem a ponte entre o Agent orquestrador e os sub-Agents. Este e o **padrao Agent-as-Tool**: a Tool recebe parametros do LLM, instancia um sub-Agent, chama `prompt()` e retorna o resultado como string JSON.

### 3.1 — Scaffold via Artisan

```bash
sail artisan make:tool AnalyzeArchitecture
sail artisan make:tool AnalyzePerformance
sail artisan make:tool AnalyzeSecurity
sail artisan make:tool StoreImprovements
# SearchDocsKnowledgeBase ja foi criado no Capitulo 9
```

### 3.2 — AnalyzeArchitecture Tool

Edite `app/Ai/Tools/AnalyzeArchitecture.php`:

```php
<?php

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
        // Padrao Agent-as-Tool: instancia o sub-Agent e chama prompt()
        // Usa modelo mais leve (flash-lite) para economia
        $response = (new ArchitectureAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
```

**Anatomia do padrao Agent-as-Tool:**

```
1. O LLM do CodeMentor decide chamar analyze_architecture(context: "...")
2. O SDK executa AnalyzeArchitecture::execute()
3. Dentro de execute(), instanciamos ArchitectureAnalyst (outro Agent)
4. Chamamos prompt() com Lab::Gemini e modelo flash-lite (mais barato)
5. O ArchitectureAnalyst pode usar suas proprias Tools (RAG)
6. O resultado estruturado volta como JSON string
7. O SDK injeta esse JSON no contexto do CodeMentor
8. O CodeMentor continua seu raciocinio com os dados do analista
```

### 3.3 — AnalyzePerformance Tool

Edite `app/Ai/Tools/AnalyzePerformance.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Agents\PerformanceAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzePerformance implements Tool
{
    public function name(): string
    {
        return 'analyze_performance';
    }

    public function description(): string
    {
        return 'Consult the Performance Analyst agent to identify bottlenecks, '
            . 'N+1 queries, caching opportunities and optimization suggestions.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for performance analysis');
    }

    public function execute(array $parameters): string
    {
        $response = (new PerformanceAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
```

### 3.4 — AnalyzeSecurity Tool

Edite `app/Ai/Tools/AnalyzeSecurity.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Agents\SecurityAnalyst;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Enums\Lab;

class AnalyzeSecurity implements Tool
{
    public function name(): string
    {
        return 'analyze_security';
    }

    public function description(): string
    {
        return 'Consult the Security Analyst agent to identify vulnerabilities, '
            . 'OWASP Top 10 issues and security best practices violations.';
    }

    public function schema(): ToolSchema
    {
        return ToolSchema::make()
            ->string('context', 'Code and context for security analysis');
    }

    public function execute(array $parameters): string
    {
        $response = (new SecurityAnalyst)->prompt(
            $parameters['context'],
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash-lite',
        );

        return json_encode($response->toArray());
    }
}
```

```bash
# Commitar tools Agent-as-Tool
cd ~/laravel_ai
git add .
git commit -m "feat: add AnalyzeArchitecture, AnalyzePerformance and AnalyzeSecurity tools"
```

---

## Passo 4 — Criar a StoreImprovements Tool

Esta Tool nao chama nenhum Agent — ela persiste as melhorias geradas pelo CodeMentor diretamente no banco de dados via Eloquent.

Edite `app/Ai/Tools/StoreImprovements.php`:

```php
<?php

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

**Pontos importantes:**
- Recebe `Project` via construtor — injetado pelo `CodeMentor` ao registrar a Tool
- `improvement_step_id` sempre comeca como `1` (ToDo) — o usuario move no Kanban depois
- `order` usa o indice do array para manter a sequencia definida pelo Agent

```bash
# Commitar StoreImprovements
cd ~/laravel_ai
git add .
git commit -m "feat: add StoreImprovements tool for persisting improvements"
```

---

## Passo 5 — Criar o CodeMentor Agent (Orquestrador)

O CodeMentor e o **Agent principal** que coordena todo o fluxo. Ele recebe o codigo do usuario, decide quais analistas consultar, coleta os resultados e persiste as melhorias.

### 5.1 — Scaffold via Artisan

```bash
sail artisan make:agent CodeMentor
```

### 5.2 — Implementar o Agent

Edite `app/Ai/Agents/CodeMentor.php`:

```php
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
```

**O que acontece quando o CodeMentor roda:**

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

```bash
# Commitar CodeMentor
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeMentor orchestrator agent"
```

---

## Passo 6 — Criar o prompt do CodeMentor

Crie `resources/views/prompts/code-mentor.blade.php`:

```php
Voce e um Code Mentor Senior com experiencia em arquitetura de software,
performance e seguranca.

Projeto: {{ $project->name }}
Linguagem: {{ $project->language }}

@if($codeReview && $codeReview->findings->count())
Findings do code review anterior:
@foreach($codeReview->findings as $finding)
- [{{ $finding->pillar->name }}] [{{ $finding->severity }}] {{ $finding->description }}
@endforeach
@endif

Sua missao:
1. Use analyze_architecture para avaliar a qualidade arquitetural do codigo
2. Use analyze_performance para identificar gargalos de performance
3. Use analyze_security para encontrar vulnerabilidades
4. Com base nas 3 analises, gere um plano de melhorias priorizado
5. Use store_improvements para salvar as melhorias no banco de dados
6. Retorne um resumo executivo do plano

IMPORTANTE: Sempre consulte os 3 analistas ANTES de gerar o plano final.
Cada melhoria deve ter: title, description, improvement_type_id (1=Refactor, 2=Fix, 3=Optimization),
file_path (se aplicavel) e priority (0=baixa, 1=media, 2=alta).
```

```bash
# Commitar prompt do CodeMentor
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeMentor Blade prompt"
```

---

## Passo 7 — Criar o ImprovementPlanService

O Service e o ponto de entrada que dispara o CodeMentor. Ele carrega os dados do projeto, chama o Agent e marca que o plano foi criado.

Crie `app/Services/ImprovementPlanService.php`:

```php
<?php

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

**Como o Service funciona:**
1. Carrega o projeto com findings priorizados (flagged pelo Agent ou pelo usuario)
2. Monta o prompt com o codigo e os findings
3. Chama `CodeMentor->prompt()` com o provider Gemini e modelo `gemini-2.5-flash`
4. O CodeMentor **automaticamente** chama os 3 sub-Agents via Tools e salva as melhorias
5. Marca `first_plan_at` no usuario para tracking de onboarding

```bash
# Commitar service
cd ~/laravel_ai
git add .
git commit -m "feat: add ImprovementPlanService to orchestrate CodeMentor"
```

---

## Passo 8 — Verificar a estrutura criada

Verifique se todos os arquivos foram criados corretamente:

```bash
# Verificar Agents
ls -la app/Ai/Agents/
# Deve listar: ArchitectureAnalyst.php, PerformanceAnalyst.php, SecurityAnalyst.php, CodeMentor.php

# Verificar Tools
ls -la app/Ai/Tools/
# Deve listar: AnalyzeArchitecture.php, AnalyzePerformance.php, AnalyzeSecurity.php, StoreImprovements.php

# Verificar prompts
ls -la resources/views/prompts/
# Deve listar: architecture-analysis.blade.php, performance-analysis.blade.php, security-analysis.blade.php, code-mentor.blade.php

# Verificar service
ls -la app/Services/ImprovementPlanService.php

# Verificar sintaxe PHP de todos os arquivos
sail artisan about
```

Se `sail artisan about` executar sem erros, toda a sintaxe PHP esta correta.

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

## Passo 9 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: complete multi-agent system with CodeMentor orchestrator"

# Push da branch
git push -u origin feat/cap10-multi-agents

# Criar Pull Request
gh pr create --title "feat: multi-agents e tool use" --body "Capitulo 10 - Sub-Agents analistas, Tools Agent-as-Tool, CodeMentor orquestrador e ImprovementPlanService"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `app/Ai/Agents/ArchitectureAnalyst.php` | Sub-Agent: analise arquitetural com Structured Output |
| `app/Ai/Agents/PerformanceAnalyst.php` | Sub-Agent: analise de performance com Structured Output |
| `app/Ai/Agents/SecurityAnalyst.php` | Sub-Agent: analise de seguranca com Structured Output |
| `app/Ai/Agents/CodeMentor.php` | Agent orquestrador: coordena sub-Agents e Tools |
| `app/Ai/Tools/AnalyzeArchitecture.php` | Tool Agent-as-Tool: delega para ArchitectureAnalyst |
| `app/Ai/Tools/AnalyzePerformance.php` | Tool Agent-as-Tool: delega para PerformanceAnalyst |
| `app/Ai/Tools/AnalyzeSecurity.php` | Tool Agent-as-Tool: delega para SecurityAnalyst |
| `app/Ai/Tools/StoreImprovements.php` | Tool: persiste melhorias no banco via Eloquent |
| `resources/views/prompts/architecture-analysis.blade.php` | Prompt: persona do arquiteto |
| `resources/views/prompts/performance-analysis.blade.php` | Prompt: persona do engenheiro de performance |
| `resources/views/prompts/security-analysis.blade.php` | Prompt: persona do especialista em seguranca |
| `resources/views/prompts/code-mentor.blade.php` | Prompt: instrucoes do orquestrador CodeMentor |
| `app/Services/ImprovementPlanService.php` | Service: ponto de entrada que dispara o CodeMentor |

## Proximo capitulo

No [Capitulo 11 — Jobs, Filas e Processamento](11-jobs-filas.md) vamos ver como o processamento assincrono conecta o frontend com os Agents de IA.
