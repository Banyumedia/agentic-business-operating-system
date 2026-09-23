<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Uang yang ditahan dari tagihan termin sampai tanggal rilisnya tiba.
        </p>
    </header>

    @if (! $isOwner)
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            Hanya owner yang dapat mencairkan retensi.
        </p>
    @endif

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

    <section aria-labelledby="retention-list-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 id="retention-list-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Daftar retensi</h2>

        @if ($retentions === [])
            <p class="mt-4 text-sm text-[var(--erp-text-secondary)]">Belum ada retensi tercatat untuk usaha ini.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Daftar retensi proyek</caption>
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                            <th scope="col" class="py-2 pr-3">Proyek</th>
                            <th scope="col" class="py-2 pr-3 text-right">Nominal</th>
                            <th scope="col" class="py-2 pr-3">Status</th>
                            <th scope="col" class="py-2 pr-3">Tanggal rilis</th>
                            <th scope="col" class="py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($retentions as $retention)
                            <tr class="border-b border-[var(--erp-border)] last:border-0">
                                <td class="py-2 pr-3 text-[var(--erp-text-primary)]">{{ $retention['project_name'] }}</td>
                                <td class="py-2 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format($retention['amount'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $retention['status'] === 'held' ? 'bg-[var(--erp-warning-soft)] text-[var(--erp-warning)]' : 'bg-[var(--erp-success-soft)] text-[var(--erp-success)]' }}">
                                        {{ $retention['status'] === 'held' ? 'Tertahan' : 'Sudah dicairkan' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-[var(--erp-text-secondary)]">{{ $retention['release_on'] ?? '—' }}</td>
                                <td class="py-2 text-right">
                                    @if ($retention['can_release'] && $isOwner)
                                        <button type="button" wire:click="requestRelease({{ $retention['id'] }})"
                                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-3 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                                            Cairkan
                                        </button>
                                    @elseif ($retention['status'] === 'held')
                                        <span class="text-xs text-[var(--erp-text-muted)]">Belum jatuh tanggal rilis</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($confirmLevel !== null)
        <x-confirm-dialog
            level="{{ $confirmLevel }}"
            title="Cairkan retensi?"
            description="Nominal akan tercatat sebagai kas keluar di Buku Kas dan tidak dapat dibatalkan."
            confirm-label="Cairkan"
            confirm="confirmRelease"
            cancel="cancelRelease"
            phrase-model="confirmPhrase"
            :invalid-phrase="$failure"
        />
    @endif
</div>
