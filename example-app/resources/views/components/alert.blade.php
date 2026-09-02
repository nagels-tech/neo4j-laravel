@if (session('success'))
    <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        {{ session('error') }}
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <p class="font-medium">Please fix the following:</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
