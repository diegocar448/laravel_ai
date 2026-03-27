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
