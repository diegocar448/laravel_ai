@props([])

<div {{ $attributes->merge([
    'class' => 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden'
]) }}>
    {{ $slot }}
</div>
