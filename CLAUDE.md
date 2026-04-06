# CodeReview AI — Contexto do Projeto

## O que é este projeto

Tutorial passo a passo de um SaaS de code review com IA construído com Laravel 13 + Laravel AI SDK.
O repositório contém:
- `codereview-ai/` — aplicação Laravel (backend + frontend Livewire)
- `docs/` — 14 capítulos do tutorial em Markdown

Diego escreve o tutorial, segue os passos, encontra erros reais e eu corrijo tanto o código quanto o `.md`.

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 13, PHP 8.5 |
| Frontend | Livewire 4 + Volt, Tailwind CSS |
| IA | Laravel AI SDK 0.4.x, Gemini (gemini-2.5-flash) |
| Embeddings | Gemini gemini-embedding-001, 768 dims |
| Banco | PostgreSQL + pgvector |
| Queue | Laravel Queue (database driver) |
| Container (dev) | Laravel Sail |
| Container (prod) | Docker multi-stage + Supervisor |
| Testes | Pest PHP |

## Arquitetura de IA

```
CodeAnalyst (Agent)          — analisa codigo, structured output
SecurityAnalyst (Agent)      — pilar segurança (OWASP)
ArchitectureAnalyst (Agent)  — pilar arquitetura (PSR)
PerformanceAnalyst (Agent)   — pilar performance
CodeMentor (Agent)           — orquestra e gera plano de melhorias
SearchDocsKnowledgeBase      — Tool RAG: busca pgvector nos docs importados
```

Jobs assíncronos:
- `AnalyzeCodeJob` — dispara CodeAnalyst
- `GenerateImprovementsJob` — dispara multi-agentes + CodeMentor

## Convenções do projeto

- **Idioma:** Português brasileiro em todos os docs e comentários
- **Commits:** `feat:`, `fix:`, `chore:` — mensagens curtas e técnicas (sem explicações longas)
- **Branch:** `feat/cap{N}-{nome}` por capítulo
- **Testes:** Pest com `uses(TestCase, RefreshDatabase)` + `beforeEach(LookupSeeder)`
- **Factories:** `ProjectFactory` e `CodeReviewFactory` existem em `database/factories/`
- **Tinker:** Sempre usar `--execute` para evitar problemas de multiline no psysh

## APIs críticas do Laravel AI SDK (versão atual)

```php
// Agent prompt
(new CodeAnalyst($review))->prompt($context, provider: Lab::Gemini, model: 'gemini-2.5-flash');

// Embeddings
Embeddings::for([$text])->dimensions(768)->generate(Lab::Gemini, 'gemini-embedding-001');
$result->first(); // retorna array<float>, NÃO objeto

// Fake em testes
Ai::fakeAgent(CodeAnalyst::class, [['summary' => '...', 'score' => 85, ...]]);
Embeddings::fake([[array_fill(0, 768, 0.1)]]);

// Tool interface (Tool::class)
public function schema(JsonSchema $schema): array  // NÃO ToolSchema
public function handle(Request $request): string   // NÃO execute(array)
```

## Erros conhecidos / armadilhas

- `opcache` no PHP 8.5 é built-in — não usar `docker-php-ext-install opcache`
- `FakeAi` não existe — usar `Ai::fakeAgent()` e `Embeddings::fake()`
- `EmbeddingsResponse::first()` retorna `array<float>`, não objeto
- Gemini rate limit: ~15 req/min no free tier, cota diária reseta às 05:00 BRT
- pgvector HNSW: máximo 2000 dims — usar `->dimensions(768)` para truncar
- `$event->agent` não existe em `AgentPrompted` — usar `$event->prompt->agent`
- Tools com `schema(): ToolSchema` (API antiga) causam fatal error no PHP 8.5 — usar `schema(JsonSchema $schema): array` e `handle(Request $request): string`
- Rotas `/login` e `/register` são Livewire Volt (só GET) — testar com `Volt::test()` não `$this->post()`
- Scramble restringe docs a env `local` — em testes usar `Gate::before(fn () => true)` + `actingAs`
- Em Pest 4, usar `pest()->extend()->beforeEach()->in()` encadeado — `beforeEach()->in()` standalone não funciona

## Como rodar

```bash
cd codereview-ai
sail up -d
sail artisan migrate
sail artisan db:seed --class=LookupSeeder
sail artisan docs:import psr-12   # importar knowledge base
sail artisan queue:work --tries=3 -v
sail artisan test
```

## Estrutura dos docs

Cada capítulo em `docs/NN-nome.md` cobre um passo do tutorial.
Quando Diego encontra erro ao seguir o passo, corrijo o código E o `.md` correspondente.
Capítulo atual: **14-testes.md** (concluído)
