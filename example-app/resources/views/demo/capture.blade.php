@extends('layouts.app')

@section('title', $api . ' capture demo')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium uppercase tracking-wide text-zinc-500">Debugbar artifact</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $api }} API</h1>
        <p class="mt-2 max-w-2xl text-zinc-600">{{ $description }}</p>
        <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            Open <strong>Debugbar → Queries</strong> and screenshot this request.
            Expected Cypher shape: <code class="text-xs">{{ $cypherHint }}</code>
        </p>
    </div>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 px-4 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Result rows</h2>
        </div>

        @if (empty($rows))
            <p class="px-4 py-8 text-center text-sm text-zinc-500">
                No rows yet. Create a movie on
                <a href="{{ route('movies.index') }}" class="underline">/movies</a>
                first (transaction demo still creates its own nodes).
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500">
                        <tr>
                            @foreach (array_keys($rows[0]) as $column)
                                <th class="px-4 py-2 font-medium">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-zinc-100">
                                @foreach ($row as $value)
                                    <td class="px-4 py-2 text-zinc-800">{{ $value ?: '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="mt-6 text-sm text-zinc-500">
        Other demos:
        <a class="underline" href="{{ url('/demo/client') }}">Client</a> ·
        <a class="underline" href="{{ url('/demo/driver') }}">Driver</a> ·
        <a class="underline" href="{{ url('/demo/session') }}">Session</a> ·
        <a class="underline" href="{{ url('/demo/transaction') }}">Transaction</a>
    </p>
@endsection
