# Capitulo 9 — RAG com pgvector

> **Este capitulo cobre os pilares: RAG (3) e Vector Databases (6)**

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

## Como funciona no projeto

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

## Embeddings com Laravel AI SDK

### O que sao embeddings?

Um **embedding** e uma representacao numerica (vetor) de um texto. Textos com significados similares ficam proximos no espaco vetorial.

```
"SQL injection prevention"  -> [0.12, -0.34, 0.56, ..., 0.78]  (768 dimensoes)
"input sanitization"        -> [0.11, -0.33, 0.55, ..., 0.77]  <- similar!
"receita de bolo"           -> [-0.89, 0.45, -0.12, ..., 0.23] <- distante!
```

### Gerando embeddings com Ai::embeddings()

O Laravel AI SDK oferece a facade `Ai::embeddings()` para gerar vetores:

```php
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Enums\Lab;

// Gerar embedding de um texto
$result = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed('PSR-12: Methods must declare visibility...');

// Acessar o vetor (array de 768 floats)
$vector = $result[0]->embedding;
echo count($vector); // 768
```

### Cache de embeddings

Para queries repetidas, o SDK suporta cache nativo:

```php
$result = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed('SQL injection prevention', cache: true);

// Chamadas subsequentes usam cache
```

---

## pgvector — vetores no PostgreSQL

### Instalacao do pgvector

O `compose.yaml` ja usa a imagem com pgvector (Capitulo 2):

```yaml
pgsql:
    image: 'pgvector/pgvector:pg18'  # PostgreSQL 18 + extensao pgvector
```

No PHP, o pacote `pgvector/pgvector` adiciona suporte ao Laravel:

```bash
sail composer require pgvector/pgvector
```

### Migration com tipo vector

```php
// database/migrations/create_doc_embeddings_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source');       // PSR-12, OWASP, Laravel Docs
            $table->string('title');
            $table->text('content');
            $table->vector('embedding', 768);  // <- tipo pgvector!
            $table->string('category');     // architecture, performance, security
            $table->timestamps();
        });
    }
};
```

### Model com HasNeighbors

```php
// app/Models/DocEmbedding.php

use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocEmbedding extends Model
{
    use HasNeighbors;

    protected $fillable = ['source', 'title', 'content', 'embedding', 'category'];

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
```

O trait `HasNeighbors` adiciona o scope `nearestNeighbors()` que gera a query SQL com operadores de distancia do pgvector.

---

## Populando a knowledge base

### Fontes de conteudo

| Fonte | Categoria | Exemplos |
|-------|-----------|----------|
| **PSRs** | architecture | PSR-1, PSR-4, PSR-12 (coding style) |
| **OWASP Top 10** | security | A01:Broken Access, A03:Injection, A07:XSS |
| **Laravel Docs** | architecture | Service Container, Eloquent, Middleware |
| **Clean Code** | architecture | SOLID, DRY, KISS, naming conventions |
| **Performance** | performance | N+1, caching, indexing, query optimization |
| **Design Patterns** | architecture | Repository, Strategy, Observer, Factory |

### Gerando embeddings com Ai::embeddings()

```php
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Enums\Lab;
use App\Models\DocEmbedding;

// Gerar embedding de um trecho de documentacao
$result = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed('PSR-12: Extended Coding Style Guide. Methods must declare
        visibility. Opening braces for methods MUST go on the next line...');

// Salvar no banco
DocEmbedding::create([
    'source' => 'PSR-12',
    'title' => 'Extended Coding Style Guide - Methods',
    'content' => 'Methods must declare visibility. Opening braces...',
    'embedding' => $result[0]->embedding,
    'category' => 'architecture',
]);
```

### Comando Artisan para importar docs

```php
// app/Console/Commands/ImportDocsCommand.php

class ImportDocsCommand extends Command
{
    protected $signature = 'docs:import {source}';

    public function handle(): void
    {
        $source = $this->argument('source');
        $docs = $this->loadDocs($source);

        $bar = $this->output->createProgressBar(count($docs));

        foreach ($docs as $doc) {
            $result = Ai::embeddings()
                ->provider(Lab::Gemini)
                ->model('text-embedding-004')
                ->embed($doc['content']);

            DocEmbedding::create([
                'source' => $source,
                'title' => $doc['title'],
                'content' => $doc['content'],
                'embedding' => $result[0]->embedding,
                'category' => $doc['category'],
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nImportadas " . count($docs) . " documentacoes de {$source}");
    }
}
```

```bash
sail artisan docs:import PSR-12
sail artisan docs:import OWASP
sail artisan docs:import laravel-docs
```

---

## Busca semantica — SearchDocsKnowledgeBase Tool

No Laravel AI SDK, a busca RAG e implementada como uma **Tool** que os Agents podem chamar:

```php
// app/Ai/Tools/SearchDocsKnowledgeBase.php

namespace App\Ai\Tools;

use App\Models\DocEmbedding;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Contracts\ToolSchema;
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Enums\Lab;
use Pgvector\Laravel\Distance;

class SearchDocsKnowledgeBase implements Tool
{
    public function name(): string
    {
        return 'search_docs_knowledge_base';
    }

    public function description(): string
    {
        return 'Search PSRs, OWASP guides and Laravel documentation by semantic similarity. '
            . 'Use this to find relevant documentation before making recommendations.';
    }

    public function schema(): ToolSchema
    {
        // Schema dos parametros que a IA pode passar
        return ToolSchema::make()
            ->string('query', 'Search query describing what documentation to find')
            ->string('category', 'Filter by category: architecture, performance, or security');
    }

    public function execute(array $parameters): string
    {
        // 1. Converter a query em vetor via Ai::embeddings()
        $result = Ai::embeddings()
            ->provider(Lab::Gemini)
            ->model('text-embedding-004')
            ->embed($parameters['query']);

        $queryVector = $result[0]->embedding;

        // 2. Buscar os 5 docs mais similares via pgvector
        $docsQuery = DocEmbedding::query()
            ->nearestNeighbors('embedding', $queryVector, Distance::Cosine);

        if (isset($parameters['category'])) {
            $docsQuery->where('category', $parameters['category']);
        }

        $docs = $docsQuery->take(5)->get();

        // 3. Formatar o resultado como contexto
        return $docs->map(function ($doc) {
            return "[{$doc->source}] {$doc->title}\n{$doc->content}";
        })->implode("\n\n---\n\n");
    }
}
```

### Como a Tool e usada pelos Agents

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
// 5. Retorna: "[OWASP A03] Injection... [PSR-12] Input validation..."
// 6. Agent usa os docs para gerar resposta com referencias
```

---

## Como funciona a busca por distancia

```sql
-- Query SQL gerada pelo pgvector (simplificada)
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

### Tipos de distancia

```php
use Pgvector\Laravel\Distance;

// Cosseno — bom para similaridade semantica (recomendado)
->nearestNeighbors('embedding', $vector, Distance::Cosine)

// L2 (Euclidiana)
->nearestNeighbors('embedding', $vector, Distance::L2)

// Produto interno
->nearestNeighbors('embedding', $vector, Distance::InnerProduct)
```

---

## Fluxo RAG completo

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
|  1. Ai::embeddings |  text-embedding-004
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

## Otimizacoes de performance

### Indice HNSW

Para bases com muitos documentos, crie um indice HNSW:

```php
// Em uma migration
DB::statement(
    'CREATE INDEX doc_embeddings_embedding_idx
     ON doc_embeddings
     USING hnsw (embedding vector_cosine_ops)
     WITH (m = 16, ef_construction = 64)'
);
```

### Cache de embeddings

Para queries repetidas, use o cache nativo do SDK:

```php
$result = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed($query, cache: true);
```

Ou cache manual com Redis:

```php
$cacheKey = 'embedding:' . md5($query);

$queryVector = Cache::remember($cacheKey, 3600, function () use ($query) {
    $result = Ai::embeddings()
        ->provider(Lab::Gemini)
        ->model('text-embedding-004')
        ->embed($query);

    return $result[0]->embedding;
});
```

## Proximo capitulo

No [Capitulo 10 — Multi-Agents e Tool Use](10-multi-agentes.md) vamos ver como 3 Agent classes colaboram usando Tools para gerar planos de melhorias.
