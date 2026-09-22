<?php

namespace Tests\Feature\Hermes;

use App\Contracts\HermesNodeClient as PlatformSender;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Billing\DunningLadder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Sisa T-69: lajur **platform** benar-benar mengirim.
 *
 * `App\Contracts\HermesNodeClient` selama ini dibind ke `FakeHermesNodeClient`
 * yang **hanya menulis ke log lalu mengembalikan `true`**. Akibatnya dunning
 * langganan (D-23/D-49) dan notifikasi platform tidak pernah terkirim, dan
 * kegagalannya tidak pernah terlihat — bahkan test lama justru mengunci teks
 * "FAKE WA to ..." sehingga yang terbukti adalah fake-nya, bukan lajurnya.
 *
 * Dua batas yang dijaga di sini dan tidak boleh dilonggarkan:
 *
 * 1. **Pengirimnya bot CS platform, bukan bot dev.** Bot dev punya toolset penuh
 *    dan nomornya internal; memakainya untuk menagih pelanggan membocorkan nomor
 *    itu sekaligus mengundang pelanggan ke kanal yang berwenang menjalankan
 *    perintah. Kalau profil CS tidak ada, lajur ini **menolak** — tidak ada jatuh
 *    kembali ke bot dev.
 * 2. **Gagal kirim tidak boleh tercatat sebagai terkirim.** `DunningLadder`
 *    menulis `dunning_notified_at`, dan cabang H+90 memakai "sudah 3 peringatan"
 *    sebagai dasar company boleh dihapus. Mencatat peringatan yang tidak pernah
 *    sampai berarti menyiapkan penghapusan data atas dasar yang tidak benar.
 */
class PlatformDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.node_secrets', ['ref_uji' => 'rahasia-uji']);
    }

    public function test_the_platform_cs_bot_sends_the_message(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'messageId' => 'ABC'], 200)]);

        $this->platformProfile();

        $this->assertTrue(app(PlatformSender::class)->sendWhatsApp('0812-3456-7890', 'Tagihan langganan jatuh tempo.'));

        Http::assertSent(fn ($request) => $request->url() === 'https://node.uji.test/send'
            && $request['chatId'] === '6281234567890@s.whatsapp.net'
            && $request['message'] === 'Tagihan langganan jatuh tempo.');
    }

    public function test_the_profile_bridge_address_wins_over_the_node_address(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->platformProfile(profileUrl: 'http://127.0.0.1:3001', nodeUrl: 'http://127.0.0.1:3000', secretReference: 'none');

        app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3001/send');
    }

    public function test_negative_without_any_platform_profile_nothing_is_sent(): void
    {
        // Keadaan hari ini. Jawaban yang benar adalah menolak, bukan mengembalikan
        // `true` seperti fake lama.
        Http::fake();

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
    }

    public function test_negative_a_tenant_profile_is_never_used_for_platform_messages(): void
    {
        Http::fake();

        $owner = User::factory()->create();
        $node = $this->node();
        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'status' => 'paired',
            'is_platform_provided' => false,
        ]);

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
    }

    public function test_negative_the_dev_bot_is_not_a_fallback_sender(): void
    {
        // Profil platform ada, tetapi tipenya `primary` — itu bot dev. Lajur ini
        // tetap menolak: memakainya berarti pelanggan menerima pesan dari nomor
        // internal yang berwenang menjalankan perintah.
        Http::fake();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $node = $this->node();
        HermesProfile::factory()->create([
            'owner_user_id' => $admin->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'status' => 'paired',
            'is_platform_provided' => true,
        ]);

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
    }

    public function test_negative_an_unpaired_platform_profile_is_refused(): void
    {
        Http::fake();

        $this->platformProfile(profileStatus: 'unpaired');

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
    }

    public function test_negative_a_node_that_is_not_active_is_refused(): void
    {
        Http::fake();

        $this->platformProfile(nodeStatus: 'maintenance');

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
    }

    public function test_negative_a_missing_secret_is_reported_by_reference_name_not_by_value(): void
    {
        Log::spy();
        Http::fake();

        Config::set('hermes.node_secrets', []);
        $this->platformProfile();

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            $reason = (string) ($context['reason'] ?? '');

            return str_contains($reason, 'ref_uji') && ! str_contains($reason, 'rahasia-uji');
        });
    }

    public function test_negative_the_recipient_number_is_never_written_to_the_log(): void
    {
        // Log bukan tempat data pelanggan. Yang dicatat adalah sebabnya, bukan
        // kepada siapa dan bukan isi pesannya.
        Log::spy();
        Http::fake(['*' => Http::response('', 500)]);

        $this->platformProfile();

        app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Isi pesan rahasia');

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            $flat = $message.json_encode($context);

            return ! str_contains($flat, '6281234567890') && ! str_contains($flat, 'Isi pesan rahasia');
        });
    }

    public function test_negative_a_non_success_response_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Not connected to WhatsApp'], 503)]);

        $this->platformProfile();

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));
    }

    public function test_negative_a_200_that_does_not_confirm_delivery_is_a_failure(): void
    {
        // Bridge bisa menjawab 200 tanpa mengirim apa pun. Tanpa pemeriksaan badan
        // respons, kegagalan node tercatat sebagai terkirim dan tidak pernah
        // dicoba ulang.
        Http::fake(['*' => Http::response(['success' => false, 'error' => 'not paired'], 200)]);

        $this->platformProfile();

        $this->assertFalse(app(PlatformSender::class)->sendWhatsApp('6281234567890', 'Halo'));
    }

    public function test_negative_dunning_does_not_record_a_warning_it_could_not_deliver(): void
    {
        // Cabang H+90 `DunningLadder` memakai "sudah 3 peringatan" sebagai dasar
        // company boleh dihapus. Peringatan yang gagal terkirim tidak boleh ikut
        // dihitung.
        Http::fake(['*' => Http::response('', 500)]);

        $owner = User::factory()->create(['wa_number' => '6281234567890']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->platformProfile();

        app(DunningLadder::class)->process($company, 30);

        $membership->refresh();
        $this->assertSame('frozen', $membership->status, 'Status tetap ditegakkan walau notifikasi gagal.');
        $this->assertEmpty($membership->metadata['dunning_notified_at'] ?? []);
    }

    public function test_dunning_records_the_warning_once_it_really_is_delivered(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $owner = User::factory()->create(['wa_number' => '6281234567890']);
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->platformProfile();

        app(DunningLadder::class)->process($company, 30);

        $this->assertCount(1, $membership->fresh()->metadata['dunning_notified_at'] ?? []);
    }

    public function test_the_send_command_proves_the_lane_without_waiting_for_a_bill(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->platformProfile();

        $this->artisan('bos:hermes-send', [
            '--platform' => true,
            '--to' => '6281234567890',
            '--message' => 'Uji lajur platform.',
        ])->assertSuccessful();

        Http::assertSent(fn ($request) => $request['message'] === 'Uji lajur platform.');
    }

    public function test_negative_the_send_command_fails_loudly_when_the_lane_is_not_ready(): void
    {
        Http::fake();

        $this->artisan('bos:hermes-send', [
            '--platform' => true,
            '--to' => '6281234567890',
            '--message' => 'Uji lajur platform.',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_tenant_messages_never_go_through_the_platform_lane(): void
    {
        // D-63 sebagai keputusan, dijaga di tingkat berkas: tiga jalur yang
        // mengirim atas nama tenant wajib memakai kelas yang ter-scope company.
        // Antarmuka platform tidak ter-scope, jadi memakainya di sini berarti satu
        // tenant bisa mengirim dari nomor tenant lain.
        $tenantLanes = [
            'Services/Receivables/ReceivableReminderService.php',
            'Services/Team/TeamInvitationService.php',
            'Services/Workflow/Effects/NotifyOwnerWa.php',
        ];

        foreach ($tenantLanes as $path) {
            $source = (string) file_get_contents(app_path($path));

            $this->assertStringContainsString('App\\Services\\HermesNodeClient', $source, $path);
            $this->assertStringNotContainsString('App\\Contracts\\HermesNodeClient', $source, $path);
        }
    }

    private function platformProfile(
        string $profileStatus = 'paired',
        string $nodeStatus = 'active',
        string $nodeUrl = 'https://node.uji.test',
        ?string $profileUrl = null,
        string $secretReference = 'ref_uji',
    ): HermesProfile {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        return HermesProfile::factory()->create([
            'owner_user_id' => $admin->id,
            'node_id' => $this->node($nodeUrl, $nodeStatus, $secretReference)->id,
            'api_url' => $profileUrl,
            'type' => 'addon',
            'label' => 'Bot CS Platform',
            'status' => $profileStatus,
            'is_platform_provided' => true,
        ]);
    }

    private function node(
        string $apiUrl = 'https://node.uji.test',
        string $status = 'active',
        string $secretReference = 'ref_uji',
    ): HermesNode {
        return HermesNode::factory()->create([
            'api_url' => $apiUrl,
            'api_secret_reference' => $secretReference,
            'status' => $status,
        ]);
    }
}
