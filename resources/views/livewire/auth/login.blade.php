<div>
    <form wire:submit="login" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                Alamat email
            </label>
            <div class="mt-1">
                <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required
                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                       @if ($errors->has('email')) aria-describedby="email-error" @endif
                       x-init="$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === $el && $el.focus()"
                       class="appearance-none block w-full min-h-11 px-3 py-2 border {{ $errors->has('email') ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border-strong)]' }} rounded-[var(--erp-radius-md)] placeholder:text-[var(--erp-text-muted)] bg-[var(--erp-bg-inset)] text-[var(--erp-text-primary)] focus:outline-none focus:border-[var(--erp-border-focus)] focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] sm:text-sm">
            </div>
            @error('email') <span id="email-error" class="mt-2 text-sm text-[var(--erp-danger)] block" role="alert">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                Kata sandi
            </label>
            <div class="mt-1">
                <input wire:model="password" id="password" name="password" type="password" autocomplete="current-password" required
                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                       @if ($errors->has('password')) aria-describedby="password-error" @endif
                       x-init="$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === $el && $el.focus()"
                       class="appearance-none block w-full min-h-11 px-3 py-2 border {{ $errors->has('password') ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border-strong)]' }} rounded-[var(--erp-radius-md)] placeholder:text-[var(--erp-text-muted)] bg-[var(--erp-bg-inset)] text-[var(--erp-text-primary)] focus:outline-none focus:border-[var(--erp-border-focus)] focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] sm:text-sm">
            </div>
            @error('password') <span id="password-error" class="mt-2 text-sm text-[var(--erp-danger)] block" role="alert">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-[var(--erp-text-primary)]">
                <input wire:model="remember" id="remember_me" name="remember_me" type="checkbox"
                       class="h-5 w-5 rounded border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] text-[var(--erp-accent)] focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                Ingat saya
            </label>
        </div>

        <div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex justify-center min-h-11 items-center py-2 px-4 border border-transparent rounded-[var(--erp-radius-md)] shadow-[var(--erp-card-shadow)] text-sm font-semibold text-[var(--erp-text-inverse)] bg-[var(--erp-accent)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--erp-bg-base)] disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="login">Masuk</span>
                <span wire:loading wire:target="login">Memproses…</span>
                <span wire:loading wire:target="login" class="ml-2" aria-hidden="true">
                    <svg class="animate-spin h-5 w-5 text-[var(--erp-text-inverse)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </div>
    </form>
</div>
