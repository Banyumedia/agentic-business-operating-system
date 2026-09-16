<?php

namespace App\Services\Schema;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

class SchemaValidator
{
    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function validate(string|EntitySchema $schema, array $row): array
    {
        $schema = is_string($schema) ? EntitySchema::load($schema) : $schema;

        foreach ($schema->required() as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null) {
                throw new InvalidArgumentException("Field wajib tidak ada: {$field}");
            }
        }

        foreach ($row as $field => $value) {
            $definition = $schema->properties()[$field] ?? null;

            if ($definition === null) {
                throw new InvalidArgumentException("Field tidak dideklarasikan: {$field}");
            }

            if ($field === 'attributes') {
                continue;
            }

            $this->validateType("field: {$field}", $value, $definition);
        }

        $attributes = $row['attributes'] ?? [];
        if (! is_array($attributes) || ($attributes !== [] && array_is_list($attributes))) {
            throw new InvalidArgumentException('Tipe field tidak valid: attributes harus object');
        }

        foreach ($attributes as $key => $value) {
            $definition = $schema->attributes()[$key] ?? null;

            if ($definition === null) {
                throw new InvalidArgumentException("Attribute tidak dideklarasikan: {$key}");
            }

            $this->validateType("attribute: {$key}", $value, $definition);
        }

        return $row;
    }

    /** @param array<string, mixed> $definition */
    private function validateType(string $subject, mixed $value, array $definition): void
    {
        [$kind, $name] = array_map('trim', explode(':', $subject, 2));
        $type = $definition['type'] ?? null;

        if ($value === null && ($definition['nullable'] ?? false) === true) {
            return;
        }

        $valid = match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || (is_float($value) && is_finite($value)),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            default => false,
        };

        if (! $valid) {
            throw new InvalidArgumentException("Tipe {$kind} tidak valid: {$name} harus {$type}");
        }

        if (isset($definition['enum']) && ! in_array($value, $definition['enum'], true)) {
            throw new InvalidArgumentException("Nilai {$kind} tidak valid: {$name}");
        }

        if (is_string($value) && isset($definition['length']) && mb_strlen($value) > $definition['length']) {
            throw new InvalidArgumentException("Panjang {$kind} tidak valid: {$name}");
        }

        if (is_string($value) && isset($definition['format']) && ! $this->matchesFormat($value, $definition['format'])) {
            throw new InvalidArgumentException("Format {$kind} tidak valid: {$name}");
        }

        if (($definition['unsigned'] ?? false) === true && is_int($value) && $value < 0) {
            throw new InvalidArgumentException("Nilai {$kind} tidak valid: {$name}");
        }

        if (is_int($value) || is_float($value)) {
            if (isset($definition['minimum']) && $value < $definition['minimum']) {
                throw new InvalidArgumentException("Nilai {$kind} tidak valid: {$name}");
            }

            if (isset($definition['maximum']) && $value > $definition['maximum']) {
                throw new InvalidArgumentException("Nilai {$kind} tidak valid: {$name}");
            }

            if (isset($definition['precision'], $definition['scale']) && ! $this->fitsDecimal($value, $definition['precision'], $definition['scale'])) {
                throw new InvalidArgumentException("Presisi {$kind} tidak valid: {$name}");
            }
        }
    }

    private function fitsDecimal(int|float $value, int $precision, int $scale): bool
    {
        if (is_int($value)) {
            return strlen((string) abs($value)) <= $precision - $scale;
        }

        $float = (float) $value;

        // Nilai yang sah pada skala ini tidak berubah ketika dibulatkan ke skala
        // itu. Memeriksa lewat `sprintf('%.14F')` memaparkan galat representasi
        // biner sehingga hasil pembulatan dua desimal yang sah - misalnya
        // pemecahan PPN inklusif 684684.68 - ikut tertolak.
        if (round($float, $scale) !== $float) {
            return false;
        }

        $normalized = number_format(abs($float), $scale, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $wholeDigits = max(1, strlen(ltrim($whole, '0')));

        return strlen($fraction) <= $scale
            && $wholeDigits <= $precision - $scale
            && $wholeDigits + strlen($fraction) <= $precision;
    }

    private function matchesFormat(string $value, string $format): bool
    {
        if ($format === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($format === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value;
        }

        if ($format === 'date-time') {
            try {
                new DateTimeImmutable($value);

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }
}
