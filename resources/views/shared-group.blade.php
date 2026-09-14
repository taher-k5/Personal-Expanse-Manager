<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $group->name }}</title>
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="min-h-screen">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <p class="mb-6 text-sm text-muted">
            A read-only view of this ledger. Nothing here can be changed from this page.
        </p>

        @livewire(\App\Livewire\GroupLedger::class, ['group' => $group, 'readOnly' => true])
    </div>
    @livewireScripts
</body>
</html>
