<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Membuktikan layar akuntansi dan payroll benar-benar berisi di tenant demo.
 *
 * Tiga entitas T-54 sempat punya schema, layar, dan menu tanpa satu baris data
 * pun, jadi Bagan Akun, Jurnal, Rincian Jurnal, dan Payroll tampak rusak padahal
 * hanya kosong. Test ini menjaga fixture-nya tetap terpasang dan terbaca layar
 * generik - bukan hanya lolos validator schema.
 */
class AccountingDemoDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('datasource.demo_companies', ['bengkel-arka']);
        Session::put('company_role', 'owner');
    }

    public function test_chart_of_accounts_screen_shows_committed_accounts(): void
    {
        $response = $this->get('/app/accounting/coa?company=bengkel-arka');

        $response->assertStatus(200);
        $response->assertSee('1-1000');
        $response->assertSee('Kas');
        $response->assertSee('Piutang Usaha');
    }

    public function test_journal_screen_shows_committed_journals(): void
    {
        $response = $this->get('/app/accounting/journals?company=bengkel-arka');

        $response->assertStatus(200);
        $response->assertSee('JV-202609-0001');
        $response->assertSee('JV-202609-0005');
    }

    public function test_journal_line_screen_shows_debit_and_credit(): void
    {
        // Inti temuan yang ditutup: debit dan kredit hidup di baris, jadi tanpa
        // layar ini angka jurnal tidak pernah terlihat di mana pun.
        $response = $this->get('/app/accounting/journal-lines?company=bengkel-arka');

        $response->assertStatus(200);
        $response->assertSee('Debit');
        $response->assertSee('Kredit');
    }

    public function test_payroll_screen_shows_committed_periods(): void
    {
        $response = $this->get('/app/hrd/payroll?company=bengkel-arka');

        $response->assertStatus(200);
        $response->assertSee('2026-08');
        $response->assertSee('2026-09');
    }

    public function test_every_demo_journal_is_balanced(): void
    {
        // Data demo tidak boleh mengajari bentuk jurnal yang salah: setiap
        // jurnal harus seimbang debit dan kreditnya.
        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu', 'laundry-bersih'] as $company) {
            $lines = json_decode(
                (string) file_get_contents(storage_path("app/json/{$company}/accounting_journal_lines.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $balances = [];
            foreach ($lines as $line) {
                $journalId = (int) $line['journal_id'];
                $balances[$journalId] = ($balances[$journalId] ?? 0)
                    + (float) $line['debit'] - (float) $line['credit'];
            }

            $this->assertNotEmpty($balances, "Fixture jurnal {$company} kosong.");

            foreach ($balances as $journalId => $balance) {
                $this->assertSame(
                    0.0,
                    $balance,
                    "Jurnal {$company}#{$journalId} tidak seimbang (selisih {$balance}).",
                );
            }
        }
    }
}
