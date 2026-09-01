@extends('layouts.app')

@section('title', 'Movies')

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight">Movies</h1>
        <p class="mt-2 text-zinc-600">Browse Neo4j movie nodes and their ACTED_IN relationships.</p>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_320px]">
        <section class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Catalog</h2>

            @forelse ($movies as $movie)
                <x-movie-card :movie="$movie">
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <a href="{{ route('movies.show', $movie['title']) }}" class="text-zinc-700 underline-offset-2 hover:underline">
                            Details
                        </a>
                        <a href="{{ route('movies.similar', $movie['title']) }}" class="text-zinc-700 underline-offset-2 hover:underline">
                            Similar
                        </a>
                    </div>
                </x-movie-card>
            @empty
                <p class="rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-8 text-center text-sm text-zinc-500">
                    No movies yet. Create one with the form.
                </p>
            @endforelse
        </section>

        <aside class="h-fit rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Add a movie</h2>
            <p class="mt-1 mb-4 text-sm text-zinc-500">Creates a <code class="text-xs">:Movie</code> node.</p>
            <x-forms.create-movie />
        </aside>
    </div>
@endsection
