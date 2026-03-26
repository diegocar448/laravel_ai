# Capitulo 4 — Models e Relacionamentos

> **Este capitulo cobre o pilar de AI Engineering: Structured Output (pilar 2)**

## Design dos Models para AI Engineering

Os Models foram desenhados para suportar **Structured Output** — forcar a IA a responder em formatos tipados e validaveis via `HasStructuredOutput` do Laravel AI SDK.

### Padrao de Design: Agent Schema + Eloquent

No Laravel AI SDK, o Agent define o schema da resposta, e o Eloquent persiste:

```php
// Agent define o schema (Capitulo 8)
class CodeAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'score' => $schema->integer()->min(0)->max(100)->required(),
            'findings' => $schema->array()->required(),
        ];
    }
}

// Eloquent persiste a resposta tipada
$response = (new CodeAnalyst)->prompt($code);

$codeReview->update([
    'summary' => $response['summary'],   // string garantida
    'review_status_id' => 2,             // Completed
]);
```

**Por que esse padrao?**

1. **Tipagem forte** — JsonSchema valida antes de persistir
2. **Schema no Agent** — Definido junto com a logica de IA
3. **Eloquent para persistencia** — Models salvam resultado final
4. **Rejeicao automatica** — Se LLM retornar formato errado, excecao antes de salvar

---

## Visao geral dos Models

O projeto possui Eloquent Models organizados em 3 grupos:

```
Dominio principal:          Lookup tables (Enums):       RAG:
+-- User                    +-- ProjectStatus            +-- DocEmbedding
+-- Project                 +-- ImprovementType
+-- CodeReview              +-- ImprovementStep
+-- ReviewFinding           +-- ReviewStatus
+-- Improvement             +-- FindingType
                            +-- ReviewPillar
```

---

## Modelos-Chave para AI Engineering

### CodeReview — Pilar 7 (Orchestration)

Este model armazena o **resultado final da orquestracao de Agents**:

```php
class CodeReview extends Model
{
    // Relacionamentos
    public function project(): BelongsTo          // qual projeto foi analisado
    public function status(): BelongsTo           // Pending, Completed, Failed
    public function findings(): HasMany           // findings de todos os 3 agents
    public function improvements(): HasMany       // melhorias combinadas

    // Tracking de IA
    public $timestamps = true;                    // created_at, updated_at
    public string $summary;                       // Resumo gerado pelo CodeAnalyst Agent
}
```

**Fluxo:**
```
User submete codigo
        |
CodeReview criado (status=Pending)
        |
Job AnalyzeCodeJob disparado
        |
CodeAnalyst Agent gera summary + score (HasStructuredOutput)
        |
CodeMentor Agent orquestra 3 sub-Agents via Tools
    +- ArchitectureAnalyst Agent -> retorna findings
    +- PerformanceAnalyst Agent  -> retorna findings
    +- SecurityAnalyst Agent     -> retorna findings
        |
ReviewFindings salvos no DB
Improvements gerados via StoreImprovements Tool
        |
CodeReview.status = Completed
```

### ReviewFinding — Pilar 2 (Structured Output)

Cada finding e uma **resposta estruturada de um Agent**, validada e tipada:

```php
class ReviewFinding extends Model
{
    // Dados estruturados (garantidos tipados pelo Agent schema)
    public string $description;                    // "SQL Injection risk in..."
    public string $severity;                       // low|medium|high|critical
    public ?datetime $agent_flagged_at;            // quando agent encontrou
    public ?datetime $user_flagged_at;             // quando user validou/descartou
}
```

**Exemplo de ReviewFinding estruturado (do Agent):**
```json
{
  "pillar": "security",
  "severity": "critical",
  "description": "SQL Injection vulnerability: User input not parameterized",
  "code_snippet": "DB::select('SELECT * FROM users WHERE id = ' . $id)",
  "fix_suggestion": "Use parameterized query: DB::select('SELECT * FROM users WHERE id = ?', [$id])",
  "line_number": 45
}
```

---

## Resumo da Modelagem para AI

| Modelo | Pilar IA | Funcao |
|--------|----------|--------|
| **CodeReview** | Orchestration (7) | Rastreia analise completa |
| **ReviewFinding** | Structured Output (2) | Armazena resposta tipada do Agent |
| **Improvement** | Orchestration (7) | Agrupa findings em acao (Tool) |
| **DocEmbedding** | RAG + Vector DB (3, 6) | Base de conhecimento de PSRs/OWASP |
| **Project** | — | Projeto sendo analisado |

---

## User

```php
class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'first_review_at',
        'first_plan_at',
    ];

    protected $hidden = ['password', 'remember_token'];

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

Pontos importantes:
- `password` usa o cast `hashed` — o Laravel automaticamente aplica bcrypt ao salvar
- `is_admin` controla acesso ao painel admin
- `first_review_at` e `first_plan_at` rastreiam a primeira interacao com IA

## Project

```php
class Project extends Model
{
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

## CodeReview e ReviewFinding

```php
class CodeReview extends Model
{
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

```php
class ReviewFinding extends Model
{
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

## Improvement

```php
class Improvement extends Model
{
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

## Enums PHP

O projeto usa **Backed Enums** do PHP 8.5 para mapear os valores das lookup tables:

```php
enum ReviewPillarEnum: int
{
    case Architecture = 1;
    case Performance = 2;
    case Security = 3;
}

enum FindingTypeEnum: string
{
    case Strength = 'strength';
    case Improvement = 'improvement';
}

enum ReviewStatusEnum: int
{
    case Pending = 1;
    case Completed = 2;
    case Failed = 3;
}

enum ProjectStatusEnum: int
{
    case Active = 1;
    case Completed = 2;
    case Archived = 3;
}

enum ImprovementTypeEnum: int
{
    case Refactor = 1;
    case Fix = 2;
    case Optimization = 3;
}

enum SeverityEnum: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

## DocEmbedding (Model RAG)

```php
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocEmbedding extends Model
{
    use HasNeighbors;

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

O trait `HasNeighbors` do pacote `pgvector/pgvector` adiciona o metodo `nearestNeighbors()`:

```php
// Buscar os 5 documentos mais similares a um vetor de query
$docs = DocEmbedding::query()
    ->nearestNeighbors('embedding', $queryVector, Distance::Cosine)
    ->take(5)
    ->get();
```

Este e o coracao do RAG — permite buscar documentacao relevante por similaridade semantica. Os embeddings sao gerados via `Ai::embeddings()` do Laravel AI SDK (Capitulo 9).

## Diagrama de relacionamentos

```
User (1) -------- (N) Project
                        |
                        +-- (1) CodeReview ---- (N) ReviewFinding
                        |                           +-- BelongsTo FindingType
                        |                           +-- BelongsTo ReviewPillar
                        |
                        +-- (N) Improvement
                        |       +-- BelongsTo ImprovementType
                        |       +-- BelongsTo ImprovementStep
                        |
                        +-- BelongsTo ProjectStatus

DocEmbedding (standalone — sem FK, usado via RAG Tool)
```

## Proximo capitulo

No [Capitulo 5 — Rotas e Livewire Volt](05-rotas-livewire.md) vamos ver como as rotas sao definidas e como os componentes Livewire Volt funcionam.
