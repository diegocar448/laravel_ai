# Capitulo 5 — Rotas e Livewire Volt

> **Este capitulo cobre: Rotas server-rendered, Livewire Volt single-file components e Form Objects**

Neste capitulo vamos criar o **arquivo de rotas**, as **paginas Livewire Volt** e os **Form Objects** que encapsulam validacao e logica de formularios. Ao final, voce tera toda a estrutura de navegacao e paginas do projeto funcionando.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap05-routes
cd codereview-ai
```

---

## Como funciona o Livewire Volt

Antes de criar os arquivos, entenda o modelo que vamos usar. O projeto **nao usa `routes/api.php`** — toda a aplicacao e server-rendered com Livewire. Em vez de apontar rotas para controllers, usamos `Route::livewire()` que aponta diretamente para arquivos Blade:

```
Route::livewire('/kanban', 'pages.kanban')
                  |              |
              URL path     resources/views/pages/kanban.blade.php
```

### Anatomia de um componente Volt

O Livewire Volt permite escrever componentes Livewire inteiros em um unico arquivo Blade, com o PHP no topo:

```
<?php
// 1. Imports
use Livewire\Volt\Component;

// 2. Classe anonima que estende Component
new class extends Component
{
    // 3. Propriedades publicas = estado reativo
    public string $name = '';

    // 4. Metodos = acoes chamadas do Blade
    public function save(): void { ... }

    // 5. with() = dados passados para a view
    public function with(): array {
        return ['items' => Item::all()];
    }
}
?>

<!-- 6. Template Blade com diretivas wire: -->
<div>
    <input wire:model="name">
    <button wire:click="save">Salvar</button>
</div>
```

### Diferencas do Livewire tradicional

| Livewire Tradicional | Livewire Volt |
|----------------------|---------------|
| Classe PHP separada + Blade separado | Tudo em um arquivo Blade |
| `app/Livewire/MyComponent.php` | `resources/views/pages/my-component.blade.php` |
| `extends Component` (classe nomeada) | `new class extends Component` (anonima) |
| Metodo `render()` retorna view | Metodo `with()` retorna dados |
| Registrado via tag `<livewire:my-component>` | Registrado via `Route::livewire()` |

---

## Passo 1 — Configurar o Volt no AppServiceProvider

O Livewire Volt precisa saber **onde** procurar componentes single-file. Sem essa configuracao, ao acessar qualquer pagina voce vera o erro:

```
Unable to find component: [pages.auth.register]
```

Edite `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Volt::mount([
            resource_path('views'),
        ]);
    }
}
```

**Pontos importantes:**
- `Volt::mount()` — registra os diretorios onde o Volt deve buscar componentes single-file
- `resource_path('views')` — o Volt busca a partir de `resources/views/`. Como as rotas usam `pages.home`, o Volt traduz para `resources/views/pages/home.blade.php`
- **Importante:** se voce usar `resource_path('views/pages')`, o Volt procuraria em `views/pages/pages/home.blade.php` (duplicando "pages") e daria erro `Unable to find component`
- Sem isso, `Route::livewire('/', 'pages.home')` nao encontra o componente

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: configure Volt mount in AppServiceProvider"
```

---

### Como funciona o layout em paginas Volt

Antes de criar as paginas, entenda como o layout e aplicado:

1. **`Route::livewire()` aplica automaticamente o layout `layouts::app`** — voce **nao** precisa (e nem deve) envolver o template com `<x-layouts::app>`. O conteudo do template e injetado no `{{ $slot }}` do layout automaticamente.

2. **Para usar um layout diferente** (como `layouts::guest` para login/registro), adicione o atributo `#[Layout('layouts::guest')]` na classe do componente e importe `use Livewire\Attributes\Layout;`. O template continua sem tag de layout.

3. **NUNCA use `<x-layouts::app>` ou `<x-layouts::guest>` dentro do template Volt** — isso causa **double layout** (o layout e renderizado duas vezes, quebrando o HTML).

**Resumo:**

| Situacao | O que fazer no PHP | O que fazer no template |
|----------|-------------------|------------------------|
| Layout padrao (`layouts::app`) | Nada (automatico) | Apenas o conteudo, sem `<x-layouts::app>` |
| Layout alternativo (`layouts::guest`) | Adicionar `use Livewire\Attributes\Layout;` e `#[Layout('layouts::guest')]` | Apenas o conteudo, sem `<x-layouts::guest>` |

---

## Passo 2 — Criar o arquivo de rotas

Edite `routes/web.php` e substitua todo o conteudo:

```php
<?php

use Illuminate\Support\Facades\Route;

// Rotas autenticadas
Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages.home')->name('home');
    Route::livewire('/kanban', 'pages.kanban')->name('kanban');
    Route::livewire('/project/{project}', 'pages.projects.show')->name('project');
    Route::livewire('/review/{codeReview}', 'pages.reviews.show')->name('review');

    // Admin
    Route::livewire('/admin/users', 'pages.admin.users')
        ->middleware('admin')
        ->name('admin.users');

    // Logout
    Route::post('/logout', function () {
        auth()->logout();
        return redirect('/login');
    })->name('logout');
});

// Rotas guest (nao autenticado)
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages.auth.login')->name('login');
    Route::livewire('/register', 'pages.auth.register')->name('register');
});

// Design System (publico)
Route::livewire('/design-system', 'pages.design-system.index')->name('design-system');
```

**Mapa de rotas:**

| URL | Blade File | Nome | Middleware |
|-----|-----------|------|-----------|
| `/` | `pages/home.blade.php` | `home` | auth |
| `/kanban` | `pages/kanban.blade.php` | `kanban` | auth |
| `/project/{project}` | `pages/projects/show.blade.php` | `project` | auth |
| `/review/{codeReview}` | `pages/reviews/show.blade.php` | `review` | auth |
| `/admin/users` | `pages/admin/users.blade.php` | `admin.users` | auth, admin |
| `/login` | `pages/auth/login.blade.php` | `login` | guest |
| `/register` | `pages/auth/register.blade.php` | `register` | guest |
| `/design-system` | `pages/design-system/index.blade.php` | `design-system` | nenhum |

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add routes/web.php with Livewire Volt routes"
```

---

## Passo 3 — Criar os diretorios das paginas

Antes de criar os arquivos Blade, precisamos dos diretorios:

```bash
mkdir -p resources/views/pages/projects
mkdir -p resources/views/pages/reviews
mkdir -p resources/views/pages/auth
mkdir -p resources/views/pages/admin
mkdir -p resources/views/pages/design-system
```

---

## Passo 4 — Criar a pagina Home

A pagina Home lista os projetos do usuario e tem o formulario para criar novos projetos.

Crie `resources/views/pages/home.blade.php`:

```php
<?php
// resources/views/pages/home.blade.php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Livewire\Forms\ProjectForm;

new class extends Component
{
    public ProjectForm $form;

    public function save(): void
    {
        $project = $this->form->store();
        $this->redirect(route('project', $project));
    }

    public function with(): array
    {
        return [
            'projects' => auth()->user()->projects()->latest()->get(),
        ];
    }
}
?>

<div>
    <h1>Meus Projetos</h1>

    @forelse($projects as $project)
        <a href="{{ route('project', $project) }}" class="block">
            <x-card class="hover:border-indigo-500 transition-colors cursor-pointer">
                <x-card.header>
                    <div class="flex items-center justify-between">
                        <span>{{ $project->name }}</span>
                        <div class="flex gap-2 text-sm text-gray-500">
                            <span>{{ strtoupper($project->language) }}</span>
                            <span>·</span>
                            <span>{{ $project->status->name ?? 'Active' }}</span>
                        </div>
                    </div>
                </x-card.header>
                <x-card.body>
                    <code class="text-xs">{{ Str::limit($project->code_snippet, 120) }}</code>
                </x-card.body>
            </x-card>
        </a>
    @empty
        <p class="text-gray-500 text-sm">Nenhum projeto ainda. Crie o primeiro abaixo.</p>
    @endforelse

    <hr class="my-8 border-gray-700">
    <h2 class="text-lg font-semibold mb-4">Novo Projeto</h2>

    <form wire:submit="save">
        <x-form.input wire:model="form.name" label="Nome do projeto" />
        <x-form.select wire:model="form.language" label="Linguagem" :options="[
            'php' => 'PHP',
            'javascript' => 'JavaScript',
            'python' => 'Python',
            'typescript' => 'TypeScript',
            'go' => 'Go',
            'rust' => 'Rust',
            'java' => 'Java',
        ]" />
        <x-form.textarea wire:model="form.code_snippet" label="Cole seu codigo aqui" rows="15" />
        <x-form.input wire:model="form.repository_url" label="URL do repositorio (opcional)" />
        <x-button type="submit">Enviar para analise</x-button>
    </form>
</div>
```

**O que esta acontecendo:**
- `ProjectForm $form` — Livewire injeta o Form Object automaticamente
- `save()` — valida via Form Object e redireciona para a pagina do projeto
- `with()` — passa os projetos do usuario para o template
- `@forelse` — exibe mensagem quando nao ha projetos
- Cards clicaveis com `<a href="{{ route('project', $project) }}">` — navegam para a pagina do projeto
- `wire:model="form.name"` — binding bidirecional com propriedades do Form Object

---

## Passo 5 — Criar a pagina Kanban

A pagina mais complexa do projeto. Usa Alpine.js para drag-and-drop e Livewire para persistir mudancas.

Crie `resources/views/pages/kanban.blade.php`:

```php
<?php
// resources/views/pages/kanban.blade.php

use Livewire\Volt\Component;
use App\Models\Improvement;
use App\Enums\ImprovementStepEnum;

new class extends Component
{
    public function updateStep(int $improvementId, int $stepId): void
    {
        $improvement = Improvement::findOrFail($improvementId);

        $this->authorize('update', $improvement);

        $improvement->update([
            'improvement_step_id' => $stepId,
            'completed_at' => $stepId === ImprovementStepEnum::Done->value ? now() : null,
        ]);
    }

    public function updateOrder(array $items): void
    {
        foreach ($items as $item) {
            Improvement::where('id', $item['id'])->update(['order' => $item['order']]);
        }
    }

    public function with(): array
    {
        $improvements = auth()->user()->projects()
            ->with('improvements.type', 'improvements.step')
            ->get()
            ->pluck('improvements')
            ->flatten();

        return [
            'todo' => $improvements->where('improvement_step_id', ImprovementStepEnum::ToDo->value)->sortBy('order'),
            'inProgress' => $improvements->where('improvement_step_id', ImprovementStepEnum::InProgress->value)->sortBy('order'),
            'done' => $improvements->where('improvement_step_id', ImprovementStepEnum::Done->value)->sortBy('order'),
        ];
    }
}
?>

<div>
    <h1>Kanban de Melhorias</h1>

    <div class="grid grid-cols-3 gap-4">
        {{-- Coluna ToDo --}}
        <div>
            <h2>ToDo</h2>
            @foreach($todo as $item)
                <x-card>
                    <x-card.header>{{ $item->title }}</x-card.header>
                    <x-card.body>{{ $item->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>

        {{-- Coluna InProgress --}}
        <div>
            <h2>In Progress</h2>
            @foreach($inProgress as $item)
                <x-card>
                    <x-card.header>{{ $item->title }}</x-card.header>
                    <x-card.body>{{ $item->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>

        {{-- Coluna Done --}}
        <div>
            <h2>Done</h2>
            @foreach($done as $item)
                <x-card>
                    <x-card.header>{{ $item->title }}</x-card.header>
                    <x-card.body>{{ $item->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>
    </div>
</div>
```

**Estrutura visual do Kanban:**

```
+--------------+ +--------------+ +--------------+
|   TO DO      | | IN PROGRESS  | |    DONE      |
|              | |              | |              |
| +----------+ | | +----------+ | | +----------+ |
| | Fix SQL  | | | | Refactor | | | | Add CSRF | |
| | Inject.  | | | | Service  | | | | tokens   | |
| +----------+ | | +----------+ | | +----------+ |
| +----------+ | | +----------+ | |              |
| | Add      | | | | Cache    | | |              |
| | indexes  | | | | queries  | | |              |
| +----------+ | | +----------+ | |              |
|              | |              | |              |
|    [+ Add]   | |              | |              |
+--------------+ +--------------+ +--------------+
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add home and kanban Volt pages"
```

---

## Passo 6 — Criar a pagina Project Show

Crie `resources/views/pages/projects/show.blade.php`:

```php
<?php
// resources/views/pages/projects/show.blade.php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Livewire\Forms\CodeReviewForm;

new class extends Component
{
    public Project $project;
    public CodeReviewForm $form;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function requestReview(): void
    {
        $this->form->store($this->project);
        $this->redirect(route('project', $this->project));
    }

    public function with(): array
    {
        return [
            'project' => $this->project->load('codeReview.findings', 'improvements', 'status'),
        ];
    }
}
?>

<div>
    <h1>{{ $project->name }}</h1>
    <span class="text-sm text-gray-500">{{ $project->language }}</span>
    <span class="text-sm">{{ $project->status->name }}</span>

    <div>
        <h2>Codigo</h2>
        <pre><code>{{ $project->code_snippet }}</code></pre>
    </div>

    @if($project->codeReview)
        <div>
            <h2>Resultado da Analise</h2>
            <p>{{ $project->codeReview->summary }}</p>

            @foreach($project->codeReview->findings as $finding)
                <x-card>
                    <x-card.header>
                        {{ $finding->pillar->name }} — {{ $finding->type->name }}
                        <span class="text-sm">{{ $finding->severity }}</span>
                    </x-card.header>
                    <x-card.body>{{ $finding->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>
    @else
        <form wire:submit="requestReview">
            <x-button type="submit">
                <span wire:loading.remove>Solicitar Analise IA</span>
                <span wire:loading>Analisando...</span>
            </x-button>
        </form>
    @endif

    @if($project->improvements->count())
        <div>
            <h2>Melhorias</h2>
            @foreach($project->improvements as $improvement)
                <x-card>
                    <x-card.header>{{ $improvement->title }}</x-card.header>
                    <x-card.body>{{ $improvement->description }}</x-card.body>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
```

---

## Passo 7 — Criar a pagina Review Show

Crie `resources/views/pages/reviews/show.blade.php`:

```php
<?php
// resources/views/pages/reviews/show.blade.php

use Livewire\Volt\Component;
use App\Models\CodeReview;

new class extends Component
{
    public CodeReview $codeReview;

    public function mount(CodeReview $codeReview): void
    {
        $this->authorize('view', $codeReview);
        $this->codeReview = $codeReview;
    }

    public function with(): array
    {
        return [
            'review' => $this->codeReview->load('project', 'status', 'findings.type', 'findings.pillar'),
        ];
    }
}
?>

<div>
    <h1>Review: {{ $review->project->name }}</h1>
    <span class="text-sm">{{ $review->status->name }}</span>

    @if($review->summary)
        <div>
            <h2>Resumo</h2>
            <p>{{ $review->summary }}</p>
        </div>
    @endif

    <div>
        <h2>Findings</h2>
        @foreach($review->findings as $finding)
            <x-card>
                <x-card.header>
                    {{ $finding->pillar->name }} — {{ $finding->type->name }}
                    <span class="text-sm">{{ $finding->severity }}</span>
                </x-card.header>
                <x-card.body>{{ $finding->description }}</x-card.body>
            </x-card>
        @endforeach
    </div>

    <div wire:poll.5s>
        @if($review->status->name === 'Pending')
            <p>Analise em andamento... atualizando automaticamente.</p>
        @endif
    </div>
</div>
```

**Diretivas wire: usadas neste capitulo:**

```html
<!-- Binding bidirecional -->
<input wire:model="name">

<!-- Binding com debounce (para inputs de busca) -->
<input wire:model.debounce.300ms="search">

<!-- Submit de formulario -->
<form wire:submit="save">

<!-- Click handler -->
<button wire:click="delete({{ $id }})">

<!-- Confirmacao -->
<button wire:click="delete" wire:confirm="Tem certeza?">

<!-- Loading state -->
<button wire:click="save">
    <span wire:loading.remove>Analisar</span>
    <span wire:loading>Analisando...</span>
</button>

<!-- Polling (atualizar a cada 5 segundos) -->
<div wire:poll.5s>
    Status: {{ $status }}
</div>
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add project show and review show Volt pages"
```

---

## Passo 8 — Criar o diretorio dos Form Objects

```bash
mkdir -p app/Livewire/Forms
```

---

## Passo 9 — Criar o ProjectForm

Crie `app/Livewire/Forms/ProjectForm.php`:

```php
<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Project;
use App\Enums\ProjectStatusEnum;

class ProjectForm extends Form
{
    public string $name = '';
    public string $language = 'php';
    public string $code_snippet = '';
    public ?string $repository_url = null;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'language' => 'required|string|in:php,javascript,python,go,rust,java,typescript',
            'code_snippet' => 'required|string|min:50',
            'repository_url' => 'nullable|url',
        ];
    }

    public function store(): Project
    {
        $this->validate();

        return auth()->user()->projects()->create([
            'name' => $this->name,
            'language' => $this->language,
            'code_snippet' => $this->code_snippet,
            'repository_url' => $this->repository_url,
            'project_status_id' => ProjectStatusEnum::Active->value,
        ]);
    }
}
```

**Como o Form Object se conecta ao Volt Component:**

```php
// No Volt Component (home.blade.php):
public ProjectForm $form;  // Livewire injeta automaticamente

public function save(): void
{
    $project = $this->form->store();  // Valida e salva
    $this->redirect(route('project', $project));
}

// No template:
<form wire:submit="save">
    <x-form.input wire:model="form.name" />   // Acessa $form->name
</form>
```

---

## Passo 10 — Criar o LoginForm

Crie `app/Livewire/Forms/LoginForm.php`:

```php
<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginForm extends Form
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ];
    }

    public function authenticate(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        session()->regenerate();
    }
}
```

---

## Passo 11 — Criar o RegisterForm

Crie `app/Livewire/Forms/RegisterForm.php`:

```php
<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegisterForm extends Form
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function store(): User
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        Auth::login($user);
        session()->regenerate();

        return $user;
    }
}
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add LoginForm and RegisterForm objects"
```

---

## Passo 12 — Criar o CodeReviewForm

Crie `app/Livewire/Forms/CodeReviewForm.php`:

```php
<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Project;
use App\Models\CodeReview;
use App\Enums\ReviewStatusEnum;

class CodeReviewForm extends Form
{
    public function store(Project $project): CodeReview
    {
        return $project->codeReview()->create([
            'review_status_id' => ReviewStatusEnum::Pending->value,
            'summary' => null,
        ]);
    }
}
```

**Os 4 Form Objects do projeto:**

| Form | Funcao |
|------|--------|
| `ProjectForm` | Criar/editar projetos para review |
| `LoginForm` | Autenticacao |
| `RegisterForm` | Registro de novo usuario |
| `CodeReviewForm` | Submeter codigo para analise da IA |

---

## Passo 13 — Criar as paginas de autenticacao

### 13.1 — Login

Crie `resources/views/pages/auth/login.blade.php`:

```php
<?php
// resources/views/pages/auth/login.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\LoginForm;

#[Layout('layouts::guest')]
new class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->authenticate();
        $this->redirect(route('home'));
    }
}
?>

<div>
    <h1>Login</h1>

    <form wire:submit="login">
        <x-form.input wire:model="form.email" label="Email" type="email" />
        <x-form.input wire:model="form.password" label="Senha" type="password" />

        <label>
            <input type="checkbox" wire:model="form.remember">
            Lembrar de mim
        </label>

        <x-button type="submit">Entrar</x-button>
    </form>

    <p>Nao tem conta? <a href="{{ route('register') }}">Registre-se</a></p>
</div>
```

### 13.2 — Register

Crie `resources/views/pages/auth/register.blade.php`:

```php
<?php
// resources/views/pages/auth/register.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\RegisterForm;

#[Layout('layouts::guest')]
new class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->form->store();
        $this->redirect(route('home'));
    }
}
?>

<div>
    <h1>Criar Conta</h1>

    <form wire:submit="register">
        <x-form.input wire:model="form.name" label="Nome" />
        <x-form.input wire:model="form.email" label="Email" type="email" />
        <x-form.input wire:model="form.password" label="Senha" type="password" />
        <x-form.input wire:model="form.password_confirmation" label="Confirmar Senha" type="password" />

        <x-button type="submit">Registrar</x-button>
    </form>

    <p>Ja tem conta? <a href="{{ route('login') }}">Faca login</a></p>
</div>
```

```bash
# Commitar
cd ~/laravel_ai
git add .
git commit -m "feat: add CodeReviewForm and auth Volt pages (login, register)"
```

---

## Passo 14 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: finalize routes and Livewire Volt pages"

# Push da branch
git push -u origin feat/cap05-routes

# Criar Pull Request
gh pr create --title "feat: rotas e Livewire Volt pages" --body "Capitulo 05 - Routes, Livewire Volt pages (home, kanban, project show, review show, auth), Form Objects (ProjectForm, LoginForm, RegisterForm, CodeReviewForm)"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `routes/web.php` | Rotas com `Route::livewire()` para todas as paginas |
| `resources/views/pages/home.blade.php` | Listagem de projetos + formulario de criacao |
| `resources/views/pages/kanban.blade.php` | Kanban de melhorias (ToDo, InProgress, Done) |
| `resources/views/pages/projects/show.blade.php` | Detalhes do projeto + resultado da analise IA |
| `resources/views/pages/reviews/show.blade.php` | Detalhes do review com polling automatico |
| `resources/views/pages/auth/login.blade.php` | Pagina de login |
| `resources/views/pages/auth/register.blade.php` | Pagina de registro |
| `app/Livewire/Forms/ProjectForm.php` | Validacao e criacao de projetos |
| `app/Livewire/Forms/LoginForm.php` | Validacao e autenticacao |
| `app/Livewire/Forms/RegisterForm.php` | Validacao e registro de usuarios |
| `app/Livewire/Forms/CodeReviewForm.php` | Criacao de code reviews (Pending) |

## Proximo capitulo

No [Capitulo 6 — Design System e Componentes](06-design-system.md) vamos explorar os 20+ componentes Blade reutilizaveis.
