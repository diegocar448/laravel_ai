@props([
    'language' => 'php',
    'filename' => null,
])

<div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
    @if($filename)
        <div class="bg-gray-100 dark:bg-gray-900 px-4 py-2 text-sm text-gray-600 dark:text-gray-400
                    border-b border-gray-200 dark:border-gray-700 font-mono flex items-center justify-between">
            <span>{{ $filename }}</span>
            <span class="text-xs uppercase text-gray-400 dark:text-gray-500">{{ $language }}</span>
        </div>
    @endif
    <pre class="bg-gray-50 dark:bg-gray-950 p-4 overflow-x-auto">
        <code class="language-{{ $language }} text-sm font-mono text-gray-800 dark:text-gray-200">{{ $slot }}</code>
    </pre>
</div>
