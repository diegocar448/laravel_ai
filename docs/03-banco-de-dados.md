# Capitulo 3 — Banco de Dados e Migrations

> **Este capitulo cobre o pilar de AI Engineering: Vector Databases (pilar 6)**

## Design do Banco para AI Engineering

O schema foi projetado especificamente para suportar os pilares de AI Engineering:

| Grupo | Tabela | Relacionado com AI | Pilar |
|-------|--------|-------------------|-------|
| **Core** | users, projects | — | — |
| **IA Output** | code_reviews, review_findings, improvements | Structured Output | 2, 8 |
| **IA Knowledge** | doc_embeddings | RAG, Vector DB | 3, 6, 9 |
| **Lookup** | project_statuses, finding_types, review_pillars | Orchestration | 7 |

### Exemplo: Como RAG usa o banco

```
Fluxo do RAG (Capitulo 9):
1. User submete codigo
2. Ai::embeddings() converte codigo -> embedding (768 dims)
3. Busca semantica: "Quais PSRs sao similares ao codigo?"
   SELECT * FROM doc_embeddings
   WHERE embedding <-> codigo_embedding < 0.5
   LIMIT 5
4. Retorna: ["PSR-12", "OWASP SQL Injection", "Laravel N+1 patterns"]
5. Injeta docs no prompt do Agent
6. Agent responde baseado em contexto real
```

---

## Visao geral do schema

O banco de dados usa **PostgreSQL 18** (18.3) com a extensao **pgvector 0.8.2** para busca vetorial.

```
+----------+     +--------------+     +------------------+
|  users   |---->|   projects   |---->|   code_reviews   |
|          |     |              |     |                  |
| id       |     | id           |     | id               |
| name     |     | user_id (FK) |     | project_id (FK)  |
| email    |     | project_     |     | review_status_id |
| password |     |  status_id   |     | summary          |
| is_admin |     | name         |     +--------+---------+
| first_   |     | language     |              |
| review_at|     | code_snippet |     +--------v---------+
| first_   |     | repository_  |     | review_findings  |
| plan_at  |     |  url         |     |                  |
+----------+     +------+-------+     | id               |
                        |             | code_review_id   |
                        |             | finding_type_id  |
                        |             | review_pillar_id |
                        |             | description      |
                        |             | severity         |
                        |             | agent_flagged_at |
                        |             | user_flagged_at  |
                        |             +------------------+
                        |
                +-------v----------+
                |  improvements    |
                |                  |
                | id               |
                | project_id (FK)  |
                | improvement_     |
                |  type_id         |
                | improvement_     |
                |  step_id         |
                | title            |
                | description      |
                | file_path        |
                | priority         |
                | order            |
                | completed_at     |
                +------------------+

+--------------------+
|  doc_embeddings    |  <- Tabela RAG (pgvector)
|                    |
| id                 |
| source             |  <- "PSR-12", "OWASP", "Laravel Docs"
| title              |
| content (text)     |
| embedding (vector) |  <- vetor 768 dimensoes
| category           |  <- "architecture", "security", "performance"
+--------------------+
```

---

## Extensao pgvector para AI Engineering

### O que e pgvector?

pgvector e uma extensao PostgreSQL que adiciona suporte a **vetores de embeddings**, permitindo busca por **similaridade semantica** em O(log n) em vez de O(n).

**Sem pgvector (busca bruta):**
```sql
-- Lento para milhoes de documentos
SELECT * FROM docs
WHERE content LIKE '%pattern%'  -- O(n), ate 10s
```

**Com pgvector (busca semantica):**
```sql
-- Rapido mesmo com milhoes de docs
SELECT * FROM doc_embeddings
WHERE embedding <-> novo_embedding < 0.5  -- O(log n), < 1ms com indice
ORDER BY embedding <-> novo_embedding
LIMIT 5;
```

### Por que pgvector para RAG?

Nosso sistema usa pgvector para o **pilar de RAG (Retrieval-Augmented Generation)**:

```
User submete codigo PHP
        |
Ai::embeddings() converte codigo em embedding (768 dims)
        |
Busca pgvector: "Quais PSRs/docs sao similares?"
        |
Retorna Top 5 docs relevantes em < 1ms
        |
Incorpora docs no prompt do Agent
        |
Agent responde baseado em documentation real
```

### Indices HNSW

O PostgreSQL requer **indices** para busca rapida. Usamos **HNSW (Hierarchical Navigable Small Worlds)**:

```sql
-- Cria indice HNSW na coluna embedding
CREATE INDEX ON doc_embeddings
    USING hnsw (embedding vector_cosine_ops);

-- Resultado: < 1ms mesmo com 1M+ documentos
```

**Caracteristicas:**
- **Cosine distance** — similaridade semantica (0 = identico, 1 = oposto)
- **HNSW** — estrutura hierarquica, busca eficiente
- **Production-ready** — pgvector 0.8+ tem otimizacoes de memoria

---

## Dimensoes de Embeddings

Google Gemini usa **text-embedding-004** que gera vetores de **768 dimensoes**:

```
Embedding = [0.234, -0.891, 0.123, ..., 0.456]
              ^      ^       ^            ^
            dim1   dim2    dim3        dim768

Cada dimensao representa um aspecto semantico do texto
"SQL" pode estar na dim 45, "Security" na dim 123, etc
```

**Por que 768 e nao 1536?**
- OpenAI text-embedding-3: 1536 dims (cara, mais exata)
- Google text-embedding-004: 768 dims (gratis, 50% menor memoria)
- **Trade-off:** Perda minima em accuracy, ganho em performance

Para RAG com codigo, **768 e suficiente e recomendado**.

---

## Tabelas de Lookup

```
+------------------+ +------------------+ +-------------------+
| project_statuses | |improvement_types | | improvement_steps |
| Active(1)        | | Refactor(1)      | | ToDo(1)           |
| Completed(2)     | | Fix(2)           | | InProgress(2)     |
| Archived(3)      | | Optimization(3)  | | Done(3)           |
+------------------+ +------------------+ +-------------------+
+------------------+ +----------------------+ +---------------------+
| review_statuses  | | finding_types        | | review_pillars      |
| Pending(1)       | | Strength(1)          | | Architecture(1)     |
| Completed(2)     | | Improvement(2)       | | Performance(2)      |
| Failed(3)        | +----------------------+ | Security(3)         |
+------------------+                          +---------------------+
```

## Migrations em detalhe

### Tabelas base do Laravel

As 3 primeiras migrations sao padrao do Laravel:

```php
// 0001_01_01_000000_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

// + sessions, cache, jobs tables
```

### Tabelas de lookup (enums do banco)

Essas tabelas armazenam valores fixos que correspondem aos Enums PHP:

```php
// create_project_statuses_table.php
Schema::create('project_statuses', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Active, Completed, Archived
    $table->timestamps();
});

// create_improvement_types_table.php — Refactor, Fix, Optimization
// create_improvement_steps_table.php — ToDo, InProgress, Done
// create_review_statuses_table.php — Pending, Completed, Failed
// create_finding_types_table.php — Strength, Improvement
// create_review_pillars_table.php — Architecture, Performance, Security
```

### Tabela projects

```php
// create_projects_table.php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_status_id')->constrained();
    $table->string('name');
    $table->string('language'); // php, javascript, python, etc.
    $table->text('code_snippet');
    $table->string('repository_url')->nullable();
    $table->timestamps();
});
```

### Tabela code_reviews

```php
// create_code_reviews_table.php
Schema::create('code_reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('review_status_id')->nullable()->default(1)->constrained();
    $table->text('summary')->nullable();
    $table->timestamps();
});
```

### Tabela review_findings

```php
// create_review_findings_table.php
Schema::create('review_findings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('code_review_id')->constrained()->cascadeOnDelete();
    $table->foreignId('finding_type_id')->constrained();
    $table->foreignId('review_pillar_id')->constrained();
    $table->text('description');
    $table->string('severity')->default('medium'); // low, medium, high, critical
    $table->dateTime('agent_flagged_at')->nullable();
    $table->dateTime('user_flagged_at')->nullable();
    $table->timestamps();
});
```

Cada review gera **6 findings** (3 pilares x 2 tipos):

| Pilar | Strength | Improvement |
|-------|----------|-------------|
| Architecture | Bom uso de Repository Pattern | Falta inversao de dependencia |
| Performance | Queries otimizadas com eager loading | N+1 query no loop de relatorios |
| Security | CSRF token em todos os forms | SQL Injection via input nao sanitizado |

### Tabela improvements

```php
// create_improvements_table.php
Schema::create('improvements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('improvement_type_id')->constrained();
    $table->foreignId('improvement_step_id')->constrained();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('file_path')->nullable();
    $table->integer('priority')->default(0);
    $table->integer('order');
    $table->dateTime('completed_at')->nullable();
    $table->timestamps();
});
```

### Tabela doc_embeddings (RAG)

```php
// create_doc_embeddings_table.php
Schema::create('doc_embeddings', function (Blueprint $table) {
    $table->id();
    $table->string('source');       // "PSR-12", "OWASP Top 10", "Laravel 13 Docs"
    $table->string('title');
    $table->text('content');
    $table->vector('embedding', 768);  // pgvector! 768 dimensoes
    $table->string('category');     // architecture, performance, security
    $table->timestamps();
});
```

Esta e a tabela mais interessante:

- `embedding` — tipo `vector(768)`, nativo do pgvector. Armazena o vetor gerado pelo `Ai::embeddings()` com modelo `text-embedding-004` do Google Gemini (gratuito)
- `content` — trecho da documentacao
- `source` — origem do conteudo (PSR, OWASP, docs do Laravel, etc.)
- `category` — facilita filtrar por pilar na busca RAG

> **O tipo `vector`** e adicionado pela extensao pgvector ao PostgreSQL. O pacote `pgvector/pgvector` do PHP adiciona o metodo `$table->vector()` ao Blueprint do Laravel.

### Adicoes a tabela users

```php
// add_review_fields_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->dateTime('first_review_at')->nullable();
    $table->dateTime('first_plan_at')->nullable();
    $table->boolean('is_admin')->default(false);
});
```

## Criando a extensao pgvector

Para que o tipo `vector` funcione, a extensao precisa ser criada no PostgreSQL. O script em `docker/pgsql/create-testing-database.sql` (Capitulo 2) faz isso automaticamente, mas voce tambem pode fazer manualmente:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

## Rodando as migrations

```bash
# Criar todas as tabelas
sail artisan migrate

# Recriar do zero (apaga tudo)
sail artisan migrate:fresh

# Ver status das migrations
sail artisan migrate:status
```

## Proximo capitulo

No [Capitulo 4 — Models e Relacionamentos](04-models.md) vamos ver como os Eloquent Models mapeiam essas tabelas e seus relacionamentos.
