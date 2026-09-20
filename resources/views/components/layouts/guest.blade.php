<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Agentic BOS') }} - Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-[var(--erp-text-primary)] bg-[var(--erp-bg-base)]">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-extrabold text-[var(--erp-text-primary)]">
                Masuk ke akun Anda
            </h2>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-[var(--erp-bg-secondary)] border border-[var(--erp-border)] py-8 px-4 shadow-[var(--erp-card-shadow)] sm:rounded-[var(--erp-radius-lg)] sm:px-10">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
