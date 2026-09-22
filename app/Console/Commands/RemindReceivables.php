<?php

namespace App\Console\Commands;

use App\Services\Receivables\ReceivableReminderService;
use Illuminate\Console\Command;

/**
 * Pengingat piutang harian (D-63, T-49).
 *
 * Aman dijalankan berulang: idempotensi dijaga indeks unik pada jejak
 * pengingat, bukan oleh asumsi bahwa scheduler hanya memanggil sekali.
 */
class RemindReceivables extends Command
{
    protected $signature = 'bos:remind-receivables';

    protected $description = 'Kirim pengingat piutang pelanggan yang jatuh tempo lewat WhatsApp (D-63).';

    public function handle(ReceivableReminderService $service): int
    {
        $result = $service->run();

        $this->table(
            ['terkirim', 'dilewati', 'gagal'],
            [[$result['sent'], $result['skipped'], $result['failed']]],
        );

        // Kegagalan kirim bukan kegagalan perintah: jejaknya sengaja tidak
        // ditulis sehingga putaran berikutnya mencoba lagi. Exit non-nol
        // disimpan untuk keadaan yang benar-benar butuh perhatian operator.
        return self::SUCCESS;
    }
}
