@extends('layouts.app')

@section('title', $movie['title'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('movies.index') }}" class="text-sm text-zinc-600 hover:text-zinc-900">&larr; All movies</a>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_320px]">
        <section class="space-y-6">
            <x-movie-card :movie="$movie">
                <div class="mt-5 flex flex-wrap gap-3">
                    <a
                        href="{{ route('movies.similar', $movie['title']) }}"
                        class="inline-flex items-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50"
                    >
                        Find similar
                    </a>
                    <x-forms.delete-movie :title="$movie['title']" />
                </div>
            </x-movie-card>
        </section>

        <aside class="h-fit rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Add an actor</h2>
            <p class="mt-1 mb-4 text-sm text-zinc-500">
                MERGEs a <code class="text-xs">:Person</code> and <code class="text-xs">ACTED_IN</code> relationship.
            </p>
            <x-forms.add-actor :movie-title="$movie['title']" />
        </aside>
    </div>
@endsection
