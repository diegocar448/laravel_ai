# Capitulo 1 — Introducao e Pilares de AI Engineering

Bem-vindo ao **CodeReview AI**, um tutorial pratico de **AI Engineering com Laravel 13** usando o **Laravel AI SDK**.

Este capitulo posiciona o projeto e define os pilares da Engenharia de IA que voce vai aprender na pratica.

---

## O que e AI Engineering?

**AI Engineering** e a disciplina de **integrar, orquestrar e colocar em producao** modelos de linguagem (LLMs) sem treinar novos modelos. E software engineering para IA.

### Diferenca: ML Engineer vs AI Engineer

```
ML Engineer                          AI Engineer (voce aqui)
+- Treina modelos                   +- Orquestra APIs de LLMs
+- Python, PyTorch, JAX             +- PHP/Laravel, HTTP APIs
+- Datasets, fine-tuning            +- Prompts, RAG, tools
+- GPUs durante meses               +- Deploy em dias
+- Rare (hard to hire)              +- Demanda explodindo em 2026
```

**Exemplo:**

```python
# ML Engineer: treina modelo customizado
from transformers import Trainer, TrainingArguments
trainer = Trainer(model=model, optimizer=optimizer, train_dataset=data)
trainer.train(num_epochs=100)  # horas/dias

# AI Engineer: orquestra APIs
response = Gemini.text(prompt, tools=[analyze_sql, check_xss, ...])
improvements = parse_structured_output(response)  # minutos
```

---

## Laravel AI SDK — O Toolkit Oficial

O **Laravel AI SDK** (`laravel/ai`) e o pacote first-party oficial do Laravel para AI Engineering. Ele substitui a necessidade de usar Prism PHP diretamente, oferecendo uma API mais Laravel-way.

### O que o SDK oferece

| Feature | Descricao | Capitulo |
|---------|-----------|----------|
| **Agents** | Classes PHP autonomas com `instructions()`, `tools()`, `schema()` | 8, 10 |
| **Structured Output** | `HasStructuredOutput` com `JsonSchema` nativo | 8 |
| **Tools** | `HasTools` + `make:tool` — function calling automatico | 10 |
| **Embeddings** | `Ai::embeddings()` — geracao de vetores para RAG | 9 |
| **Conversation Memory** | `RemembersConversations` — persistencia automatica | 10 |
| **Streaming** | Respostas em tempo real via `->stream()` | 8 |
| **Failover** | Fallback automatico entre providers | 8 |
| **Testing** | `FakeAi` para testes sem chamar API | 11 |
| **Images/Audio** | Geracao de imagens e audio (bonus) | — |

### Agents: O conceito central

No Laravel AI SDK, tudo gira em torno de **Agents**. Um Agent e uma classe PHP que:

```php
// app/Ai/Agents/SecurityAnalyst.php
class SecurityAnalyst implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    // 1. Instrucoes (system prompt)
    public function instructions(): string
    {
        return 'You are a Security Expert specialized in OWASP Top 10...';
    }

    // 2. Ferramentas que a IA pode chamar
    public function tools(): iterable
    {
        return [new SearchDocsKnowledgeBase];
    }

    // 3. Schema da resposta (Structured Output)
    public function schema(JsonSchema $schema): array
    {
        return [
            'findings' => $schema->array()->required(),
            'severity' => $schema->string()->required(),
        ];
    }
}

// Uso: uma linha!
$response = (new SecurityAnalyst)->prompt($code, provider: Lab::Gemini);
echo $response['severity']; // 'critical'
```

### Scaffold com Artisan

```bash
# Criar Agent
sail artisan make:agent SecurityAnalyst --structured

# Criar Tool
sail artisan make:tool SearchDocsKnowledgeBase
```

---

## Os 8 Pilares de AI Engineering

Este tutorial ensina cada pilar com exemplos praticos em Laravel usando o AI SDK.

### 1. Prompt Engineering

**O que:** Escrever instrucoes efetivas para LLMs.
**Por que:** Qualidade do prompt = qualidade da resposta.

```php
// Agent com instructions (system prompt)
class CodeAnalyst implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        // Blade template como system prompt
        return view('prompts.code-review', [
            'language' => $this->project->language,
        ])->render();
    }
}
```

**Tecnicas:**
- **Role-based**: "You are a security expert..."
- **Few-shot**: Incluir exemplos de analise esperada
- **Chain-of-thought**: "Think step by step..."
- **Output formatting**: "Return ONLY valid JSON"

**No projeto:** Capitulo 8 — Agent `instructions()` com templates Blade

---

### 2. Structured Output

**O que:** Forcar LLM responder em formato tipado (JSON Schema).
**Por que:** Responses inconsistentes causam bugs — tipagem resolve.

```php
// Agent com HasStructuredOutput
class CodeAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'score' => $schema->integer()->min(0)->max(100)->required(),
            'priority_finding_ids' => $schema->array()->required(),
        ];
    }
}

// Uso: resposta sempre tipada
$response = (new CodeAnalyst)->prompt($code);
echo $response['score'];    // 72 (integer, garantido)
echo $response['summary'];  // string, nunca null
```

**No projeto:** Capitulo 8 — `HasStructuredOutput` + `JsonSchema`

---

### 3. RAG (Retrieval-Augmented Generation)

**O que:** Buscar documentacao relevante **antes** de fazer prompt.
**Por que:** Combater hallucination, garantir accuracy, usar contexto recente.

```
Fluxo sem RAG:
Codigo -> LLM think -> "Acho que deve usar Factory Pattern"
                     (talvez errado)

Fluxo com RAG:
Codigo -> Ai::embeddings() -> Busca pgvector -> "Top 5 PSRs relevantes"
                                                   |
                              Injeta PSRs no prompt
                                                   |
                              LLM responde baseado em docs reais
                              (accuracy 99%)
```

**No projeto:** Capitulo 9
- `Ai::embeddings()` com `text-embedding-004` (768 dims)
- pgvector com indices HNSW
- Busca semantica de PSRs, OWASP, Laravel docs

---

### 4. Multi-Agent Systems

**O que:** Multiplos Agents especializados, cada um com seu proposito.
**Por que:** Agent generalista e fraco; especialista > generalista.

```
Sem Multi-Agents (1 gigante):
Prompt: "Analise este codigo em TODOS os aspectos"
-> LLM fica confuso, resposta superficial

Com Multi-Agents (3 especialistas via Laravel AI SDK):
+--------------------+--------------------+------------------+
| ArchitectureAnalyst| PerformanceAnalyst | SecurityAnalyst  |
|                    |                    |                  |
| Conhece SOLID,     | Conhece N+1, cache| Conhece OWASP,   |
| Design Patterns,   | optimization,     | CSRF, XSS,       |
| Clean Code         | indexing           | SQL Injection    |
+--------------------+--------------------+------------------+
     |                    |                    |
   Findings             Findings             Findings
     +---------------------+------------------+
                    Combinado em plano
```

**No projeto:** Capitulo 10 — 3 Agent classes em `app/Ai/Agents/`

---

### 5. Tool Use / Function Calling

**O que:** LLM pode chamar funcoes PHP autonomamente.
**Por que:** IA executa acoes, nao apenas gera texto.

```php
// Tool criada via: sail artisan make:tool SearchDocsKnowledgeBase
class SearchDocsKnowledgeBase implements Tool
{
    public function name(): string { return 'search_docs'; }

    public function description(): string
    {
        return 'Search PSRs, OWASP and Laravel docs by semantic similarity';
    }

    public function execute(array $parameters): string
    {
        // RAG: busca pgvector e retorna docs relevantes
        $embedding = Embeddings::for([$parameters['query']])
            ->dimensions(768)
            ->generate(Lab::Gemini, 'gemini-embedding-001')
            ->first();

        $docs = DocEmbedding::query()
            ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
            ->where('category', $parameters['category'])
            ->take(5)->get();

        return $docs->map(fn ($d) => "[{$d->source}] {$d->content}")->implode("\n---\n");
    }
}
```

**No projeto:** Capitulo 10 — `HasTools` + `make:tool` no Prism

---

### 6. Vector Databases

**O que:** Armazena dados em vetores (embeddings), buscar por similaridade.
**Por que:** Cornerstone do RAG, busca semantica em producao.

```sql
-- Tabela de embeddings (RAG knowledge base)
CREATE TABLE doc_embeddings (
    id BIGINT PRIMARY KEY,
    content TEXT,
    embedding vector(768),  -- 768 dims (Gemini text-embedding-004)
    category VARCHAR,       -- 'architecture', 'security', etc
    source VARCHAR          -- 'PSR-12', 'OWASP', 'Laravel Docs'
);

-- Indice HNSW para busca rapida (< 1ms)
CREATE INDEX ON doc_embeddings
    USING hnsw (embedding vector_cosine_ops);
```

**No projeto:** Capitulos 3, 9
- pgvector (extensao PostgreSQL)
- Indices HNSW (Hierarchical Navigable Small Worlds)

---

### 7. Agent Orchestration

**O que:** Executar multiplos agents em pipeline, coletar + combinar resultados.
**Por que:** Coordenar fluxo complexo de IA.

```
Pipeline (Capitulo 10):

+---------------------------------------------+
| 1. Receber codigo                           |
|    |                                        |
| 2. CodeMentor Agent (principal)             |
|    +- Tool Call: ArchitectureAnalyst Agent   |
|    +- Tool Call: PerformanceAnalyst Agent    |
|    +- Tool Call: SecurityAnalyst Agent       |
|    |                                        |
| 3. Cada Agent faz RAG [busca pgvector]      |
| 4. Cada Agent retorna findings estruturados |
|    |                                        |
| 5. CodeMentor combina findings              |
| 6. Tool Call: StoreImprovements             |
| 7. Gera Kanban de melhorias                 |
+---------------------------------------------+
```

**No projeto:** Capitulo 10 — Agent chama Agents via Tools

---

### 8. AI Infrastructure

**O que:** Infraestrutura operacional para IA em producao.
**Por que:** Prompts levam tempo (5-30s), nao pode bloquear request.

```
Problema (sem infra):
User faz upload -> POST /analyze -> PHP espera LLM responder (30s)
                                 -> Timeout

Solucao (com infra):
User faz upload -> POST /analyze -> Dispatch Job AnalyzeCode
                                 -> Retorna: "Analise iniciada"
User recebe notificacao: "Analise concluida"
```

**Componentes:**

| Componente | Funcao |
|-----------|--------|
| **Laravel Queue** | Dispatch jobs async |
| **Worker** | Processa jobs em background |
| **Supervisor** | Supervisa workers 24/7 |
| **FakeAi** | Testa agents sem API real |
| **Events** | `AgentPrompted` para monitoring |

**No projeto:** Capitulos 11, 12

---

## O Fluxo Completo do CodeReview AI

```
User Interface (Capitulo 5, 6)
+- Paste codigo OU URL GitHub
+- Click "Analyze"
+- Kanban aparece

        |

Laravel Queue (Capitulo 11)
+- AnalyzeCodeJob dispatched
+- Adicionado na fila
+- Worker espera

        |

Queue Worker em Background (Capitulo 12)
+- Carrega projeto
+- Inicia CodeAnalyst Agent (Cap 08)
|  +- Agent usa HasStructuredOutput
|  +- Retorna summary + score + priority findings
|
+- Inicia CodeMentor Agent (Cap 10)
|  +- Envia para 3 Agents via Tools
|  |  +- ArchitectureAnalyst Agent
|  |  |  +- RAG: busca pgvector (Cap 09)
|  |  |  +- Prompt + PSRs relevantes
|  |  |  +- Retorna findings estruturados
|  |  |
|  |  +- PerformanceAnalyst Agent (mesmo fluxo)
|  |  +- SecurityAnalyst Agent (mesmo fluxo)
|  |
|  +- Tool Call: StoreImprovements
|     +- Cria Improvements em Kanban

        |

Salva findings em DB (Capitulo 3, 4)
+- CodeReview criado
+- ReviewFindings criados
+- Improvements agrupadas em steps
+- Status = Completed

        |

Frontend atualiza (Capitulo 5, 6)
+- wire:poll detecta mudanca
+- Kanban mostra:
|  +- To Do (30 improvements)
|  +- In Progress (5)
|  +- Done (0)
+- User clica em cada improvement
   +- Details com contexto + fix sugerido
```

---

## Estrutura dos Capitulos

| Cap | Tema | Tecnologias | Pilares de IA |
|-----|------|-------------|---------------|
| 2 | Setup | Docker, Sail, Gemini API | — |
| 3 | Banco de dados | PostgreSQL, pgvector, migrations | Vector DB |
| 4 | Models | Eloquent, relationships | — |
| 5 | Rotas + Livewire | Blade, Volt, forms | — |
| 6 | Design System | Tailwind 4.2, dark mode | — |
| 7 | Autenticacao | Laravel Auth, Blade gates | — |
| 8 | **Agents + Structured Output** | Laravel AI SDK, `make:agent`, JsonSchema | Pilares 1, 2 |
| 9 | **RAG com pgvector** | `Ai::embeddings()`, pgvector, similarity search | Pilares 3, 6 |
| 10 | **Multi-Agents + Tool Use + Orchestration** | `HasTools`, `make:tool`, Agent classes | Pilares 4, 5, 7 |
| 11 | Jobs e Filas | Queue, workers, FakeAi | Pilar 8 |
| 12 | Deploy Docker | Dockerfile, Supervisor, scaling | Pilar 8 |
| 13 | API REST + Swagger | Sanctum, OpenAPI, endpoints REST | — |
| 14 | Testes Automatizados | Pest, FakeAi, unitario, integracao, E2E, smoke | Pilar 8 |

---

## Arquitetura Multi-Agent

```
                    +----------------------+
                    |   CodeMentor Agent   |
                    |   (Orchestrador)     |
                    +------+---------------+
                           | Tool Calls
              +------------+----------------+
              v            v                v
     +------------+ +------------+ +-------------+
     |Architecture| |Performance | |  Security   |
     |  Analyst   | |  Analyst   | |  Analyst    |
     |   Agent    | |   Agent    | |   Agent     |
     +------+-----+ +------+-----+ +------+------+
            |              |               |
            v              v               v
     +---------------------------------------------+
     |      SearchDocsKnowledgeBase (RAG Tool)     |
     |    PSRs, OWASP, Laravel docs, patterns      |
     |        pgvector similarity search            |
     +---------------------------------------------+
```

## Arquitetura tecnica

### Por que nao ha Controllers?

Este projeto usa **Livewire 4.2 Volt** (single-file components). Toda a logica que normalmente ficaria em controllers esta dentro dos proprios arquivos Blade, usando a diretiva `<?php` no topo do arquivo.

### Por que PostgreSQL e nao MySQL?

O projeto usa **pgvector**, uma extensao do PostgreSQL que adiciona suporte nativo a vetores e busca por similaridade. Isso e essencial para o RAG — sem pgvector, seria necessario um servico externo como Pinecone ou Weaviate.

### Por que Blade templates para prompts?

Os prompts de IA ficam em `resources/views/prompts/`. Usar Blade permite:
- Interpolar variaveis PHP nos prompts (`{{ $project->language }}`)
- Reutilizar partials entre prompts
- Versionar prompts junto com o codigo
- Usar a mesma engine de template que o resto da aplicacao

## Estrutura de pastas do projeto

```
codereview-ai/
+-- app/
|   +-- Ai/
|   |   +-- Agents/           # Agent classes (Laravel AI SDK)
|   |   |   +-- CodeAnalyst.php
|   |   |   +-- CodeMentor.php
|   |   |   +-- ArchitectureAnalyst.php
|   |   |   +-- PerformanceAnalyst.php
|   |   |   +-- SecurityAnalyst.php
|   |   +-- Tools/            # Tool classes (make:tool)
|   |       +-- SearchDocsKnowledgeBase.php
|   |       +-- AnalyzeArchitecture.php
|   |       +-- AnalyzePerformance.php
|   |       +-- AnalyzeSecurity.php
|   |       +-- StoreImprovements.php
|   +-- Enums/                # Enums PHP 8.5
|   +-- Jobs/                 # AnalyzeCodeJob, GenerateImprovementsJob
|   +-- Livewire/Forms/       # Form objects do Livewire
|   +-- Models/               # Eloquent models
|   +-- Services/             # Services auxiliares
+-- database/migrations/      # Migrations (inclui pgvector)
+-- resources/views/
|   +-- components/           # 20+ componentes do Design System
|   +-- pages/                # Paginas Livewire 4.2 Volt
|   +-- prompts/              # Templates de prompts de IA
+-- routes/web.php            # Rotas Livewire (sem api.php)
+-- docker/                   # Configs nginx + supervisor
+-- Dockerfile                # Multi-stage para producao
+-- compose.yaml              # Sail + pgvector
```

## Proximo capitulo

No [Capitulo 2 — Setup do Ambiente](02-setup-ambiente.md) vamos configurar todo o ambiente de desenvolvimento com Docker e Sail.
