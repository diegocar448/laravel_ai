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
