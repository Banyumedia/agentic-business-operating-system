<?php

namespace App\Services\Receivables;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceReminder;
use App\Services\HermesNodeClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengingat piutang pelanggan lewat WhatsApp (D-63).
 *
 * Yang diingatkan adalah **pemilik usaha**, bukan pelanggannya: mengirim pesan
 * ke nomor pelanggan menyentuh persetujuan pihak ketiga dan reputasi nomor
 * tenant, jadi itu keputusan terpisah. Di sini pemilik diberi tahu piutang mana
 * yang jatuh tempo supaya ia menindaklanjuti sendiri.
 *
 * Tiga sifat yang dijaga:
 * 1. **Idempoten** - jejak tersimpan per tagihan per tahap dengan indeks unik,
 *    sehingga scheduler yang berjalan dua kali tidak mengirim dua kali.
 * 2. **Fail-closed** - nomor pemilik yang belum terverifikasi atau pengiriman
 *    yang gagal tidak dicatat sebagai terkirim, jadi akan dicoba lagi.
 * 3. **Terisolasi** - setiap tagihan hanya dibaca dalam lingkup company-nya.
 */
class ReceivableReminderService
{
    public function __construct(private readonly HermesNodeClient $hermes) {}

    /**
     * Menjalankan satu putaran pengingat.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (config('receivables.reminders.enabled') !== true) {
            return $result;
        }

        foreach ($this->stages() as $stage) {
            $dueDate = $today->subDays($stage['offset_days'])->toDateString();

            foreach ($this->outstandingInvoicesDueOn($dueDate) as $invoice) {
                $outcome = $this->remind($invoice, (string) $stage['code']);
                $result[$outcome]++;
            }
        }

        return $result;
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}[]|string
     */
    private function remind(CustomerInvoice $invoice, string $stage): string
    {
        // Jejak ditulis LEBIH DULU: bila dua proses berjalan bersamaan, indeks
        // unik menolak yang kedua sebelum pesan apa pun terkirim.
        try {
            $reminder = CustomerInvoiceReminder::create([
                'company_id' => $invoice->company_id,
                'customer_invoice_id' => $invoice->id,
                'stage' => $stage,
                'sent_at' => now(),
            ]);
        } catch (QueryException) {
            return 'skipped';
        }

        $owner = $invoice->company?->owner;

        // Nomor belum terverifikasi = tidak ada penerima sah. Jejak dibatalkan
        // supaya putaran berikutnya mencoba lagi setelah nomor diverifikasi.
        if ($owner === null || ! $owner->wa_number || ($owner->wa_is_verified ?? false) !== true) {
            $reminder->delete();

            return 'failed';
        }

        try {
            $this->hermes->sendWhatsAppMessage(
                (string) $invoice->company_id,
                (string) $owner->wa_number,
                $this->message($invoice, $stage),
            );
        } catch (Throwable $exception) {
            // Gagal kirim tidak boleh tercatat sebagai terkirim.
            $reminder->delete();
            Log::warning('Pengingat piutang gagal terkirim', [
                'company_id' => $invoice->company_id,
                'customer_invoice_id' => $invoice->id,
                'stage' => $stage,
                'error' => $exception->getMessage(),
            ]);

            return 'failed';
        }

        return 'sent';
    }

    /**
     * Tagihan yang masih punya sisa bayar dan jatuh tempo pada tanggal tertentu.
     * Draf tidak pernah diingatkan - ia belum menagih siapa pun.
     *
     * @return iterable<CustomerInvoice>
     */
    private function outstandingInvoicesDueOn(string $dueDate): iterable
    {
        return CustomerInvoice::query()
            ->with(['company.owner'])
            ->whereDate('due_date', $dueDate)
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->whereHas('company', fn ($query) => $query->whereNotNull('owner_user_id'))
            ->cursor();
    }

    private function message(CustomerInvoice $invoice, string $stage): string
    {
        $outstanding = (float) $invoice->grand_total - (float) $invoice->paid_amount;
        $due = $invoice->due_date?->toDateString() ?? '-';

        $prefix = str_starts_with($stage, 'due_minus')
            ? 'Tagihan jatuh tempo besok'
            : 'Tagihan lewat jatuh tempo';

        return sprintf(
            '%s: %s (%s), sisa Rp %s, jatuh tempo %s.',
            $prefix,
            $invoice->number,
            $invoice->title,
            number_format($outstanding, 0, ',', '.'),
            $due,
        );
    }

    /** @return list<array{code: string, offset_days: int}> */
    private function stages(): array
    {
        $stages = [];

        foreach ((array) config('receivables.reminders.stages', []) as $stage) {
            if (! is_array($stage) || ! isset($stage['code'], $stage['offset_days'])) {
                continue;
            }

            $stages[] = ['code' => (string) $stage['code'], 'offset_days' => (int) $stage['offset_days']];
        }

        return $stages;
    }

    /** Dipakai command untuk melaporkan cakupan tanpa menebak isi config. */
    public function companiesWithReceivables(): int
    {
        return Company::query()
            ->whereHas('customerInvoices', fn ($query) => $query
                ->whereNotIn('status', ['draft', 'void', 'paid'])
                ->whereColumn('paid_amount', '<', 'grand_total'))
            ->count();
    }
}
