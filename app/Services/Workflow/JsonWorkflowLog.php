<?php

namespace App\Services\Workflow;

use App\Contracts\CompanyContext;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use LogicException;
use RuntimeException;

class JsonWorkflowLog
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    /** @param array<string, mixed> $entry */
    public function append(string $company, array $entry): void
    {
        $this->assertCompanyScope($company);
        if (array_is_list($entry)) {
            throw new InvalidArgumentException('Workflow log entry harus berupa object.');
        }
        if (array_key_exists('company', $entry) && $entry['company'] !== $company) {
            throw new InvalidArgumentException('Company pada workflow log tidak sesuai scope.');
        }
        $entry['company'] = $company;

        $path = Storage::disk('company-json')->path("json/{$company}/workflow_log.json");
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        $lock = fopen($path.'.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Lock workflow log tidak dapat dibuat.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Lock workflow log tidak dapat diperoleh.');
            }

            $this->assertCompanyScope($company);
            $entries = $this->read($path);
            $operationId = $this->operationId($entry, requireApprovalId: true);
            if ($operationId !== null) {
                foreach ($entries as $existing) {
                    if ($this->operationId($existing, requireApprovalId: false) !== $operationId) {
                        continue;
                    }
                    if (! $this->sameOperation($existing, $entry)) {
                        throw new LogicException('Operation ID workflow log sudah dipakai oleh entry berbeda.');
                    }

                    return;
                }
            }
            $entries[] = $entry;
            $contents = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
            $this->writeAtomically($path, $contents);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return list<array<string, mixed>> */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Workflow log tidak dapat dibaca.');
        }
        if (! str_starts_with(ltrim($contents), '[')) {
            throw new JsonException('Root workflow log harus berupa JSON array.');
        }

        $entries = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($entries) || ! array_is_list($entries)) {
            throw new JsonException('Root workflow log harus berupa JSON array.');
        }
        foreach ($entries as $entry) {
            if (! is_array($entry) || array_is_list($entry)) {
                throw new JsonException('Setiap workflow log harus berupa JSON object.');
            }
        }

        return $entries;
    }

    private function writeAtomically(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), basename($path).'.tmp-');
        if ($temporary === false) {
            throw new RuntimeException('File sementara workflow log tidak dapat dibuat.');
        }

        try {
            $bytes = file_put_contents($temporary, $contents);
            if ($bytes !== strlen($contents) || ! rename($temporary, $path)) {
                throw new RuntimeException('Workflow log tidak dapat diganti secara atomik.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function assertCompanyScope(string $company): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)) {
            throw new InvalidArgumentException('Company workflow tidak valid.');
        }
        if ($company !== $this->companyContext->current()) {
            throw new LogicException('Akses workflow lintas company ditolak.');
        }
    }

    /** @param array<string, mixed> $entry */
    private function operationId(array $entry, bool $requireApprovalId): ?string
    {
        $effects = $entry['effects'] ?? [];
        if (! is_array($effects) || ! array_is_list($effects)) {
            throw new InvalidArgumentException('Effects workflow log harus berupa list.');
        }

        $operationId = null;
        foreach ($effects as $effect) {
            if (! is_array($effect) || array_is_list($effect)) {
                throw new InvalidArgumentException('Setiap effect workflow log harus berupa object.');
            }
            if (! is_string($effect['effect'] ?? null) || $effect['effect'] === '') {
                throw new InvalidArgumentException('Key effect workflow log tidak valid.');
            }
            if ($effect['effect'] !== 'approval.request') {
                continue;
            }
            if (! array_key_exists('operation_id', $effect)) {
                if ($requireApprovalId) {
                    throw new InvalidArgumentException('Operation ID approval workflow wajib ada.');
                }

                continue;
            }
            if (! is_string($effect['operation_id'])
                || ! preg_match('/^[a-f0-9]{64}$/', $effect['operation_id'])) {
                throw new InvalidArgumentException('Operation ID workflow log tidak valid.');
            }
            if ($operationId !== null) {
                throw new InvalidArgumentException('Workflow log hanya boleh memiliki satu operation ID approval.');
            }

            $operationId = $effect['operation_id'];
        }

        return $operationId;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function sameOperation(array $left, array $right): bool
    {
        unset($left['occurred_at'], $right['occurred_at']);

        return $this->canonicalize($left) === $this->canonicalize($right);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
