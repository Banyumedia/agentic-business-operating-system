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
            if ($daysOverdue === 30) {
                $this->notify($company, 'Peringatan 1: Akun Anda dibekukan karena tagihan menunggak 30 hari.');
                $this->recordNotification($membership);
            }
        } elseif ($daysOverdue >= 60 && $daysOverdue < 83) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 60) {
                $this->notify($company, 'Peringatan 2: Akun Anda menunggak 60 hari. Data Anda berisiko dihapus dalam 30 hari.');
                $this->recordNotification($membership);
            }
        } elseif ($daysOverdue >= 83 && $daysOverdue < 90) {
            if ($membership->status !== 'frozen') {
                $membership->update(['status' => 'frozen']);
            }
            if ($daysOverdue === 83) {
                $this->notify($company, 'Peringatan 3: H-7 penghapusan data permanen. Segera lakukan pembayaran.');
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

    private function notify(Company $company, string $message): void
    {
        $owner = $company->owner;
        if ($owner && $owner->wa_number) {
            $this->hermesClient->sendWhatsApp($owner->wa_number, $message);
        }
    }

    private function recordNotification($membership): void
    {
        $metadata = $membership->metadata ?? [];
        $metadata['dunning_notified_at'][] = now()->toIso8601String();
        $membership->update(['metadata' => $metadata]);
    }
}
