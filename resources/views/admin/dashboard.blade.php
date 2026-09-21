<!DOCTYPE html>
<html lang="id" data-theme="e">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen p-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold">Panel Super Admin</h1>
        <nav class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary,#2563eb)] text-white">Tenant</a>
            <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-bg-inset)]">Invoice</a>
            <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-bg-inset)]">Pembayaran</a>
            <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-bg-inset)]">AI Pricing</a>
            <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-bg-inset)]">Paket</a>
            <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-bg-inset)]">Tiket</a>
        </nav>
    </div>

    <table class="w-full text-left border-collapse border border-[var(--erp-border)] rounded-[var(--erp-radius-md)]">
        <thead>
            <tr class="bg-[var(--erp-bg-inset)]">
                <th class="border border-[var(--erp-border)] p-2">Company</th>
                <th class="border border-[var(--erp-border)] p-2">Owner</th>
                <th class="border border-[var(--erp-border)] p-2">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $c)
            <tr>
                <td class="border border-[var(--erp-border)] p-2">{{ $c->name }}</td>
                <td class="border border-[var(--erp-border)] p-2">{{ $c->owner->name ?? "-" }}</td>
                <td class="border border-[var(--erp-border)] p-2">
                    <form method="POST" action="{{ route("admin.impersonate", $c) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium text-[var(--erp-text-link)] underline hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Login As</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
