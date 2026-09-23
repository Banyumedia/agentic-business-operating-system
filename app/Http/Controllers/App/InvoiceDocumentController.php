<?php

namespace App\Http\Controllers\App;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Http\Controllers\Controller;
use App\Services\BusinessIdentityStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Dokumen tagihan siap cetak (T-52).
 *
 * Isolasi tenant tidak dijaga oleh pemeriksaan tambahan di sini, melainkan oleh
 * `EntityRepository::for()` yang selalu ter-scope company aktif: id dari URL yang
 * menunjuk tagihan usaha lain sederhananya tidak ditemukan.
 */
class InvoiceDocumentController extends Controller
{
    public function show(int $invoice): View
    {
        $registry = app(DynamicMenuRegistry::class);

        // Gerbang yang sama dengan menu tagihan; tanpa kapabilitasnya dokumen
        // ini pun tidak ada.
        abort_unless($registry->hasPath('accounting', 'invoices'), 404);
        abort_unless($registry->isModuleVisible('accounting'), 403);

        $definition = $registry->routeDefinition('accounting', 'invoices');
        abort_if($definition === null, 403);

        $company = app(CompanyContext::class)->current();
        $entity = $definition['entity'];

        $record = app(EntityRepository::class)->for($company, $entity)->find($invoice);
        abort_if($record === null, 404);

        // Draf belum menagih siapa pun, jadi tidak boleh keluar sebagai dokumen
        // resmi yang bisa dikirim ke pelanggan.
        abort_if(($record['status'] ?? 'draft') === 'draft', 403, 'Tagihan draf belum dapat dicetak. Terbitkan lebih dulu.');

        $lineEntity = Str::singular($entity).'_lines';
        $foreignKey = Str::singular($entity).'_id';

        $lines = array_values(array_filter(
            app(EntityRepository::class)->for($company, $lineEntity)->all(),
            static fn (array $line): bool => (int) ($line[$foreignKey] ?? 0) === $invoice,
        ));
        usort($lines, static fn (array $left, array $right): int => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));

        $paid = (float) ($record['paid_amount'] ?? 0);
        $total = (float) ($record['grand_total'] ?? 0);

        return view('app.invoice-document', [
            'identity' => app(BusinessIdentityStore::class)->read($company),
            // D-44/TX-04: kosakata pajak hanya untuk usaha PKP - dokumen cetak
            // yang dipegang pelanggan tidak boleh menyebut DPP/PPN untuk
            // non-PKP, bukan sekadar menampilkannya bernilai nol.
            'taxable' => app(BusinessIdentityStore::class)->taxProfile($company)->taxable,
            'contact' => $this->contactOf($company, $record['contact_id'] ?? null),
            'invoice' => $record,
            'lines' => $lines,
            'paid' => $paid,
            'outstanding' => round($total - $paid, 2),
            'documentLabel' => $definition['term'] ?? $definition['label'],
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
