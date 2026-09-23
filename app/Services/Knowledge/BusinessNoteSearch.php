<?php

namespace App\Services\Knowledge;

use App\Models\BusinessNote;
use Illuminate\Support\Str;

/**
 * Pencarian basis pengetahuan usaha untuk bot (T-107c).
 *
 * Mengembalikan potongan relevan berbatas karakter, BUKAN seluruh isi
 * catatan. Mengirim seluruh catatan ke prompt menabrak context window,
 * menaikkan biaya token, dan membocorkan hal yang tidak relevan ke
 * percakapan yang tidak memintanya.
 */
class BusinessNoteSearch
{
    public const SNIPPET_LENGTH = 400;

    /**
     * @return list<array{id: int, title: string, snippet: string, sensitive: bool}>
     */
    public function search(int $companyId, string $query, int $limit = 5): array
    {
        $query = trim($query);
        $builder = BusinessNote::where('company_id', $companyId);

        if ($query !== '') {
            $builder->where(fn ($q) => $q
                ->where('title', 'like', '%'.$query.'%')
                ->orWhere('content', 'like', '%'.$query.'%'));
        }

        return $builder
            ->orderByDesc('updated_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (BusinessNote $note): array => [
                'id' => $note->id,
                'title' => $note->title,
                'snippet' => $this->snippetOf($note->content, $query),
                'sensitive' => $note->sensitive,
            ])
            ->all();
    }

    /**
     * Potongan di sekitar kecocokan pertama bila query cocok isi, atau awal
     * catatan bila kecocokan hanya pada judul / query kosong.
     */
    private function snippetOf(string $content, string $query): string
    {
        $position = $query !== '' ? mb_stripos($content, $query) : false;

        if ($position === false) {
            return Str::limit($content, self::SNIPPET_LENGTH, '…');
        }

        $start = max(0, $position - 80);
        $snippet = mb_substr($content, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '').trim($snippet).(mb_strlen($content) > $start + self::SNIPPET_LENGTH ? '…' : '');
    }
}
