<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Hermes\ProfileStatusRefresher;
use App\Services\HermesNodeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dua utang dari runbook klien pertama, dibayar di sini.
 *
 * **Utang 1 — alamat bridge milik profil, bukan node.** Satu bridge WhatsApp =
 * satu nomor = satu port. Menyimpan alamatnya di `hermes_nodes` memaksa satu baris
 * node per bridge, yang membuat `max_capacity` kehilangan arti. Yang benar: node
 * adalah host/klaster, dan profil menyimpan alamat bridge-nya sendiri.
 *
 * **Utang 2 — status profil diturunkan dari kenyataan, bukan diketik tangan.**
 * Sebelum ini `hermes_profiles.status` hanya bisa diubah lewat basis data, dan
 * tiga kata dipakai untuk keadaan siap yang sama (`paired`, `connected`, `active`)
 * tanpa ada yang memvalidasinya. Status yang diketik tangan bisa berbohong;
 * status yang dibaca dari bridge tidak bisa.
 */
class ProfileBridgeStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('hermes.node_secrets', ['ref_uji' => 'rahasia-uji']);
    }

    public function test_a_profile_may_carry_its_own_bridge_address(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        // Node menunjuk satu alamat, profil menunjuk alamat lain. Yang dipakai
        // harus milik profil - itulah bridge tempat nomor ini hidup.
        $company = $this->tenant(nodeUrl: 'http://127.0.0.1:3000', profileUrl: 'http://127.0.0.1:3002');

        (new HermesNodeClient)->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3002/send');
    }

    public function test_a_profile_without_its_own_address_falls_back_to_the_node(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $company = $this->tenant(nodeUrl: 'http://127.0.0.1:3000', profileUrl: null);

        (new HermesNodeClient)->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:3000/send');
    }

    public function test_status_becomes_paired_when_the_bridge_reports_connected(): void
    {
        Http::fake(['*' => Http::response(['status' => 'connected'], 200)]);

        $this->tenant(profileUrl: 'http://127.0.0.1:3002', status: 'unpaired');
        $profile = HermesProfile::query()->first();
        $this->assertSame('unpaired', $profile->status);

        app(ProfileStatusRefresher::class)->refresh($profile);

        $this->assertSame('paired', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->last_ping_at);
    }

    public function test_negative_status_returns_to_unpaired_when_the_bridge_is_disconnected(): void
    {
        // Jebakan yang ditutup: bridge menjawab HTTP 200 walau WhatsApp terputus.
        // Status yang ikut-ikutan 'paired' akan membuat pengiriman dicoba terus dan
        // gagal terus, tanpa petunjuk apa pun.
        Http::fake(['*' => Http::response(['status' => 'disconnected'], 200)]);

        $this->tenant(profileUrl: 'http://127.0.0.1:3002');
        $profile = HermesProfile::query()->first();
        $profile->update(['status' => 'paired']);

        app(ProfileStatusRefresher::class)->refresh($profile);

        $this->assertSame('unpaired', $profile->fresh()->status);
    }

    public function test_negative_an_unreachable_bridge_marks_the_profile_unpaired(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->tenant(profileUrl: 'http://127.0.0.1:3002');
        $profile = HermesProfile::query()->first();
        $profile->update(['status' => 'paired']);

        app(ProfileStatusRefresher::class)->refresh($profile);

        $this->assertSame('unpaired', $profile->fresh()->status);
    }

    public function test_the_command_refreshes_every_profile(): void
    {
        Http::fake(['*' => Http::response(['status' => 'connected'], 200)]);

        $this->tenant(profileUrl: 'http://127.0.0.1:3002');
        $this->tenant(profileUrl: 'http://127.0.0.1:3003');

        $this->artisan('bos:hermes-profile-status')->assertSuccessful();

        $this->assertSame(0, HermesProfile::query()->where('status', '!=', 'paired')->count());
    }

    public function test_a_profile_with_no_address_anywhere_is_reported_not_crashed(): void
    {
        // Profil yang belum ditempatkan pada node mana pun tidak boleh menjatuhkan
        // perintah yang memeriksa seluruh armada.
        Http::fake();

        $owner = User::factory()->create();
        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => null,
            'status' => 'unpaired',
        ]);

        $this->artisan('bos:hermes-profile-status')->assertSuccessful();

        $this->assertSame('unpaired', HermesProfile::query()->first()->status);
        Http::assertNothingSent();
    }

    private function tenant(
        string $nodeUrl = 'http://127.0.0.1:3000',
        ?string $profileUrl = null,
        string $status = 'paired',
    ): Company {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $node = HermesNode::factory()->create([
            'api_url' => $nodeUrl,
            'api_secret_reference' => 'none',
            'status' => 'active',
        ]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'status' => $status,
            'api_url' => $profileUrl,
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        return $company;
    }
}
