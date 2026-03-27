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
