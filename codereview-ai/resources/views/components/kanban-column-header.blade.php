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
