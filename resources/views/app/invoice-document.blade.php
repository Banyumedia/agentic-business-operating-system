<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $documentLabel }} {{ $invoice['number'] ?? '' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[var(--erp-bg-base)] p-6 text-[var(--erp-text-primary)]">
    <main class="mx-auto max-w-2xl space-y-6 rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-8 print:border-0 print:bg-white print:p-0">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--erp-border)] pb-4">
            <div>
                <h1 class="text-xl font-bold">{{ $identity['name'] ?? 'Usaha' }}</h1>
                @if (! empty($identity['address']))
                    <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">{{ $identity['address'] }}</p>
                @endif
                @if (! empty($identity['npwp']))
                    <p class="text-sm text-[var(--erp-text-secondary)]">NPWP {{ $identity['npwp'] }}</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">{{ $documentLabel }}</p>
                <p class="mt-1 font-[family-name:var(--erp-font-mono)] text-lg font-bold">{{ $invoice['number'] ?? '' }}</p>
                <p class="text-sm text-[var(--erp-text-secondary)]">Terbit {{ $invoice['issue_date'] ?? '—' }}</p>
                <p class="text-sm text-[var(--erp-text-secondary)]">Jatuh tempo {{ $invoice['due_date'] ?? '—' }}</p>
            </div>
        </header>

        <section>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Ditagihkan kepada</h2>
            <p class="mt-1 font-semibold">{{ $contact['name'] ?? 'Pelanggan tidak dicantumkan' }}</p>
            @if (! empty($contact['wa_number']))
                <p class="text-sm text-[var(--erp-text-secondary)]">{{ $contact['wa_number'] }}</p>
            @endif
            <p class="mt-2 text-sm">{{ $invoice['title'] ?? '' }}</p>
        </section>

        <section>
            <table class="w-full text-sm">
                <caption class="sr-only">Rincian {{ $documentLabel }} {{ $invoice['number'] ?? '' }}</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                        <th scope="col" class="py-2 pr-3">Keterangan</th>
                        <th scope="col" class="py-2 pr-3 text-right">Jumlah</th>
                        <th scope="col" class="py-2 pr-3 text-right">Harga</th>
                        <th scope="col" class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr class="border-b border-[var(--erp-border)] last:border-0">
                            <td class="py-2 pr-3">{{ $line['description'] ?? '' }}</td>
                            <td class="py-2 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 4, ',', '.'), '0'), ',') }}</td>
                            <td class="py-2 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($line['unit_price'] ?? 0), 2, ',', '.') }}</td>
                            <td class="py-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($line['line_total'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- D-44/TX-04: Dasar Pengenaan dan Pajak hanya untuk usaha PKP - tidak
             dirender sama sekali untuk non-PKP, bukan ditampilkan bernilai
             nol. --}}
        <section class="ml-auto max-w-xs space-y-1 text-sm">
            <div class="flex justify-between gap-6">
                <span class="text-[var(--erp-text-secondary)]">Subtotal</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($invoice['subtotal'] ?? 0), 2, ',', '.') }}</span>
            </div>
            @if ($taxable)
                <div class="flex justify-between gap-6">
                    <span class="text-[var(--erp-text-secondary)]">Dasar pengenaan</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($invoice['dpp'] ?? 0), 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-6">
                    <span class="text-[var(--erp-text-secondary)]">Pajak</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($invoice['tax'] ?? 0), 2, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between gap-6 border-t border-[var(--erp-border)] pt-1 font-bold">
                <span>Total</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($invoice['grand_total'] ?? 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-6">
                <span class="text-[var(--erp-text-secondary)]">Sudah dibayar</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format($paid, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-6 font-semibold">
                <span>Sisa</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format($outstanding, 2, ',', '.') }}</span>
            </div>
        </section>

        @if (! empty($invoice['notes']))
            <section class="border-t border-[var(--erp-border)] pt-4 text-sm text-[var(--erp-text-secondary)]">
                {{ $invoice['notes'] }}
            </section>
        @endif

        <div class="print:hidden">
            <button type="button" onclick="window.print()"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                Cetak atau simpan sebagai PDF
            </button>
        </div>
    </main>
</body>
</html>
