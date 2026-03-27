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
