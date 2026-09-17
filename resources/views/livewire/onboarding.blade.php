<div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-12">
    <header class="mb-8">
        <p class="text-sm font-semibold text-[var(--erp-accent)]">Mulai pakai Agentic BOS</p>
        <h1 class="mt-1 text-3xl font-bold text-[var(--erp-text-primary)]">Ceritakan sedikit tentang usaha Anda</h1>
        <p class="mt-2 text-[var(--erp-text-secondary)]">Cukup dua pertanyaan. Anda bisa ubah pengaturan lain kapan saja lewat Pengaturan &gt; Fitur Bisnis.</p>
    </header>

    @if ($createdSlug)
        <div role="status" aria-live="polite" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-success-soft)] p-6">
            <p class="font-semibold text-[var(--erp-text-primary)]">Usaha Anda berhasil dibuat.</p>
            <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                Identitas usaha tersimpan dengan kode <span class="font-mono">{{ $createdSlug }}</span>.
                Tim kami akan menghubungkan akun Anda ke usaha ini sebelum Anda bisa login.
            </p>
        </div>
    @else
        <form wire:submit.prevent="submit" class="space-y-6">
            @if ($failure)
                <p role="alert" class="rounded-[var(--erp-radius-sm)] bg-[var(--erp-danger-soft)] p-3 text-sm text-[var(--erp-danger)]">{{ $failure }}</p>
            @endif

            <div>
                <label for="onboarding-name" class="block text-sm font-medium text-[var(--erp-text-secondary)]">Nama usaha</label>
                <input
                    type="text"
                    id="onboarding-name"
                    wire:model="name"
                    required
                    class="mt-2 min-h-11 w-full rounded-[var(--erp-radius-sm)] border {{ $errors->has('name') ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-elevated)] px-3 text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    placeholder="Contoh: Usaha Jaya Bersama"
                >
                @error('name')
                    <p class="mt-1 text-xs text-[var(--erp-danger)]" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="onboarding-preset" class="block text-sm font-medium text-[var(--erp-text-secondary)]">Jenis usaha yang paling mendekati</label>
                <select
                    id="onboarding-preset"
                    wire:model="preset"
                    class="mt-2 min-h-11 w-full rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-3 text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    @foreach ($presets as $presetOption)
                        <option value="{{ $presetOption['key'] }}">{{ $presetOption['name'] }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">Tidak masalah bila belum pas. Anda dapat menggantinya nanti.</p>
            </div>

            <button
                type="submit"
                class="min-h-11 w-full rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                Buat usaha saya
            </button>
        </form>
    @endif
</div>
