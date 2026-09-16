<?php

namespace App\Services\Schema;

use InvalidArgumentException;
use JsonException;

class EntitySchema
{
    /** @param array<string, mixed> $definition */
    private function __construct(private readonly array $definition) {}

    public static function load(string $entity): self
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $entity)) {
            throw new InvalidArgumentException('Nama entitas tidak valid.');
        }

        $path = database_path("schemas/{$entity}.schema.json");

        if (! is_file($path)) {
            throw new InvalidArgumentException("Schema entitas tidak ditemukan: {$entity}");
        }

        try {
            $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Schema entitas bukan JSON valid: {$entity}", previous: $exception);
        }

        if (! is_array($definition) || ($definition['entity'] ?? null) !== $entity) {
            throw new InvalidArgumentException("Identitas schema tidak cocok: {$entity}");
        }

        foreach (['required', 'properties', 'attributes'] as $key) {
            if (! isset($definition[$key]) || ! is_array($definition[$key])) {
                throw new InvalidArgumentException("Bagian schema tidak valid: {$key}");
            }
        }

        self::validateDefinition($definition);

        return new self($definition);
    }

    /** @param array<string, mixed> $definition */
    private static function validateDefinition(array $definition): void
    {
        if (($definition['$schema'] ?? null) !== 'agentic-bos/entity-schema/v1' || ($definition['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Versi schema entitas tidak didukung.');
        }

        if (($definition['company_scope'] ?? null) !== 'folder') {
            throw new InvalidArgumentException('Company scope schema harus folder.');
        }

        $properties = $definition['properties'];
        if (array_key_exists('company_id', $properties)) {
            throw new InvalidArgumentException('company_id harus implisit dari folder company.');
        }

        if (count($definition['required']) !== count(array_unique($definition['required'], SORT_REGULAR))) {
            throw new InvalidArgumentException('Field wajib schema duplikat.');
        }

        foreach ($definition['required'] as $field) {
            if (! is_string($field) || ! array_key_exists($field, $properties)) {
                throw new InvalidArgumentException('Field wajib schema tidak dideklarasikan.');
            }
        }

        if (isset($properties['attributes']) && ($properties['attributes']['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException('Field attributes harus bertipe object.');
        }

        $allowedTypes = ['string', 'integer', 'number', 'boolean', 'array', 'object'];
        foreach ([$properties, $definition['attributes']] as $fields) {
            foreach ($fields as $field => $fieldDefinition) {
                if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_]*$/', $field)) {
                    throw new InvalidArgumentException('Nama field schema tidak valid.');
                }

                if (! is_array($fieldDefinition) || ! in_array($fieldDefinition['type'] ?? null, $allowedTypes, true)) {
                    throw new InvalidArgumentException("Tipe schema tidak valid: {$field}");
                }

                if (array_key_exists('nullable', $fieldDefinition) && ! is_bool($fieldDefinition['nullable'])) {
                    throw new InvalidArgumentException("Nullable schema tidak valid: {$field}");
                }

                if (array_key_exists('unsigned', $fieldDefinition) && (! is_bool($fieldDefinition['unsigned']) || $fieldDefinition['type'] !== 'integer')) {
                    throw new InvalidArgumentException("Unsigned schema tidak valid: {$field}");
                }

                if (array_key_exists('length', $fieldDefinition) && ($fieldDefinition['type'] !== 'string' || ! is_int($fieldDefinition['length']) || $fieldDefinition['length'] < 1)) {
                    throw new InvalidArgumentException("Length schema tidak valid: {$field}");
                }

                if (array_key_exists('format', $fieldDefinition) && ($fieldDefinition['type'] !== 'string' || ! in_array($fieldDefinition['format'], ['date', 'date-time', 'email'], true))) {
                    throw new InvalidArgumentException("Format schema tidak valid: {$field}");
                }

                if (array_key_exists('precision', $fieldDefinition) || array_key_exists('scale', $fieldDefinition)) {
                    $precision = $fieldDefinition['precision'] ?? null;
                    $scale = $fieldDefinition['scale'] ?? null;
                    if ($fieldDefinition['type'] !== 'number' || ! is_int($precision) || ! is_int($scale) || $precision < 1 || $scale < 0 || $scale > $precision) {
                        throw new InvalidArgumentException("Presisi schema tidak valid: {$field}");
                    }
                }

                foreach (['minimum', 'maximum'] as $bound) {
                    if (isset($fieldDefinition[$bound]) && (! is_int($fieldDefinition[$bound]) && ! is_float($fieldDefinition[$bound]))) {
                        throw new InvalidArgumentException("Batas schema tidak valid: {$field}");
                    }
                }

                if (isset($fieldDefinition['minimum'], $fieldDefinition['maximum']) && $fieldDefinition['minimum'] > $fieldDefinition['maximum']) {
                    throw new InvalidArgumentException("Rentang schema tidak valid: {$field}");
                }

                if (array_key_exists('default', $fieldDefinition) && ! self::valueMatchesType($fieldDefinition['default'], $fieldDefinition['type'])) {
                    throw new InvalidArgumentException("Default schema tidak valid: {$field}");
                }

                if (isset($fieldDefinition['enum'])) {
                    $enum = $fieldDefinition['enum'];
                    if (! is_array($enum) || ! array_is_list($enum) || $enum === []) {
                        throw new InvalidArgumentException("Enum schema tidak valid: {$field}");
                    }

                    foreach ($enum as $value) {
                        if (! self::valueMatchesType($value, $fieldDefinition['type'])) {
                            throw new InvalidArgumentException("Nilai enum schema tidak valid: {$field}");
                        }
                    }
                }
            }
        }

        foreach (['indexes', 'unique'] as $collection) {
            if (! isset($definition[$collection]) || ! is_array($definition[$collection])) {
                throw new InvalidArgumentException("Bagian schema tidak valid: {$collection}");
            }

            foreach ($definition[$collection] as $index) {
                $fields = is_array($index) ? ($index['fields'] ?? null) : null;
                if (! is_array($fields) || $fields === []) {
                    throw new InvalidArgumentException("Definisi {$collection} tidak valid.");
                }

                foreach ($fields as $field) {
                    if (! is_string($field) || ! array_key_exists($field, $properties)) {
                        throw new InvalidArgumentException("Field {$collection} tidak dideklarasikan.");
                    }
                }

                if (($index['company_scoped'] ?? null) !== true) {
                    throw new InvalidArgumentException("{$collection} harus company-scoped.");
                }
            }
        }

        if (array_key_exists('no_overlap', $definition)) {
            self::validateNoOverlap($definition['no_overlap'], $properties);
        }

        if (! isset($definition['references']) || ! is_array($definition['references'])) {
            throw new InvalidArgumentException('Bagian schema tidak valid: references');
        }

        foreach ($definition['references'] as $field => $reference) {
            if (! array_key_exists($field, $properties) || ! is_array($reference)) {
                throw new InvalidArgumentException("Reference schema tidak valid: {$field}");
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $reference['entity'] ?? '') || ! in_array($reference['on_delete'] ?? null, ['cascade', 'restrict', 'set_null'], true)) {
                throw new InvalidArgumentException("Target reference schema tidak valid: {$field}");
            }
        }
    }

    /**
     * Kontrak `no_overlap` menyatakan bahwa dua baris pada scope yang sama tidak
     * boleh memiliki rentang waktu yang bertumpang-tindih. Dideklarasikan sebagai
     * data supaya aturannya berlaku untuk entitas apa pun tanpa kode khusus.
     *
     * @param  array<string, array<string, mixed>>  $properties
     */
    private static function validateNoOverlap(mixed $rule, array $properties): void
    {
        if (! is_array($rule) || array_is_list($rule)) {
            throw new InvalidArgumentException('Definisi no_overlap harus object.');
        }

        $scope = $rule['scope'] ?? null;
        if (! is_array($scope) || ! array_is_list($scope) || $scope === []) {
            throw new InvalidArgumentException('Scope no_overlap harus daftar field.');
        }

        foreach ([...$scope, $rule['start'] ?? null, $rule['end'] ?? null] as $field) {
            if (! is_string($field) || ! array_key_exists($field, $properties)) {
                throw new InvalidArgumentException('Field no_overlap tidak dideklarasikan.');
            }
        }

        foreach ([$rule['start'], $rule['end']] as $field) {
            if (($properties[$field]['format'] ?? null) !== 'date-time') {
                throw new InvalidArgumentException("Batas no_overlap harus date-time: {$field}");
            }
        }
    }

    private static function valueMatchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || (is_float($value) && is_finite($value)),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            default => false,
        };
    }

    public function name(): string
    {
        return $this->definition['entity'];
    }

    public function version(): int
    {
        return $this->definition['version'];
    }

    /** @return array<int, string> */
    public function required(): array
    {
        return $this->definition['required'];
    }

    /** @return array<string, array<string, mixed>> */
    public function properties(): array
    {
        return $this->definition['properties'];
    }

    /** @return array<string, array<string, mixed>> */
    public function attributes(): array
    {
        return $this->definition['attributes'];
    }

    /** @return array<int, array{fields: array<int, string>, company_scoped: bool}> */
    public function indexes(): array
    {
        return $this->definition['indexes'];
    }

    /** @return array<int, array{fields: array<int, string>, company_scoped: bool}> */
    public function unique(): array
    {
        return $this->definition['unique'];
    }

    /** @return array<string, array{entity: string, on_delete: string}> */
    public function references(): array
    {
        return $this->definition['references'];
    }

    /** @return array{scope: list<string>, start: string, end: string}|null */
    public function noOverlap(): ?array
    {
        return $this->definition['no_overlap'] ?? null;
    }
}
