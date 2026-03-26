# Capitulo 14 — Testes Automatizados

> **Testes desde o inicio:** Este capitulo acompanha cada capitulo do tutorial. Conforme voce constroi o projeto, escreva os testes correspondentes.

## Piramide de Testes

```
                    /\
                   /  \        E2E (poucos)
                  /    \       Caro, fragil, lento
                 /------\
                / Accept.\     Aceitacao / Funcional
               /----------\    Comportamento do usuario
              / Integracao  \  Multiplas partes juntas
             /--------------\
            /   Unitario     \ Barato, rapido, muitos
           /------------------\
          /   Smoke / Perf.    \ Apos deploy, carga
         /----------------------\
```

| Tipo | Quantidade | Velocidade | Custo | Quando rodar |
|------|-----------|-----------|-------|-------------|
| **Unitario** | Muitos (60%+) | < 1s cada | Baixo | Cada commit |
| **Integracao** | Medio (20%) | 1-5s cada | Medio | Cada commit |
| **Funcional** | Medio (15%) | 1-10s cada | Medio | Cada PR |
| **E2E** | Poucos (3%) | 10-30s cada | Alto | Pre-deploy |
| **Aceitacao** | Poucos | 5-15s cada | Medio | Cada PR |
| **Performance** | Poucos | 30-60s | Alto | Pre-release |
| **Smoke** | Minimo (2%) | 1-5s cada | Baixo | Pos-deploy |

---

## Setup do Pest (Capitulo 2)

### Instalacao

```bash
sail composer require --dev pestphp/pest
sail artisan pest:install
```

### Configuracao

```php
// tests/Pest.php

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
```

### Estrutura de pastas

```
tests/
+-- Unit/
|   +-- Models/
|   |   +-- UserTest.php
|   |   +-- ProjectTest.php
|   |   +-- CodeReviewTest.php
|   |   +-- ImprovementTest.php
|   |   +-- DocEmbeddingTest.php
|   +-- Enums/
|   |   +-- SeverityEnumTest.php
|   |   +-- ReviewPillarEnumTest.php
|   +-- Services/
|       +-- CodeAnalysisServiceTest.php
+-- Feature/
|   +-- Auth/
|   |   +-- LoginTest.php
|   |   +-- RegisterTest.php
|   +-- Api/
|   |   +-- AuthTokenTest.php
|   |   +-- ProjectApiTest.php
|   |   +-- CodeReviewApiTest.php
|   |   +-- ImprovementApiTest.php
|   +-- Livewire/
|   |   +-- HomePageTest.php
|   |   +-- KanbanPageTest.php
|   +-- Agents/
|   |   +-- CodeAnalystTest.php
|   |   +-- CodeMentorTest.php
|   |   +-- SecurityAnalystTest.php
|   +-- Jobs/
|   |   +-- AnalyzeCodeJobTest.php
|   |   +-- GenerateImprovementsJobTest.php
|   +-- Rag/
|       +-- SearchDocsKnowledgeBaseTest.php
|       +-- ImportDocsCommandTest.php
+-- E2E/
|   +-- FullReviewFlowTest.php
+-- Performance/
|   +-- RagPerformanceTest.php
+-- Smoke/
    +-- HealthCheckTest.php
    +-- SwaggerTest.php
```

### Rodar testes

```bash
# Todos os testes
sail test

# Apenas unitarios
sail test --testsuite=Unit

# Apenas feature
sail test --testsuite=Feature

# Filtrar por nome
sail test --filter="ProjectTest"

# Com cobertura
sail test --coverage --min=80

# Modo watch (reexecuta ao salvar)
sail test --watch
```

---

## Factories (Capitulo 3-4)

Crie factories para todos os models:

```php
// database/factories/UserFactory.php (ja existe, ajustar)

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password', // cast 'hashed' faz o bcrypt
            'is_admin' => false,
            'first_review_at' => null,
            'first_plan_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    public function withReview(): static
    {
        return $this->state(['first_review_at' => now()]);
    }
}
```

```php
// database/factories/ProjectFactory.php

namespace Database\Factories;

use App\Enums\ProjectStatusEnum;
use App\Models\User;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_status_id' => ProjectStatusEnum::Active->value,
            'name' => fake()->words(3, true),
            'language' => fake()->randomElement(['php', 'javascript', 'python', 'typescript']),
            'code_snippet' => fake()->text(500),
            'repository_url' => fake()->optional()->url(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['project_status_id' => ProjectStatusEnum::Completed->value]);
    }
}
```

```php
// database/factories/CodeReviewFactory.php

namespace Database\Factories;

use App\Enums\ReviewStatusEnum;
use App\Models\Project;

class CodeReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'review_status_id' => ReviewStatusEnum::Pending->value,
            'summary' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'review_status_id' => ReviewStatusEnum::Completed->value,
            'summary' => fake()->paragraphs(3, true),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'review_status_id' => ReviewStatusEnum::Failed->value,
            'summary' => 'Erro ao analisar o codigo.',
        ]);
    }
}
```

```php
// database/factories/ReviewFindingFactory.php

namespace Database\Factories;

use App\Models\CodeReview;

class ReviewFindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code_review_id' => CodeReview::factory(),
            'finding_type_id' => fake()->randomElement([1, 2]),
            'review_pillar_id' => fake()->randomElement([1, 2, 3]),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'agent_flagged_at' => null,
            'user_flagged_at' => null,
        ];
    }

    public function flaggedByAgent(): static
    {
        return $this->state(['agent_flagged_at' => now()]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => 'critical']);
    }
}
```

```php
// database/factories/ImprovementFactory.php

namespace Database\Factories;

use App\Models\Project;

class ImprovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'improvement_type_id' => fake()->randomElement([1, 2, 3]),
            'improvement_step_id' => 1, // ToDo
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'file_path' => 'app/Services/' . fake()->word() . 'Service.php',
            'priority' => fake()->randomElement([0, 1, 2]),
            'order' => fake()->numberBetween(0, 20),
            'completed_at' => null,
        ];
    }

    public function done(): static
    {
        return $this->state([
            'improvement_step_id' => 3,
            'completed_at' => now(),
        ]);
    }
}
```

```php
// database/factories/DocEmbeddingFactory.php

namespace Database\Factories;

use Pgvector\Laravel\Vector;

class DocEmbeddingFactory extends Factory
{
    public function definition(): array
    {
        // Gera vetor fake de 768 dimensoes
        $embedding = array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 768));

        return [
            'source' => fake()->randomElement(['PSR-12', 'OWASP', 'Laravel Docs']),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(3),
            'embedding' => new Vector($embedding),
            'category' => fake()->randomElement(['architecture', 'performance', 'security']),
        ];
    }
}
```

---

## Testes Unitarios (Capitulos 3-4)

> **Rapidos, isolados, sem banco de dados**

### Enums

```php
// tests/Unit/Enums/SeverityEnumTest.php

use App\Enums\SeverityEnum;

test('severity enum has correct values', function () {
    expect(SeverityEnum::Low->value)->toBe('low');
    expect(SeverityEnum::Medium->value)->toBe('medium');
    expect(SeverityEnum::High->value)->toBe('high');
    expect(SeverityEnum::Critical->value)->toBe('critical');
});

test('severity enum has 4 cases', function () {
    expect(SeverityEnum::cases())->toHaveCount(4);
});

test('severity can be created from string', function () {
    expect(SeverityEnum::from('critical'))->toBe(SeverityEnum::Critical);
});

test('severity tryFrom returns null for invalid value', function () {
    expect(SeverityEnum::tryFrom('invalid'))->toBeNull();
});
```

```php
// tests/Unit/Enums/ReviewPillarEnumTest.php

use App\Enums\ReviewPillarEnum;

test('review pillar has 3 cases', function () {
    expect(ReviewPillarEnum::cases())->toHaveCount(3);
    expect(ReviewPillarEnum::Architecture->value)->toBe(1);
    expect(ReviewPillarEnum::Performance->value)->toBe(2);
    expect(ReviewPillarEnum::Security->value)->toBe(3);
});
```

### Models (atributos e casts)

```php
// tests/Unit/Models/UserTest.php

use App\Models\User;

test('user has correct fillable attributes', function () {
    $user = new User;

    expect($user->getFillable())->toContain(
        'name', 'email', 'password', 'is_admin', 'first_review_at', 'first_plan_at'
    );
});

test('user casts password as hashed', function () {
    $user = new User;
    $casts = $user->getCasts();

    expect($casts['password'])->toBe('hashed');
    expect($casts['is_admin'])->toBe('boolean');
    expect($casts['first_review_at'])->toBe('datetime');
});

test('user hides sensitive fields', function () {
    $user = new User;

    expect($user->getHidden())->toContain('password', 'remember_token');
});
```

```php
// tests/Unit/Models/ProjectTest.php

use App\Models\Project;

test('project has correct fillable attributes', function () {
    $project = new Project;

    expect($project->getFillable())->toContain(
        'user_id', 'project_status_id', 'name', 'language', 'code_snippet', 'repository_url'
    );
});
```

---

## Testes de Integracao (Capitulos 3-7)

> **Testam multiplas camadas juntas — models + banco + relacionamentos**

### Relacionamentos

```php
// tests/Feature/Models/ProjectRelationshipsTest.php

use App\Models\User;
use App\Models\Project;
use App\Models\CodeReview;
use App\Models\Improvement;

test('user has many projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->projects)->toHaveCount(3);
    expect($user->projects->first())->toBeInstanceOf(Project::class);
});

test('project has one code review', function () {
    $project = Project::factory()->create();
    $review = CodeReview::factory()->create(['project_id' => $project->id]);

    expect($project->codeReview->id)->toBe($review->id);
});

test('project has many improvements', function () {
    $project = Project::factory()->create();
    Improvement::factory()->count(5)->create(['project_id' => $project->id]);

    expect($project->improvements)->toHaveCount(5);
});

test('code review has many findings', function () {
    $review = CodeReview::factory()->create();
    \App\Models\ReviewFinding::factory()->count(6)->create(['code_review_id' => $review->id]);

    expect($review->findings)->toHaveCount(6);
});

test('deleting user cascades to projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(Project::find($project->id))->toBeNull();
});
```

### Migrations (banco integro)

```php
// tests/Feature/Database/MigrationTest.php

use Illuminate\Support\Facades\Schema;

test('all required tables exist', function () {
    $tables = [
        'users', 'projects', 'code_reviews', 'review_findings',
        'improvements', 'doc_embeddings', 'project_statuses',
        'improvement_types', 'improvement_steps', 'review_statuses',
        'finding_types', 'review_pillars', 'jobs', 'failed_jobs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} does not exist");
    }
});

test('doc_embeddings has vector column', function () {
    expect(Schema::hasColumn('doc_embeddings', 'embedding'))->toBeTrue();
});

test('pgvector extension is enabled', function () {
    $result = DB::select("SELECT * FROM pg_available_extensions WHERE name = 'vector'");
    expect($result)->not->toBeEmpty();
});
```

---

## Testes Funcionais — Auth (Capitulo 7)

> **Verificam saidas esperadas baseadas em requisitos de negocio**

```php
// tests/Feature/Auth/RegisterTest.php

test('user can register', function () {
    $response = $this->post('/register', [
        'name' => 'Diego',
        'email' => 'diego@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'diego@test.com']);
});

test('registration requires valid email', function () {
    $response = $this->post('/register', [
        'name' => 'Diego',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('registration requires unique email', function () {
    User::factory()->create(['email' => 'taken@test.com']);

    $response = $this->post('/register', [
        'name' => 'Diego',
        'email' => 'taken@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
});
```

```php
// tests/Feature/Auth/LoginTest.php

use App\Models\User;

test('user can login', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong password', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('guest cannot access home page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('authenticated user can access home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
});
```

---

## Testes Funcionais — API (Capitulo 13)

```php
// tests/Feature/Api/AuthTokenTest.php

use App\Models\User;

test('can generate api token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/token', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'test',
    ]);

    $response->assertOk()->assertJsonStructure(['token']);
});

test('token generation fails with wrong credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/token', [
        'email' => $user->email,
        'password' => 'wrong',
        'device_name' => 'test',
    ])->assertUnprocessable();
});
```

```php
// tests/Feature/Api/ProjectApiTest.php

use App\Models\User;
use App\Models\Project;

test('can list own projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['user_id' => $user->id]);
    Project::factory()->count(2)->create(); // outros usuarios

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can create project via api', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'API de Pagamentos',
            'language' => 'php',
            'code_snippet' => str_repeat('x', 50),
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'API de Pagamentos');

    $this->assertDatabaseHas('projects', ['name' => 'API de Pagamentos']);
});

test('cannot create project with invalid language', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'Test',
            'language' => 'brainfuck',
            'code_snippet' => str_repeat('x', 50),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');
});

test('cannot view another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/projects/{$otherProject->id}")
        ->assertForbidden();
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});
```

```php
// tests/Feature/Api/CodeReviewApiTest.php

use App\Models\User;
use App\Models\Project;
use App\Jobs\AnalyzeCodeJob;
use Illuminate\Support\Facades\Queue;

test('can start code review', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$project->id}/reviews", [
            'architecture_strength' => 'Good patterns',
            'architecture_improvement' => 'Needs DI',
            'performance_strength' => 'Fast queries',
            'performance_improvement' => 'N+1 detected',
            'security_strength' => 'CSRF present',
            'security_improvement' => 'SQL injection risk',
        ])
        ->assertCreated()
        ->assertJsonPath('review_status_id', 1); // Pending

    Queue::assertPushed(AnalyzeCodeJob::class);
    $this->assertDatabaseCount('review_findings', 6);
});

test('cannot create duplicate review', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $project->codeReview()->create(['review_status_id' => 1]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$project->id}/reviews", [
            'architecture_strength' => 'x',
            'architecture_improvement' => 'x',
            'performance_strength' => 'x',
            'performance_improvement' => 'x',
            'security_strength' => 'x',
            'security_improvement' => 'x',
        ])
        ->assertConflict();
});
```

---

## Testes de AI Agents com FakeAi (Capitulos 8-10)

> **O mais importante: testar Agents SEM chamar APIs reais**

```php
// tests/Feature/Agents/CodeAnalystTest.php

use App\Ai\Agents\CodeAnalyst;
use App\Models\CodeReview;
use App\Models\Project;
use App\Services\CodeAnalysisService;
use Laravel\Ai\Testing\FakeAi;

test('code analyst returns structured output', function () {
    FakeAi::fake();

    $project = Project::factory()->create(['language' => 'php']);
    $review = CodeReview::factory()->create(['project_id' => $project->id]);

    FakeAi::agent(CodeAnalyst::class)->respondWith([
        'summary' => '## Analise\nCodigo com boa estrutura.',
        'score' => 85,
        'priority_finding_ids' => [1, 2, 3],
    ]);

    $service = new CodeAnalysisService;
    $service->handle($review);

    $review->refresh();
    expect($review->summary)->toContain('Analise');
    expect($review->review_status_id)->toBe(2); // Completed
});

test('code analysis handles agent failure', function () {
    FakeAi::fake();

    $review = CodeReview::factory()->create();

    FakeAi::agent(CodeAnalyst::class)
        ->throwException(new \Exception('API timeout'));

    $service = new CodeAnalysisService;

    expect(fn () => $service->handle($review))->toThrow(\Exception::class);
});
```

```php
// tests/Feature/Agents/CodeMentorTest.php

use App\Ai\Agents\CodeMentor;
use App\Models\Project;
use App\Models\CodeReview;
use App\Models\ReviewFinding;
use App\Services\ImprovementPlanService;
use Laravel\Ai\Testing\FakeAi;

test('code mentor creates improvements via tools', function () {
    FakeAi::fake();

    $project = Project::factory()->create();
    $review = CodeReview::factory()->create(['project_id' => $project->id]);
    ReviewFinding::factory()->count(3)->flaggedByAgent()->create(['code_review_id' => $review->id]);

    FakeAi::agent(CodeMentor::class)->respondWith('Plano gerado com sucesso.');

    $service = new ImprovementPlanService;
    $service->handle($project);

    $project->user->refresh();
    expect($project->user->first_plan_at)->not->toBeNull();
});
```

```php
// tests/Feature/Rag/SearchDocsKnowledgeBaseTest.php

use App\Ai\Tools\SearchDocsKnowledgeBase;
use App\Models\DocEmbedding;
use Laravel\Ai\Testing\FakeAi;
use Pgvector\Laravel\Vector;

test('search docs returns relevant results', function () {
    FakeAi::fake();

    // Criar docs no banco com embeddings fake
    $securityDoc = DocEmbedding::factory()->create([
        'source' => 'OWASP',
        'title' => 'SQL Injection Prevention',
        'content' => 'Always use parameterized queries...',
        'category' => 'security',
    ]);

    // Fake embedding response
    $fakeVector = array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 768));
    FakeAi::embeddings()->respondWith([$fakeVector]);

    $tool = new SearchDocsKnowledgeBase;
    $result = $tool->execute([
        'query' => 'SQL injection prevention',
        'category' => 'security',
    ]);

    expect($result)->toBeString();
    expect($result)->toContain('OWASP');
});
```

---

## Testes de Jobs (Capitulo 11)

```php
// tests/Feature/Jobs/AnalyzeCodeJobTest.php

use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use App\Services\CodeAnalysisService;
use Laravel\Ai\Testing\FakeAi;

test('analyze code job calls service', function () {
    FakeAi::fake();

    $review = CodeReview::factory()->create();

    FakeAi::agent(\App\Ai\Agents\CodeAnalyst::class)->respondWith([
        'summary' => 'Analise completa.',
        'score' => 90,
        'priority_finding_ids' => [],
    ]);

    AnalyzeCodeJob::dispatchSync($review);

    $review->refresh();
    expect($review->review_status_id)->toBe(2);
});

test('failed job updates status to failed', function () {
    FakeAi::fake();

    $review = CodeReview::factory()->create();

    FakeAi::agent(\App\Ai\Agents\CodeAnalyst::class)
        ->throwException(new \Exception('Timeout'));

    $job = new AnalyzeCodeJob($review);

    try {
        $job->handle(new CodeAnalysisService);
    } catch (\Exception $e) {
        $job->failed($e);
    }

    $review->refresh();
    expect($review->review_status_id)->toBe(3); // Failed
});

test('job is dispatched to queue', function () {
    Queue::fake();

    $review = CodeReview::factory()->create();
    AnalyzeCodeJob::dispatch($review);

    Queue::assertPushed(AnalyzeCodeJob::class, function ($job) use ($review) {
        return $job->codeReview->id === $review->id;
    });
});
```

---

## Testes E2E (Capitulo 12)

> **Poucos, mas cobrem o fluxo completo do usuario**

```php
// tests/E2E/FullReviewFlowTest.php

use App\Models\User;
use App\Jobs\AnalyzeCodeJob;
use App\Jobs\GenerateImprovementsJob;
use Laravel\Ai\Testing\FakeAi;
use Illuminate\Support\Facades\Queue;

test('full review flow: create project -> review -> improvements', function () {
    FakeAi::fake();
    Queue::fake();

    // 1. Registrar usuario
    $this->post('/register', [
        'name' => 'Diego',
        'email' => 'diego@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/');

    $user = User::where('email', 'diego@test.com')->first();
    expect($user)->not->toBeNull();

    // 2. Criar projeto via API
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/projects', [
            'name' => 'E2E Test Project',
            'language' => 'php',
            'code_snippet' => str_repeat('class Test { }', 10),
        ]);

    $response->assertCreated();
    $projectId = $response->json('id');

    // 3. Iniciar code review
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/projects/{$projectId}/reviews", [
            'architecture_strength' => 'Good',
            'architecture_improvement' => 'Needs refactor',
            'performance_strength' => 'OK',
            'performance_improvement' => 'N+1',
            'security_strength' => 'CSRF',
            'security_improvement' => 'SQL Injection',
        ])
        ->assertCreated();

    // 4. Verificar que job foi enfileirado
    Queue::assertPushed(AnalyzeCodeJob::class);

    // 5. Verificar estado no banco
    $this->assertDatabaseHas('projects', ['name' => 'E2E Test Project']);
    $this->assertDatabaseHas('code_reviews', ['project_id' => $projectId]);
    $this->assertDatabaseCount('review_findings', 6);
});
```

---

## Testes de Aceitacao (Capitulo 7)

> **Focados no comportamento do usuario**

```php
// tests/Feature/Acceptance/UserJourneyTest.php

use App\Models\User;

test('new user can register and see empty dashboard', function () {
    $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Meus Projetos');
});

test('admin can access admin panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('Usuarios');
});

test('regular user cannot access admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});
```

---

## Testes de Performance (Capitulo 9)

> **Simulam carga para validar performance do RAG**

```php
// tests/Performance/RagPerformanceTest.php

use App\Models\DocEmbedding;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;

test('pgvector query returns in under 100ms with 1000 docs', function () {
    // Seed 1000 embeddings
    DocEmbedding::factory()->count(1000)->create();

    $queryVector = new Vector(
        array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 768))
    );

    $start = microtime(true);

    $results = DocEmbedding::query()
        ->nearestNeighbors('embedding', $queryVector, Distance::Cosine)
        ->take(5)
        ->get();

    $elapsed = (microtime(true) - $start) * 1000;

    expect($results)->toHaveCount(5);
    expect($elapsed)->toBeLessThan(100, "Query took {$elapsed}ms, expected < 100ms");
});

test('bulk embedding creation is efficient', function () {
    $start = microtime(true);

    DocEmbedding::factory()->count(100)->create();

    $elapsed = (microtime(true) - $start) * 1000;

    expect($elapsed)->toBeLessThan(5000, "Bulk create took {$elapsed}ms");
    expect(DocEmbedding::count())->toBe(100);
});
```

---

## Smoke Tests (Capitulo 12)

> **Rodam apos deploy para validar o basico**

```php
// tests/Smoke/HealthCheckTest.php

test('health endpoint returns ok', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

test('login page loads', function () {
    $this->get('/login')->assertOk();
});

test('register page loads', function () {
    $this->get('/register')->assertOk();
});

test('database connection works', function () {
    expect(DB::connection()->getPdo())->not->toBeNull();
});

test('pgvector extension is available', function () {
    $result = DB::select("SELECT extname FROM pg_extension WHERE extname = 'vector'");
    expect($result)->not->toBeEmpty();
});
```

```php
// tests/Smoke/SwaggerTest.php

test('swagger documentation is accessible', function () {
    $this->get('/api/documentation')->assertOk();
});

test('swagger json is valid', function () {
    $response = $this->getJson('/docs/api-docs.json');
    $response->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('openapi');
    expect($data)->toHaveKey('paths');
    expect($data['info']['title'])->toBe('CodeReview AI API');
});
```

---

## CI/CD — GitHub Actions

Vamos configurar um pipeline de CI/CD que roda automaticamente todos os testes a cada push ou pull request.

### Criando o workflow

```bash
mkdir -p .github/workflows
```

Crie `.github/workflows/tests.yml`:

```yaml
# .github/workflows/tests.yml

name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: pgvector/pgvector:pg18
        env:
          POSTGRES_USER: sail
          POSTGRES_PASSWORD: password
          POSTGRES_DB: testing
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.5
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: pdo_pgsql, pcntl, zip, intl
          coverage: none

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Prepare environment
        run: |
          cp .env.example .env
          php artisan key:generate
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password

      - name: Enable pgvector
        run: PGPASSWORD=password psql -h localhost -U sail -d testing -c "CREATE EXTENSION IF NOT EXISTS vector;"

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password

      - name: Run unit tests
        run: php artisan test --testsuite=Unit

      - name: Run feature tests
        run: php artisan test --testsuite=Feature
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password

      - name: Run smoke tests
        run: php artisan test tests/Smoke/
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: sail
          DB_PASSWORD: password
```

### Como o workflow funciona

```
Push/PR no GitHub
       |
       v
+----------------------------------------------+
| GitHub Actions Runner (ubuntu-latest)        |
|                                              |
|  +------------------+   +-----------------+  |
|  | Service:         |   | Steps:          |  |
|  | pgvector/pg18    |   |                 |  |
|  | - User: sail     |   | 1. Checkout     |  |
|  | - DB: testing    |   | 2. Setup PHP    |  |
|  | - Port: 5432     |   | 3. Cache deps   |  |
|  +--------+---------+   | 4. Composer     |  |
|           |              | 5. .env + key   |  |
|           |  localhost   | 6. pgvector ext |  |
|           +<------------>| 7. Migrations   |  |
|                          | 8. Unit tests   |  |
|                          | 9. Feature tests|  |
|                          | 10. Smoke tests |  |
|                          +-----------------+  |
+----------------------------------------------+
       |
       v
  Badge: ✅ Passing / ❌ Failing
```

### Pontos-chave do workflow

| Configuracao | Motivo |
|-------------|--------|
| `pgvector/pgvector:pg18` como service | Mesmo banco do projeto, com pgvector incluso |
| `DB_HOST: localhost` (nao `pgsql`) | No GitHub Actions, services sao acessiveis via localhost |
| `actions/cache@v4` no Composer | Evita reinstalar dependencias a cada build (~30s economizados) |
| `coverage: none` no setup-php | Builds mais rapidos — cobertura so quando necessario |
| pgvector habilitado **antes** das migrations | Migrations usam `CREATE EXTENSION vector` |
| Testes separados por step | Fica claro qual suite falhou no log do GitHub |

### Triggers — quando o workflow roda

```yaml
on:
  push:
    branches: [main, develop]    # Roda em push direto nestas branches
  pull_request:
    branches: [main]             # Roda em PRs para main
```

Isso significa:
- **Push em `main` ou `develop`:** testes rodam automaticamente
- **PR para `main`:** testes rodam e o resultado aparece no PR (check obrigatorio)
- **Push em feature branches:** nao roda (para economizar minutos do GitHub Actions)

> **Dica:** No repositorio do GitHub, va em **Settings > Branches > Branch protection rules** e ative "Require status checks to pass before merging" selecionando o job `tests`. Assim nenhum PR pode ser mergeado sem os testes passarem.

### Adicionando badge no README

Adicione no topo do `README.md` para mostrar o status dos testes:

```markdown
![Tests](https://github.com/SEU-USUARIO/codereview-ai/actions/workflows/tests.yml/badge.svg)
```

### Inicializando o repositorio

```bash
# Dentro do diretorio do projeto
cd codereview-ai

# Inicializar git (se ainda nao fez)
git init
git add .
git commit -m "Initial commit: CodeReview AI com Laravel AI SDK"

# Criar repositorio no GitHub e push
gh repo create codereview-ai --public --source=. --push

# Ou manualmente:
git remote add origin git@github.com:SEU-USUARIO/codereview-ai.git
git branch -M main
git push -u origin main
```

Apos o push, acesse a aba **Actions** no GitHub para ver o workflow rodando.

### Segredos e variaveis de ambiente

Os testes usam `FakeAi` (Capitulo 8-10), entao **nao precisam** de `GEMINI_API_KEY` no CI. Mas se quiser rodar testes de integracao real com a API:

1. Va em **Settings > Secrets and variables > Actions**
2. Adicione `GEMINI_API_KEY` como secret
3. Use no workflow:

```yaml
      - name: Run integration tests (real API)
        run: php artisan test tests/Integration/
        env:
          GEMINI_API_KEY: ${{ secrets.GEMINI_API_KEY }}
```

> **Importante:** Nunca commite chaves de API no repositorio. Use sempre GitHub Secrets.

---

## Resumo: Mapa de testes por capitulo

| Capitulo | Tipo de Teste | Arquivo |
|----------|--------------|---------|
| 2 (Setup) | Pest config | `tests/Pest.php` |
| 3 (Banco) | Integracao: migrations | `tests/Feature/Database/MigrationTest.php` |
| 4 (Models) | Unitario: attrs, casts | `tests/Unit/Models/` |
| 4 (Models) | Integracao: relacionamentos | `tests/Feature/Models/` |
| 7 (Auth) | Funcional: login, register | `tests/Feature/Auth/` |
| 7 (Auth) | Aceitacao: jornada usuario | `tests/Feature/Acceptance/` |
| 8 (Agents) | Integracao: FakeAi | `tests/Feature/Agents/CodeAnalystTest.php` |
| 9 (RAG) | Integracao: embeddings + pgvector | `tests/Feature/Rag/` |
| 9 (RAG) | Performance: 1000 docs | `tests/Performance/RagPerformanceTest.php` |
| 10 (Multi-Agent) | Integracao: FakeAi | `tests/Feature/Agents/CodeMentorTest.php` |
| 11 (Jobs) | Integracao: Queue fake | `tests/Feature/Jobs/` |
| 12 (Deploy) | Smoke: health, pgvector | `tests/Smoke/` |
| 13 (API) | Funcional: endpoints | `tests/Feature/Api/` |
| 13 (Swagger) | Smoke: docs | `tests/Smoke/SwaggerTest.php` |
| E2E | Fluxo completo | `tests/E2E/FullReviewFlowTest.php` |
