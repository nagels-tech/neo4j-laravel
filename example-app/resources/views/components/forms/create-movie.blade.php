<form method="POST" action="{{ route('movies.store') }}" class="space-y-4">
    @csrf

    <div>
        <label for="title" class="block text-sm font-medium text-zinc-700">Title</label>
        <input
            id="title"
            name="title"
            type="text"
            value="{{ old('title') }}"
            required
            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
    </div>

    <div>
        <label for="released" class="block text-sm font-medium text-zinc-700">Released</label>
        <input
            id="released"
            name="released"
            type="number"
            value="{{ old('released') }}"
            required
            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
    </div>

    <div>
        <label for="tagline" class="block text-sm font-medium text-zinc-700">Tagline</label>
        <input
            id="tagline"
            name="tagline"
            type="text"
            value="{{ old('tagline') }}"
            required
            class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
    </div>

    <button
        type="submit"
        class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700"
    >
        Create movie
    </button>
</form>
