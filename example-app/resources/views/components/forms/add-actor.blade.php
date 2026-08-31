@props(['movieTitle'])

<form method="POST" action="{{ route('movies.actors.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="movie_title" value="{{ $movieTitle }}">

    <div>
        <label for="actor_name" class="block text-sm font-medium text-zinc-700">Actor name</label>
        <input
            id="actor_name"
            name="actor_name"
            type="text"
            value="{{ old('actor_name') }}"
            required
            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
    </div>

    <div>
        <label for="roles" class="block text-sm font-medium text-zinc-700">Roles</label>
        <input
            id="roles"
            name="roles"
            type="text"
            value="{{ old('roles') }}"
            placeholder="Neo, Thomas Anderson"
            required
            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
        <p class="mt-1 text-xs text-zinc-500">Comma-separated role names.</p>
    </div>

    <button
        type="submit"
        class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700"
    >
        Add actor
    </button>
</form>
