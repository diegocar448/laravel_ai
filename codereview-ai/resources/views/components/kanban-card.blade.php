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
