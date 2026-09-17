@props(['field', 'model'])

@php
    $fieldId = 'field-'.$field['field'];
    $extraAttrs = collect($field['attrs'] ?? [])
        ->map(fn ($value, $name) => $name.'="'.e($value).'"')
        ->implode(' ');
@endphp

<div class="space-y-1.5">
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

        @if ($field['input'] === 'select')
            <select
                id="{{ $fieldId }}"
                wire:model="{{ $model }}"
                @required($field['required'])
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border {{ $errors->has($field['field']) ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
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
                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border {{ $errors->has($field['field']) ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]' }} bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $field['type'] === 'integer' || $field['type'] === 'number' ? 'font-[family-name:var(--erp-font-mono)] text-right' : '' }}"
            />
        @endif

        @error($field['field'])
            <p class="mt-1 text-xs text-[var(--erp-danger)]" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
