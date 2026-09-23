@php
    // Dua format dari satu sumber data (MP-03): termal 58mm (bawaan, dipakai
    // langsung dari kasir) atau faktur A4 (dipilih lewat ?format=a4, mis. bila
    // pelanggan minta bukti yang lebih formal). Tidak ada perhitungan ulang di
    // sini untuk format mana pun - nominal selalu dibaca dari $order tersimpan.
    $isA4 = request()->query('format') === 'a4';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Struk {{ $order['order_no'] ?? '' }}</title>
    @vite(['resources/css/app.css'])
    @if (! $isA4)
        <style>
            /* Termal 58mm: lebar cetak fisik ~48mm setelah margin printer. */
            @media print {
                @page { size: 58mm auto; margin: 2mm; }
            }
        </style>
    @endif
</head>
<body class="bg-[var(--erp-bg-base)] p-6 text-[var(--erp-text-primary)]">
    <main class="mx-auto space-y-4 rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 print:border-0 print:bg-white print:p-0 {{ $isA4 ? 'max-w-2xl' : 'max-w-[58mm] text-xs' }}">
        <header class="space-y-1 border-b border-[var(--erp-border)] pb-3 text-center">
            <h1 class="{{ $isA4 ? 'text-xl' : 'text-sm' }} font-bold">{{ $identity['name'] }}</h1>
            @if (! empty($identity['address']))
                <p class="{{ $isA4 ? 'text-sm' : 'text-xs' }} text-[var(--erp-text-secondary)]">{{ $identity['address'] }}</p>
            @endif
            @if (! empty($identity['npwp']))
                <p class="{{ $isA4 ? 'text-sm' : 'text-xs' }} text-[var(--erp-text-secondary)]">NPWP {{ $identity['npwp'] }}</p>
            @endif
        </header>

        <section class="{{ $isA4 ? 'flex flex-wrap items-start justify-between gap-4' : 'space-y-0.5' }}">
            <div>
                <p class="font-[family-name:var(--erp-font-mono)] font-bold">{{ $order['order_no'] ?? '' }}</p>
                <p class="text-[var(--erp-text-secondary)]">{{ $order['paid_at'] ?? '' }}</p>
                @if ($contact !== null)
                    <p class="text-[var(--erp-text-secondary)]">{{ $contact['name'] ?? '' }}</p>
                @endif
            </div>
            @if ($isA4)
                <p class="text-right text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Struk / Faktur Penjualan</p>
            @endif
        </section>

        <section>
            <table class="w-full {{ $isA4 ? 'text-sm' : 'text-xs' }}">
                <caption class="sr-only">Rincian struk {{ $order['order_no'] ?? '' }}</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border)] text-left uppercase tracking-wide text-[var(--erp-text-muted)]">
                        <th scope="col" class="py-1 pr-2">Item</th>
                        <th scope="col" class="py-1 pr-2 text-right">Jml</th>
                        @if ($isA4)
                            <th scope="col" class="py-1 pr-2 text-right">Harga</th>
                        @endif
                        <th scope="col" class="py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr class="border-b border-[var(--erp-border)] last:border-0">
                            <td class="py-1 pr-2">{{ $line['description'] ?? '' }}</td>
                            <td class="py-1 pr-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ rtrim(rtrim(number_format((float) ($line['qty'] ?? 0), 3, ',', '.'), '0'), ',') }}</td>
                            @if ($isA4)
                                <td class="py-1 pr-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($line['unit_price'] ?? 0), 2, ',', '.') }}</td>
                            @endif
                            <td class="py-1 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($line['line_total'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- D-44/TX-04: Dasar Pengenaan dan Pajak hanya untuk usaha PKP - tidak
             dirender sama sekali untuk non-PKP, bukan ditampilkan bernilai
             nol. Pola identik invoice-document.blade.php (T-52). --}}
        <section class="space-y-0.5 {{ $isA4 ? 'ml-auto max-w-xs text-sm' : 'text-xs' }}">
            <div class="flex justify-between gap-4">
                <span class="text-[var(--erp-text-secondary)]">Subtotal</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($order['subtotal'] ?? 0), 2, ',', '.') }}</span>
            </div>
            @if (($order['discount_amount'] ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-[var(--erp-text-secondary)]">Diskon</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">-{{ number_format((float) $order['discount_amount'], 2, ',', '.') }}</span>
                </div>
            @endif
            @if ($taxable)
                <div class="flex justify-between gap-4">
                    <span class="text-[var(--erp-text-secondary)]">Dasar pengenaan</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($order['dpp'] ?? 0), 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-[var(--erp-text-secondary)]">Pajak</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($order['tax_amount'] ?? 0), 2, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between gap-4 border-t border-[var(--erp-border)] pt-1 font-bold">
                <span>Total</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($order['grand_total'] ?? 0), 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-[var(--erp-text-secondary)]">Bayar ({{ $order['payment_method'] ?? '—' }})</span>
                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($order['grand_total'] ?? 0), 2, ',', '.') }}</span>
            </div>
        </section>

        <footer class="border-t border-[var(--erp-border)] pt-3 text-center {{ $isA4 ? 'text-sm' : 'text-xs' }} text-[var(--erp-text-secondary)]">
            Terima kasih atas kunjungan Anda.
        </footer>

        <div class="flex flex-wrap justify-center gap-3 print:hidden">
            <button type="button" onclick="window.print()"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                Cetak atau simpan sebagai PDF
            </button>
            @if ($isA4)
                <a href="{{ request()->fullUrlWithQuery(['format' => null]) }}"
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                    Tampilkan sebagai struk 58mm
                </a>
            @else
                <a href="{{ request()->fullUrlWithQuery(['format' => 'a4']) }}"
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                    Tampilkan sebagai faktur A4
                </a>
            @endif
        </div>
    </main>
</body>
</html>
