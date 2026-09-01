<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Movies') — Neo4j Laravel</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <style type="text/tailwindcss">
            @theme {
                --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            }
        </style>
    @endif
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 font-sans antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('movies.index') }}" class="text-lg font-semibold tracking-tight text-zinc-900">
                Neo4j Movies
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('movies.index') }}" class="text-zinc-600 hover:text-zinc-900">All movies</a>
                <a href="{{ url('/api/movies') }}" class="text-zinc-600 hover:text-zinc-900">JSON API</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <x-alert />
        @yield('content')
    </main>
</body>
</html>
