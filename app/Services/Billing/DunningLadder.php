<?php

namespace App\Services\Billing;

use App\Contracts\HermesNodeClient;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

class DunningLadder
{
    public function __construct(private HermesNodeClient $hermesClient) {}

    public function process(Company $company, int $daysOverdue): void
    {
        $membership = $company->memberships()->first();
        if (! $membership) {
            return;
        }

        if ($daysOverdue === 0) {
            $membership->update(['status' => 'ai_suspended']);
            $this->notify($company, 'Layanan AI ditangguhkan karena tagihan belum dibayar. Akses web tetap tersedia.');
        } elseif ($daysOverdue >= 7 && $daysOverdue < 30) {
            if ($membership->status !== 'read_only') {
                $membership->update(['status' => 'read_only']);
                $this->notify($company, 'Akses tulis ditangguhkan. Akun Anda sekarang dalam mode read-only. Ekspor data tetap bisa dilakukan.');
            }
        } elseif ($daysOverdue >= 30 && $daysOverdue < 60) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 30 && $this->notify($company, 'Peringatan 1: Akun Anda dibekukan karena tagihan menunggak 30 hari.')) {
                $this->recordNotification($membership);
            }
        } elseif ($daysOverdue >= 60 && $daysOverdue < 83) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 60 && $this->notify($company, 'Peringatan 2: Akun Anda menunggak 60 hari. Data Anda berisiko dihapus dalam 30 hari.')) {
                $this->recordNotification($membership);
            }
        } elseif ($daysOverdue >= 83 && $daysOverdue < 90) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 83 && $this->notify($company, 'Peringatan 3: H-7 penghapusan data permanen. Segera lakukan pembayaran.')) {
                $this->recordNotification($membership);
            }
        } elseif ($daysOverdue >= 90) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 90) {
                $notifiedDates = $membership->metadata['dunning_notified_at'] ?? [];
                if (count($notifiedDates) >= 3) {
                    // Mark for deletion or delete
                    Log::info(sprintf('Company %s eligible for deletion after 3 warnings.', $company->id));
                }
            }
        }
    }

    public function restore(Company $company): void
    {
        $membership = $company->memberships()->first();
        if (! $membership) {
            return;
        }

        $membership->update(['status' => 'active']);
        $metadata = $membership->metadata ?? [];
        unset($metadata['dunning_notified_at']);
        $membership->update(['metadata' => $metadata]);

        $this->notify($company, 'Pembayaran berhasil. Layanan telah aktif kembali.');
    }

    /**
     * Mengembalikan apakah pesannya **benar-benar** terkirim.
     *
     * Penting karena `recordNotification()` menulis `dunning_notified_at`, dan
     * cabang H+90 memakai "sudah 3 peringatan" sebagai dasar company boleh dihapus.
     * Selama lajur platform dilayani fake yang selalu berhasil, perbedaan ini tidak
     * terlihat; dengan lajur yang nyata (T-69), peringatan yang gagal terkirim tidak
     * boleh ikut dihitung. Akibat yang diterima sadar: tanpa bot platform yang siap,
     * tangga ini tidak akan pernah sampai ke penghapusan data - dan itu arah yang
     * benar.
     */
    private function notify(Company $company, string $message): bool
    {
        $owner = $company->owner;

        if (! $owner || ! $owner->wa_number) {
            Log::warning('Notifikasi dunning tidak dikirim: owner tidak punya nomor WhatsApp.', [
                'company_id' => $company->id,
            ]);

            return false;
        }

        return $this->hermesClient->sendWhatsApp($owner->wa_number, $message);
    }

    private function recordNotification($membership): void
    {
        $metadata = $membership->metadata ?? [];
        $metadata['dunning_notified_at'][] = now()->toIso8601String();
        $membership->update(['metadata' => $metadata]);
    }
}
