<?php

namespace App\Console\Commands;

use App\Contracts\HermesNodeClient as PlatformSender;
use App\Services\HermesNodeClient as TenantSender;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Mengirim satu pesan WhatsApp sungguhan untuk membuktikan lajurnya hidup.
 *
 * Tanpa perintah ini, satu-satunya cara membuktikan pengiriman adalah menunggu
 * tagihan jatuh tempo atau piutang lewat jatuh tempo - jadi lajur yang rusak baru
 * terlihat pada saat yang paling merugikan. `bos:hermes-ping` hanya membuktikan
 * node hidup; ini membuktikan pesannya benar-benar keluar.
 *
 * Dua lajur sengaja dipisah di sini persis seperti di kode (D-63):
 *
 *   php artisan bos:hermes-send --platform --to=628... --message="Uji"
 *   php artisan bos:hermes-send --company=3 --to=628... --message="Uji"
 *
 * Nomor tujuan wajib ditulis penuh oleh operator. Tidak ada bawaan dan tidak ada
 * "kirim ke semua": perintah yang bisa menyiram banyak nomor dengan satu ketikan
 * tidak layak ada hanya untuk keperluan verifikasi.
 */
class HermesSend extends Command
{
    protected $signature = 'bos:hermes-send
        {--platform : Kirim lewat bot platform (bot CS), bukan bot tenant}
        {--company= : ID company yang atas namanya pesan dikirim (lajur tenant)}
        {--to= : Nomor WhatsApp tujuan}
        {--message=Uji kanal WhatsApp Agentic BOS. : Isi pesan}';

    protected $description = 'Mengirim satu pesan WhatsApp uji lewat lajur platform atau lajur tenant';

    public function handle(PlatformSender $platform, TenantSender $tenant): int
    {
        $to = trim((string) $this->option('to'));
        $message = (string) $this->option('message');

        if ($to === '') {
            $this->error('Nomor tujuan wajib diisi: --to=628...');

            return self::FAILURE;
        }

        if ($this->option('platform') && $this->option('company')) {
            // Keduanya sekaligus berarti operator tidak menyatakan atas nama siapa
            // pesan itu keluar, dan itu justru yang harus jelas.
            $this->error('Pilih satu lajur: --platform atau --company, tidak keduanya.');

            return self::FAILURE;
        }

        if ($this->option('platform')) {
            // Lajur platform mengembalikan bool; sebabnya sudah masuk log aplikasi.
            if (! $platform->sendWhatsApp($to, $message)) {
                $this->error('Lajur platform menolak mengirim. Periksa log: bot CS platform mungkin belum ada atau belum paired.');

                return self::FAILURE;
            }

            $this->info('Terkirim lewat bot platform.');

            return self::SUCCESS;
        }

        if (! $this->option('company')) {
            $this->error('Sebutkan lajurnya: --platform untuk bot platform, atau --company=<id> untuk bot tenant.');

            return self::FAILURE;
        }

        try {
            $tenant->sendWhatsAppMessage((string) $this->option('company'), $to, $message);
        } catch (RuntimeException|Throwable $exception) {
            $this->error('Lajur tenant menolak mengirim: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Terkirim lewat bot tenant.');

        return self::SUCCESS;
    }
}
