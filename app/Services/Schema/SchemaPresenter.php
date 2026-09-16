<?php

namespace App\Services\Schema;

/**
 * Menurunkan kolom tabel dan field form dari `{entity}.schema.json`.
 *
 * Tidak ada daftar kolom per entitas di kode: seluruh keputusan diambil dari
 * metadata schema, sehingga entitas baru langsung memiliki layar tanpa
 * perubahan kode (D-31/D-42).
 */
class SchemaPresenter
{
    /**
     * Field yang tidak pernah disajikan: `attributes` adalah kantong dinamis,
     * dan timestamp diurus sistem, bukan operator.
     *
     * @var list<string>
     */
    private const SYSTEM_FIELDS = ['attributes', 'created_at', 'updated_at'];

    /** @var list<string> */
    private const SCALAR_TYPES = ['string', 'integer', 'number', 'boolean'];

    /** @var list<string> */
    private const NUMERIC_TYPES = ['integer', 'number'];

    /**
     * Kolom tabel: properti skalar, tanpa foreign key (id opaque bagi operator)
     * dan tanpa field sistem.
     *
     * @return list<array{field: string, label: string, type: string, numeric: bool}>
     */
    public function columns(EntitySchema $schema): array
    {
        $references = array_keys($schema->references());
        $columns = [];

        foreach ($schema->properties() as $field => $definition) {
            if (in_array($field, self::SYSTEM_FIELDS, true) || in_array($field, $references, true)) {
                continue;
            }

            $type = (string) ($definition['type'] ?? '');
            if (! in_array($type, self::SCALAR_TYPES, true)) {
                continue;
            }

            $columns[] = [
                'field' => $field,
                'label' => $this->label($field, $definition),
                'type' => $type,
                'numeric' => in_array($type, self::NUMERIC_TYPES, true),
            ];
        }

        return $columns;
    }

    /**
     * Field form: sama dengan kolom tabel, tanpa `id` yang ditetapkan repository.
     *
     * @return list<array{field: string, label: string, input: string, type: string, required: bool, nullable: bool, options: list<string>, attrs: array<string, string|int|float>}>
     */
    public function fields(EntitySchema $schema): array
    {
        $required = $schema->required();
        $fields = [];

        foreach ($this->columns($schema) as $column) {
            if ($column['field'] === 'id') {
                continue;
            }

            $definition = $schema->properties()[$column['field']];

            $fields[] = [
                'field' => $column['field'],
                'label' => $column['label'],
                'input' => $this->input($definition),
                'type' => $column['type'],
                'required' => in_array($column['field'], $required, true),
                'nullable' => ($definition['nullable'] ?? false) === true,
                'options' => $this->options($definition),
                'attrs' => $this->attributes($definition),
            ];
        }

        return $fields;
    }

    /**
     * Field yang boleh dipakai sebagai target pencarian bebas: hanya string,
     * karena pencocokan dilakukan sebagai substring.
     *
     * @return list<string>
     */
    public function searchable(EntitySchema $schema): array
    {
        return array_values(array_map(
            static fn (array $column): string => $column['field'],
            array_filter($this->columns($schema), static fn (array $column): bool => $column['type'] === 'string'),
        ));
    }

    /**
     * Mengubah nilai mentah dari form menjadi tipe yang diterima schema.
     */
    public function cast(EntitySchema $schema, string $field, mixed $value): mixed
    {
        $definition = $schema->properties()[$field] ?? null;
        if ($definition === null) {
            return $value;
        }

        $type = (string) ($definition['type'] ?? '');
        $nullable = ($definition['nullable'] ?? false) === true;

        if ($type === 'boolean') {
            return (bool) $value;
        }

        if (is_string($value) && trim($value) === '') {
            return $nullable ? null : ($type === 'string' ? '' : null);
        }

        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => is_numeric($value) ? (int) $value : $value,
            'number' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            default => $value,
        };
    }

    /**
     * Nilai awal form: default schema bila ada, jika tidak nilai netral per tipe.
     *
     * @return array<string, mixed>
     */
    public function blank(EntitySchema $schema): array
    {
        $blank = [];

        foreach ($this->fields($schema) as $field) {
            $definition = $schema->properties()[$field['field']];

            $blank[$field['field']] = array_key_exists('default', $definition)
                ? $definition['default']
                : match ($field['type']) {
                    'boolean' => false,
                    default => $field['nullable'] ? null : '',
                };
        }

        return $blank;
    }

    /** @param array<string, mixed> $definition */
    private function label(string $field, array $definition): string
    {
        $label = $definition['label'] ?? null;

        if (is_string($label) && trim($label) !== '') {
            return $label;
        }

        return ucwords(str_replace('_', ' ', $field));
    }

    /** @param array<string, mixed> $definition */
    private function input(array $definition): string
    {
        if (isset($definition['enum'])) {
            return 'select';
        }

        $type = (string) ($definition['type'] ?? '');

        if ($type === 'boolean') {
            return 'checkbox';
        }

        if (in_array($type, self::NUMERIC_TYPES, true)) {
            return 'number';
        }

        return match ($definition['format'] ?? null) {
            'email' => 'email',
            'date' => 'date',
            'date-time' => 'datetime-local',
            default => 'text',
        };
    }

    /** @param array<string, mixed> $definition
     * @return list<string>
     */
    private function options(array $definition): array
    {
        $enum = $definition['enum'] ?? [];

        if (! is_array($enum)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $enum));
    }

    /** @param array<string, mixed> $definition
     * @return array<string, string|int|float>
     */
    private function attributes(array $definition): array
    {
        $attributes = [];
        $type = (string) ($definition['type'] ?? '');

        if ($type === 'string' && isset($definition['length']) && is_int($definition['length'])) {
            $attributes['maxlength'] = $definition['length'];
        }

        if (in_array($type, self::NUMERIC_TYPES, true)) {
            if (isset($definition['minimum']) && (is_int($definition['minimum']) || is_float($definition['minimum']))) {
                $attributes['min'] = $definition['minimum'];
            }

            if (isset($definition['maximum']) && (is_int($definition['maximum']) || is_float($definition['maximum']))) {
                $attributes['max'] = $definition['maximum'];
            }

            $scale = $definition['scale'] ?? null;
            $attributes['step'] = $type === 'integer' || ! is_int($scale) || $scale === 0
                ? 1
                : (float) ('0.'.str_repeat('0', $scale - 1).'1');
        }

        return $attributes;
    }
}
