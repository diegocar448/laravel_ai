# Capitulo 13 — API REST e Swagger

> **Este capitulo cobre:** Endpoints REST para integracao externa (CI/CD, GitHub Actions, CLIs) e documentacao interativa com Swagger/OpenAPI.

Neste capitulo vamos criar uma **API REST completa** com autenticacao via Sanctum, documentada com Scramble. Ao final, voce tera endpoints para criar projetos, disparar code reviews e gerenciar melhorias — tudo acessivel por ferramentas externas, pipelines e apps terceiros.

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

## Passo 1 — Instalar o Scramble

O **Scramble** e um pacote que gera documentacao OpenAPI/Swagger automaticamente a partir do codigo — sem anotacoes. Ele le as rotas, validacoes e tipos para gerar a documentacao.

> **Por que Scramble e nao L5-Swagger?** O `darkaonline/l5-swagger` nao tem suporte para Laravel 13. O Scramble e a alternativa moderna, suportada oficialmente pela comunidade Laravel.

```bash
sail composer require dedoc/scramble
sail artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

Edite `config/scramble.php` — ajuste as informacoes da API:

```php
return [
    'api_path' => 'api',
    'api_domain' => null,
    'info' => [
        'title' => 'CodeReview AI API',
        'version' => '1.0.0',
        'description' => 'API REST para code review com IA — Laravel AI SDK + Gemini',
    ],
    'middleware' => [],
    'extensions' => [],
];
```

Adicione a configuracao de seguranca (Bearer token) no `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::configure()
            ->withDocumentTransformer(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                        ->setDescription('Token Sanctum via POST /api/auth/token')
                );
            });
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: install and configure Scramble for API docs"
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

## Passo 3 — Criar o AuthController (token endpoint)

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

class AuthController extends Controller
{
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

> **Scramble auto-documenta este endpoint** lendo as regras de validacao do `$request->validate()` e o tipo de retorno `array`.

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add AuthController with Sanctum token endpoint"
```

---

## Passo 4 — Criar a ProjectPolicy

A `ProjectPolicy` garante que somente o dono do projeto pode visualiza-lo, edita-lo ou deleta-lo. Se ainda nao existe no projeto, crie-a:

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

## Passo 5 — Criar o ProjectController da API

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->projects()
            ->with(['status', 'codeReview.status'])
            ->latest()
            ->paginate(20);
    }

    public function store(Request $request): JsonResponse
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

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project->load([
            'status',
            'codeReview.status',
            'codeReview.findings.pillar',
            'codeReview.findings.type',
            'improvements.type',
            'improvements.step',
        ]));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(null, 204);
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API ProjectController"
```

---

## Passo 6 — Criar o CodeReviewController da API

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeReviewController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
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

        AnalyzeCodeJob::dispatch($codeReview);

        $request->user()->update(['first_review_at' => now()]);

        return response()->json(
            $codeReview->load(['status', 'findings.pillar', 'findings.type']),
            201
        );
    }

    public function show(CodeReview $codeReview): JsonResponse
    {
        $this->authorize('view', $codeReview->project);

        return response()->json($codeReview->load([
            'status',
            'project',
            'findings.pillar',
            'findings.type',
        ]));
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API CodeReviewController"
```

---

## Passo 7 — Criar o ImprovementController da API

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImprovementController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $query = $project->improvements()->with(['type', 'step'])->orderBy('order');

        if ($request->has('step')) {
            $query->where('improvement_step_id', $request->step);
        }

        return response()->json($query->get());
    }

    public function update(Request $request, Improvement $improvement): JsonResponse
    {
        $this->authorize('update', $improvement->project);

        $validated = $request->validate([
            'improvement_step_id' => 'sometimes|integer|in:1,2,3',
            'order' => 'sometimes|integer',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        $improvement->update($validated);

        return response()->json($improvement->load(['type', 'step']));
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add API ImprovementController"
```

---

## Passo 8 — Configurar as rotas da API

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

## Passo 9 — Verificar a documentacao Swagger

Acesse a documentacao interativa no navegador:

```
http://localhost/docs/api
```

Voce deve ver a interface Swagger UI com todos os endpoints organizados automaticamente pelo Scramble.

> **Como o Scramble funciona:** ele le as rotas em `routes/api.php`, os parametros das funcoes, as regras de `$request->validate()` e os tipos de retorno `JsonResponse` para gerar o schema OpenAPI automaticamente — sem anotacoes.

---

## Passo 10 — Testar a API com curl

Vamos testar o fluxo completo via terminal. Certifique-se de ter um usuario no banco (crie pelo formulario da interface ou pelo Tinker).

### 10.1 — Obter token de autenticacao

```bash
TOKEN=$(curl -s -X POST http://localhost/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"seu@email.com","password":"suasenha","device_name":"curl"}' \
  | jq -r '.token')

echo $TOKEN
```

Deve retornar um token longo. Se retornar `null`, verifique se o usuario existe e a senha esta correta.

### 10.2 — Criar um projeto

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

### 10.3 — Iniciar code review

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

### 10.4 — Verificar status do review (poll)

```bash
curl -s "http://localhost/api/reviews/1" \
  -H "Authorization: Bearer $TOKEN" | jq '.status.name'
```

Deve retornar `"Pending"` (muda para `"Completed"` quando o Agent termina).

### 10.5 — Listar projetos

```bash
curl -s "http://localhost/api/projects" \
  -H "Authorization: Bearer $TOKEN" | jq '.data[].name'
```

### 10.6 — Listar melhorias de um projeto

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

## Passo 11 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: generate Swagger docs and add curl test examples"

# Push da branch
git push -u origin feat/cap13-api

# Criar Pull Request
gh pr create --title "feat: API REST e Swagger" --body "Capitulo 13 - API REST com Sanctum, controllers, ProjectPolicy, rotas e documentacao Scramble"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `config/scramble.php` | Configuracao do Scramble (titulo, versao, descricao) |
| `app/Providers/AppServiceProvider.php` | Configura Bearer token na documentacao |
| `app/Http/Controllers/Api/AuthController.php` | Endpoint POST /api/auth/token (Sanctum) |
| `app/Http/Controllers/Api/ProjectController.php` | CRUD de projetos (index, store, show, destroy) |
| `app/Http/Controllers/Api/CodeReviewController.php` | Criar review (store) e consultar status (show) |
| `app/Http/Controllers/Api/ImprovementController.php` | Listar melhorias (index) e mover no Kanban (update) |
| `app/Policies/ProjectPolicy.php` | Autorizacao: view, update, delete por dono |
| `routes/api.php` | Rotas da API com middleware auth:sanctum |

> **Scramble vs L5-Swagger:** O Scramble nao precisa de anotacoes PHP — ele gera a documentacao lendo o codigo. Isso significa menos boilerplate e documentacao sempre atualizada automaticamente.

## Proximo capitulo

No [Capitulo 14 — Testes Automatizados](14-testes.md) vamos criar testes para cada camada do projeto: unitarios, integracao, funcionais, E2E, performance e smoke tests.
