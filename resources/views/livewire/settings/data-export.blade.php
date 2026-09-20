<div>
    <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">Ekspor Data Usaha</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Unduh seluruh data usaha Anda dalam satu berkas ZIP: satu berkas CSV per bagian data
            (kontak, pesanan, pembukuan, dan sebagainya) plus berkas JSON berisi pengaturan dan preset usaha.
            Data Anda tetap milik Anda — fitur ini selalu tersedia untuk pemilik usaha,
            termasuk saat langganan atau trial berakhir.
        </p>

        <div class="mt-4">
            @if ($downloadUrl)
                <p role="status" class="mb-4 rounded-[var(--erp-radius-md)] border border-[var(--erp-success)] bg-[var(--erp-success-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
                    Ekspor selesai!
                    <a href="{{ $downloadUrl }}" class="font-semibold text-[var(--erp-text-link)] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" download>Klik di sini untuk mengunduh berkas ZIP.</a>
                </p>
            @endif

            <button
                type="button"
                wire:click="export"
                wire:loading.attr="disabled"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="export">Mulai Ekspor Data</span>
                <span wire:loading wire:target="export">Sedang Menyiapkan...</span>
            </button>
        </div>
    </div>
</div>
