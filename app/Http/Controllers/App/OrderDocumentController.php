<?php

namespace App\Http\Controllers\App;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Http\Controllers\Controller;
use App\Services\BusinessIdentityStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;

/**
 * Struk POS siap cetak (MP-03), termal 58mm + faktur A4 dari satu sumber data.
 *
 * Meniru `InvoiceDocumentController` (T-52) apa adanya: isolasi tenant bukan
 * dari pemeriksaan tambahan, melainkan dari `EntityRepository::for()` yang
 * selalu ter-scope company aktif - id order usaha lain sederhananya tidak
 * ditemukan. `orders`/`order_lines` punya schema JSON (D-42), jadi
 * controller ini bekerja untuk kedua driver tanpa cabang - berbeda dari
 * MP-02/MP-09/MP-10 yang harus Eloquent-only karena tabel mereka
 * (`stock_movements`, `retentions`) tidak punya rekan JSON.
 */
class OrderDocumentController extends Controller
{
    public function show(int $order): View
    {
        $registry = app(DynamicMenuRegistry::class);

        // Gerbang yang sama dengan menu kasir; tanpa kapabilitasnya dokumen
        // ini pun tidak ada.
        abort_unless($registry->hasModule('pos'), 404);
        abort_unless($registry->isModuleVisible('pos'), 403);

        $definition = $registry->routeDefinition('pos');
        abort_if($definition === null, 403);

        $company = app(CompanyContext::class)->current();
        $entity = $definition['entity'];

        $record = app(EntityRepository::class)->for($company, $entity)->find($order);
        abort_if($record === null, 404);

        // Struk adalah bukti bayar; order yang belum tercatat lunas belum
        // boleh keluar sebagai dokumen resmi ke tangan pelanggan.
        abort_if(empty($record['paid_at']), 403, 'Transaksi belum lunas, struk belum dapat dicetak.');

        $lines = array_values(array_filter(
            app(EntityRepository::class)->for($company, 'order_lines')->all(),
            static fn (array $line): bool => (int) ($line['order_id'] ?? 0) === $order,
        ));
        usort($lines, static fn (array $left, array $right): int => ($left['id'] ?? 0) <=> ($right['id'] ?? 0));

        $identity = app(BusinessIdentityStore::class)->read($company);

        return view('app.order-document', [
            // Jalur JSON menulis `name`, jalur Eloquent menulis `legal_name`
            // (T-52 mewarisi celah yang sama - dicatat, tidak ditutup di sini
            // karena bukan file target task ini). Dibaca dengan fallback
            // supaya struk POS sendiri tidak diam-diam kosong di mode Eloquent.
            'identity' => $identity + ['name' => $identity['name'] ?? $identity['legal_name'] ?? 'Usaha'],
            // D-44/TX-04: kosakata pajak hanya untuk usaha PKP - tidak
            // dirender sama sekali untuk non-PKP, bukan ditampilkan nol.
            'taxable' => app(BusinessIdentityStore::class)->taxProfile($company)->taxable,
            'contact' => $this->contactOf($company, $record['contact_id'] ?? null),
            'order' => $record,
            'lines' => $lines,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function contactOf(string $company, mixed $contactId): ?array
    {
        if ($contactId === null || $contactId === '') {
            return null;
        }

        return app(EntityRepository::class)->for($company, 'contacts')->find((int) $contactId);
    }
}
