<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceReminder;
use App\Models\User;
use App\Services\HermesNodeClient;
use App\Services\Receivables\ReceivableReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * T-49 (D-63): pengingat piutang lewat WhatsApp.
 *
 * Tiga sifat yang diuji lebih dulu karena inilah yang bisa merusak kepercayaan
 * pemilik usaha: tidak mengirim dua kali, tidak mengirim ke nomor yang belum
 * terverifikasi, dan tidak pernah mengingatkan tagihan yang tidak menagih apa
 * pun (draf/lunas/void).
 */
class ReceivableReminderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{company: string, to: string, message: string}> */
    private array $sent = [];

    private bool $deliveryFails = false;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['wa_number' => '628110000001', 'wa_is_verified' => true]);
        $this->company = Company::create([
            'name' => 'Usaha Uji',
            'slug' => 'usaha-uji',
            'owner_user_id' => $this->owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);

        $test = $this;
        $this->app->bind(HermesNodeClient::class, fn (): HermesNodeClient => new class($test) extends HermesNodeClient
        {
            public function __construct(private readonly ReceivableReminderTest $test) {}

            public function sendWhatsAppMessage(string $companyId, string $to, string $message): void
            {
                $this->test->recordDelivery($companyId, $to, $message);
            }
        });
    }

    public function recordDelivery(string $companyId, string $to, string $message): void
    {
        if ($this->deliveryFails) {
            throw new RuntimeException('Node Hermes tidak tersedia.');
        }

        $this->sent[] = ['company' => $companyId, 'to' => $to, 'message' => $message];
    }

    public function test_reminder_is_sent_one_day_before_the_due_date(): void
    {
        $invoice = $this->invoice('INV-1', due: '2026-09-23', total: 1000000, paid: 250000);

        $result = $this->runAt('2026-09-22');

        $this->assertSame(1, $result['sent']);
        $this->assertCount(1, $this->sent);
        $this->assertSame('628110000001', $this->sent[0]['to']);
        $this->assertStringContainsString('INV-1', $this->sent[0]['message']);
        // Sisa bayar, bukan nilai penuh tagihan.
        $this->assertStringContainsString('750.000', $this->sent[0]['message']);
        $this->assertStringContainsString('jatuh tempo besok', $this->sent[0]['message']);

        $this->assertDatabaseHas('customer_invoice_reminders', [
            'customer_invoice_id' => $invoice->id,
            'stage' => 'due_minus_1',
        ]);
    }

    public function test_negative_second_run_on_the_same_day_does_not_send_again(): void
    {
        $this->invoice('INV-1', due: '2026-09-23', total: 1000000, paid: 0);

        $this->runAt('2026-09-22');
        $this->sent = [];

        // Scheduler terpanggil dua kali, atau job di-retry.
        $result = $this->runAt('2026-09-22');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame([], $this->sent);
        $this->assertSame(1, CustomerInvoiceReminder::count());
    }

    public function test_each_stage_sends_exactly_once(): void
    {
        $this->invoice('INV-1', due: '2026-09-22', total: 500000, paid: 0);

        $this->runAt('2026-09-21'); // H-1
        $this->runAt('2026-09-23'); // H+1
        $this->runAt('2026-09-29'); // H+7
        $this->runAt('2026-10-22'); // H+30
        $this->runAt('2026-10-22'); // ulangi tahap terakhir

        $this->assertCount(4, $this->sent);
        $this->assertSame(
            ['due_minus_1', 'due_plus_1', 'due_plus_7', 'due_plus_30'],
            CustomerInvoiceReminder::orderBy('id')->pluck('stage')->all(),
        );
    }

    public function test_negative_unverified_owner_number_is_fail_closed_and_retried_later(): void
    {
        $this->owner->update(['wa_is_verified' => false]);
        $this->invoice('INV-1', due: '2026-09-23', total: 100000, paid: 0);

        $result = $this->runAt('2026-09-22');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame([], $this->sent);
        // Jejak tidak ditulis, jadi pengingat masih bisa terkirim setelah
        // nomornya diverifikasi.
        $this->assertSame(0, CustomerInvoiceReminder::count());

        $this->owner->update(['wa_is_verified' => true]);
        $this->assertSame(1, $this->runAt('2026-09-22')['sent']);
    }

    public function test_negative_delivery_failure_is_not_recorded_as_sent(): void
    {
        $this->invoice('INV-1', due: '2026-09-23', total: 100000, paid: 0);
        $this->deliveryFails = true;

        $result = $this->runAt('2026-09-22');

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, CustomerInvoiceReminder::count());

        $this->deliveryFails = false;
        $this->assertSame(1, $this->runAt('2026-09-22')['sent']);
    }

    public function test_negative_draft_paid_and_void_invoices_are_never_reminded(): void
    {
        $this->invoice('INV-DRAFT', due: '2026-09-23', total: 100000, paid: 0, status: 'draft');
        $this->invoice('INV-PAID', due: '2026-09-23', total: 100000, paid: 100000, status: 'paid');
        $this->invoice('INV-VOID', due: '2026-09-23', total: 100000, paid: 0, status: 'void');

        $result = $this->runAt('2026-09-22');

        $this->assertSame(0, $result['sent']);
        $this->assertSame([], $this->sent);
    }

    public function test_negative_fully_settled_invoice_is_not_reminded_even_if_status_lags(): void
    {
        // Status bisa tertinggal; yang menentukan adalah sisa bayar.
        $this->invoice('INV-1', due: '2026-09-23', total: 100000, paid: 100000, status: 'partial');

        $this->assertSame(0, $this->runAt('2026-09-22')['sent']);
    }

    public function test_negative_invoice_without_a_due_date_is_never_reminded(): void
    {
        $this->invoice('INV-1', due: null, total: 100000, paid: 0);

        $this->assertSame(0, $this->runAt('2026-09-22')['sent']);
    }

    public function test_negative_reminder_goes_only_to_the_owner_of_that_company(): void
    {
        $otherOwner = User::factory()->create(['wa_number' => '628999999999', 'wa_is_verified' => true]);
        $otherCompany = Company::create([
            'name' => 'Usaha Tetangga',
            'slug' => 'usaha-tetangga',
            'owner_user_id' => $otherOwner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);

        $this->invoice('INV-KITA', due: '2026-09-23', total: 100000, paid: 0);
        CustomerInvoice::create([
            'company_id' => $otherCompany->id,
            'number' => 'INV-TETANGGA',
            'title' => 'Tagihan tetangga',
            'status' => 'issued',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-23',
            'grand_total' => 200000,
            'paid_amount' => 0,
        ]);

        $this->runAt('2026-09-22');

        $recipients = array_column($this->sent, 'to');
        sort($recipients);
        $this->assertSame(['628110000001', '628999999999'], $recipients);

        // Setiap pesan hanya menyebut tagihan milik company-nya sendiri.
        foreach ($this->sent as $delivery) {
            $expected = $delivery['to'] === '628110000001' ? 'INV-KITA' : 'INV-TETANGGA';
            $this->assertStringContainsString($expected, $delivery['message']);
        }
    }

    public function test_reminders_can_be_switched_off_by_config(): void
    {
        $this->invoice('INV-1', due: '2026-09-23', total: 100000, paid: 0);
        config(['receivables.reminders.enabled' => false]);

        $this->assertSame(0, $this->runAt('2026-09-22')['sent']);
        $this->assertSame(0, CustomerInvoiceReminder::count());
    }

    public function test_stage_schedule_comes_from_config_not_from_code(): void
    {
        $this->invoice('INV-1', due: '2026-09-30', total: 100000, paid: 0);
        config(['receivables.reminders.stages' => [['code' => 'due_minus_14', 'offset_days' => -14]]]);

        $this->assertSame(1, $this->runAt('2026-09-16')['sent']);
        $this->assertSame('due_minus_14', CustomerInvoiceReminder::first()->stage);
    }

    public function test_reminder_path_does_not_touch_the_platform_dunning_ladder(): void
    {
        // D-63: piutang tenant dan tagihan langganan platform adalah dua hal
        // berbeda; jalurnya tidak boleh saling memakai.
        $source = file_get_contents(app_path('Services/Receivables/ReceivableReminderService.php'));

        $this->assertStringNotContainsString('DunningLadder', $source);
        $this->assertStringNotContainsString('BillingCheckExpiring', $source);
        $this->assertStringNotContainsString('App\\Models\\Invoice', $source);
    }

    /** @return array{sent: int, skipped: int, failed: int} */
    private function runAt(string $today): array
    {
        return app(ReceivableReminderService::class)->run(CarbonImmutable::parse($today));
    }

    private function invoice(
        string $number,
        ?string $due,
        float $total,
        float $paid,
        string $status = 'issued',
    ): CustomerInvoice {
        return CustomerInvoice::create([
            'company_id' => $this->company->id,
            'number' => $number,
            'title' => 'Tagihan '.$number,
            'status' => $status,
            'issue_date' => '2026-09-01',
            'due_date' => $due,
            'grand_total' => $total,
            'paid_amount' => $paid,
        ]);
    }
}
