<div>
    @if($this->branches->count() > 1)
        <div class="mt-6 flex flex-col items-center border-t border-[var(--erp-border)] pt-6">
            <p class="mb-3 text-sm font-semibold text-[var(--erp-text-secondary)] uppercase tracking-wider">Switch Cabang / Lokasi</p>
            <div class="flex flex-wrap justify-center gap-2">
                @foreach($this->branches as $branch)
                    <button
                        type="button"
                        wire:click="switchBranch({{ $branch->id }})"
                        wire:loading.attr="disabled"
                        @class([
                            'min-h-11 px-4 py-2 rounded-full text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60',
                            'bg-[var(--erp-accent)] text-[var(--erp-text-inverse)] shadow-[var(--erp-card-shadow)]' => $branch->id == app(\App\Contracts\CompanyContext::class)->current(),
                            'bg-[var(--erp-bg-secondary)] text-[var(--erp-text-primary)] border border-[var(--erp-border)] hover:bg-[var(--erp-sidebar-active)]' => $branch->id != app(\App\Contracts\CompanyContext::class)->current(),
                        ])
                        @if($branch->id == app(\App\Contracts\CompanyContext::class)->current()) aria-current="true" @endif
                    >
                        {{ $branch->name }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>