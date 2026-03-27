<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'CodeReview AI') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <!-- Logo -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">CodeReview AI</h1>
            <p class="text-gray-500 dark:text-gray-400 text-center mt-1">Analise inteligente de codigo</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-8">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
