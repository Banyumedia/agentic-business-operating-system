<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\CalendarScreen;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Resource;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MP-07: buat booking dari kalender lewat `BookingService`.
 *
 * Jalur JSON dan Eloquent diuji terpisah - JSON menegakkan `no_overlap`
 * secara generik lewat `JsonEntityRepository`, Eloquent lewat
 * `BookingService::assertNoOverlap()`. Keduanya diuji supaya aturan
 * tabrakan jadwal terbukti berlaku di kedua jalur, bukan diasumsikan.
 */
class CalendarBookingCreationTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/calbook-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/salon-ayu/settings.json',
            json_encode(['preset' => 'salon', 'timezone' => 'Asia/Jakarta'], JSON_THROW_ON_ERROR),
        );

        // Kamis 17 September 2026 pukul 09:00 WIB.
        $this->travelTo('2026-09-17T09:00:00+07:00');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_owner_can_create_a_booking_from_a_calendar_slot(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $resourceId = $this->jsonResource('salon-ayu', 'Kursi 1');

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resourceId)
            ->set('form.starts_at', '2026-09-18T10:00')
            ->set('form.ends_at', '2026-09-18T11:00')
            ->call('create')
            ->assertSet('failure', null)
            ->assertSee('langsung tampil di kalender');

        $bookings = app(EntityRepository::class)->for('salon-ayu', 'bookings')->all();
        $this->assertCount(1, $bookings);
        $this->assertSame($resourceId, $bookings[0]['resource_id']);
        $this->assertSame('dijadwalkan', $bookings[0]['stage']);
    }

    public function test_negative_overlapping_slot_is_rejected_on_json_driver(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $resourceId = $this->jsonResource('salon-ayu', 'Kursi 1');

        app(EntityRepository::class)->for('salon-ayu', 'bookings')->save([
            'resource_id' => $resourceId,
            'starts_at' => '2026-09-18T10:00:00',
            'ends_at' => '2026-09-18T11:00:00',
        ]);

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resourceId)
            ->set('form.starts_at', '2026-09-18T10:30')
            ->set('form.ends_at', '2026-09-18T11:30')
            ->call('create')
            ->assertSee('bertumpang-tindih');

        $this->assertCount(1, app(EntityRepository::class)->for('salon-ayu', 'bookings')->all());
    }

    public function test_negative_a_slot_in_the_past_is_rejected(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $resourceId = $this->jsonResource('salon-ayu', 'Kursi 1');

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-16')
            ->set('form.resource_id', $resourceId)
            ->set('form.starts_at', '2026-09-16T08:00')
            ->set('form.ends_at', '2026-09-16T09:00')
            ->call('create')
            ->assertSee('sudah lewat');

        $this->assertSame([], app(EntityRepository::class)->for('salon-ayu', 'bookings')->all());
    }

    public function test_negative_a_resource_belonging_to_another_company_is_rejected(): void
    {
        Storage::disk('company-json')->put(
            'json/klinik-sehat/settings.json',
            json_encode(['preset' => 'salon', 'timezone' => 'Asia/Jakarta'], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $foreignResourceId = $this->jsonResource('klinik-sehat', 'Kursi Tetangga');

        app(CompanyContext::class)->setCurrent('salon-ayu');

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $foreignResourceId)
            ->set('form.starts_at', '2026-09-18T10:00')
            ->set('form.ends_at', '2026-09-18T11:00')
            ->call('create')
            ->assertSee('tidak ditemukan pada usaha ini');

        $this->assertSame([], app(EntityRepository::class)->for('salon-ayu', 'bookings')->all());
    }

    public function test_negative_double_click_does_not_create_two_bookings(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $resourceId = $this->jsonResource('salon-ayu', 'Kursi 1');

        $screen = Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resourceId)
            ->set('form.starts_at', '2026-09-18T10:00')
            ->set('form.ends_at', '2026-09-18T11:00')
            ->call('create')
            ->assertSet('failure', null);

        // Form sudah dikosongkan setelah sukses; klik ganda pada state
        // berikutnya tidak punya resource/waktu untuk dikirim ulang, jadi
        // tidak bisa menggandakan booking yang sama secara tidak sengaja.
        $screen->call('create')->assertSee('Pilih sumber daya');

        $this->assertCount(1, app(EntityRepository::class)->for('salon-ayu', 'bookings')->all());
    }

    public function test_booking_appears_on_the_calendar_without_a_manual_reload(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $resourceId = $this->jsonResource('salon-ayu', 'Kursi 1');

        $screen = Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resourceId)
            ->set('form.starts_at', '2026-09-18T10:00')
            ->set('form.ends_at', '2026-09-18T11:00')
            ->call('create');

        $days = $screen->viewData('days');
        $day = array_values(array_filter($days, static fn (array $day): bool => $day['date'] === '2026-09-18'))[0];

        $this->assertCount(1, $day['slots']);
        $this->assertSame('10:00', $day['slots'][0]['from']);
    }

    public function test_owner_can_create_a_booking_on_the_eloquent_driver(): void
    {
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);

        $company = Company::factory()->create(['business_preset' => 'salon']);
        $resource = Resource::factory()->create(['company_id' => $company->id, 'name' => 'Kursi 1']);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resource->id)
            ->set('form.starts_at', '2026-09-18T10:00')
            ->set('form.ends_at', '2026-09-18T11:00')
            ->call('create')
            ->assertSet('failure', null);

        $this->assertSame(1, Booking::where('company_id', $company->id)->count());
    }

    public function test_negative_overlapping_slot_is_rejected_on_eloquent_driver(): void
    {
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);

        $company = Company::factory()->create(['business_preset' => 'salon']);
        $resource = Resource::factory()->create(['company_id' => $company->id, 'name' => 'Kursi 1']);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Booking::create([
            'company_id' => $company->id,
            'resource_id' => $resource->id,
            'starts_at' => '2026-09-18T10:00:00',
            'ends_at' => '2026-09-18T11:00:00',
            'stage' => 'dijadwalkan',
        ]);

        Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('requestCreate', '2026-09-18')
            ->set('form.resource_id', $resource->id)
            ->set('form.starts_at', '2026-09-18T10:30')
            ->set('form.ends_at', '2026-09-18T11:30')
            ->call('create')
            ->assertSee('overlap');

        $this->assertSame(1, Booking::where('company_id', $company->id)->count());
    }

    private function jsonResource(string $company, string $name): int
    {
        $saved = app(EntityRepository::class)->for($company, 'resources')->save([
            'type' => 'kursi',
            'name' => $name,
        ]);

        return (int) $saved['id'];
    }
}
