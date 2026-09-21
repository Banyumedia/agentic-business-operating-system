@props(['field', 'model'])

@php
    $fieldId = 'field-'.$field['field'];
    $extraAttrs = collect($field['attrs'] ?? [])
        ->map(fn ($value, $name) => $name.'="'.e($value).'"')
        ->implode(' ');
    $invalid = $errors->has($model);
    $errorId = $fieldId.'-error';
    // Key berubah saat status validasi berubah supaya morph Livewire
    // mengganti node dan x-init Alpine berjalan lagi (fokus pindah ke
    // field pertama yang salah setelah round-trip, bukan hanya saat
    // halaman dimuat pertama).
    $key = $fieldId.($invalid ? '-invalid' : '-ok');
@endphp

<div wire:key="{{ $key }}" class="space-y-1.5">
    @if ($field['input'] === 'checkbox')
        <label for="{{ $fieldId }}" class="flex min-h-11 items-center gap-3 text-sm font-medium text-[var(--erp-text-primary)]">
            <input
                id="{{ $fieldId }}"
                type="checkbox"
                wire:model="{{ $model }}"
                class="size-5 rounded-[var(--erp-radius-sm)] border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] text-[var(--erp-accent)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            />
            <span>{{ $field['label'] }}</span>
        </label>
    @else
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-[var(--erp-text-primary)]">
            {{ $field['label'] }}
            @if ($field['required'])
                <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span>
                <span class="sr-only">(wajib)</span>
            @endif
        </label>

        @if ($field['input'] === 'relation')
            {{-- Relasi: nilai = id baris, teks = judul baris. Pilihan sudah
                 dibatasi company aktif oleh layar yang merender field ini. --}}
            <select
                id="{{ $fieldId }}"
                wire:model="{{ $model }}"
                @required($field['required'])
                aria-invalid="{{ $invalid ? 'true' : 'false' }}"
                @if ($invalid) aria-describedby="{{ $errorId }}" @endif
                x-init="$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === $el && $el.focus()"
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border {{ $invalid ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                <option value="">{{ $field['required'] ? 'Pilih '.$field['label'] : 'Tanpa '.$field['label'] }}</option>
                @foreach ($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($field['input'] === 'select')
            <select
                id="{{ $fieldId }}"
                wire:model="{{ $model }}"
                @required($field['required'])
                aria-invalid="{{ $invalid ? 'true' : 'false' }}"
                @if ($invalid) aria-describedby="{{ $errorId }}" @endif
                x-init="$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === $el && $el.focus()"
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border {{ $invalid ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                <option value="">Pilih {{ $field['label'] }}</option>
                @foreach ($field['options'] as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        @else
            <input
                id="{{ $fieldId }}"
                type="{{ $field['input'] }}"
                wire:model="{{ $model }}"
                @required($field['required'])
                {!! $extraAttrs !!}
                aria-invalid="{{ $invalid ? 'true' : 'false' }}"
                @if ($invalid) aria-describedby="{{ $errorId }}" @endif
                x-init="$el.getAttribute('aria-invalid') === 'true' && document.querySelector('[aria-invalid=true]') === $el && $el.focus()"
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border {{ $invalid ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $field['type'] === 'integer' || $field['type'] === 'number' ? 'font-[family-name:var(--erp-font-mono)] text-right' : '' }}"
            />
        @endif

        @error($model)
            <p id="{{ $errorId }}" class="mt-1 text-xs text-[var(--erp-danger)]" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
