<!DOCTYPE html>
<html lang="id" data-theme="e">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen p-8">
    <h1 class="text-2xl font-bold mb-4">Panel Super Admin</h1>
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
