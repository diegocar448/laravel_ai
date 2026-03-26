# Capitulo 3 — Banco de Dados e Migrations

> **Este capitulo cobre o pilar de AI Engineering: Vector Databases (pilar 6)**

Neste capitulo vamos criar **todas as migrations** do projeto. Ao final, voce tera o schema completo para suportar Agents, Structured Output, RAG e o Kanban de melhorias.

## Antes de comecar

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap03-database
cd codereview-ai
```

---

## Visao geral do schema

Antes de criar as migrations, entenda o que vamos construir:

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

| Grupo | Tabelas | Relacionado com AI | Pilar |
|-------|---------|-------------------|-------|
| **Core** | users, projects | — | — |
| **IA Output** | code_reviews, review_findings, improvements | Structured Output | 2, 8 |
| **IA Knowledge** | doc_embeddings | RAG, Vector DB | 3, 6, 9 |
| **Lookup** | project_statuses, finding_types, review_pillars | Orchestration | 7 |

---

## Passo 1 — Adicionar campos a tabela users

O Laravel ja criou a migration de users (`0001_01_01_000000_create_users_table.php`). Vamos adicionar campos extras para nosso projeto.

Gere a migration:

```bash
sail artisan make:migration add_review_fields_to_users_table --table=users
```

Edite o arquivo gerado em `database/migrations/xxxx_xx_xx_xxxxxx_add_review_fields_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->dateTime('first_review_at')->nullable()->after('is_admin');
            $table->dateTime('first_plan_at')->nullable()->after('first_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'first_review_at', 'first_plan_at']);
        });
    }
};
```

**O que cada campo faz:**
- `is_admin` — controle de acesso ao painel admin (Capitulo 7)
- `first_review_at` — marca quando o usuario fez sua primeira analise de codigo
- `first_plan_at` — marca quando o usuario gerou seu primeiro plano de melhorias

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add review fields to users table"
```

---

## Passo 2 — Criar as tabelas de lookup

As tabelas de lookup armazenam valores fixos que correspondem aos Enums PHP (Capitulo 4). Cada uma e simples: `id` + `name`.

### 2.1 — project_statuses

```bash
sail artisan make:migration create_project_statuses_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_project_statuses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Active, Completed, Archived
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_statuses');
    }
};
```

### 2.2 — review_statuses

```bash
sail artisan make:migration create_review_statuses_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_review_statuses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Pending, Completed, Failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_statuses');
    }
};
```

### 2.3 — finding_types

```bash
sail artisan make:migration create_finding_types_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Strength, Improvement
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_types');
    }
};
```

### 2.4 — review_pillars

```bash
sail artisan make:migration create_review_pillars_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_pillars', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Architecture, Performance, Security
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_pillars');
    }
};
```

### 2.5 — improvement_types

```bash
sail artisan make:migration create_improvement_types_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Refactor, Fix, Optimization
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_types');
    }
};
```

### 2.6 — improvement_steps

```bash
sail artisan make:migration create_improvement_steps_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ToDo, InProgress, Done
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_steps');
    }
};
```

```bash
# Commitar todas as lookups
cd ~/laravel_ai
git add .
git commit -m "feat: add lookup tables (statuses, types, pillars, steps)"
```

---

## Passo 3 — Criar a tabela projects

```bash
sail artisan make:migration create_projects_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_projects_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_status_id')->constrained();
            $table->string('name');
            $table->string('language'); // php, javascript, python, go, rust, etc.
            $table->text('code_snippet');
            $table->string('repository_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

**Pontos importantes:**
- `user_id` — cada projeto pertence a um usuario, com `cascadeOnDelete` (deletou usuario, deletou projetos)
- `project_status_id` — FK para lookup table `project_statuses`
- `code_snippet` — texto com o codigo que sera analisado pela IA
- `language` — linguagem do codigo (o Agent usa isso no prompt)

---

## Passo 4 — Criar a tabela code_reviews

```bash
sail artisan make:migration create_code_reviews_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_code_reviews_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_status_id')->nullable()->default(1)->constrained();
            $table->text('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_reviews');
    }
};
```

**Pontos importantes:**
- `review_status_id` — comeca como `1` (Pending), muda para `2` (Completed) quando o Agent termina
- `summary` — preenchido pelo Agent `CodeAnalyst` via `HasStructuredOutput` (Capitulo 8)

---

## Passo 5 — Criar a tabela review_findings

```bash
sail artisan make:migration create_review_findings_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_review_findings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('review_findings');
    }
};
```

Cada review gera **6 findings** (3 pilares x 2 tipos):

| Pilar | Strength (ponto forte) | Improvement (melhoria) |
|-------|----------------------|----------------------|
| Architecture | Bom uso de Repository Pattern | Falta inversao de dependencia |
| Performance | Queries otimizadas com eager loading | N+1 query no loop de relatorios |
| Security | CSRF token em todos os forms | SQL Injection via input nao sanitizado |

```bash
# Commitar tabelas core
cd ~/laravel_ai
git add .
git commit -m "feat: add projects, code_reviews and review_findings tables"
```

---

## Passo 6 — Criar a tabela improvements

```bash
sail artisan make:migration create_improvements_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_improvements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('improvements');
    }
};
```

**Pontos importantes:**
- `improvement_step_id` — FK para lookup `improvement_steps` (ToDo, InProgress, Done) — usado no Kanban (Capitulo 6)
- `improvement_type_id` — FK para lookup `improvement_types` (Refactor, Fix, Optimization)
- `order` — posicao do card no Kanban
- `completed_at` — preenchido quando o usuario move para "Done"

---

## Passo 7 — Criar a tabela doc_embeddings (RAG)

Esta e a tabela mais importante para AI Engineering. Ela armazena os **embeddings vetoriais** usados pelo RAG no Capitulo 9.

```bash
sail artisan make:migration create_doc_embeddings_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_create_doc_embeddings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source');       // "PSR-12", "OWASP Top 10", "Laravel 13 Docs"
            $table->string('title');
            $table->text('content');
            $table->vector('embedding', 768);  // pgvector! 768 dimensoes
            $table->string('category');     // architecture, performance, security
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_embeddings');
    }
};
```

**Entendendo a coluna `embedding`:**

```
$table->vector('embedding', 768)
         |           |        |
         |           |        +-- 768 dimensoes (Google text-embedding-004)
         |           +-- nome da coluna
         +-- tipo pgvector (adicionado pelo pacote pgvector/pgvector)

Resultado no PostgreSQL: embedding vector(768)
```

**Por que 768 dimensoes?**
- Google `text-embedding-004`: **768 dims** (gratis, boa accuracy)
- OpenAI `text-embedding-3-large`: 1536 dims (pago, mais preciso)
- **Trade-off:** 768 usa 50% menos memoria com perda minima. Para RAG com codigo, e suficiente.

### Como o RAG usa esta tabela (preview do Capitulo 9)

```
1. Importar docs:
   "PSR-12: Code Style" -> Ai::embeddings() -> [0.23, -0.89, ...] (768 dims)
                                                     |
                                            Salva em doc_embeddings

2. Buscar docs similares:
   "Meu codigo PHP" -> Ai::embeddings() -> [0.45, -0.12, ...] (768 dims)
                                                  |
                                    SELECT * FROM doc_embeddings
                                    ORDER BY embedding <-> query_embedding
                                    LIMIT 5
                                                  |
                                    Retorna: PSR-12, OWASP SQL Injection, ...

3. Agent usa os docs no prompt para responder com base em documentacao real
```

---

## Passo 8 — Criar o indice HNSW para busca vetorial

Para que a busca por similaridade seja rapida em producao, precisamos de um indice. Crie uma migration separada:

```bash
sail artisan make:migration add_hnsw_index_to_doc_embeddings_table
```

Edite `database/migrations/xxxx_xx_xx_xxxxxx_add_hnsw_index_to_doc_embeddings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE INDEX doc_embeddings_embedding_idx
            ON doc_embeddings
            USING hnsw (embedding vector_cosine_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS doc_embeddings_embedding_idx');
    }
};
```

### O que e HNSW?

**HNSW (Hierarchical Navigable Small Worlds)** e um algoritmo de busca aproximada que cria uma estrutura hierarquica em camadas:

```
Sem indice (busca bruta):
  Compara com TODOS os vetores -> O(n) -> 10s com 1M docs

Com HNSW:
  Camada 3:  [A] ---- [B]                    (poucos nos, saltos grandes)
  Camada 2:  [A] - [C] - [B] - [D]           (mais nos, saltos medios)
  Camada 1:  [A]-[E]-[C]-[F]-[B]-[G]-[D]     (todos nos, saltos curtos)

  Busca: comeca no topo, desce refinando -> O(log n) -> < 1ms com 1M docs
```

**Cosine distance** (`vector_cosine_ops`) mede similaridade semantica:
- `0.0` = textos identicos em significado
- `1.0` = textos completamente diferentes
- Ideal para comparar codigo e documentacao

```bash
# Commitar tabelas de IA
cd ~/laravel_ai
git add .
git commit -m "feat: add doc_embeddings table with pgvector and HNSW index"
```

---

## Passo 9 — Criar os Seeders

As tabelas de lookup precisam de dados iniciais. Vamos criar seeders para popular esses valores fixos.

### 9.1 — LookupSeeder

```bash
sail artisan make:seeder LookupSeeder
```

Edite `database/seeders/LookupSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // Project Statuses
        DB::table('project_statuses')->insert([
            ['id' => 1, 'name' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Completed', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Archived', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Review Statuses
        DB::table('review_statuses')->insert([
            ['id' => 1, 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Completed', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Failed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Finding Types
        DB::table('finding_types')->insert([
            ['id' => 1, 'name' => 'Strength', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Improvement', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Review Pillars
        DB::table('review_pillars')->insert([
            ['id' => 1, 'name' => 'Architecture', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Performance', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Security', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Improvement Types
        DB::table('improvement_types')->insert([
            ['id' => 1, 'name' => 'Refactor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Fix', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Optimization', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Improvement Steps
        DB::table('improvement_steps')->insert([
            ['id' => 1, 'name' => 'ToDo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'InProgress', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Done', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
```

### 9.2 — Registrar no DatabaseSeeder

Edite `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LookupSeeder::class,
        ]);
    }
}
```

---

## Passo 10 — Rodar tudo

Agora vamos executar as migrations e seeders:

```bash
# Rodar todas as migrations
sail artisan migrate

# Popular tabelas de lookup
sail artisan db:seed
```

### Verificar se tudo foi criado

```bash
# Ver status das migrations
sail artisan migrate:status
```

Deve mostrar todas as migrations com status `Ran`.

### Verificar pgvector

```bash
sail exec pgsql psql -U sail -d laravel
```

No console do PostgreSQL:

```sql
-- Verificar extensao pgvector
\dx

-- Listar tabelas criadas
\dt

-- Verificar que o indice HNSW foi criado
\di doc_embeddings_embedding_idx

-- Verificar dados de lookup
SELECT * FROM project_statuses;
SELECT * FROM review_pillars;

-- Sair
\q
```

### Comando util: recriar tudo do zero

Se precisar resetar o banco durante o desenvolvimento:

```bash
sail artisan migrate:fresh --seed
```

Isso executa `down()` de todas migrations, depois `up()` de todas, e roda os seeders.

---

## Passo 11 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add seeders for lookup tables"

# Push da branch
git push -u origin feat/cap03-database

# Criar Pull Request
gh pr create --title "feat: banco de dados e migrations" --body "Capitulo 03 - Migrations completas, pgvector, HNSW index e seeders de lookup"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `migrations/add_review_fields_to_users_table.php` | Adiciona `is_admin`, `first_review_at`, `first_plan_at` |
| `migrations/create_project_statuses_table.php` | Lookup: Active, Completed, Archived |
| `migrations/create_review_statuses_table.php` | Lookup: Pending, Completed, Failed |
| `migrations/create_finding_types_table.php` | Lookup: Strength, Improvement |
| `migrations/create_review_pillars_table.php` | Lookup: Architecture, Performance, Security |
| `migrations/create_improvement_types_table.php` | Lookup: Refactor, Fix, Optimization |
| `migrations/create_improvement_steps_table.php` | Lookup: ToDo, InProgress, Done |
| `migrations/create_projects_table.php` | Projetos dos usuarios |
| `migrations/create_code_reviews_table.php` | Analises de codigo (output dos Agents) |
| `migrations/create_review_findings_table.php` | Findings da analise (Structured Output) |
| `migrations/create_improvements_table.php` | Cards do Kanban de melhorias |
| `migrations/create_doc_embeddings_table.php` | Embeddings vetoriais para RAG |
| `migrations/add_hnsw_index_to_doc_embeddings.php` | Indice HNSW para busca rapida |
| `seeders/LookupSeeder.php` | Dados iniciais das tabelas de lookup |
| `seeders/DatabaseSeeder.php` | Registra o LookupSeeder |

## Proximo capitulo

No [Capitulo 4 — Models e Relacionamentos](04-models.md) vamos criar os Eloquent Models que mapeiam essas tabelas, com Enums, traits e relacionamentos.
