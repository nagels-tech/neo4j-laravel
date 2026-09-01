@props(['actors' => []])

@if (count($actors) === 0)
    <p class="text-sm text-zinc-500">No actors linked yet.</p>
@else
    <ul class="space-y-2">
        @foreach ($actors as $actor)
            <li class="text-sm">
                <span class="font-medium text-zinc-900">{{ $actor['name'] }}</span>
                @if (!empty($actor['roles']))
                    <span class="text-zinc-500"> — {{ implode(', ', $actor['roles']) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
