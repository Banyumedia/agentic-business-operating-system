<?php

namespace App\Services\Billing;

use App\Models\BusinessNote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Kuota + batas ukuran basis pengetahuan usaha per tenant (T-107e).
 *
 * Fail-closed: tabel hilang atau query bermasalah berarti kuota dianggap
 * PENUH (tolak tulis), bukan tanpa batas - mengikuti arah aman `UserQuotaGate`
 * (fail-closed ke tier gratis), tapi di sini "tier gratis" untuk kuota jumlah
 * catatan tidak berarti apa pun; tidak ada jalur aman selain menolak.
 */
class BusinessNoteQuotaGate
{
    public function maxNotes(): int
    {
        return max(1, (int) config('billing.business_notes.max_notes_per_company', 500));
    }

    public function maxContentLength(): int
    {
        return max(1, (int) config('billing.business_notes.max_content_length', 8000));
    }

    public function used(int $companyId): int
    {
        if (! Schema::hasTable('business_notes')) {
            return $this->maxNotes();
        }

        try {
            return BusinessNote::where('company_id', $companyId)->count();
        } catch (QueryException) {
            return $this->maxNotes();
        }
    }

    public function remaining(int $companyId): int
    {
        return max(0, $this->maxNotes() - $this->used($companyId));
    }

    public function canAddNote(int $companyId): bool
    {
        return $this->remaining($companyId) > 0;
    }

    /** @throws RuntimeException bila kuota jumlah catatan penuh. */
    public function assertCanAddNote(int $companyId): void
    {
        if (! $this->canAddNote($companyId)) {
            throw new RuntimeException(
                "Kuota catatan usaha ini sudah penuh ({$this->maxNotes()} catatan). Hapus catatan lama atau hubungi Bos untuk menaikkan batas."
            );
        }
    }

    /** @throws RuntimeException bila isi catatan melebihi batas panjang. */
    public function assertContentWithinLimit(string $content): void
    {
        $limit = $this->maxContentLength();
        $length = mb_strlen($content);

        if ($length > $limit) {
            throw new RuntimeException(
                "Isi catatan melebihi batas {$limit} karakter (panjang saat ini: {$length})."
            );
        }
    }
}
