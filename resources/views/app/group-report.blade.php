@php
$theme = $company->theme ?? 'a';
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group Report - Agentic BOS</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen">
        <div class="p-8 max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">Laporan Gabungan Grup Cabang</h1>
            <p class="text-gray-600 mb-6">Grup: {{ $company->parentCompany->name ?? $company->name }}</p>

            <div class="grid grid-cols-2 gap-4">
                <div class="p-6 border rounded-lg shadow-sm bg-[var(--erp-bg-secondary)]">
                    <h2 class="text-lg font-semibold text-[var(--erp-text-secondary)]">Total Kontak</h2>
                    <p class="text-3xl mt-2 font-bold">{{ $aggregate['total_contacts'] ?? 0 }}</p>
                </div>
                <div class="p-6 border rounded-lg shadow-sm bg-[var(--erp-bg-secondary)]">
                    <h2 class="text-lg font-semibold text-[var(--erp-text-secondary)]">Total Cash Entries</h2>
                    <p class="text-3xl mt-2 font-bold">{{ $aggregate['total_cash_entries'] ?? 0 }}</p>
                </div>
            </div>

            <div class="mt-8">
                <a href="/app" class="text-[var(--erp-accent)] underline">Kembali ke App</a>
            </div>
        </div>
    </body>
</html>
