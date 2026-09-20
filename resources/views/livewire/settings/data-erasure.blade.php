<div>
    <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-danger)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 class="text-lg font-semibold text-[var(--erp-danger)]">Hapus Data {{ ucfirst($contactTerm) }}</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Hapus seluruh data pribadi seorang {{ $contactTerm }}. Jejak transaksi (invoice, pesanan) akan dianomimkan untuk kebutuhan pembukuan. Tindakan ini tidak dapat dibatalkan.
        </p>

        <div class="mt-4">
            @if ($feedback)
                <p
                    class="mb-4 rounded-[var(--erp-radius-md)] border px-4 py-3 text-sm text-[var(--erp-text-primary)] {{ $feedbackType === 'success' ? 'border-[var(--erp-success)] bg-[var(--erp-success-soft)]' : 'border-[var(--erp-danger)] bg-[var(--erp-danger-soft)]' }}"
                    role="{{ $feedbackType === 'success' ? 'status' : 'alert' }}"
                    @if ($feedbackType === 'success') aria-live="polite" @endif
                >
                    {{ $feedback }}
                </p>
            @endif

            {{--
                Nama terenkripsi (D-42) tidak bisa dicari di SQL, jadi pencarian
                berjalan di PHP lewat contactMatches(). Hasil hanya membantu
                memilih ID; target destruktif tetap ID yang divalidasi ulang
                di server pada saat aksi.
            --}}
            <div class="max-w-md">
                <label for="erasure-search" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                    Cari nama untuk menemukan ID
                </label>
                <input
                    id="erasure-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Ketik nama (min. 2 huruf)"
                    class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >

                @if ($matches !== [])
                    <ul role="list" class="mt-2 divide-y divide-[var(--erp-border)] rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)]" wire:loading.class="opacity-50 pointer-events-none" wire:target="search">
                        @foreach ($matches as $match)
                            <li>
                                <button
                                    type="button"
                                    wire:click="$set('contactId', '{{ $match['id'] }}')"
                                    class="flex min-h-11 w-full items-baseline justify-between gap-3 px-3 py-2 text-left hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                                >
                                    <span class="text-sm text-[var(--erp-text-primary)]">{{ $match['name'] }}</span>
                                    <span class="shrink-0 font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-secondary)]">#{{ $match['id'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <form wire:submit="erase" class="mt-4 grid max-w-md gap-4">
                <fieldset class="grid gap-4" aria-describedby="erasure-mode-hint">
                    <legend class="sr-only">Penghapusan data {{ $contactTerm }}</legend>
                    <p id="erasure-mode-hint" class="text-sm text-[var(--erp-text-secondary)]">
                        Satu mode penghapusan: data pribadi dihapus permanen, jejak transaksi dianomimkan.
                    </p>

                    <div>
                        <label for="erasure-contact-id" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                            ID {{ ucfirst($contactTerm) }}
                            <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib)</span>
                        </label>
                        <input
                            id="erasure-contact-id"
                            type="text"
                            inputmode="numeric"
                            wire:model="contactId"
                            required
                            class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                        >
                    </div>

                    <div>
                        <label for="erasure-confirmation-code" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                            Ketik <strong>YA</strong> untuk konfirmasi
                            <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib)</span>
                        </label>
                        <input
                            id="erasure-confirmation-code"
                            type="text"
                            wire:model="confirmationCode"
                            required
                            autocapitalize="characters"
                            autocorrect="off"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="YA"
                            class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 font-[family-name:var(--erp-font-mono)] text-sm uppercase text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                        >
                    </div>
                </fieldset>

                <div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-danger)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="erase">Hapus Permanen</span>
                        <span wire:loading wire:target="erase">Menghapus...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
