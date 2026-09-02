@props(['title'])

<form method="POST" action="{{ route('movies.destroy', $title) }}" onsubmit="return confirm('Delete {{ $title }} and its relationships?')">
    @csrf
    @method('DELETE')
    <button
        type="submit"
        class="inline-flex items-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
    >
        Delete movie
    </button>
</form>
