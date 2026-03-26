# Capítulo 7 — Autenticação

## Visão geral

O projeto implementa autenticação **sem pacotes externos** (sem Breeze, Fortify ou Jetstream). Usa Livewire Forms para login e registro, com middleware para proteger rotas.

## Páginas de autenticação

### Registro

```php
<?php
// resources/views/pages/auth/register.blade.php

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
                Já tem conta? <a href="{{ route('login') }}" class="text-indigo-600">Entrar</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
```

### RegisterForm

```php
// app/Livewire/Forms/RegisterForm.php

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

### Login

```php
<?php
// resources/views/pages/auth/login.blade.php

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
                Não tem conta? <a href="{{ route('register') }}" class="text-indigo-600">Cadastre-se</a>
            </p>
        </x-card.body>
    </x-card>
</x-layouts.guest>
```

### LoginForm

```php
// app/Livewire/Forms/LoginForm.php

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
                'email' => 'As credenciais fornecidas não correspondem aos nossos registros.',
            ]);
        }

        session()->regenerate();
    }
}
```

## Middleware

### Middleware de autenticação

O Laravel já fornece o middleware `auth` que redireciona para `/login` se não autenticado:

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages.home');
    // ...
});
```

### Middleware de admin

O projeto usa um middleware customizado para proteger o painel admin:

```php
Route::livewire('/admin/users', 'pages.admin.users')
    ->middleware('admin');
```

O middleware `admin` verifica se `auth()->user()->is_admin` é `true`. Caso contrário, retorna 403.

### Middleware guest

Rotas de login e registro usam `guest` — redireciona para `/` se já autenticado:

```php
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages.auth.login');
    Route::livewire('/register', 'pages.auth.register');
});
```

## Logout

```php
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');
```

## Painel Admin

A página admin lista usuários e suas interações com a IA:

```php
<?php
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
            <h1>Usuários</h1>
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
                        <td>{{ $user->is_admin ? 'Sim' : 'Não' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-section>
</x-layouts.app>
```

## Fluxo completo

```
1. Dev acessa /
   ↓ middleware 'auth' → não autenticado
2. Redireciona para /login
   ↓ middleware 'guest' → ok
3. Exibe formulário de login
   ↓ wire:submit="login"
4. LoginForm::authenticate()
   ↓ Auth::attempt() → sucesso
5. Redireciona para / (home)
   ↓ middleware 'auth' → autenticado
6. Exibe dashboard com projetos
```

## Próximo capítulo

No [Capitulo 8 — Agents e Structured Output](08-ia-code-analysis.md) vamos criar os Agent classes do Laravel AI SDK para analisar codigo.
