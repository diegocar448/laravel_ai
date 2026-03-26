# Capitulo 4 — Models e Relacionamentos

> **Este capitulo cobre o pilar de AI Engineering: Structured Output (pilar 2)**

Neste capitulo vamos criar **todos os Eloquent Models**, **Enums PHP** e configurar os **relacionamentos**. Os Models sao a ponte entre o banco de dados (Capitulo 3) e os Agents de IA (Capitulo 8).

## Antes de comecar

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap04-models
cd codereview-ai
```

---

## Como Models se conectam com AI Engineering

No Laravel AI SDK, o **Agent define o schema** da resposta e o **Eloquent persiste** o resultado:

```
Agent (Capitulo 8)                    Eloquent (este capitulo)
+---------------------------+         +-------------------------+
| CodeAnalyst Agent         |         | CodeReview Model        |
|                           |         |                         |
| schema():                 |  save   | $fillable = [           |
|   summary -> string       |-------->|   'summary',            |
|   score -> integer        |         |   'review_status_id',   |
|   findings -> array       |         | ]                       |
+---------------------------+         +-------------------------+
                                              |
                                      +-------v-----------------+
                                      | ReviewFinding Model     |
                                      |                         |
                                      | $fillable = [           |
                                      |   'description',        |
                                      |   'severity',           |
                                      | ]                       |
                                      +-------------------------+
```

**Por que esse padrao?**
1. O Agent garante **tipagem forte** via `HasStructuredOutput` + `JsonSchema`
2. Se a IA retornar formato errado, excecao **antes** de salvar
3. Eloquent apenas persiste dados ja validados

---

## Passo 1 — Criar os Enums PHP

O projeto usa **Backed Enums** do PHP 8.5 para mapear os valores das lookup tables (Capitulo 3). Os IDs dos enums correspondem aos IDs inseridos pelo `LookupSeeder`.

### 1.1 — Criar o diretorio de Enums

```bash
mkdir -p app/Enums
```

### 1.2 — ReviewPillarEnum

Crie `app/Enums/ReviewPillarEnum.php`:

```php
<?php

namespace App\Enums;

enum ReviewPillarEnum: int
{
    case Architecture = 1;
    case Performance = 2;
    case Security = 3;
}
```

### 1.3 — FindingTypeEnum

Crie `app/Enums/FindingTypeEnum.php`:

```php
<?php

namespace App\Enums;

enum FindingTypeEnum: string
{
    case Strength = 'strength';
    case Improvement = 'improvement';
}
```

### 1.4 — ReviewStatusEnum

Crie `app/Enums/ReviewStatusEnum.php`:

```php
<?php

namespace App\Enums;

enum ReviewStatusEnum: int
{
    case Pending = 1;
    case Completed = 2;
    case Failed = 3;
}
```

### 1.5 — ProjectStatusEnum

Crie `app/Enums/ProjectStatusEnum.php`:

```php
<?php

namespace App\Enums;

enum ProjectStatusEnum: int
{
    case Active = 1;
    case Completed = 2;
    case Archived = 3;
}
```

### 1.6 — ImprovementTypeEnum

Crie `app/Enums/ImprovementTypeEnum.php`:

```php
<?php

namespace App\Enums;

enum ImprovementTypeEnum: int
{
    case Refactor = 1;
    case Fix = 2;
    case Optimization = 3;
}
```

### 1.7 — ImprovementStepEnum

Crie `app/Enums/ImprovementStepEnum.php`:

```php
<?php

namespace App\Enums;

enum ImprovementStepEnum: int
{
    case ToDo = 1;
    case InProgress = 2;
    case Done = 3;
}
```

### 1.8 — SeverityEnum

Crie `app/Enums/SeverityEnum.php`:

```php
<?php

namespace App\Enums;

enum SeverityEnum: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

```bash
# Commitar enums
cd ~/laravel_ai
git add .
git commit -m "feat: add PHP backed enums for lookup tables"
```

---

## Passo 2 — Criar os Models de Lookup

As lookup tables sao simples — apenas `id` e `name`. Vamos gerar e editar cada uma.

### 2.1 — ProjectStatus

```bash
sail artisan make:model ProjectStatus
```

Edite `app/Models/ProjectStatus.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    protected $fillable = ['name'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

### 2.2 — ReviewStatus

```bash
sail artisan make:model ReviewStatus
```

Edite `app/Models/ReviewStatus.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewStatus extends Model
{
    protected $fillable = ['name'];

    public function codeReviews(): HasMany
    {
        return $this->hasMany(CodeReview::class);
    }
}
```

### 2.3 — FindingType

```bash
sail artisan make:model FindingType
```

Edite `app/Models/FindingType.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FindingType extends Model
{
    protected $fillable = ['name'];

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }
}
```

### 2.4 — ReviewPillar

```bash
sail artisan make:model ReviewPillar
```

Edite `app/Models/ReviewPillar.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewPillar extends Model
{
    protected $fillable = ['name'];

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }
}
```

### 2.5 — ImprovementType

```bash
sail artisan make:model ImprovementType
```

Edite `app/Models/ImprovementType.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementType extends Model
{
    protected $fillable = ['name'];

    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class);
    }
}
```

### 2.6 — ImprovementStep

```bash
sail artisan make:model ImprovementStep
```

Edite `app/Models/ImprovementStep.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementStep extends Model
{
    protected $fillable = ['name'];

    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class);
    }
}
```

```bash
# Commitar lookup models
cd ~/laravel_ai
git add .
git commit -m "feat: add lookup models (statuses, types, pillars, steps)"
```

---

## Passo 3 — Editar o Model User

O Laravel ja criou `app/Models/User.php`. Vamos editar para adicionar os novos campos e o relacionamento com Projects.

Edite `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'first_review_at',
        'first_plan_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_review_at' => 'datetime',
            'first_plan_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

**Pontos importantes:**
- `'password' => 'hashed'` — o Laravel aplica bcrypt automaticamente ao salvar
- `'is_admin' => 'boolean'` — converte 0/1 do banco para true/false no PHP
- `projects()` — um usuario tem muitos projetos

---

## Passo 4 — Criar o Model Project

```bash
sail artisan make:model Project
```

Edite `app/Models/Project.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_status_id',
        'name',
        'language',
        'code_snippet',
        'repository_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    public function codeReview(): HasOne
    {
        return $this->hasOne(CodeReview::class);
    }

    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class);
    }
}
```

**Relacionamentos:**
- `user()` — projeto pertence a um usuario
- `status()` — FK explicita porque o nome da coluna (`project_status_id`) nao segue a convencao (`status_id`)
- `codeReview()` — HasOne, cada projeto tem **uma** analise
- `improvements()` — HasMany, cada projeto tem **muitas** melhorias (Kanban)

---

## Passo 5 — Criar o Model CodeReview

```bash
sail artisan make:model CodeReview
```

Edite `app/Models/CodeReview.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'review_status_id',
        'summary',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ReviewStatus::class, 'review_status_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }
}
```

**Este model e central para AI Engineering:**
- `summary` — preenchido pelo Agent `CodeAnalyst` via `HasStructuredOutput` (Capitulo 8)
- `review_status_id` — comeca como `1` (Pending), vira `2` (Completed) quando os Agents terminam
- `findings()` — os 6 findings gerados pelos 3 sub-Agents (Capitulo 10)

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add User, Project and CodeReview models"
```

---

## Passo 6 — Criar o Model ReviewFinding

```bash
sail artisan make:model ReviewFinding
```

Edite `app/Models/ReviewFinding.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_review_id',
        'finding_type_id',
        'review_pillar_id',
        'description',
        'severity',
        'agent_flagged_at',
        'user_flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'agent_flagged_at' => 'datetime',
            'user_flagged_at' => 'datetime',
        ];
    }

    public function codeReview(): BelongsTo
    {
        return $this->belongsTo(CodeReview::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(FindingType::class, 'finding_type_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ReviewPillar::class, 'review_pillar_id');
    }
}
```

**Este model armazena cada finding individual da IA:**

Cada review gera **6 findings** (3 pilares x 2 tipos):

| Pilar | Strength (ponto forte) | Improvement (melhoria) |
|-------|----------------------|----------------------|
| Architecture | Bom uso de Repository Pattern | Falta inversao de dependencia |
| Performance | Queries otimizadas com eager loading | N+1 query no loop |
| Security | CSRF token em todos os forms | SQL Injection via input |

**Exemplo de dados reais (gerados pelo Agent):**

```json
{
  "pillar": "security",
  "severity": "critical",
  "description": "SQL Injection: User input not parameterized in DB::select()",
  "fix_suggestion": "Use DB::select('SELECT * FROM users WHERE id = ?', [$id])"
}
```

---

## Passo 7 — Criar o Model Improvement

```bash
sail artisan make:model Improvement
```

Edite `app/Models/Improvement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Improvement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'improvement_type_id',
        'improvement_step_id',
        'title',
        'description',
        'file_path',
        'priority',
        'order',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ImprovementType::class, 'improvement_type_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ImprovementStep::class, 'improvement_step_id');
    }
}
```

**Este model alimenta o Kanban de melhorias:**

```
ToDo (step=1)        InProgress (step=2)     Done (step=3)
+---------------+    +---------------+       +---------------+
| Refatorar     |    | Corrigir N+1  |       | Adicionar     |
| controller    | -> | query no loop | ->    | CSRF token    |
| (priority: 1) |    | (priority: 2) |       | (priority: 3) |
+---------------+    +---------------+       +---------------+
```

- O usuario arrasta cards entre colunas, atualizando `improvement_step_id`
- `order` controla a posicao dentro da coluna
- `completed_at` e preenchido ao mover para "Done"

---

## Passo 8 — Criar o Model DocEmbedding (RAG)

Este e o model mais importante para AI Engineering — e a base do sistema RAG.

```bash
sail artisan make:model DocEmbedding
```

Edite `app/Models/DocEmbedding.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocEmbedding extends Model
{
    use HasFactory, HasNeighbors;

    protected $fillable = [
        'source',
        'title',
        'content',
        'embedding',
        'category',
    ];

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
```

**Pontos importantes:**

- `HasNeighbors` — trait do pacote `pgvector/pgvector` que adiciona o metodo `nearestNeighbors()`
- `Vector::class` — cast que converte o array PHP para tipo `vector` do PostgreSQL
- Sem relacionamentos (standalone) — usado via **Tool de RAG** (Capitulo 9), nao por FK

### Como o RAG usa este model (preview do Capitulo 9)

```php
use Pgvector\Laravel\Distance;

// 1. Gerar embedding da query do usuario
$queryVector = Ai::embeddings()
    ->provider(Lab::Gemini)
    ->model('text-embedding-004')
    ->embed('Como evitar SQL Injection em Laravel?');

// 2. Buscar os 5 documentos mais similares
$docs = DocEmbedding::query()
    ->nearestNeighbors('embedding', $queryVector[0]->embedding, Distance::Cosine)
    ->take(5)
    ->get();

// 3. Retorna: PSR-12, OWASP SQL Injection, Laravel Security Best Practices...
// 4. Esses docs sao injetados no prompt do Agent
```

```bash
# Commitar models restantes
cd ~/laravel_ai
git add .
git commit -m "feat: add ReviewFinding, Improvement and DocEmbedding models"
```

---

## Passo 9 — Verificar os relacionamentos no Tinker

Vamos testar se tudo esta funcionando. Primeiro, certifique-se de que o banco esta com as migrations e seeders rodados:

```bash
sail artisan migrate:fresh --seed
```

Agora abra o Tinker:

```bash
sail artisan tinker
```

```php
// Verificar lookup tables
App\Models\ProjectStatus::all()->pluck('name');
// => ["Active", "Completed", "Archived"]

App\Models\ReviewPillar::all()->pluck('name');
// => ["Architecture", "Performance", "Security"]

App\Models\ImprovementStep::all()->pluck('name');
// => ["ToDo", "InProgress", "Done"]

// Criar um usuario de teste
$user = App\Models\User::create([
    'name' => 'Diego',
    'email' => 'diego@test.com',
    'password' => 'password',
]);

// Criar um projeto
$project = $user->projects()->create([
    'project_status_id' => 1,
    'name' => 'Meu Primeiro Projeto',
    'language' => 'php',
    'code_snippet' => '<?php echo "Hello World";',
]);

// Verificar relacionamento
$project->user->name;
// => "Diego"

$project->status->name;
// => "Active"

$user->projects->count();
// => 1

// Criar um code review
$review = $project->codeReview()->create([
    'review_status_id' => 1,
    'summary' => null,
]);

$review->status->name;
// => "Pending"

// Sair do Tinker
exit
```

> Se todos os comandos retornaram os valores esperados, os Models e relacionamentos estao corretos.

---

## Passo 10 — Diagrama de relacionamentos completo

```
User (1) -------- (N) Project
                        |
                        +-- BelongsTo ProjectStatus
                        |
                        +-- (1) CodeReview ---- (N) ReviewFinding
                        |       |                    +-- BelongsTo FindingType
                        |       +-- BelongsTo        +-- BelongsTo ReviewPillar
                        |           ReviewStatus
                        |
                        +-- (N) Improvement
                                +-- BelongsTo ImprovementType
                                +-- BelongsTo ImprovementStep

DocEmbedding (standalone — sem FK, usado via RAG Tool no Capitulo 9)
```

---

## Passo 11 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: verify models and relationships via tinker"

# Push da branch
git push -u origin feat/cap04-models

# Criar Pull Request
gh pr create --title "feat: models e relacionamentos" --body "Capitulo 04 - Eloquent Models, PHP Enums, relacionamentos e verificacao via Tinker"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `app/Enums/ReviewPillarEnum.php` | Architecture, Performance, Security |
| `app/Enums/FindingTypeEnum.php` | Strength, Improvement |
| `app/Enums/ReviewStatusEnum.php` | Pending, Completed, Failed |
| `app/Enums/ProjectStatusEnum.php` | Active, Completed, Archived |
| `app/Enums/ImprovementTypeEnum.php` | Refactor, Fix, Optimization |
| `app/Enums/ImprovementStepEnum.php` | ToDo, InProgress, Done |
| `app/Enums/SeverityEnum.php` | Low, Medium, High, Critical |
| `app/Models/ProjectStatus.php` | Lookup model |
| `app/Models/ReviewStatus.php` | Lookup model |
| `app/Models/FindingType.php` | Lookup model |
| `app/Models/ReviewPillar.php` | Lookup model |
| `app/Models/ImprovementType.php` | Lookup model |
| `app/Models/ImprovementStep.php` | Lookup model |
| `app/Models/User.php` | Editado: +is_admin, +first_review_at, +projects() |
| `app/Models/Project.php` | user(), status(), codeReview(), improvements() |
| `app/Models/CodeReview.php` | project(), status(), findings() |
| `app/Models/ReviewFinding.php` | codeReview(), type(), pillar() |
| `app/Models/Improvement.php` | project(), type(), step() |
| `app/Models/DocEmbedding.php` | HasNeighbors, Vector cast (RAG) |

## Proximo capitulo

No [Capitulo 5 — Rotas e Livewire Volt](05-rotas-livewire.md) vamos criar as rotas e os componentes Livewire Volt single-file para a interface do projeto.
