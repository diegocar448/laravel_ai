# Capitulo 6 — Design System e Componentes

> **Este capitulo cobre: componentes Blade reutilizaveis, dark mode e Tailwind CSS 4**

Neste capitulo vamos criar o **Design System completo** do projeto: 20+ componentes Blade com suporte a dark mode, layouts (app e guest), componentes de formulario, componentes UI, componentes do Kanban e uma pagina showcase acessivel em `/design-system`.

## Antes de comecar

> **Lembrete:** Se `sail` retornar "command not found", crie o alias (feito no Capitulo 2):
> ```bash
> alias sail='./vendor/bin/sail'
> ```

Crie a branch para este capitulo:

```bash
cd ~/laravel_ai
git checkout main && git pull
git checkout -b feat/cap06-design-system
cd codereview-ai
```

---

## Passo 1 — Configurar Tailwind CSS 4 com Vite

O projeto usa **Tailwind CSS 4** com o plugin Vite. Vamos garantir que a configuracao esta correta.

### 1.1 — vite.config.js

Edite `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

### 1.2 — resources/css/app.css

Edite `resources/css/app.css`:

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif,
        'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}
```

**Pontos importantes:**
- No Tailwind 4, a configuracao e feita **via CSS** em vez de `tailwind.config.js`
- `@import 'tailwindcss'` substitui os antigos `@tailwind base/components/utilities`
- `@source` indica onde o Tailwind deve procurar classes para incluir no build
- `@theme` permite customizar design tokens diretamente no CSS

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: configure Tailwind CSS 4 with Vite"
```

---

## Passo 2 — Criar os Layouts

Os layouts sao a base visual de todas as paginas. Vamos criar dois: `app` (para usuarios autenticados) e `guest` (para login/registro).

### 2.1 — Criar diretorios

```bash
mkdir -p resources/views/layouts
```

> **Como funciona:** O Livewire 4.2 registra automaticamente o namespace `layouts` apontando para `resources/views/layouts/`. Assim, `<x-layouts::guest>` resolve para `resources/views/layouts/guest.blade.php`. Alem disso, o layout padrao do Livewire para page components e `layouts::app`, que tambem resolve para `resources/views/layouts/app.blade.php`.

### 2.2 — Layout App (usuarios autenticados)

Crie `resources/views/layouts/app.blade.php`:

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'CodeReview AI') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans antialiased">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 hidden lg:block">
            <div class="p-6">
                <h1 class="text-xl font-bold text-indigo-600 dark:text-indigo-400">CodeReview AI</h1>
            </div>
            <nav class="mt-4 px-4 space-y-1">
                {{ $sidebar ?? '' }}
            </nav>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Top bar -->
            <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    {{ $header ?? '' }}
                </div>
                <div class="flex items-center gap-4">
                    <x-theme-toggle />
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
```

### 2.3 — Layout Guest (login/registro)

Crie `resources/views/layouts/guest.blade.php`:

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'CodeReview AI') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <!-- Logo -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">CodeReview AI</h1>
            <p class="text-gray-500 dark:text-gray-400 text-center mt-1">Analise inteligente de codigo</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-8">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
```

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add app and guest layouts with dark mode"
```

---

## Passo 3 — Criar os componentes de formulario

Os componentes de formulario sao usados na criacao de projetos e na submissao de codigo para review.

### 3.1 — Criar diretorio

```bash
mkdir -p resources/views/components/form
```

### 3.2 — Form Input

Crie `resources/views/components/form/input.blade.php`:

```html
@props([
    'label' => null,
    'name' => '',
    'type' => 'text',
    'placeholder' => '',
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-lg border border-gray-300 dark:border-gray-600
                        bg-white dark:bg-gray-700
                        text-gray-900 dark:text-gray-100
                        px-4 py-2.5
                        placeholder-gray-400 dark:placeholder-gray-500
                        focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                        dark:focus:ring-indigo-400 dark:focus:border-indigo-400
                        transition-colors'
        ]) }}
    />

    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

### 3.3 — Form Textarea

Crie `resources/views/components/form/textarea.blade.php`:

```html
@props([
    'label' => null,
    'name' => '',
    'placeholder' => '',
    'rows' => 4,
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-lg border border-gray-300 dark:border-gray-600
                        bg-white dark:bg-gray-700
                        text-gray-900 dark:text-gray-100
                        px-4 py-2.5
                        placeholder-gray-400 dark:placeholder-gray-500
                        focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                        dark:focus:ring-indigo-400 dark:focus:border-indigo-400
                        transition-colors resize-y'
        ]) }}
    >{{ $slot }}</textarea>

    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

### 3.4 — Form Select

Crie `resources/views/components/form/select.blade.php`:

```html
@props([
    'label' => null,
    'name' => '',
    'options' => [],
    'placeholder' => 'Selecione...',
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-lg border border-gray-300 dark:border-gray-600
                        bg-white dark:bg-gray-700
                        text-gray-900 dark:text-gray-100
                        px-4 py-2.5
                        focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                        dark:focus:ring-indigo-400 dark:focus:border-indigo-400
                        transition-colors'
        ]) }}
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>

    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

### 3.5 — Form Code Editor

Crie `resources/views/components/form/code-editor.blade.php`:

```html
@props([
    'label' => null,
    'name' => '',
    'language' => 'php',
    'placeholder' => 'Cole seu codigo aqui...',
    'rows' => 12,
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $label }}
            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400 font-normal">
                ({{ strtoupper($language) }})
            </span>
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-lg border border-gray-300 dark:border-gray-600
                        bg-gray-50 dark:bg-gray-950
                        text-gray-900 dark:text-gray-100
                        px-4 py-3
                        font-mono text-sm leading-relaxed
                        placeholder-gray-400 dark:placeholder-gray-500
                        focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                        dark:focus:ring-indigo-400 dark:focus:border-indigo-400
                        transition-colors resize-y'
        ]) }}
    >{{ $slot }}</textarea>

    @error($name)
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
```

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add form components (input, textarea, select, code-editor)"
```

---

## Passo 4 — Criar os componentes UI base

Esses sao os componentes visuais usados em toda a aplicacao.

### 4.1 — Button

Crie `resources/views/components/button.blade.php`:

```html
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $classes = match($variant) {
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600',
        'secondary' => 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600',
        'ghost' => 'bg-transparent text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800',
        default => 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600',
    };

    $sizes = match($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
        default => 'px-4 py-2 text-base',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "$classes $sizes rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"]) }}
>
    {{ $slot }}
</button>
```

### 4.2 — Card (com sub-componentes)

Crie o diretorio:

```bash
mkdir -p resources/views/components/card
```

Crie `resources/views/components/card/index.blade.php`:

```html
@props([])

<div {{ $attributes->merge([
    'class' => 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden'
]) }}>
    {{ $slot }}
</div>
```

Crie `resources/views/components/card/header.blade.php`:

```html
@props([])

<div {{ $attributes->merge([
    'class' => 'px-6 py-4 border-b border-gray-200 dark:border-gray-700'
]) }}>
    {{ $slot }}
</div>
```

Crie `resources/views/components/card/body.blade.php`:

```html
@props([])

<div {{ $attributes->merge(['class' => 'px-6 py-4']) }}>
    {{ $slot }}
</div>
```

### 4.3 — Alert

Crie `resources/views/components/alert.blade.php`:

```html
@props([
    'type' => 'info',
    'dismissible' => false,
])

@php
    $classes = match($type) {
        'success' => 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300',
        'warning' => 'bg-yellow-50 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-300',
        'danger' => 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300',
        'info' => 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
        default => 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
    };

    $icons = match($type) {
        'success' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'warning' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        'danger' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
        'info' => '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => "$classes rounded-lg border px-4 py-3 flex items-start gap-3"]) }}
     @if($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif>
    <div class="flex-shrink-0 mt-0.5">
        {!! $icons !!}
    </div>
    <div class="flex-1 text-sm">
        {{ $slot }}
    </div>
    @if($dismissible)
        <button @click="show = false" class="flex-shrink-0 ml-2 opacity-70 hover:opacity-100 transition-opacity">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    @endif
</div>
```

### 4.4 — Modal

Crie `resources/views/components/modal.blade.php`:

```html
@props([
    'name' => '',
    'maxWidth' => 'lg',
])

@php
    $maxWidthClass = match($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-lg',
    };
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
>
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="show = false"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="{{ $maxWidthClass }} w-full bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            {{ $slot }}
        </div>
    </div>
</div>
```

### 4.5 — Severity Badge

Crie `resources/views/components/severity-badge.blade.php`:

```html
@props(['severity' => 'medium'])

@php
    $classes = match($severity) {
        'low' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        'critical' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "$classes px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
]) }}>
    {{ $severity }}
</span>
```

### 4.6 — Code Block

Crie `resources/views/components/code-block.blade.php`:

```html
@props([
    'language' => 'php',
    'filename' => null,
])

<div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
    @if($filename)
        <div class="bg-gray-100 dark:bg-gray-900 px-4 py-2 text-sm text-gray-600 dark:text-gray-400
                    border-b border-gray-200 dark:border-gray-700 font-mono flex items-center justify-between">
            <span>{{ $filename }}</span>
            <span class="text-xs uppercase text-gray-400 dark:text-gray-500">{{ $language }}</span>
        </div>
    @endif
    <pre class="bg-gray-50 dark:bg-gray-950 p-4 overflow-x-auto">
        <code class="language-{{ $language }} text-sm font-mono text-gray-800 dark:text-gray-200">{{ $slot }}</code>
    </pre>
</div>
```

### 4.7 — Table

Crie `resources/views/components/table.blade.php`:

```html
@props([])

<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200 dark:divide-gray-700']) }}>
        @if(isset($head))
            <thead class="bg-gray-50 dark:bg-gray-800">
                {{ $head }}
            </thead>
        @endif
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            {{ $slot }}
        </tbody>
    </table>
</div>
```

### 4.8 — Section

Crie `resources/views/components/section.blade.php`:

```html
@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-8']) }}>
    @if($title)
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
            @if($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
```

### 4.9 — Theme Toggle (dark/light mode)

Crie `resources/views/components/theme-toggle.blade.php`:

```html
<button
    x-data="{
        dark: localStorage.getItem('theme') === 'dark' ||
              (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
        toggle() {
            this.dark = !this.dark;
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
        }
    }"
    x-init="document.documentElement.classList.toggle('dark', dark)"
    @click="toggle()"
    class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors"
    title="Alternar tema"
>
    <!-- Sun icon (shown in dark mode) -->
    <svg x-show="dark" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
    </svg>
    <!-- Moon icon (shown in light mode) -->
    <svg x-show="!dark" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
    </svg>
</button>
```

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add UI components (button, card, alert, modal, severity-badge, code-block, table, section, theme-toggle)"
```

---

## Passo 5 — Criar os componentes do Kanban

Os componentes do Kanban sao usados na pagina de melhorias (Capitulo 5), onde o usuario gerencia os cards de improvement.

### 5.1 — Kanban Card

Crie `resources/views/components/kanban-card.blade.php`:

```html
@props(['improvement'])

<div
    draggable="true"
    class="bg-white dark:bg-gray-700 rounded-lg p-4 shadow-sm border
           border-gray-200 dark:border-gray-600 cursor-grab active:cursor-grabbing
           hover:shadow-md transition-shadow"
>
    <div class="flex items-center justify-between mb-2">
        <x-severity-badge :severity="match($improvement->priority) {
            2 => 'critical', 1 => 'high', default => 'medium'
        }" />
        <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ $improvement->type->name ?? 'N/A' }}
        </span>
    </div>

    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
        {{ $improvement->title }}
    </p>

    @if($improvement->description)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
            {{ $improvement->description }}
        </p>
    @endif

    @if($improvement->file_path)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono truncate">
            {{ $improvement->file_path }}
        </p>
    @endif
</div>
```

### 5.2 — Kanban Column Header

Crie `resources/views/components/kanban-column-header.blade.php`:

```html
@props([
    'title' => '',
    'count' => 0,
    'color' => 'gray',
])

@php
    $colorClasses = match($color) {
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };
@endphp

<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        <span class="{{ $colorClasses }} px-2 py-0.5 rounded-full text-xs font-medium">
            {{ $count }}
        </span>
    </div>
    {{ $slot }}
</div>
```

### 5.3 — Kanban Empty State

Crie `resources/views/components/kanban-empty-state.blade.php`:

```html
@props([
    'message' => 'Nenhum item nesta coluna',
])

<div class="flex flex-col items-center justify-center py-8 px-4
            border-2 border-dashed border-gray-200 dark:border-gray-700
            rounded-lg text-center">
    <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
    </svg>
    <p class="text-sm text-gray-400 dark:text-gray-500">{{ $message }}</p>
</div>
```

### 5.4 — Kanban Add Button

Crie `resources/views/components/kanban-add-button.blade.php`:

```html
@props([
    'label' => 'Adicionar item',
])

<button
    {{ $attributes->merge([
        'class' => 'w-full py-2 px-4 rounded-lg border-2 border-dashed
                    border-gray-300 dark:border-gray-600
                    text-gray-500 dark:text-gray-400
                    hover:border-indigo-400 hover:text-indigo-500
                    dark:hover:border-indigo-500 dark:hover:text-indigo-400
                    transition-colors text-sm font-medium
                    flex items-center justify-center gap-2'
    ]) }}
>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    {{ $label }}
</button>
```

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add Kanban components (card, column-header, empty-state, add-button)"
```

---

## Passo 6 — Criar a pagina showcase do Design System

A pagina showcase permite visualizar todos os componentes em um unico lugar, acessivel em `/design-system`.

### 6.1 — Criar a rota

Edite `routes/web.php` e adicione a rota (dentro do grupo existente ou apos as rotas ja definidas):

```php
Route::get('/design-system', function () {
    return view('pages.design-system');
})->name('design-system');
```

### 6.2 — Criar o diretorio e a pagina

```bash
mkdir -p resources/views/pages
```

Crie `resources/views/pages/design-system.blade.php`:

```html
<x-layouts::guest>
    <x-slot:title>Design System — CodeReview AI</x-slot:title>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            <!-- Header -->
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100">Design System</h1>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Todos os componentes do CodeReview AI</p>
                <div class="mt-4">
                    <x-theme-toggle />
                </div>
            </div>

            <!-- Buttons -->
            <x-section title="Buttons" description="Variantes e tamanhos disponiveis">
                <div class="flex flex-wrap gap-4">
                    <x-button variant="primary">Primary</x-button>
                    <x-button variant="secondary">Secondary</x-button>
                    <x-button variant="danger">Danger</x-button>
                    <x-button variant="ghost">Ghost</x-button>
                </div>
                <div class="flex flex-wrap gap-4 mt-4">
                    <x-button size="sm">Small</x-button>
                    <x-button size="md">Medium</x-button>
                    <x-button size="lg">Large</x-button>
                </div>
            </x-section>

            <!-- Alerts -->
            <x-section title="Alerts" description="Notificacoes e mensagens de feedback">
                <div class="space-y-4">
                    <x-alert type="info">Informacao: sua analise esta sendo processada.</x-alert>
                    <x-alert type="success">Sucesso: code review finalizado com nota 8.5!</x-alert>
                    <x-alert type="warning">Aviso: encontramos 3 pontos de atencao no seu codigo.</x-alert>
                    <x-alert type="danger">Erro: falha ao conectar com o servico de IA.</x-alert>
                    <x-alert type="info" :dismissible="true">Este alerta pode ser fechado.</x-alert>
                </div>
            </x-section>

            <!-- Severity Badges -->
            <x-section title="Severity Badges" description="Indicadores de severidade dos findings">
                <div class="flex flex-wrap gap-4">
                    <x-severity-badge severity="low" />
                    <x-severity-badge severity="medium" />
                    <x-severity-badge severity="high" />
                    <x-severity-badge severity="critical" />
                </div>
            </x-section>

            <!-- Cards -->
            <x-section title="Cards" description="Containers de conteudo">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-card>
                        <x-card.header>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Card com Header</h3>
                        </x-card.header>
                        <x-card.body>
                            <p class="text-gray-600 dark:text-gray-400">
                                Este e um card com header e body separados.
                            </p>
                        </x-card.body>
                    </x-card>

                    <x-card class="p-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Card Simples</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Card sem sub-componentes, usando padding direto.
                        </p>
                    </x-card>
                </div>
            </x-section>

            <!-- Form Components -->
            <x-section title="Form Components" description="Campos de formulario">
                <x-card>
                    <x-card.body>
                        <div class="space-y-4">
                            <x-form.input label="Nome do Projeto" name="name" placeholder="Ex: Minha API REST" />
                            <x-form.select label="Linguagem" name="language" :options="[
                                'php' => 'PHP',
                                'javascript' => 'JavaScript',
                                'python' => 'Python',
                                'go' => 'Go',
                                'rust' => 'Rust',
                            ]" />
                            <x-form.textarea label="Descricao" name="description" placeholder="Descreva seu projeto..." />
                            <x-form.code-editor label="Codigo" name="code" language="php" placeholder="<?php echo 'Hello World';" />
                        </div>
                    </x-card.body>
                </x-card>
            </x-section>

            <!-- Code Block -->
            <x-section title="Code Block" description="Exibicao de codigo com destaque">
                <x-code-block language="php" filename="app/Services/PaymentService.php">
public function charge(User $user, float $amount): bool
{
    return DB::transaction(function () use ($user, $amount) {
        $user->balance -= $amount;
        $user->save();
        return true;
    });
}
                </x-code-block>
            </x-section>

            <!-- Table -->
            <x-section title="Table" description="Tabela responsiva">
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pilar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Severidade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descricao</th>
                        </tr>
                    </x-slot:head>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Architecture</td>
                        <td class="px-6 py-4"><x-severity-badge severity="medium" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Falta inversao de dependencia no controller</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Security</td>
                        <td class="px-6 py-4"><x-severity-badge severity="critical" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">SQL Injection via input nao sanitizado</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">Performance</td>
                        <td class="px-6 py-4"><x-severity-badge severity="high" /></td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">N+1 query no loop de relatorios</td>
                    </tr>
                </x-table>
            </x-section>

            <!-- Kanban Components -->
            <x-section title="Kanban Components" description="Componentes do board de melhorias">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- ToDo Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="ToDo" :count="1" color="blue" />
                        <div class="space-y-3">
                            <div class="bg-white dark:bg-gray-700 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-2">
                                    <x-severity-badge severity="high" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Refactor</span>
                                </div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Refatorar controller de projetos</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 font-mono">app/Http/Controllers/ProjectController.php</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-kanban-add-button label="Adicionar tarefa" />
                        </div>
                    </div>

                    <!-- InProgress Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="In Progress" :count="0" color="yellow" />
                        <x-kanban-empty-state message="Nenhuma tarefa em andamento" />
                    </div>

                    <!-- Done Column -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4">
                        <x-kanban-column-header title="Done" :count="0" color="green" />
                        <x-kanban-empty-state message="Nenhuma tarefa concluida" />
                    </div>
                </div>
            </x-section>

            <!-- Modal -->
            <x-section title="Modal" description="Dialog para acoes e confirmacoes">
                <x-button
                    variant="primary"
                    @click="$dispatch('open-modal', 'demo-modal')"
                >
                    Abrir Modal
                </x-button>

                <x-modal name="demo-modal" maxWidth="md">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            Confirmar acao
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            Tem certeza que deseja executar esta acao? Esta operacao nao pode ser desfeita.
                        </p>
                        <div class="flex justify-end gap-3">
                            <x-button variant="ghost" @click="$dispatch('close-modal', 'demo-modal')">
                                Cancelar
                            </x-button>
                            <x-button variant="danger" @click="$dispatch('close-modal', 'demo-modal')">
                                Confirmar
                            </x-button>
                        </div>
                    </div>
                </x-modal>
            </x-section>

        </div>
    </div>
</x-layouts::guest>
```

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: add design system showcase page with all components"
```

---

## Passo 7 — Verificar o resultado

Suba o ambiente e acesse a pagina showcase:

```bash
sail up -d
sail npm run dev
```

Acesse no navegador:

```
http://localhost/design-system
```

Voce deve ver todos os componentes renderizados: botoes, alertas, badges, cards, formularios, code blocks, tabela, kanban e modal.

**Testar dark mode:**
1. Clique no icone de sol/lua no topo da pagina
2. O tema deve alternar entre claro e escuro
3. A preferencia e salva no `localStorage`

---

## Passo 8 — Padroes do Design System

Para referencia futura, estes sao os padroes de cores usados em todos os componentes:

```
Primaria:   indigo-600 / dark:indigo-500
Superficie: white / dark:gray-800
Borda:      gray-200 / dark:gray-700
Texto:      gray-900 / dark:gray-100
Muted:      gray-500 / dark:gray-400
Danger:     red-600 / dark:red-500
Success:    green-600 / dark:green-500
Warning:    yellow-600 / dark:yellow-500
```

**Padrao de props em todos os componentes:**

1. `@props([...])` — define props com valores default
2. `$attributes->merge([...])` — permite passar atributos extras (classes, ids, etc.)
3. `{{ $slot }}` — conteudo filho
4. Variantes `dark:` para todos os estilos

---

## Passo 9 — Commitar e criar PR

```bash
cd ~/laravel_ai
git add .
git commit -m "feat: design system complete with docs and patterns"

# Push da branch
git push -u origin feat/cap06-design-system

# Criar Pull Request
gh pr create --title "feat: design system e componentes" --body "Capitulo 06 - Design System com 20+ componentes Blade, dark mode, layouts e pagina showcase"

# Apos merge do PR no GitHub:
git checkout main
git pull
```

---

## Resumo do que foi criado

| Arquivo | O que faz |
|---------|-----------|
| `vite.config.js` | Configuracao Vite com plugin Tailwind CSS 4 |
| `resources/css/app.css` | Tema e imports do Tailwind 4 |
| `resources/views/layouts/app.blade.php` | Layout para usuarios autenticados (sidebar + header) |
| `resources/views/layouts/guest.blade.php` | Layout para login/registro (centralizado) |
| `resources/views/components/form/input.blade.php` | Input text/email/password com label e validacao |
| `resources/views/components/form/textarea.blade.php` | Textarea com label e validacao |
| `resources/views/components/form/select.blade.php` | Select dropdown com opcoes dinamicas |
| `resources/views/components/form/code-editor.blade.php` | Editor de codigo com fonte mono |
| `resources/views/components/button.blade.php` | Botao com 4 variantes e 3 tamanhos |
| `resources/views/components/card/index.blade.php` | Container do card |
| `resources/views/components/card/header.blade.php` | Cabecalho do card |
| `resources/views/components/card/body.blade.php` | Corpo do card |
| `resources/views/components/alert.blade.php` | Alerta com 4 tipos e icones SVG |
| `resources/views/components/modal.blade.php` | Modal dialog com Alpine.js |
| `resources/views/components/severity-badge.blade.php` | Badge de severidade (low/medium/high/critical) |
| `resources/views/components/code-block.blade.php` | Bloco de codigo com filename e linguagem |
| `resources/views/components/table.blade.php` | Tabela responsiva com head e body |
| `resources/views/components/section.blade.php` | Secao com titulo e descricao |
| `resources/views/components/theme-toggle.blade.php` | Toggle dark/light mode com Alpine.js |
| `resources/views/components/kanban-card.blade.php` | Card draggable do Kanban |
| `resources/views/components/kanban-column-header.blade.php` | Header da coluna do Kanban com contador |
| `resources/views/components/kanban-empty-state.blade.php` | Estado vazio da coluna do Kanban |
| `resources/views/components/kanban-add-button.blade.php` | Botao para adicionar item no Kanban |
| `resources/views/pages/design-system.blade.php` | Pagina showcase com todos os componentes |
| `routes/web.php` | Rota `/design-system` adicionada |

## Proximo capitulo

No [Capitulo 7 — Autenticacao](07-autenticacao.md) vamos implementar login, registro e controle de acesso.
