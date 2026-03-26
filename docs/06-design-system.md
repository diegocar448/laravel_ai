# Capítulo 6 — Design System e Componentes

## Visão geral

O projeto implementa um **Design System completo** com 20+ componentes Blade reutilizáveis, suporte a dark mode e documentação interativa acessível em `/design-system`.

## Tailwind CSS 4

O projeto usa **Tailwind CSS 4.2** com o plugin Vite 8:

```js
// vite.config.js
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
});
```

No Tailwind 4.2, a configuração é feita via CSS em vez de `tailwind.config.js`:

```css
/* resources/css/app.css */
@import "tailwindcss";

@theme {
    /* Customizações de tema aqui */
}
```

## Estrutura dos componentes

```
resources/views/components/
├── alert/
│   └── index.blade.php          # Container do alerta
├── card/
│   ├── index.blade.php          # Container do card
│   ├── header.blade.php         # Cabeçalho
│   └── body.blade.php           # Corpo
├── form/
│   ├── input.blade.php          # Input text/email/etc
│   ├── textarea.blade.php       # Textarea
│   ├── select.blade.php         # Select dropdown
│   └── code-editor.blade.php   # Editor de código com highlight
├── section/
│   └── index.blade.php          # Seção com título
├── sidebar/
│   └── ...                      # Navegação lateral
├── table/
│   └── index.blade.php          # Tabela responsiva
├── alert.blade.php              # Alerta simples
├── button.blade.php             # Botão com variantes
├── card.blade.php               # Card simples
├── code-block.blade.php         # Bloco de código com syntax highlight
├── severity-badge.blade.php     # Badge de severidade (low/medium/high/critical)
├── kanban-add-button.blade.php  # Botão adicionar no kanban
├── kanban-card.blade.php        # Card do kanban
├── kanban-column-header.blade.php
├── kanban-empty-state.blade.php
├── modal.blade.php              # Modal dialog
├── section.blade.php            # Seção
├── table.blade.php              # Tabela
└── theme-toggle.blade.php       # Toggle dark/light mode
```

## Componentes em detalhe

### Button

```html
<!-- resources/views/components/button.blade.php -->
@props([
    'variant' => 'primary',    // primary, secondary, danger, ghost
    'size' => 'md',            // sm, md, lg
    'type' => 'button',
])

@php
    $classes = match($variant) {
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500',
        'secondary' => 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'bg-transparent text-gray-600 hover:bg-gray-100 dark:text-gray-400',
    };

    $sizes = match($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "$classes $sizes rounded-lg font-medium transition-colors"]) }}
>
    {{ $slot }}
</button>
```

### Severity Badge (componente novo para code review)

```html
<!-- resources/views/components/severity-badge.blade.php -->
@props(['severity' => 'medium'])

@php
    $classes = match($severity) {
        'low' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        'critical' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "$classes px-2.5 py-0.5 rounded-full text-xs font-medium uppercase"
]) }}>
    {{ $severity }}
</span>
```

**Uso:**

```html
<x-severity-badge severity="critical" />
<x-severity-badge severity="high" />
<x-severity-badge severity="medium" />
<x-severity-badge severity="low" />
```

### Code Block (exibição de código)

```html
<!-- resources/views/components/code-block.blade.php -->
@props([
    'language' => 'php',
    'filename' => null,
])

<div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
    @if($filename)
        <div class="bg-gray-100 dark:bg-gray-900 px-4 py-2 text-sm text-gray-600 dark:text-gray-400
                    border-b border-gray-200 dark:border-gray-700 font-mono">
            {{ $filename }}
        </div>
    @endif
    <pre class="bg-gray-50 dark:bg-gray-950 p-4 overflow-x-auto">
        <code class="language-{{ $language }} text-sm">{{ $slot }}</code>
    </pre>
</div>
```

**Uso:**

```html
<x-code-block language="php" filename="app/Services/PaymentService.php">
    public function charge(User $user, float $amount): bool
    {
        // código aqui
    }
</x-code-block>
```

### Card, Form Input, Modal, Theme Toggle

Esses componentes seguem o mesmo padrão do Design System completo. Consulte o [Capítulo 6 do projeto original](https://github.com/beerandcodeteam/laravel-lab-planner) para ver a implementação detalhada de cada um.

### Componentes do Kanban

```html
<!-- x-kanban-card adaptado para code review -->
@props(['improvement'])

<div
    draggable="true"
    class="bg-white dark:bg-gray-700 rounded-lg p-4 shadow-sm border
           border-gray-200 dark:border-gray-600 cursor-grab active:cursor-grabbing"
>
    <div class="flex items-center justify-between mb-2">
        <x-severity-badge :severity="match($improvement->priority) {
            2 => 'critical', 1 => 'high', default => 'medium'
        }" />
        <span class="text-xs text-gray-500">
            {{ match($improvement->improvement_type_id) {
                1 => 'Refactor', 2 => 'Fix', 3 => 'Optimization'
            } }}
        </span>
    </div>
    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $improvement->title }}</p>
    @if($improvement->file_path)
        <p class="text-xs text-gray-500 mt-2 font-mono">{{ $improvement->file_path }}</p>
    @endif
</div>
```

## Padrões do Design System

### Consistência de cores

```
Primária:   indigo-600 / dark:indigo-500
Superfície: white / dark:gray-800
Borda:      gray-200 / dark:gray-700
Texto:      gray-900 / dark:gray-100
Muted:      gray-500 / dark:gray-400
Danger:     red-600 / dark:red-500
Success:    green-600 / dark:green-500
Warning:    yellow-600 / dark:yellow-500
```

### Padrão de props

Todos os componentes seguem o mesmo padrão:

1. `@props([...])` — define props com defaults
2. `$attributes->merge([...])` — permite passar atributos extras
3. `{{ $slot }}` — conteúdo filho
4. Variantes `dark:` para todos os estilos

## Próximo capítulo

No [Capítulo 7 — Autenticação](07-autenticacao.md) vamos implementar login, registro e controle de acesso.
