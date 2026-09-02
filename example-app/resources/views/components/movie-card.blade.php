@props([
    'movie',
    'commonActors' => null,
])

<article {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold tracking-tight">
                <a href="{{ route('movies.show', $movie['title']) }}" class="hover:underline">
                    {{ $movie['title'] }}
                </a>
            </h2>
            @if (!empty($movie['released']))
                <p class="mt-1 text-sm text-zinc-500">Released {{ $movie['released'] }}</p>
            @endif
        </div>
        @if ($commonActors !== null)
            <span class="shrink-0 rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700">
                {{ $commonActors }} shared {{ \Illuminate\Support\Str::plural('actor', $commonActors) }}
            </span>
        @endif
    </div>

    @if (!empty($movie['tagline']))
        <p class="mt-3 text-zinc-700 italic">“{{ $movie['tagline'] }}”</p>
    @endif

    <div class="mt-4">
        <x-actor-list :actors="$movie['actors'] ?? []" />
    </div>

    {{ $slot }}
</article>
