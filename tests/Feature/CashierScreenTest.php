<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\CashierScreen;
use App\Services\BusinessIdentityStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

class CashierScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/pos-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_non_taxable_company_never_renders_tax_vocabulary(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        $html = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->assertOk()
            ->html();

        // D-44: kata DPP dan PPN tidak boleh muncul sama sekali, bukan Rp 0.
        $this->assertSame(0, preg_match_all('/\b(?:DPP|PPN)\b/', $html));
    }

    public function test_taxable_company_renders_an_inclusive_split_that_adds_up(): void
    {
        $this->useCompany('salon-ayu', 'salon', [
            'tax_mode' => 'taxable',
            'price_includes_tax' => true,
            'tax_rate' => 0.11,
        ]);
        $this->seedItems();

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])->call('addItem', 1);
        $totals = $component->viewData('totals');

        $component->assertSee('DPP')->assertSee('PPN');

        // Harga 305.000 sudah termasuk PPN: total yang dibayar tidak bergeser.
        $this->assertSame(305000.0, $totals['grand_total']);
        $this->assertSame(274774.77, $totals['dpp']);
        $this->assertSame(30225.23, $totals['tax']);
        $this->assertSame(305000.0, round($totals['dpp'] + $totals['tax'], 2));
    }

    public function test_exclusive_company_adds_tax_on_top_of_the_price(): void
    {
        $this->useCompany('salon-ayu', 'salon', [
            'tax_mode' => 'taxable',
            'price_includes_tax' => false,
            'tax_rate' => 0.11,
        ]);
        $this->seedItems();

        $totals = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->viewData('totals');

        $this->assertSame(305000.0, $totals['dpp']);
        $this->assertSame(33550.0, $totals['tax']);
        $this->assertSame(338550.0, $totals['grand_total']);
    }

    public function test_cart_lines_can_be_added_increased_and_removed(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('addItem', 1)
            ->call('addItem', 2);

        $this->assertCount(2, $component->get('cart'));
        // JSON tidak memisahkan 2 dan 2.0, jadi yang dijaga adalah nilainya.
        $this->assertEquals(2, $component->get('cart')[0]['qty']);
        // 2 x 305.000 + 1 x 455.000
        $this->assertSame(1065000.0, $component->viewData('totals')['subtotal']);

        $component->call('setQty', 0, '3');
        $this->assertEquals(3, $component->get('cart')[0]['qty']);

        // Jumlah nol menghapus baris, bukan menyimpan baris kosong.
        $component->call('setQty', 0, '0');
        $this->assertCount(1, $component->get('cart'));

        $component->call('removeLine', 0);
        $this->assertSame([], $component->get('cart'));
    }

    public function test_checkout_requires_a_normalised_typed_confirmation(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();
        $orders = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('requestAction', 'checkout');

        $html = $component->html();
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        // D-45: teks wajib memuat nominal konkret.
        $this->assertStringContainsString('Rp 305.000', $html);
        // D-45: tombol aksi inert saat dialog muncul.
        $this->assertStringContainsString('$wire.confirmAction()', $html);
        $this->assertStringContainsString('x-bind:disabled="! ready"', $html);
        $this->assertStringContainsString('autocapitalize="characters"', $html);

        // Singkatan ditolak.
        $component->set('confirmPhrase', 'y')->call('confirmAction')->assertSee('Ketik YA tepat');
        $this->assertSame([], $orders->all());

        // Spasi dan huruf kecil diterima setelah normalisasi.
        $component->set('confirmPhrase', '  ya  ')->call('confirmAction')->assertSet('failure', null);
        $this->assertCount(1, $orders->all());
    }

    public function test_checkout_writes_the_order_with_its_lines_and_tax_split(): void
    {
        $this->useCompany('salon-ayu', 'salon', [
            'id' => 7,
            'tax_mode' => 'taxable',
            'price_includes_tax' => true,
            'tax_rate' => 0.11,
        ]);
        $this->seedItems();

        Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('addItem', 2)
            ->set('paymentMethod', 'qris')
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'YA')
            ->call('confirmAction')
            ->assertSet('failure', null)
            ->assertSet('cart', [])
            ->assertSee('tersimpan');

        $order = app(EntityRepository::class)->for('salon-ayu', 'orders')->all()[0];
        $this->assertSame(7, $order['business_identity_id']);
        $this->assertSame('qris', $order['payment_method']);
        // Nilai bulat kembali dari JSON sebagai int, jadi yang dijaga nilainya.
        $this->assertEquals(760000, $order['subtotal']);
        $this->assertEquals(760000, $order['grand_total']);
        $this->assertSame(684684.68, $order['dpp']);
        $this->assertSame(75315.32, $order['tax_amount']);
        $this->assertEquals(760000, round($order['dpp'] + $order['tax_amount'], 2));
        $this->assertNotNull($order['paid_at']);

        $lines = app(EntityRepository::class)->for('salon-ayu', 'order_lines')->all();
        $this->assertCount(2, $lines);
        $this->assertSame([$order['id'], $order['id']], array_column($lines, 'order_id'));
        $this->assertEquals(760000, array_sum(array_column($lines, 'line_total')));
    }

    public function test_checkout_reloads_server_prices_and_rejects_unknown_payment_methods(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->set('cart.0.unit_price', 1)
            ->set('cart.0.description', 'Harga palsu')
            ->set('paymentMethod', 'gratis')
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'YA')
            ->call('confirmAction');

        $component->assertSee('Metode pembayaran tidak valid');
        $this->assertSame([], app(EntityRepository::class)->for('bengkel-arka', 'orders')->all());

        $component->set('paymentMethod', 'cash')->call('confirmAction')->assertSet('failure', null);
        $order = app(EntityRepository::class)->for('bengkel-arka', 'orders')->all()[0];
        $line = app(EntityRepository::class)->for('bengkel-arka', 'order_lines')->all()[0];

        $this->assertEquals(305000, $order['subtotal']);
        $this->assertEquals(305000, $line['unit_price']);
        $this->assertSame('Layanan Uji', $line['description']);
    }

    public function test_checkout_rejects_money_beyond_safe_exact_precision(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        app(EntityRepository::class)->for('bengkel-arka', 'items')->save([
            'id' => 1,
            'name' => 'Nilai batas',
            'price' => 1000000000001,
            'unit' => 'unit',
        ]);

        Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->assertSee('batas presisi aman');

        $this->assertSame([], app(EntityRepository::class)->for('bengkel-arka', 'orders')->all());
    }

    public function test_checkout_is_all_or_nothing_and_replay_is_a_no_op(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        $linesPath = $this->jsonPath.DIRECTORY_SEPARATOR.'bengkel-arka'.DIRECTORY_SEPARATOR.'order_lines.json';
        (new Filesystem)->ensureDirectoryExists(dirname($linesPath));
        file_put_contents($linesPath, '{rusak');

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'YA');

        try {
            $component->call('confirmAction');
        } catch (Throwable) {
            // Repository harus gagal sebelum parent ditulis.
        }

        $this->assertSame([], app(EntityRepository::class)->for('bengkel-arka', 'orders')->all());

        file_put_contents($linesPath, "[]\n");
        $component->call('confirmAction')->assertSet('failure', null);
        $this->assertCount(1, app(EntityRepository::class)->for('bengkel-arka', 'orders')->all());
        $this->assertCount(1, app(EntityRepository::class)->for('bengkel-arka', 'order_lines')->all());

        // Aksi UI yang diputar ulang setelah sukses tidak boleh menggandakan transaksi.
        $component->call('confirmAction');
        $this->assertCount(1, app(EntityRepository::class)->for('bengkel-arka', 'orders')->all());
        $this->assertCount(1, app(EntityRepository::class)->for('bengkel-arka', 'order_lines')->all());
    }

    public function test_business_identity_is_tenant_scoped_and_missing_configuration_fails_closed(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);

        $this->expectException(\LogicException::class);
        app(BusinessIdentityStore::class)->read('salon-ayu');
    }

    public function test_checkout_refuses_missing_tax_mode_or_identity_id(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', []);
        $this->seedItems();

        try {
            Livewire::test(CashierScreen::class, ['module' => 'pos']);
            $this->fail('Konfigurasi fiskal yang hilang harus fail-closed.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('Mode pajak', $exception->getMessage());
        }
    }

    public function test_checkout_starts_at_the_first_workflow_stage_when_one_exists(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'ya')
            ->call('confirmAction');

        // Alur kerja bengkel dimulai pada tahap pertama yang dideklarasikan preset.
        $order = app(EntityRepository::class)->for('bengkel-arka', 'orders')->all()[0];
        $this->assertSame('masuk', $order['stage']);
    }

    public function test_clearing_the_cart_uses_two_buttons_without_typing(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', 1)
            ->call('requestAction', 'clearCart');

        // Tingkat 2: tidak ada input ketik-untuk-menegaskan.
        $component->assertDontSee('autocapitalize="characters"', false);
        $this->assertStringContainsString('$wire.confirmAction()', $component->html());
        $this->assertStringContainsString('x-bind:disabled="! ready"', $component->html());
        $this->assertStringContainsString('bg-[var(--erp-danger)]', $component->html());
        $this->assertStringContainsString('x-trap.inert.noscroll="true"', $component->html());
        $this->assertStringContainsString('target?.focus()', $component->html());

        $component->call('cancelAction');
        $this->assertCount(1, $component->get('cart'));

        $component->call('requestAction', 'clearCart')->call('confirmAction');
        $this->assertSame([], $component->get('cart'));
    }

    public function test_unknown_tax_mode_fails_loudly_instead_of_dropping_tax(): void
    {
        $this->useCompany('salon-ayu', 'salon', ['tax_mode' => 'kira-kira']);
        $this->seedItems();

        try {
            Livewire::test(CashierScreen::class, ['module' => 'pos'])->assertOk();
            $this->fail('Mode pajak tidak dikenal seharusnya ditolak, bukan dianggap non-PKP.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('Mode pajak', $exception->getMessage());
        }
    }

    public function test_cashier_refuses_to_act_after_the_active_company_changes(): void
    {
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
        $this->seedItems();
        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])->call('addItem', 1);

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('addItem', 1)->assertForbidden();
    }

    public function test_cashier_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/CashierScreen.php')),
            file_get_contents(app_path('Services/TaxRateService.php')),
            file_get_contents(resource_path('views/livewire/screens/cashier.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    /** @param array<string, mixed> $identity */
    private function useCompany(string $company, string $preset, array $identity): void
    {
        Storage::disk('company-json')->put(
            "json/{$company}/settings.json",
            json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            "json/{$company}/business_identity.json",
            json_encode(
                array_merge(['id' => 1, 'name' => 'Usaha Uji', 'preset' => $preset], $identity),
                JSON_THROW_ON_ERROR,
            ),
        );

        app(CompanyContext::class)->setCurrent($company);
    }

    private function seedItems(): void
    {
        $items = app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'items');
        $items->save(['id' => 1, 'name' => 'Layanan Uji', 'price' => 305000, 'unit' => 'sesi']);
        $items->save(['id' => 2, 'name' => 'Barang Uji', 'price' => 455000, 'unit' => 'pcs']);
    }
}
