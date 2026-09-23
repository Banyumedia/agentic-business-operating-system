<?php

namespace App\Services\Knowledge;

use App\Models\BusinessNote;
use App\Models\User;
use App\Services\Billing\BusinessNoteQuotaGate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penulisan catatan basis pengetahuan usaha (T-107b).
 *
 * Aturan keras: bot HANYA boleh menambah - catatan baru, atau menambah baris
 * di bagian bawah catatan yang sudah ada. Menimpa isi tulisan manusia adalah
 * owner-only lewat web (`overwriteByOwner()`), bukan lewat jalur ini. Satu
 * salah tafsir bot tidak boleh menghapus SOP yang disusun manusia - riwayat
 * git/backup adalah satu-satunya cara memulihkannya kalau itu terjadi, dan
 * kita tidak ingin bergantung padanya.
 */
class BusinessNoteWriter
{
    public function __construct(private readonly BusinessNoteQuotaGate $quota) {}

    /**
     * Bot membuat catatan baru. Selalu `author_type = bot`, `created_by_user_id`
     * null (bot bukan baris `users`).
     */
    public function createByBot(int $companyId, string $title, string $content, bool $sensitive = false): BusinessNote
    {
        $this->quota->assertContentWithinLimit($content);
        $this->quota->assertCanAddNote($companyId);

        return DB::transaction(fn (): BusinessNote => BusinessNote::create([
            'company_id' => $companyId,
            'title' => $title,
            'content' => $content,
            'author_type' => 'bot',
            'created_by_user_id' => null,
            'sensitive' => $sensitive,
        ]));
    }

    /**
     * Bot menambah ke catatan yang SUDAH ADA. Isi lama tidak pernah dihapus -
     * baris baru ditambahkan di bawahnya dengan pemisah eksplisit. `author_type`
     * berubah jadi `bot` untuk menandai versi ini punya kontribusi bot, tapi
     * teks lama (termasuk tulisan manusia) tetap ada di dalam `content`.
     *
     * @throws RuntimeException bila catatan tidak ditemukan pada company ini.
     */
    public function appendByBot(int $companyId, int $noteId, string $addition): BusinessNote
    {
        $note = BusinessNote::where('company_id', $companyId)->find($noteId);
        if ($note === null) {
            throw new RuntimeException('Catatan tidak ditemukan.');
        }

        $merged = rtrim($note->content)."\n\n---\n".trim($addition);
        $this->quota->assertContentWithinLimit($merged);

        $note->content = $merged;
        $note->author_type = 'bot';
        $note->save();

        return $note;
    }

    /**
     * Owner menimpa isi catatan lewat web. Satu-satunya jalur yang boleh
     * mengganti (bukan menambah) isi tulisan manusia maupun bot.
     */
    public function overwriteByOwner(int $companyId, int $noteId, string $title, string $content, User $owner, bool $sensitive): BusinessNote
    {
        $this->quota->assertContentWithinLimit($content);

        $note = BusinessNote::where('company_id', $companyId)->find($noteId);
        if ($note === null) {
            throw new RuntimeException('Catatan tidak ditemukan.');
        }

        $note->title = $title;
        $note->content = $content;
        $note->author_type = 'user';
        $note->created_by_user_id = $owner->id;
        $note->sensitive = $sensitive;
        $note->save();

        return $note;
    }
}
