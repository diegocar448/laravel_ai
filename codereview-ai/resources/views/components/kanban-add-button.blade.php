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
