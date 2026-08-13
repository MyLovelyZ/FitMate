@if (session('success') || session('status'))
    <x-ui.alert variant="success" class="mb-6">{{ session('success') ?? session('status') }}</x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert variant="danger" class="mb-6">{{ session('error') }}</x-ui.alert>
@endif

@if (session('warning'))
    <x-ui.alert variant="warning" class="mb-6">{{ session('warning') }}</x-ui.alert>
@endif

@if ($errors->any())
    <x-ui.alert variant="danger" class="mb-6">
        <p class="font-medium">Ada {{ $errors->count() }} isian yang perlu diperbaiki.</p>

        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
