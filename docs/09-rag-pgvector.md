# Capitulo 9 — RAG com pgvector

> **Este capitulo cobre os pilares: RAG (3) e Vector Databases (6)**

Neste capitulo vamos implementar o **pipeline RAG completo**: instalar o pacote pgvector para Laravel, criar a Tool `SearchDocsKnowledgeBase` que os Agents usam para buscar documentacao relevante, e o comando Artisan `docs:import` para popular a knowledge base com embeddings vetoriais.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap09-rag
cd codereview-ai
```

---

## O que e RAG?

**RAG (Retrieval-Augmented Generation)** e uma tecnica que combina busca de informacoes com geracao de texto por IA. Em vez de depender apenas do conhecimento interno do LLM, o sistema busca dados relevantes em uma base propria e injeta no contexto.

```
Sem RAG:
"Recomendo seguir os principios SOLID"  <- generico

Com RAG:
"Conforme a PSR-12 secao 4.3, o metodo viola a regra de
 single responsibility. Veja tambem OWASP A03:2021 sobre
 injection attacks no trecho da linha 42"  <- especifico com referencias
```

### Fluxo RAG no projeto

```
1. Agent precisa recomendar melhorias
   |
2. Tool SearchDocsKnowledgeBase e chamada
   |
3. Ai::embeddings() converte query em vetor (768 dims)
   |
4. pgvector busca vetores mais similares no banco
   |
5. Top N documentacoes relevantes sao retornadas
   |
6. Conteudo das docs e injetado no prompt do Agent
   |
7. Agent gera recomendacao com referencias concretas
```

---

## Passo 1 — Instalar o pacote pgvector para Laravel

O `compose.yaml` ja usa a imagem com pgvector (Capitulo 2):

```yaml
pgsql:
    image: 'pgvector/pgvector:pg18'  # PostgreSQL 18 + extensao pgvector
```

A migration da tabela `doc_embeddings` e o Model `DocEmbedding` ja foram criados nos Capitulos 3 e 4. Agora precisamos do pacote PHP que adiciona suporte ao pgvector no Laravel:

```bash
sail composer require pgvector/pgvector
```

**O que o pacote faz:**
- Adiciona o tipo `vector` nas migrations (`$table->vector('embedding', 768)`)
- Fornece o trait `HasNeighbors` com o scope `nearestNeighbors()`
- Fornece o cast `Vector::class` para converter arrays PHP em vetores PostgreSQL
- Suporta operadores de distancia: Cosine, L2, InnerProduct

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: install pgvector/pgvector package"
```

---

## Passo 2 — Criar os arquivos de documentacao para importar

Vamos criar um diretorio com documentacoes que serao importadas como embeddings. Cada arquivo JSON contem trechos de documentacao organizados por fonte.

```bash
mkdir -p docs-knowledge-base
```

Crie `docs-knowledge-base/psr-12.json`:

```json
[
    {
        "title": "PSR-12 - Methods Visibility",
        "content": "PSR-12: Extended Coding Style Guide. Methods must declare visibility. All methods must have their visibility declared. Abstract and final declarations must precede the visibility declaration. Static declarations must come after the visibility declaration.",
        "category": "architecture"
    },
    {
        "title": "PSR-12 - Opening Braces",
        "content": "PSR-12: Opening braces for classes and methods MUST go on the next line, and closing braces MUST go on the next line after the body. Opening braces for control structures MUST go on the same line, and closing braces MUST go on the next line after the body.",
        "category": "architecture"
    },
    {
        "title": "PSR-4 - Autoloading Standard",
        "content": "PSR-4: Autoloading Standard. A fully qualified class name has the form \\Namespace\\ClassName. Each namespace must have a base directory. The subdirectory names must match the case of the sub-namespace names. Each class file must end with .php extension.",
        "category": "architecture"
    }
]
```

Crie `docs-knowledge-base/owasp.json`:

```json
[
    {
        "title": "OWASP A03:2021 - Injection",
        "content": "OWASP A03:2021 Injection. An application is vulnerable when user-supplied data is not validated, filtered, or sanitized. SQL injection occurs when untrusted data is sent to an interpreter as part of a command or query. Prevention: Use parameterized queries, stored procedures, input validation, and escaping.",
        "category": "security"
    },
    {
        "title": "OWASP A01:2021 - Broken Access Control",
        "content": "OWASP A01:2021 Broken Access Control. Access control enforces policy such that users cannot act outside of their intended permissions. Failures lead to unauthorized information disclosure, modification, or destruction. Prevention: Deny by default, implement access control mechanisms, enforce record ownership.",
        "category": "security"
    },
    {
        "title": "OWASP A07:2021 - Cross-Site Scripting (XSS)",
        "content": "OWASP A07:2021 XSS. Cross-site scripting flaws occur when an application includes untrusted data in a new web page without proper validation or escaping. XSS allows attackers to execute scripts in the victim's browser. Prevention: Escape output, use Content Security Policy, validate input on server side.",
        "category": "security"
    }
]
```

Crie `docs-knowledge-base/laravel-best-practices.json`:

```json
[
    {
        "title": "Laravel - Eloquent N+1 Problem",
        "content": "The N+1 query problem occurs when you load a collection and then access a relationship on each item without eager loading. Use with() or load() to eager load relationships. Example: User::with('posts')->get() instead of User::all() then accessing $user->posts in a loop.",
        "category": "performance"
    },
    {
        "title": "Laravel - Service Container and Dependency Injection",
        "content": "Laravel's service container is a powerful tool for managing class dependencies and performing dependency injection. Instead of creating instances manually with new, bind interfaces to implementations in a ServiceProvider. This makes code testable and follows the Dependency Inversion Principle.",
        "category": "architecture"
    },
    {
        "title": "Laravel - Query Optimization with Database Indexing",
        "content": "Always add database indexes for columns used in WHERE, ORDER BY, and JOIN clauses. Use composite indexes for queries filtering on multiple columns. Monitor slow queries with DB::listen() or Laravel Telescope. Use EXPLAIN to analyze query execution plans.",
        "category": "performance"
    }
]
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add knowledge base JSON docs for RAG import"
```

---

## Passo 3 — Criar o comando Artisan ImportDocs

Gere o scaffold do comando:

```bash
sail artisan make:command ImportDocs
```

Edite `app/Console/Commands/ImportDocs.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\DocEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Embeddings;

class ImportDocs extends Command
{
    protected $signature = 'docs:import {source : Nome da fonte (ex: psr-12, owasp, laravel-best-practices)}';

    protected $description = 'Importa documentacoes JSON para a knowledge base com embeddings vetoriais';

    public function handle(): int
    {
        $source = $this->argument('source');
        $filePath = base_path("docs-knowledge-base/{$source}.json");

        if (! File::exists($filePath)) {
            $this->error("Arquivo nao encontrado: {$filePath}");
            $this->info('Arquivos disponiveis:');

            collect(File::files(base_path('docs-knowledge-base')))
                ->each(fn ($file) => $this->line("  - {$file->getFilenameWithoutExtension()}"));

            return self::FAILURE;
        }

        $docs = json_decode(File::get($filePath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Erro ao parsear JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info("Importando {$source}: " . count($docs) . " documentos");
        $bar = $this->output->createProgressBar(count($docs));
        $bar->start();

        $imported = 0;

        foreach ($docs as $doc) {
            // Verificar se ja existe para evitar duplicatas
            $exists = DocEmbedding::where('source', $source)
                ->where('title', $doc['title'])
                ->exists();

            if ($exists) {
                $bar->advance();
                continue;
            }

            // Gerar embedding via Ai::embeddings()
            $result = Embeddings::for([$doc['content']])
                ->dimensions(768)
                ->generate(Lab::Gemini, 'gemini-embedding-001');

            // Salvar no banco com o vetor
            DocEmbedding::create([
                'source'    => $source,
                'title'     => $doc['title'],
                'content'   => $doc['content'],
                'embedding' => $result->first(),
                'category'  => $doc['category'],
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Importados {$imported} documentos de {$source} com sucesso!");

        return self::SUCCESS;
    }
}
```

**O que o comando faz:**
1. Recebe o nome da fonte (ex: `psr-12`) como argumento
2. Le o arquivo JSON correspondente em `docs-knowledge-base/`
3. Para cada documento, gera um embedding de 768 dimensoes via `Ai::embeddings()`
4. Salva o documento com o vetor na tabela `doc_embeddings`
5. Verifica duplicatas pelo par `source` + `title`

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add docs:import artisan command for RAG knowledge base"
```

---

## Passo 4 — Importar os documentos

Certifique-se de que o banco esta rodando e as migrations foram executadas:

```bash
sail artisan migrate:fresh --seed
```

Agora importe cada fonte:

```bash
sail artisan docs:import psr-12
sail artisan docs:import owasp
sail artisan docs:import laravel-best-practices
```

Cada comando deve mostrar uma barra de progresso e a mensagem de sucesso:

```
Importando psr-12: 3 documentos
 3/3 [============================] 100%
Importados 3 documentos de psr-12 com sucesso!
```

### Verificar no Tinker

```bash
sail artisan tinker
```

```php
// Total de documentos importados
App\Models\DocEmbedding::count();
// => 9

// Verificar fontes
App\Models\DocEmbedding::pluck('source')->unique()->values();
// => ["psr-12", "owasp", "laravel-best-practices"]

// Verificar que os vetores foram gerados
$doc = App\Models\DocEmbedding::first();
count($doc->embedding->toArray());
// => 768

// Sair
exit
```

> Se todos os comandos retornaram os valores esperados, a knowledge base esta pronta.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: import knowledge base docs with embeddings"
```

---

## Passo 5 — Criar a Tool SearchDocsKnowledgeBase

No Laravel AI SDK, a busca RAG e implementada como uma **Tool** que os Agents podem chamar. Gere o scaffold:

```bash
sail artisan make:tool SearchDocsKnowledgeBase
```

Edite `app/Ai/Tools/SearchDocsKnowledgeBase.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Models\DocEmbedding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Pgvector\Laravel\Distance;

class SearchDocsKnowledgeBase implements Tool
{
    public function description(): string
    {
        return 'Search PSRs, OWASP guides and Laravel documentation by semantic similarity. '
            . 'Use this to find relevant documentation before making recommendations.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search query describing what documentation to find')
                ->required(),
            'category' => $schema->string()
                ->description('Filter by category: architecture, performance, or security'),
        ];
    }

    public function handle(Request $request): string
    {
        // 1. Converter a query em vetor com cache
        $result = Embeddings::for([$request->string('query')])
                ->cache()
                ->dimensions(768)
                ->generate(Lab::Gemini, 'gemini-embedding-001');

        $queryVector = $result->first();

        // 2. Buscar os 5 docs mais similares via pgvector
        $docsQuery = DocEmbedding::query()
            ->nearestNeighbors('embedding', $queryVector, Distance::Cosine);

        if ($request->has('category')) {
            $docsQuery->where('category', $request->string('category'));
        }

        $docs = $docsQuery->take(5)->get();

        if ($docs->isEmpty()) {
            return 'Nenhuma documentacao encontrada para a query informada.';
        }

        // 3. Formatar o resultado como contexto para o Agent
        return $docs->map(function ($doc) {
            return "[{$doc->source}] {$doc->title}\n{$doc->content}";
        })->implode("\n\n---\n\n");
    }
}
```

**Como a Tool funciona:**

1. **`description()`** — o LLM le essa descricao para decidir quando chamar a Tool
2. **`schema(JsonSchema $schema)`** — define os parametros que a IA pode passar (query + category)
3. **`handle(Request $request)`** — logica de busca: embedding da query -> busca pgvector -> retorna contexto

**O operador `<=>` (cosine distance) do pgvector:**

```sql
-- Query SQL gerada pelo nearestNeighbors() (simplificada)
SELECT *,
       embedding <=> '[0.12, -0.34, ...]'::vector AS distance
FROM doc_embeddings
WHERE category = 'security'
ORDER BY embedding <=> '[0.12, -0.34, ...]'::vector
LIMIT 5;
```

- `<=>` — operador de distancia cosseno do pgvector
- Quanto menor a distancia, mais similar o conteudo
- O filtro `WHERE category` permite buscar apenas docs do pilar relevante

**Cache de embeddings:**

O metodo `->cache()` no `Embeddings::for()` evita chamadas repetidas a API para a mesma query. Queries frequentes como "SQL injection" ou "N+1 query" serao resolvidas instantaneamente apos a primeira chamada.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add SearchDocsKnowledgeBase RAG tool with pgvector"
```

---

## Passo 6 — Verificar a busca semantica

Vamos testar o pipeline RAG completo: query -> embedding -> busca pgvector -> resultado.

> **Importante:** Use `tinker --execute` para rodar o script completo de uma vez. O tinker interativo (psysh) perde variaveis entre sessoes e tem problemas com method chains em multiplas linhas.

**Teste 1 — Busca semantica por query de seguranca:**

```bash
sail artisan tinker --execute="
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Pgvector\Laravel\Distance;
use App\Models\DocEmbedding;

\$result = Embeddings::for(['How to prevent SQL injection in PHP'])->dimensions(768)->generate(Lab::Gemini, 'gemini-embedding-001');
\$queryVector = \$result->first();
echo 'Dimensoes do vetor: ' . count(\$queryVector) . PHP_EOL;

\$docs = DocEmbedding::query()->nearestNeighbors('embedding', \$queryVector, Distance::Cosine)->take(3)->get();
foreach (\$docs as \$doc) { echo '[' . \$doc->source . '] ' . \$doc->title . PHP_EOL; }
"
```

Saida esperada:
```
Dimensoes do vetor: 768
[owasp] OWASP A03:2021 - Injection
[owasp] OWASP A07:2021 - Cross-Site Scripting (XSS)
[laravel-best-practices] Laravel - Query Optimization with Database Indexing
```

**Teste 2 — Filtro por categoria:**

```bash
sail artisan tinker --execute="
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Pgvector\Laravel\Distance;
use App\Models\DocEmbedding;

\$result = Embeddings::for(['How to prevent SQL injection in PHP'])->dimensions(768)->generate(Lab::Gemini, 'gemini-embedding-001');
\$queryVector = \$result->first();

\$docs = DocEmbedding::query()->nearestNeighbors('embedding', \$queryVector, Distance::Cosine)->where('category', 'security')->take(3)->get();
foreach (\$docs as \$doc) { echo '[' . \$doc->source . '] ' . \$doc->title . PHP_EOL; }
"
```

Saida esperada: apenas documentos de categoria `security`.

**Teste 3 — Tool diretamente:**

```bash
sail artisan tinker --execute="
\$tool = new \App\Ai\Tools\SearchDocsKnowledgeBase;
\$request = new \Laravel\Ai\Tools\Request(['query' => 'coding style method visibility', 'category' => 'architecture']);
echo \$tool->handle(\$request);
"
```

Saida esperada: trechos de PSR-12 sobre visibility.

> Se a busca retornou documentos relevantes para a query, o pipeline RAG esta funcionando corretamente.

---

## Passo 7 — Como a Tool e usada pelos Agents (preview)

No Capitulo 10, os Agents vao usar a Tool `SearchDocsKnowledgeBase` para buscar documentacao antes de gerar recomendacoes:

```php
// No Agent (Capitulo 10)
class SecurityAnalyst implements Agent, HasTools
{
    use Promptable;

    public function tools(): iterable
    {
        return [
            new SearchDocsKnowledgeBase, // <- Agent pode chamar RAG
        ];
    }
}

// Quando o Agent e executado:
// 1. Agent recebe o codigo
// 2. Agent decide: "Preciso buscar docs sobre SQL Injection"
// 3. Agent faz tool_call: search_docs_knowledge_base(query: "SQL injection", category: "security")
// 4. Tool executa: Ai::embeddings() + pgvector query
// 5. Retorna: "[OWASP A03] Injection..." "[PSR-12] Input validation..."
// 6. Agent usa os docs para gerar resposta com referencias
```

**Fluxo completo visualizado:**

```
+---------------------+
|  SecurityAnalyst    |
|  Agent              |
|  "SQL injection no  |
|   input de busca"   |
+--------+------------+
         | Tool Call: search_docs_knowledge_base
         v
+--------------------+
|  1. Embeddings::for|  gemini-embedding-001
|     gera vetor     |  -> [0.12, -0.34, ...]
+--------+-----------+
         |
         v
+--------------------+
|  2. Busca pgvector |  SELECT * FROM doc_embeddings
|     Top 5 mais     |  WHERE category = 'security'
|     similares      |  ORDER BY embedding <=> query
+--------+-----------+   LIMIT 5
         |
         v
+--------------------+
|  3. Retorna como   |  "[OWASP A03] Injection..."
|     contexto texto |  "[Laravel Docs] Validation..."
|                    |  "[PSR-12] Input handling..."
+--------+-----------+
         |
         v
+--------------------+
|  4. Agent gera     |  Prompt + contexto dos docs
|     resposta com   |  -> "Conforme OWASP A03:2021,
|     referencias    |     use prepared statements..."
+--------------------+
```

---

## Passo 8 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: complete RAG pipeline with pgvector and semantic search"

# Push da branch
git push -u origin feat/cap09-rag

# Criar Pull Request
gh pr create --title "feat: RAG com pgvector e busca semantica" --body "Capitulo 09 - Knowledge base, docs:import command, SearchDocsKnowledgeBase Tool, embeddings com cache e busca vetorial via pgvector"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `docs-knowledge-base/psr-12.json` | Trechos de PSR-12 para importar como embeddings |
| `docs-knowledge-base/owasp.json` | Trechos de OWASP Top 10 para importar como embeddings |
| `docs-knowledge-base/laravel-best-practices.json` | Boas praticas Laravel para importar como embeddings |
| `app/Console/Commands/ImportDocs.php` | Comando `docs:import` que gera embeddings e salva no banco |
| `app/Ai/Tools/SearchDocsKnowledgeBase.php` | Tool RAG: busca semantica via pgvector com cache |

**Dependencias de capitulos anteriores (ja criados):**

| Arquivo | Capitulo | O que faz |
|---------|----------|-----------|
| `database/migrations/create_doc_embeddings_table.php` | 3 | Tabela com coluna `vector(768)` |
| `database/migrations/add_hnsw_index_to_doc_embeddings.php` | 3 | Indice HNSW para busca rapida |
| `app/Models/DocEmbedding.php` | 4 | Model com `HasNeighbors` e `Vector` cast |

## Proximo capitulo

No [Capitulo 10 — Multi-Agents e Tool Use](10-multi-agentes.md) vamos ver como 3 Agent classes colaboram usando Tools para gerar planos de melhorias.
