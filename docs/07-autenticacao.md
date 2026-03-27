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

    public function register(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,  // Cast 'hashed' cuida do bcrypt
        ]);

        Auth::login($user);
    }
}
```

**Pontos importantes:**
- `'password' => 'required|string|min:8|confirmed'` — a regra `confirmed` exige que exista um campo `password_confirmation` com o mesmo valor
- `Auth::login($user)` — loga o usuario automaticamente apos o registro
- O cast `'password' => 'hashed'` no Model User (Capitulo 4) aplica bcrypt ao salvar

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
                'email' => 'As credenciais fornecidas nao correspondem aos nossos registros.',
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
use App\Livewire\Forms\RegisterForm;

new class extends Component
{
    public RegisterForm $form;

    public function register(): void
    {
        $this->form->register();
        $this->redirect(route('home'));
    }
}
?>

<x-layouts.guest>
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
                <x-button type="submit" class="w-full">Cadastrar</x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Ja tem conta? <a href="{{ route('login') }}" class="text-indigo-600">Entrar</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
```

**Como funciona:**
- `wire:submit="register"` — ao submeter, chama o metodo `register()` do componente
- O componente delega para `$this->form->register()` (RegisterForm)
- Apos sucesso, redireciona para a home

---

## Passo 4 — Criar a pagina de Login (Volt)

Crie `resources/views/pages/auth/login.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use App\Livewire\Forms\LoginForm;

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

<x-layouts.guest>
    <x-card class="max-w-md mx-auto mt-20">
        <x-card.header>
            <h1 class="text-2xl font-bold">Entrar</h1>
        </x-card.header>
        <x-card.body>
            <form wire:submit="login" class="space-y-4">
                <x-form.input wire:model="form.email" type="email" label="E-mail" />
                <x-form.input wire:model="form.password" type="password" label="Senha" />
                <x-button type="submit" class="w-full">Entrar</x-button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                Nao tem conta? <a href="{{ route('register') }}" class="text-indigo-600">Cadastre-se</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
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

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
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

## Passo 6 — Configurar as rotas de autenticacao

Edite `routes/web.php` para adicionar as rotas de login, registro, logout e admin:

```php
<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Rotas de convidado (guest) — so para usuarios NAO autenticados
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'pages.auth.login')->name('login');
    Volt::route('/register', 'pages.auth.register')->name('register');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Rotas autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Volt::route('/', 'pages.home')->name('home');

    // Painel Admin — so is_admin = true
    Route::middleware('admin')->group(function () {
        Volt::route('/admin/users', 'pages.admin.users')->name('admin.users');
    });
});
```

**Pontos importantes:**
- `middleware('guest')` — redireciona para `/` se ja autenticado (evita acessar login/register logado)
- `middleware('auth')` — redireciona para `/login` se nao autenticado
- `middleware('admin')` — verifica `is_admin` (nosso middleware customizado)
- `Volt::route()` — registra rota Livewire Volt single-file
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

<x-layouts.app>
    <x-section>
        <x-section.header>
            <h1>Usuarios</h1>
        </x-section.header>

        <x-table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Primeiro Review</th>
                    <th>Primeiro Plano</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->first_review_at?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $user->first_plan_at?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $user->is_admin ? 'Sim' : 'Nao' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-section>
</x-layouts.app>
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
