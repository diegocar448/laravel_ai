# Capítulo 5 — Rotas e Livewire Volt

## Sistema de rotas

O projeto não usa `routes/api.php` — toda a aplicação é server-rendered com Livewire. As rotas ficam em `routes/web.php`:

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

// Rotas guest (não autenticado)
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages.auth.login')->name('login');
    Route::livewire('/register', 'pages.auth.register')->name('register');
});

// Design System (público)
Route::livewire('/design-system', 'pages.design-system.index')->name('design-system');
```

### Route::livewire()

O método `Route::livewire()` é do **Livewire Volt**. Em vez de apontar para um controller, ele aponta diretamente para um arquivo Blade em `resources/views/pages/`.

```
Route::livewire('/kanban', 'pages.kanban')
                  ↓              ↓
              URL path     resources/views/pages/kanban.blade.php
```

## Livewire Volt — Single-File Components

Livewire Volt permite escrever componentes Livewire inteiros em um único arquivo Blade, com o PHP no topo usando `<?php ... ?>`:

### Estrutura de um componente Volt

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
        $this->form->store();
        // ...
    }

    public function with(): array
    {
        return [
            'projects' => auth()->user()->projects()->latest()->get(),
        ];
    }
}
?>

<x-layouts.app>
    <div>
        <h1>Meus Projetos</h1>

        @foreach($projects as $project)
            <x-card>
                <x-card.header>
                    {{ $project->name }}
                    <span class="text-sm text-gray-500">{{ $project->language }}</span>
                </x-card.header>
                <x-card.body>
                    <code>{{ Str::limit($project->code_snippet, 200) }}</code>
                </x-card.body>
            </x-card>
        @endforeach

        <form wire:submit="save">
            <x-form.input wire:model="form.name" label="Nome do projeto" />
            <x-form.select wire:model="form.language" label="Linguagem" />
            <x-form.textarea wire:model="form.code_snippet" label="Código" />
            <x-button type="submit">Analisar</x-button>
        </form>
    </div>
</x-layouts.app>
```

### Anatomia de um Volt Component

```
<?php
// 1. Imports
use Livewire\Volt\Component;

// 2. Classe anônima que estende Component
new class extends Component
{
    // 3. Propriedades públicas = estado reativo
    public string $name = '';

    // 4. Métodos = ações chamadas do Blade
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

### Diferenças do Livewire tradicional

| Livewire Tradicional | Livewire Volt |
|----------------------|---------------|
| Classe PHP separada + Blade separado | Tudo em um arquivo Blade |
| `app/Livewire/MyComponent.php` | `resources/views/pages/my-component.blade.php` |
| `extends Component` (classe nomeada) | `new class extends Component` (anônima) |
| Método `render()` retorna view | Método `with()` retorna dados |
| Registrado via tag `<livewire:my-component>` | Registrado via `Route::livewire()` |

## Form Objects do Livewire

O projeto usa **Form Objects** para encapsular validação e lógica de formulários:

```php
// app/Livewire/Forms/ProjectForm.php

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

### Form Objects no Volt Component

```php
<?php
new class extends Component
{
    public ProjectForm $form;  // Livewire injeta automaticamente

    public function save(): void
    {
        $project = $this->form->store();  // Valida e salva
        $this->redirect(route('project', $project));
    }
}
?>

<form wire:submit="save">
    <x-form.input wire:model="form.name" label="Nome do projeto" />
    <x-form.select wire:model="form.language" label="Linguagem" :options="[
        'php' => 'PHP',
        'javascript' => 'JavaScript',
        'python' => 'Python',
        'typescript' => 'TypeScript',
    ]" />
    <x-form.textarea wire:model="form.code_snippet" label="Cole seu código aqui" rows="15" />
    <x-form.input wire:model="form.repository_url" label="URL do repositório (opcional)" />
    <x-button type="submit">Enviar para análise</x-button>
</form>
```

Os 4 Form Objects do projeto:

| Form | Função |
|------|--------|
| `ProjectForm` | Criar/editar projetos para review |
| `CodeReviewForm` | Submeter código para análise da IA |
| `LoginForm` | Autenticação |
| `RegisterForm` | Registro de novo usuário |

## Diretivas wire: importantes

```html
<!-- Binding bidirecional -->
<input wire:model="name">

<!-- Binding com debounce (para inputs de busca) -->
<input wire:model.debounce.300ms="search">

<!-- Submit de formulário -->
<form wire:submit="save">

<!-- Click handler -->
<button wire:click="delete({{ $id }})">

<!-- Confirmação -->
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

## Página do Kanban

A página mais complexa é o Kanban (`pages/kanban.blade.php`), que usa:
- Alpine.js para drag-and-drop
- Livewire para persistir mudanças de ordem/coluna
- Componentes `<x-kanban-*>` do Design System

```
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│   TO DO     │ │ IN PROGRESS │ │    DONE     │
│             │ │             │ │             │
│ ┌─────────┐ │ │ ┌─────────┐ │ │ ┌─────────┐ │
│ │ Fix SQL │ │ │ │ Refactor│ │ │ │ Add CSRF│ │
│ │ Inject. │ │ │ │ Service │ │ │ │ tokens  │ │
│ └─────────┘ │ │ └─────────┘ │ │ └─────────┘ │
│ ┌─────────┐ │ │ ┌─────────┐ │ │             │
│ │ Add     │ │ │ │ Cache   │ │ │             │
│ │ indexes │ │ │ │ queries │ │ │             │
│ └─────────┘ │ │ └─────────┘ │ │             │
│             │ │             │ │             │
│    [+ Add]  │ │             │ │             │
└─────────────┘ └─────────────┘ └─────────────┘
```

## Próximo capítulo

No [Capítulo 6 — Design System e Componentes](06-design-system.md) vamos explorar os 20+ componentes Blade reutilizáveis.
