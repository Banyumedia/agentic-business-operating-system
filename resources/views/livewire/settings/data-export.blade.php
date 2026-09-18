<div>
    <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 shadow-sm">
        <h2 class="text-lg font-medium text-[var(--erp-text-primary)]">Ekspor Data Usaha</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Unduh seluruh data (CSV) dan pengaturan (JSON) milik usaha Anda.
            Fitur ini tetap dapat digunakan kapan saja, termasuk jika langganan/trial Anda berakhir.
        </p>

        <div class="mt-4">
            @if ($downloadUrl)
                <div class="mb-4 rounded bg-green-50 p-4 text-green-700">
                    Ekspor selesai! <a href="{{ $downloadUrl }}" class="font-bold underline" download>Klik di sini untuk mengunduh berkas ZIP.</a>
                </div>
            @endif

            <button
                wire:click="export"
                wire:loading.attr="disabled"
                class="inline-flex items-center rounded bg-[var(--erp-accent)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[var(--erp-accent-hover)] disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="export">Mulai Ekspor Data</span>
                <span wire:loading wire:target="export">Sedang Menyiapkan...</span>
            </button>
        </div>
    </div>
</div>