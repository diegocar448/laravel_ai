# Capitulo 13 — API REST e Swagger

> **Este capitulo cobre:** Endpoints REST para integracao externa (CI/CD, GitHub Actions, CLIs) e documentacao interativa com Swagger/OpenAPI.

Neste capitulo vamos criar uma **API REST completa** com autenticacao via Sanctum, documentada com Swagger. Ao final, voce tera endpoints para criar projetos, disparar code reviews e gerenciar melhorias — tudo acessivel por ferramentas externas, pipelines e apps terceiros.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap13-api
cd codereview-ai
```

---

## Por que uma API REST?

O projeto usa Livewire para a UI, mas uma API REST permite:

- **CI/CD** — code review automatico em pipelines
- **GitHub Actions** — review em cada PR
- **CLI tools** — analise de codigo via terminal
- **Integracoes** — apps terceiros consumirem o servico
- **Mobile** — futuro app mobile

---

## Passo 1 — Instalar o L5-Swagger

```bash
sail composer require darkaonline/l5-swagger
sail artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

Edite `config/l5-swagger.php` — ajuste as seguintes chaves dentro do array `defaults`:

```php
// config/l5-swagger.php (principais ajustes)
return [
    'defaults' => [
        'routes' => [
            'api' => 'api/documentation',
        ],
        'info' => [
            'title' => 'CodeReview AI API',
            'version' => '1.0.0',
            'description' => 'API REST para code review com IA usando Laravel AI SDK',
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => 'Token gerado via POST /api/auth/token',
                ],
            ],
        ],
    ],
];
```

Adicione as variaveis ao `.env`:

```env
L5_SWAGGER_GENERATE_ALWAYS=true
L5_SWAGGER_CONST_HOST=http://localhost/api
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: install and configure L5-Swagger"
```

---

## Passo 2 — Configurar autenticacao com Laravel Sanctum

```bash
sail artisan install:api
```

Isso instala o Sanctum e cria a migration `personal_access_tokens`.

```bash
sail artisan migrate
```

**Verificacao:** confirme que a tabela foi criada:

```bash
sail artisan migrate:status | grep personal_access_tokens
```

Deve mostrar status `Ran`.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: install Sanctum and run api migration"
```

---

## Passo 3 — Adicionar annotation base do Swagger no Controller

O L5-Swagger precisa de uma annotation `OA\Info` em algum controller. Vamos adicionar no `Controller.php` base.

Edite `app/Http/Controllers/Controller.php`:

```php
<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'CodeReview AI API',
    version: '1.0.0',
    description: 'API REST para code review com IA — Laravel AI SDK + Gemini',
    contact: new OA\Contact(name: 'CodeReview AI', email: 'api@codereview.ai'),
)]
#[OA\Server(url: 'http://localhost', description: 'Local')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer token via POST /api/auth/token'
)]
abstract class Controller {}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add OpenAPI base annotation to Controller"
```

---

## Passo 4 — Criar o AuthController (token endpoint)

```bash
sail artisan make:controller Api/AuthController
```

Edite `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/token',
        summary: 'Gerar token de acesso',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'device_name'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'device_name', type: 'string', example: 'cli-tool'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token gerado'),
            new OA\Response(response: 422, description: 'Credenciais invalidas'),
        ]
    )]
    public function token(Request $request): array
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        return [
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ];
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add AuthController with Sanctum token endpoint"
```

---

## Passo 5 — Criar a ProjectPolicy

Antes dos controllers que usam autorizacao, precisamos da Policy.

```bash
sail artisan make:policy ProjectPolicy --model=Project
```

Edite `app/Policies/ProjectPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}
```

**O que cada metodo faz:**
- `view()` — so o dono do projeto pode visualizar
- `update()` — so o dono pode disparar reviews e editar
- `delete()` — so o dono pode deletar

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add ProjectPolicy for authorization"
```

---

## Passo 6 — Criar o ProjectController da API

```bash
sail artisan make:controller Api/ProjectController
```

Edite `app/Http/Controllers/Api/ProjectController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Enums\ProjectStatusEnum;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        path: '/api/projects',
        summary: 'Listar projetos do usuario',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de projetos',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Project')
                )
            ),
        ]
    )]
    public function index(Request $request)
    {
        return $request->user()
            ->projects()
            ->with(['status', 'codeReview.status'])
            ->latest()
            ->paginate(20);
    }

    #[OA\Post(
        path: '/api/projects',
        summary: 'Criar projeto para review',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'language', 'code_snippet'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'API de Pagamentos'),
                    new OA\Property(property: 'language', type: 'string', enum: ['php', 'javascript', 'python', 'go', 'rust', 'java', 'typescript']),
                    new OA\Property(property: 'code_snippet', type: 'string', example: 'class PaymentService { ... }'),
                    new OA\Property(property: 'repository_url', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Projeto criado'),
            new OA\Response(response: 422, description: 'Validacao falhou'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|in:php,javascript,python,go,rust,java,typescript',
            'code_snippet' => 'required|string|min:50',
            'repository_url' => 'nullable|url',
        ]);

        $project = $request->user()->projects()->create([
            ...$validated,
            'project_status_id' => ProjectStatusEnum::Active->value,
        ]);

        return response()->json($project->load('status'), 201);
    }

    #[OA\Get(
        path: '/api/projects/{id}',
        summary: 'Detalhes de um projeto',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Projeto com review e improvements'),
            new OA\Response(response: 404, description: 'Nao encontrado'),
        ]
    )]
    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return $project->load([
            'status',
            'codeReview.status',
            'codeReview.findings.pillar',
            'codeReview.findings.type',
            'improvements.type',
            'improvements.step',
        ]);
    }

    #[OA\Delete(
        path: '/api/projects/{id}',
        summary: 'Deletar projeto',
        tags: ['Projects'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deletado'),
        ]
    )]
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API ProjectController with OpenAPI attributes"
```

---

## Passo 7 — Criar o CodeReviewController da API

```bash
sail artisan make:controller Api/CodeReviewController
```

Edite `app/Http/Controllers/Api/CodeReviewController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCodeJob;
use App\Models\CodeReview;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CodeReviewController extends Controller
{
    #[OA\Post(
        path: '/api/projects/{projectId}/reviews',
        summary: 'Iniciar code review com IA',
        description: 'Cria um CodeReview e dispara o Agent de analise em background. Use GET /reviews/{id} para acompanhar o status.',
        tags: ['Code Reviews'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['architecture_strength', 'architecture_improvement', 'performance_strength', 'performance_improvement', 'security_strength', 'security_improvement'],
                properties: [
                    new OA\Property(property: 'architecture_strength', type: 'string'),
                    new OA\Property(property: 'architecture_improvement', type: 'string'),
                    new OA\Property(property: 'performance_strength', type: 'string'),
                    new OA\Property(property: 'performance_improvement', type: 'string'),
                    new OA\Property(property: 'security_strength', type: 'string'),
                    new OA\Property(property: 'security_improvement', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Review iniciado (status: Pending)'),
            new OA\Response(response: 409, description: 'Projeto ja possui review'),
        ]
    )]
    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        if ($project->codeReview) {
            return response()->json(['message' => 'Projeto ja possui review.'], 409);
        }

        $validated = $request->validate([
            'architecture_strength' => 'required|string',
            'architecture_improvement' => 'required|string',
            'performance_strength' => 'required|string',
            'performance_improvement' => 'required|string',
            'security_strength' => 'required|string',
            'security_improvement' => 'required|string',
        ]);

        $codeReview = $project->codeReview()->create([
            'review_status_id' => 1, // Pending
        ]);

        // Criar 6 findings (3 pilares x 2 tipos)
        $findings = [
            ['pillar' => 1, 'type' => 1, 'desc' => $validated['architecture_strength']],
            ['pillar' => 1, 'type' => 2, 'desc' => $validated['architecture_improvement']],
            ['pillar' => 2, 'type' => 1, 'desc' => $validated['performance_strength']],
            ['pillar' => 2, 'type' => 2, 'desc' => $validated['performance_improvement']],
            ['pillar' => 3, 'type' => 1, 'desc' => $validated['security_strength']],
            ['pillar' => 3, 'type' => 2, 'desc' => $validated['security_improvement']],
        ];

        foreach ($findings as $finding) {
            $codeReview->findings()->create([
                'review_pillar_id' => $finding['pillar'],
                'finding_type_id' => $finding['type'],
                'description' => $finding['desc'],
            ]);
        }

        // Dispara Agent em background
        AnalyzeCodeJob::dispatch($codeReview);

        $request->user()->update(['first_review_at' => now()]);

        return response()->json(
            $codeReview->load(['status', 'findings.pillar', 'findings.type']),
            201
        );
    }

    #[OA\Get(
        path: '/api/reviews/{id}',
        summary: 'Status e resultado de um code review',
        description: 'Retorna o review com findings. Status: 1=Pending, 2=Completed, 3=Failed.',
        tags: ['Code Reviews'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Review com findings'),
        ]
    )]
    public function show(CodeReview $codeReview)
    {
        $this->authorize('view', $codeReview->project);

        return $codeReview->load([
            'status',
            'project',
            'findings.pillar',
            'findings.type',
        ]);
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API CodeReviewController with OpenAPI attributes"
```

---

## Passo 8 — Criar o ImprovementController da API

```bash
sail artisan make:controller Api/ImprovementController
```

Edite `app/Http/Controllers/Api/ImprovementController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Improvement;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ImprovementController extends Controller
{
    #[OA\Get(
        path: '/api/projects/{projectId}/improvements',
        summary: 'Listar melhorias (Kanban) de um projeto',
        tags: ['Improvements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'step', in: 'query', required: false, description: '1=ToDo, 2=InProgress, 3=Done', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de improvements'),
        ]
    )]
    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $query = $project->improvements()->with(['type', 'step'])->orderBy('order');

        if ($request->has('step')) {
            $query->where('improvement_step_id', $request->step);
        }

        return $query->get();
    }

    #[OA\Patch(
        path: '/api/improvements/{id}',
        summary: 'Atualizar improvement (mover no Kanban)',
        tags: ['Improvements'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'improvement_step_id', type: 'integer', description: '1=ToDo, 2=InProgress, 3=Done'),
                    new OA\Property(property: 'order', type: 'integer'),
                    new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Improvement atualizado'),
        ]
    )]
    public function update(Request $request, Improvement $improvement)
    {
        $this->authorize('update', $improvement->project);

        $validated = $request->validate([
            'improvement_step_id' => 'sometimes|integer|in:1,2,3',
            'order' => 'sometimes|integer',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        $improvement->update($validated);

        return $improvement->load(['type', 'step']);
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API ImprovementController with OpenAPI attributes"
```

---

## Passo 9 — Criar o Schema OpenAPI para o Project

Crie o diretorio e o arquivo de schema:

```bash
mkdir -p app/Http/Resources/Schemas
```

Crie `app/Http/Resources/Schemas/ProjectSchema.php`:

```php
<?php

namespace App\Http\Resources\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Project',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'language', type: 'string'),
        new OA\Property(property: 'code_snippet', type: 'string'),
        new OA\Property(property: 'repository_url', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class ProjectSchema {}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add ProjectSchema for OpenAPI documentation"
```

---

## Passo 10 — Configurar as rotas da API

Edite `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\CodeReviewController;
use App\Http\Controllers\Api\ImprovementController;

Route::post('/auth/token', [AuthController::class, 'token']);

Route::middleware('auth:sanctum')->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Code Reviews
    Route::post('/projects/{project}/reviews', [CodeReviewController::class, 'store']);
    Route::get('/reviews/{codeReview}', [CodeReviewController::class, 'show']);

    // Improvements
    Route::get('/projects/{project}/improvements', [ImprovementController::class, 'index']);
    Route::patch('/improvements/{improvement}', [ImprovementController::class, 'update']);
});
```

**Verificacao:** liste as rotas para confirmar que foram registradas:

```bash
sail artisan route:list --path=api
```

Deve mostrar todas as rotas da API com seus metodos HTTP e controllers.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API routes with Sanctum middleware"
```

---

## Passo 11 — Gerar a documentacao Swagger

```bash
sail artisan l5-swagger:generate
```

**Verificacao:** acesse a documentacao interativa no navegador:

```
http://localhost/api/documentation
```

Voce deve ver a interface do Swagger UI com todos os endpoints organizados por tags: Auth, Projects, Code Reviews e Improvements.

---

## Passo 12 — Testar a API com curl

Vamos testar o fluxo completo via terminal. Certifique-se de ter um usuario no banco (se nao tiver, rode `sail artisan migrate:fresh --seed` e crie um usuario pelo Tinker ou pela interface).

### 12.1 — Obter token de autenticacao

```bash
TOKEN=$(curl -s -X POST http://localhost/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"user@test.com","password":"password","device_name":"curl"}' \
  | jq -r '.token')

echo $TOKEN
```

Deve retornar um token longo. Se retornar `null`, verifique se o usuario existe e a senha esta correta.

### 12.2 — Criar um projeto

```bash
PROJECT=$(curl -s -X POST http://localhost/api/projects \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "API de Pagamentos",
    "language": "php",
    "code_snippet": "class PaymentService {\n    public function charge($user, $amount) {\n        DB::select(\"SELECT * FROM users WHERE id = \" . $user->id);\n        return true;\n    }\n}"
  }' | jq -r '.id')

echo "Projeto criado com ID: $PROJECT"
```

### 12.3 — Iniciar code review

```bash
curl -s -X POST "http://localhost/api/projects/$PROJECT/reviews" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "architecture_strength": "Classe dedicada para pagamentos",
    "architecture_improvement": "Falta interface e dependency injection",
    "performance_strength": "Metodo simples e direto",
    "performance_improvement": "Query dentro do metodo de pagamento",
    "security_strength": "Nenhuma",
    "security_improvement": "SQL Injection via concatenacao"
  }' | jq .
```

Deve retornar status 201 com o review e seus 6 findings.

### 12.4 — Verificar status do review (poll)

```bash
curl -s "http://localhost/api/reviews/1" \
  -H "Authorization: Bearer $TOKEN" | jq '.status.name'
```

Deve retornar `"Pending"` (muda para `"Completed"` quando o Agent termina).

### 12.5 — Listar projetos

```bash
curl -s "http://localhost/api/projects" \
  -H "Authorization: Bearer $TOKEN" | jq '.data[].name'
```

### 12.6 — Listar melhorias de um projeto

```bash
curl -s "http://localhost/api/projects/$PROJECT/improvements" \
  -H "Authorization: Bearer $TOKEN" | jq '.[].title'
```

### Fluxo completo resumido

```
1. POST /api/auth/token             -> Obter Bearer token
2. POST /api/projects               -> Criar projeto com codigo
3. POST /api/projects/{id}/reviews  -> Iniciar review com IA
4. GET  /api/reviews/{id}           -> Poll status (1=Pending, 2=Completed)
5. GET  /api/projects/{id}/improvements -> Ver Kanban de melhorias
6. PATCH /api/improvements/{id}     -> Mover cards no Kanban
```

---

## Passo 13 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: generate Swagger docs and add curl test examples"

# Push da branch
git push -u origin feat/cap13-api

# Criar Pull Request
gh pr create --title "feat: API REST e Swagger" --body "Capitulo 13 - API REST com Sanctum, controllers OpenAPI, ProjectPolicy, rotas e documentacao Swagger"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `config/l5-swagger.php` | Configuracao do Swagger (rota, info, security) |
| `app/Http/Controllers/Controller.php` | Annotation base OA\Info e OA\SecurityScheme |
| `app/Http/Controllers/Api/AuthController.php` | Endpoint POST /api/auth/token (Sanctum) |
| `app/Http/Controllers/Api/ProjectController.php` | CRUD de projetos (index, store, show, destroy) |
| `app/Http/Controllers/Api/CodeReviewController.php` | Criar review (store) e consultar status (show) |
| `app/Http/Controllers/Api/ImprovementController.php` | Listar melhorias (index) e mover no Kanban (update) |
| `app/Http/Resources/Schemas/ProjectSchema.php` | Schema OpenAPI do model Project |
| `app/Policies/ProjectPolicy.php` | Autorizacao: view, update, delete por dono |
| `routes/api.php` | Rotas da API com middleware auth:sanctum |

## Proximo capitulo

No [Capitulo 14 — Testes Automatizados](14-testes.md) vamos criar testes para cada camada do projeto: unitarios, integracao, funcionais, E2E, performance e smoke tests.
