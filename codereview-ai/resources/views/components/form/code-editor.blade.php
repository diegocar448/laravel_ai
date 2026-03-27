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
