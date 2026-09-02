@extends('layouts.app')

@section('title', 'Similar to '.$title)

@section('content')
    <div class="mb-6">
        <a href="{{ route('movies.show', $title) }}" class="text-sm text-zinc-600 hover:text-zinc-900">&larr; Back to {{ $title }}</a>
    </div>

    <div class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight">Similar to {{ $title }}</h1>
        <p class="mt-2 text-zinc-600">Movies that share the most actors, limited to five.</p>
    </div>

    <div class="space-y-4">
        @forelse ($movies as $row)
            <x-movie-card :movie="$row['movie']" :common-actors="$row['common_actors']">
                <div class="mt-4">
                    <a href="{{ route('movies.show', $row['movie']['title']) }}" class="text-sm text-zinc-700 underline-offset-2 hover:underline">
                        View movie
                    </a>
                </div>
            </x-movie-card>
        @empty
            <p class="rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-8 text-center text-sm text-zinc-500">
                No similar movies found. Add shared actors to build recommendations.
            </p>
        @endforelse
    </div>
@endsection
