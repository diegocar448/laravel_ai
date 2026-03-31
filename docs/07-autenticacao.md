# Capitulo 7 — Autenticacao

> **Este capitulo cobre: autenticacao manual (sem Breeze/Fortify), Livewire Forms, middleware e painel admin**

Neste capitulo vamos criar o **sistema de autenticacao completo** do projeto: formularios de login e registro com Livewire Forms, middleware customizado para admin, logout e pagina de painel administrativo. Tudo **sem pacotes externos** — apenas Livewire Volt + Auth facade.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap07-auth
cd codereview-ai
```

---

## Visao geral do fluxo

Antes de criar os arquivos, entenda o que vamos construir:

```
Livewire Forms           Paginas Volt              Middleware          Rotas
+-----------------+      +------------------+      +----------+       +-------------+
| RegisterForm    | <--- | register.blade   | <--- | guest    | <---- | /register   |
| LoginForm       | <--- | login.blade      | <--- | guest    | <---- | /login      |
+-----------------+      +------------------+      +----------+       +-------------+
                                                   | auth     | <---- | / (home)    |
                                                   | admin    | <---- | /admin/users|
                                                   +----------+       +-------------+
```

---

## Passo 1 — Criar o RegisterForm

O `RegisterForm` encapsula a logica de validacao e criacao de usuario. O Livewire Form isola essa responsabilidade fora da pagina Volt.

Crie o diretorio:

```bash
mkdir -p app/Livewire/Forms
```

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

**Pontos importantes:**
- `'password' => 'required|string|min:8|confirmed'` — a regra `confirmed` exige que exista um campo `password_confirmation` com o mesmo valor
- `Auth::login($user)` — loga o usuario automaticamente apos o registro
- `session()->regenerate()` — previne session fixation (seguranca)
- O cast `'password' => 'hashed'` no Model User (Capitulo 4) aplica bcrypt ao salvar
- O metodo retorna o `User` criado para flexibilidade de uso

---

## Passo 2 — Criar o LoginForm

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

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }

    public function authenticate(): void
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            throw ValidationException::withMessages([
                'form.email' => 'As credenciais fornecidas nao correspondem aos nossos registros.',
            ]);
        }

        session()->regenerate();
    }
}
```

**Pontos importantes:**
- `Auth::attempt()` — verifica email + password contra o banco e retorna true/false
- `session()->regenerate()` — previne session fixation (seguranca)
- Se falhar, lanca `ValidationException` que o Livewire exibe automaticamente no formulario

```bash
# Commitar os forms
cd ~/laravel_ai
git add .
git commit -m "feat: add LoginForm and RegisterForm livewire forms"
```

---

## Passo 3 — Criar a pagina de Registro (Volt)

Crie o diretorio:

```bash
mkdir -p resources/views/pages/auth
```

Crie `resources/views/pages/auth/register.blade.php`:

```php
<?php

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

<x-card class="max-w-md mx-auto mt-20">
    <x-card.header>
        <h1 class="text-2xl font-bold">Criar conta</h1>
    </x-card.header>
    <x-card.body>
        <form wire:submit="register" class="space-y-4">
            <x-form.input wire:model="form.name" label="Nome" />
            <x-form.input wire:model="form.email" type="email" label="E-mail" />
            <x-form.input wire:model="form.password" type="password" label="Senha" />
            <x-form.input wire:model="form.password_confirmation" type="password" label="Confirmar senha" />
            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove>Cadastrar</span>
                <span wire:loading>Cadastrando...</span>
            </x-button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-500">
            Ja tem conta? <a href="{{ route('login') }}" class="text-indigo-600">Entrar</a>
        </p>
    </x-card.body>
</x-card>
```

**Como funciona:**
- `wire:submit="register"` — ao submeter, chama o metodo `register()` do componente Volt
- O componente delega para `$this->form->store()` (RegisterForm)
- Apos sucesso, redireciona para a home
- Note que o metodo do componente se chama `register()` (acao da pagina) e o metodo do Form se chama `store()` (acao de persistencia) — sao responsabilidades diferentes

---

## Passo 4 — Criar a pagina de Login (Volt)

Crie `resources/views/pages/auth/login.blade.php`:

```php
<?php

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

<x-card class="max-w-md mx-auto mt-20">
    <x-card.header>
        <h1 class="text-2xl font-bold">Entrar</h1>
    </x-card.header>
    <x-card.body>
        <form wire:submit="login" class="space-y-4">
            <x-form.input wire:model="form.email" type="email" label="E-mail" />
            <x-form.input wire:model="form.password" type="password" label="Senha" />
            <x-button type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove>Entrar</span>
                <span wire:loading>Entrando...</span>
            </x-button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-500">
            Nao tem conta? <a href="{{ route('register') }}" class="text-indigo-600">Cadastre-se</a>
        </p>
    </x-card.body>
</x-card>
```

```bash
# Commitar paginas de auth
cd ~/laravel_ai
git add .
git commit -m "feat: add login and register Volt pages"
```

---

## Passo 5 — Criar o Middleware de Admin

O middleware `admin` protege rotas que so administradores podem acessar (ex: painel de usuarios).

Crie o diretorio:

```bash
mkdir -p app/Http/Middleware
```

Crie `app/Http/Middleware/AdminMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            abort(403, 'Acesso negado.');
        }

        return $next($request);
    }
}
```

### 5.1 — Registrar o middleware

Edite `bootstrap/app.php` para registrar o alias `admin`:

```php
<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

**Pontos importantes:**
- `auth()->check()` — verifica se ha usuario logado
- `auth()->user()->is_admin` — campo booleano adicionado no Capitulo 3, com cast no Capitulo 4
- Se nao for admin, retorna **403 Forbidden**

```bash
# Commitar middleware
cd ~/laravel_ai
git add .
git commit -m "feat: add admin middleware with alias registration"
```

---

## Passo 6 — Atualizar as rotas com autenticacao

O `routes/web.php` ja existe do Capitulo 5. Agora vamos **atualizar** o logout para ser mais seguro (invalidar sessao e regenerar CSRF token).

Edite `routes/web.php` — o arquivo completo fica assim:

```php
<?php

use Illuminate\Support\Facades\Route;

// Rotas autenticadas
Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages.home')->name('home');
    Route::livewire('/kanban', 'pages.kanban')->name('kanban');
    Route::livewire('/project/{project}', 'pages.projects.show')->name('project');
    Route::livewire('/review/{codeReview}', 'pages.reviews.show')->name('review');

    // Admin — so is_admin = true
    Route::livewire('/admin/users', 'pages.admin.users')
        ->middleware('admin')
        ->name('admin.users');

    // Logout (mais seguro: invalida sessao + regenera CSRF token)
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

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

**O que mudou em relacao ao Capitulo 5:**
- Logout agora inclui `session()->invalidate()` e `session()->regenerateToken()` — previne session fixation
- Todas as rotas ja existiam, apenas o logout foi reforçado

**Pontos importantes:**
- `middleware('guest')` — redireciona para `/` se ja autenticado (evita acessar login/register logado)
- `middleware('auth')` — redireciona para `/login` se nao autenticado
- `middleware('admin')` — verifica `is_admin` (nosso middleware customizado do Passo 5)
- `Route::livewire()` — registra rota Livewire single-file (sintaxe do Laravel 13)
- O logout invalida a sessao, regenera o CSRF token e redireciona para login

```bash
# Commitar rotas
cd ~/laravel_ai
git add .
git commit -m "feat: add auth routes (login, register, logout, admin)"
```

---

## Passo 7 — Criar a pagina do Painel Admin

A pagina admin lista todos os usuarios do sistema com informacoes sobre suas interacoes com a IA.

Crie o diretorio:

```bash
mkdir -p resources/views/pages/admin
```

Crie `resources/views/pages/admin/users.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public function with(): array
    {
        return [
            'users' => User::latest()->paginate(20),
        ];
    }
}
?>

<x-section title="Usuarios" description="Lista de todos os usuarios do sistema">
        <x-table>
            <x-slot:head>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-mail</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primeiro Review</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primeiro Plano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admin</th>
                </tr>
            </x-slot:head>
            @foreach($users as $user)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->first_review_at?->format('d/m/Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->first_plan_at?->format('d/m/Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $user->is_admin ? 'Sim' : 'Nao' }}</td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
</x-section>
```

**Pontos importantes:**
- `User::latest()->paginate(20)` — lista usuarios ordenados por mais recente, com paginacao
- `first_review_at` e `first_plan_at` — campos adicionados no Capitulo 3, com cast `datetime` no Capitulo 4
- O nullsafe operator `?->format()` evita erro quando o campo e null

```bash
# Commitar painel admin
cd ~/laravel_ai
git add .
git commit -m "feat: add admin users panel page"
```

---

## Passo 8 — Verificar no navegador

Certifique-se de que o Sail esta rodando:

```bash
sail up -d
```

### 8.1 — Testar o registro

1. Acesse `http://localhost/register` no navegador
2. Preencha: Nome, E-mail, Senha (min 8 chars), Confirmar senha
3. Clique em **Cadastrar**
4. Voce deve ser redirecionado para `/` (home)

### 8.2 — Testar o logout

1. Envie um POST para `/logout` (via botao no layout ou via terminal):

```bash
# Alternativa via terminal para testar
sail artisan tinker
```

```php
// No Tinker, verificar que o usuario foi criado
App\Models\User::latest()->first()->email;
// => o email que voce cadastrou

exit
```

### 8.3 — Testar o login

1. Acesse `http://localhost/login`
2. Use o email e senha cadastrados no passo 8.1
3. Clique em **Entrar**
4. Voce deve ser redirecionado para `/` (home)

### 8.4 — Testar o middleware admin

```bash
sail artisan tinker
```

```php
// Promover o usuario a admin
$user = App\Models\User::latest()->first();
$user->update(['is_admin' => true]);
$user->is_admin;
// => true

exit
```

1. Faca login com o usuario promovido
2. Acesse `http://localhost/admin/users`
3. Voce deve ver a tabela de usuarios

### 8.5 — Testar bloqueio de acesso

1. Crie um segundo usuario (sem is_admin) via `/register`
2. Tente acessar `http://localhost/admin/users`
3. Deve retornar **403 Forbidden**

---

## Passo 9 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "docs: add verification steps for auth flow"

# Push da branch
git push -u origin feat/cap07-auth

# Criar Pull Request
gh pr create --title "feat: autenticacao completa" --body "Capitulo 07 - Login, Register, Admin middleware, logout e painel admin"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Fluxo completo

```
1. Dev acessa /
   ↓ middleware 'auth' → nao autenticado
2. Redireciona para /login
   ↓ middleware 'guest' → ok
3. Exibe formulario de login
   ↓ wire:submit="login"
4. LoginForm::authenticate()
   ↓ Auth::attempt() → sucesso
5. Redireciona para / (home)
   ↓ middleware 'auth' → autenticado
6. Exibe dashboard com projetos
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `app/Livewire/Forms/RegisterForm.php` | Validacao e criacao de usuario com auto-login |
| `app/Livewire/Forms/LoginForm.php` | Validacao e autenticacao via Auth::attempt() |
| `resources/views/pages/auth/register.blade.php` | Pagina Volt de registro com 4 campos |
| `resources/views/pages/auth/login.blade.php` | Pagina Volt de login com email e senha |
| `app/Http/Middleware/AdminMiddleware.php` | Bloqueia acesso se is_admin = false (403) |
| `bootstrap/app.php` | Registro do alias 'admin' para o middleware |
| `routes/web.php` | Rotas guest, auth, logout e admin |
| `resources/views/pages/admin/users.blade.php` | Painel admin com lista de usuarios paginada |

## Proximo capitulo

No [Capitulo 8 — Agents e Structured Output](08-ia-code-analysis.md) vamos criar os Agent classes do Laravel AI SDK para analisar codigo.
