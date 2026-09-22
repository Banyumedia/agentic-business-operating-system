<?php

namespace Tests\Feature\Api\MasterBot;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * D-76: bot CS platform boleh **mencatat prospek** yang japri walau belum punya
 * company.
 *
 * `AuthenticateMasterBot` menjaga kunci bot; tetapi jalur tiket
 * (`createTicket`/`resolveCompany`) menolak 403 pemanggil tanpa company (T-17).
 * Calon pelanggan yang bertanya ke nomor CS justru **belum** punya company - itu
 * inti keberadaan mereka. Jadi lead capture adalah jalur tersendiri yang tidak
 * menuntut company, dan sengaja **tidak** melonggarkan MasterBot tenant.
 *
 * Batas privasi yang dijaga (D-76): data orang yang belum jadi pelanggan disimpan
 * minimal - nomor, pesan, sumber - dan bisa diidentifikasi ulang untuk dihapus.
 * Tidak ada eksekusi di sini: prospek yang minta tindakan dicatat, bukan dijalankan.
 */
class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.master_bot.secret', 'test-master-secret');
    }

    public function test_negative_the_master_bot_key_is_required(): void
    {
        $this->postJson('/api/bot/master/leads', [
            'wa_number' => '6281234567890',
            'message' => 'Halo, mau tanya harga.',
        ])->assertStatus(401);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_a_prospect_without_a_company_is_recorded(): void
    {
        // Inti D-76: justru pemanggil TANPA company yang harus bisa dicatat -
        // kebalikan dari createTicket yang menolaknya.
        $response = $this->postJson('/api/bot/master/leads', [
            'wa_number' => '0812-3456-7890',
            'message' => 'Halo, usaha saya bengkel, mau coba.',
        ], ['X-Master-Bot-Key' => 'test-master-secret']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'lead_id']);

        // Nomor dinormalkan konsisten dengan consumer WA lain (BS-03,
        // User::normalizeWaNumber): 08 -> 628.
        $this->assertDatabaseHas('leads', [
            'wa_number' => '6281234567890',
            'status' => 'baru',
        ]);
    }

    public function test_a_returning_prospect_does_not_create_a_duplicate_row(): void
    {
        // CS sungguhan tidak membuat kartu prospek baru tiap pesan. Nomor yang sama
        // memperbarui pesan terakhirnya, bukan menumpuk baris - kalau tidak, satu
        // orang yang bertanya lima kali terlihat seperti lima prospek.
        $payload = fn (string $msg) => [
            'wa_number' => '6281234567890',
            'message' => $msg,
        ];

        $this->postJson('/api/bot/master/leads', $payload('pesan pertama'), ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();
        $this->postJson('/api/bot/master/leads', $payload('pesan kedua'), ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', ['wa_number' => '6281234567890', 'last_message' => 'pesan kedua']);
    }

    public function test_negative_a_lead_never_becomes_a_ticket_or_touches_a_company(): void
    {
        // D-76 mencabut penolakan company HANYA untuk jalur lead. Ia tidak boleh
        // diam-diam membuat tiket atau menautkan ke company mana pun - itu jalur
        // tenant yang berbeda dan tetap menuntut company.
        $this->postJson('/api/bot/master/leads', [
            'wa_number' => '6289999999999',
            'message' => 'tanya-tanya',
        ], ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();

        $this->assertDatabaseCount('support_tickets', 0);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_negative_an_empty_message_or_number_is_refused(): void
    {
        foreach ([['', 'ada pesan'], ['6281234567890', '']] as [$wa, $msg]) {
            $this->postJson('/api/bot/master/leads', [
                'wa_number' => $wa,
                'message' => $msg,
            ], ['X-Master-Bot-Key' => 'test-master-secret'])->assertStatus(422);
        }

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_an_existing_user_number_is_still_recorded_as_a_lead_not_promoted(): void
    {
        // Kalau nomornya kebetulan sudah user (mis. staf tenant lain yang tanya soal
        // paket), tetap dicatat sebagai lead - mempromosikannya otomatis ke sesuatu
        // yang company-scoped adalah tebakan identitas yang D-66 larang.
        User::factory()->create(['wa_number' => '6281234567890']);

        $this->postJson('/api/bot/master/leads', [
            'wa_number' => '6281234567890',
            'message' => 'halo',
        ], ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();

        $this->assertDatabaseHas('leads', ['wa_number' => '6281234567890']);
    }
}
