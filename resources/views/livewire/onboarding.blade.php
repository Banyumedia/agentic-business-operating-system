<div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-12">
    <header class="mb-6">
        <p class="text-sm font-semibold text-[var(--erp-accent)]">Mulai pakai Agentic BOS</p>
        <h1 class="mt-1 text-3xl font-bold text-[var(--erp-text-primary)]">Ceritakan sedikit tentang usaha Anda</h1>
        <p class="mt-2 text-[var(--erp-text-secondary)]">Tiga langkah singkat. Anda bisa ubah pengaturan lain kapan saja lewat Pengaturan &gt; Fitur Bisnis.</p>
    </header>

    @if ($createdSlug)
        <div role="status" aria-live="polite" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-success-soft)] p-6">
            <p class="font-semibold text-[var(--erp-text-primary)]">Usaha Anda berhasil dibuat.</p>
            <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                Identitas usaha tersimpan dengan kode <span class="font-mono">{{ $createdSlug }}</span>.
                Anda sedang diarahkan ke dashboard.
            </p>
        </div>
    @elseif ($presets === [])
        {{-- Empty state: registry preset kosong - jangan biarkan owner membuat usaha tanpa preset. --}}
        <div role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6">
            <p class="font-semibold text-[var(--erp-text-primary)]">Belum ada preset yang tersedia</p>
            <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                Daftar jenis usaha belum dimuat oleh sistem. Tidak ada yang salah dengan input Anda -
                coba muat ulang halaman, atau hubungi kami bila masalah berlanjut.
            </p>
        </div>
    @else
        {{-- Progress indicator: langkah aktif diumumkan ke screen reader. --}}
        <nav aria-label="Progres onboarding" class="mb-6">
            <ol class="flex items-center gap-2" role="list">
                @foreach (range(1, $totalSteps) as $stepIndex)
                    <li class="flex flex-1 flex-col gap-1">
                        <span
                            aria-hidden="true"
                            class="h-1.5 rounded-full {{ $stepIndex <= $step ? 'bg-[var(--erp-accent)]' : 'bg-[var(--erp-border)]' }}"
                        ></span>
                        <span class="{{ $stepIndex === $step ? 'text-xs font-semibold text-[var(--erp-text-primary)]' : 'text-xs text-[var(--erp-text-muted)]' }}">
                            {{ $stepIndex === 1 ? 'Identitas' : ($stepIndex === 2 ? 'Preset' : 'Ringkasan') }}
                        </span>
                    </li>
                @endforeach
            </ol>
            <p class="sr-only" role="status">Langkah {{ $step }} dari {{ $totalSteps }}</p>
        </nav>
        <p aria-hidden="true" class="mb-6 text-xs font-medium text-[var(--erp-text-muted)]">Langkah {{ $step }} dari {{ $totalSteps }}</p>

        <form wire:submit.prevent="submit" class="space-y-6">
            @if ($failure)
                <p role="alert" class="rounded-[var(--erp-radius-sm)] bg-[var(--erp-danger-soft)] p-3 text-sm text-[var(--erp-danger)]">{{ $failure }}</p>
            @endif

            @if ($step === 1)
                <div>
                    <label for="onboarding-name" class="block text-sm font-medium text-[var(--erp-text-secondary)]">Nama usaha</label>
                    <input
                        type="text"
                        id="onboarding-name"
                        wire:model="name"
                        required
                        autocomplete="organization"
                        class="mt-2 min-h-11 w-full rounded-[var(--erp-radius-sm)] border {{ $errors->has('name') ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-elevated)] px-3 text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                        placeholder="Contoh: Usaha Jaya Bersama"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-[var(--erp-danger)]" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">Nama ini dipakai sebagai identitas usaha Anda di seluruh sistem.</p>
                </div>
            @elseif ($step === 2)
                <fieldset>
                    <legend class="block text-sm font-medium text-[var(--erp-text-secondary)]">Jenis usaha yang paling mendekati</legend>
                    <div class="mt-2 space-y-2" role="radiogroup" aria-label="Pilih preset bisnis">
                        @foreach ($presets as $presetOption)
                            <label for="onboarding-preset-{{ $presetOption['key'] }}" class="flex cursor-pointer items-start gap-3 rounded-[var(--erp-radius-sm)] border {{ $preset === $presetOption['key'] ? 'border-[var(--erp-accent)] bg-[var(--erp-accent-soft)]' : 'border-[var(--erp-border)] bg-[var(--erp-bg-elevated)]' }} p-3 focus-within:outline-none">
                                <input
                                    id="onboarding-preset-{{ $presetOption['key'] }}"
                                    type="radio"
                                    wire:model="preset"
                                    value="{{ $presetOption['key'] }}"
                                    class="mt-1 size-4 border-[var(--erp-border)] text-[var(--erp-accent)] focus:ring-[var(--erp-focus)]"
                                >
                                <span class="text-sm text-[var(--erp-text-primary)]">
                                    <span class="block font-semibold">{{ $presetOption['name'] }}</span>
                                    @if (!empty($presetOption['missing']))
                                        <span class="mt-1 block text-xs text-[var(--erp-text-secondary)]">
                                            Perlu paket lebih tinggi: {{ implode(', ', $presetOption['missing']) }}
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">Tidak masalah bila belum pas. Anda dapat menggantinya nanti.</p>
                </fieldset>
            @else
                <div class="rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-4 space-y-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-[var(--erp-text-muted)]">Nama usaha</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--erp-text-primary)]">{{ $name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-[var(--erp-text-muted)]">Jenis usaha</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--erp-text-primary)]">
                            {{ collect($presets)->firstWhere('key', $preset)['name'] ?? $preset }}
                        </p>
                    </div>
                    <div class="rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] p-4">
                        <label for="onboarding-privacy" class="flex cursor-pointer items-start gap-3 text-sm text-[var(--erp-text-primary)]">
                            <input
                                id="onboarding-privacy"
                                type="checkbox"
                                wire:model="acceptPrivacyPolicy"
                                class="mt-1 size-4 rounded border-[var(--erp-border)] text-[var(--erp-accent)] focus:ring-[var(--erp-focus)]"
                            >
                            <span>
                                Saya menyetujui kebijakan privasi Agentic BOS versi {{ $privacyPolicyVersion }} untuk pemrosesan data usaha.
                            </span>
                        </label>
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                @if ($step > 1)
                    <button
                        type="button"
                        wire:click="previousStep"
                        wire:loading.attr="disabled"
                        class="min-h-11 flex-1 rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-4 font-semibold text-[var(--erp-text-secondary)] transition hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >
                        Kembali
                    </button>
                @endif

                @if ($step < $totalSteps)
                    {{-- Disabled saat request Livewire berjalan: double-tap di HP
                        tidak boleh melewati dua langkah sekaligus. --}}
                    <button
                        type="button"
                        wire:click="nextStep"
                        wire:loading.attr="disabled"
                        class="min-h-11 flex-1 rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >
                        <span wire:loading.remove>Lanjut</span>
                        <span wire:loading>Memproses…</span>
                    </button>
                @else
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="min-h-11 flex-1 rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >
                        <span wire:loading.remove>Buat usaha saya</span>
                        <span wire:loading>Membuat…</span>
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>
