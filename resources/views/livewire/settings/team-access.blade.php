<div class="space-y-5 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6">
    <header>
        <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">Tim & Akses</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Undang staf lewat WhatsApp. Mereka mendapat akun sendiri untuk masuk ke usaha ini —
            bukan bot tambahan, jadi tidak memakai kuota grup WhatsApp.
        </p>
        <p class="mt-2 text-sm text-[var(--erp-text-muted)]">
            Terpakai <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ $used }}</span>
            dari <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ $quota }}</span> pengguna pada paket ini.
        </p>
    </header>

    @if ($notice !== null)
        <p role="status" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-success)] bg-[var(--erp-success-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $notice }}
        </p>
    @endif

    @if ($failure !== null)
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $failure }}
        </p>
    @endif

    <form wire:submit="invite" class="flex flex-wrap items-end gap-3 border-t border-[var(--erp-border)] pt-4">
        <div class="flex-1 space-y-1.5">
            <label for="team-wa" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                Nomor WhatsApp staf <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
            </label>
            <input id="team-wa" type="text" inputmode="numeric" wire:model="waNumber" required maxlength="32"
                placeholder="08xxxxxxxxxx"
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
        </div>
        <button type="submit" wire:loading.attr="disabled"
            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
            <span wire:loading.remove>Kirim undangan</span>
            <span wire:loading>Mengirim…</span>
        </button>
    </form>

    <section aria-labelledby="team-members-title" class="border-t border-[var(--erp-border)] pt-4">
        <h3 id="team-members-title" class="text-sm font-semibold text-[var(--erp-text-primary)]">Anggota</h3>

        <ul role="list" class="mt-3 space-y-2">
            @forelse ($members as $member)
                <li wire:key="member-{{ $member['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)] px-3 py-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-[var(--erp-text-primary)]">{{ $member['name'] }}</p>
                        <p class="text-xs text-[var(--erp-text-muted)]">
                            {{ $member['wa_number'] ?? 'Nomor belum diisi' }}
                            &middot; {{ $member['is_owner'] ? 'Pemilik' : 'Staf' }}
                        </p>
                    </div>
                    @unless ($member['is_owner'])
                        <button type="button" wire:click="removeMember({{ $member['id'] }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-xs font-semibold text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                            Cabut akses<span class="sr-only"> {{ $member['name'] }}</span>
                        </button>
                    @endunless
                </li>
            @empty
                <li class="rounded-[var(--erp-radius-sm)] border border-dashed border-[var(--erp-border-strong)] px-3 py-6 text-center text-sm text-[var(--erp-text-muted)]">
                    Belum ada anggota selain Anda.
                </li>
            @endforelse
        </ul>
    </section>

    @if ($pending->isNotEmpty())
        <section aria-labelledby="team-pending-title" class="border-t border-[var(--erp-border)] pt-4">
            <h3 id="team-pending-title" class="text-sm font-semibold text-[var(--erp-text-primary)]">Undangan menunggu</h3>

            <ul role="list" class="mt-3 space-y-2">
                @foreach ($pending as $invitation)
                    <li wire:key="invite-{{ $invitation['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)] px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)]">{{ $invitation['wa_number'] }}</p>
                            <p class="text-xs text-[var(--erp-text-muted)]">Berlaku hingga {{ $invitation['expires_at'] }}</p>
                        </div>
                        <button type="button" wire:click="revoke({{ $invitation['id'] }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-xs font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                            Cabut undangan
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
