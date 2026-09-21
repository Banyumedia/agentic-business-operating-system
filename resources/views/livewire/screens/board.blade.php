<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Papan ini menampilkan yang sedang berjalan per sumber daya. Yang alurnya sudah tamat tidak dihitung sebagai terpakai.
        </p>
    </header>

    @if (! $hasResourceLink)
        {{-- Jujur soal keterbatasan, jangan pura-pura papan kosong. --}}
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-warning-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            Data {{ $term }} belum terhubung ke sumber daya, sehingga belum bisa dikelompokkan per kolom.
        </p>
    @endif

    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Sedang terpakai</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-2xl font-bold text-[var(--erp-text-primary)]">{{ $occupied }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Total kolom</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-2xl font-bold text-[var(--erp-text-primary)]">{{ count($columns) }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Sudah selesai</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-2xl font-bold text-[var(--erp-text-primary)]">{{ $closed }}</dd>
        </div>
    </dl>

    <div class="overflow-x-auto pb-2">
        <ol role="list" class="flex min-w-full gap-4">
            @forelse ($columns as $column)
                <li class="flex w-72 shrink-0 flex-col rounded-[var(--erp-radius-lg)] border {{ $column['cards'] === [] ? 'border-[var(--erp-border)]' : 'border-[var(--erp-accent)]' }} bg-[var(--erp-bg-secondary)]">
                    <h2 class="border-b border-[var(--erp-border)] px-4 py-3">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $column['label'] }}</span>
                            <span class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-muted)]">{{ count($column['cards']) }}</span>
                        </span>

                        @if ($column['status'] !== null || $column['capacity'] !== null)
                            <span class="mt-1 block text-xs text-[var(--erp-text-muted)]">
                                @if ($column['status'] !== null){{ $column['status'] }}@endif
                                @if ($column['status'] !== null && $column['capacity'] !== null) &middot; @endif
                                @if ($column['capacity'] !== null)kapasitas {{ $column['capacity'] }}@endif
                            </span>
                        @endif
                    </h2>

                    <div class="flex flex-1 flex-col gap-3 p-3">
                        @forelse ($column['cards'] as $card)
                            <article class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-3">
                                <h3 class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $card['title'] }}</h3>

                                <dl class="mt-2 space-y-1">
                                    @if ($card['stage'] !== null)
                                        <div class="flex items-baseline justify-between gap-2">
                                            <dt class="text-xs text-[var(--erp-text-muted)]">Tahap</dt>
                                            <dd class="text-xs text-[var(--erp-text-secondary)]">{{ $card['stage'] }}</dd>
                                        </div>
                                    @endif
                                    @if ($card['hasAmount'])
                                        <div class="flex items-baseline justify-between gap-2">
                                            <dt class="text-xs text-[var(--erp-text-muted)]">Nilai</dt>
                                            <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-secondary)]">{{ number_format($card['amount'], 2, ',', '.') }}</dd>
                                        </div>
                                    @endif
                                    @if ($card['hasDeposit'])
                                        <div class="flex items-baseline justify-between gap-2">
                                            <dt class="text-xs text-[var(--erp-text-muted)]">Deposit</dt>
                                            <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-secondary)]">{{ number_format($card['deposit'], 2, ',', '.') }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </article>
                        @empty
                            <p class="rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] px-3 py-6 text-center text-xs text-[var(--erp-text-muted)]">
                                Kosong.
                            </p>
                        @endforelse
                    </div>

                    @if ($column['cards'] !== [] && ($hasAmount || $hasDeposit))
                        <div class="border-t border-[var(--erp-border)] px-4 py-3">
                            @if ($hasAmount)
                                <p class="flex items-baseline justify-between gap-2 text-xs">
                                    <span class="text-[var(--erp-text-muted)]">Total nilai</span>
                                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ number_format($column['amount'], 2, ',', '.') }}</span>
                                </p>
                            @endif
                            @if ($hasDeposit)
                                <p class="mt-1 flex items-baseline justify-between gap-2 text-xs">
                                    <span class="text-[var(--erp-text-muted)]">Total deposit</span>
                                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ number_format($column['deposit'], 2, ',', '.') }}</span>
                                </p>
                            @endif
                        </div>
                    @endif
                </li>
            @empty
                <li class="w-full rounded-[var(--erp-radius-lg)] border border-dashed border-[var(--erp-border-strong)] px-4 py-10 text-center text-sm text-[var(--erp-text-muted)]">
                    Belum ada sumber daya yang bisa dijadikan kolom papan. Tambahkan lewat daftar sumber daya lebih dulu.
                </li>
            @endforelse
        </ol>
    </div>
</div>
