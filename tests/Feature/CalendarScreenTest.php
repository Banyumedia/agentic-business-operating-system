<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\CalendarScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/cal-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka' => 'bengkel', 'klinik-sehat' => 'klinik', 'salon-ayu' => 'salon'] as $company => $preset) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => $preset, 'timezone' => 'Asia/Jakarta'], JSON_THROW_ON_ERROR),
            );
        }

        // Kamis 17 September 2026 pukul 06:00 WIB - masih 16 September di UTC,
        // sehingga sekaligus membuktikan kalender memakai jam usaha.
        $this->travelTo('2026-09-17T06:00:00+07:00');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_weekly_view_covers_seven_days_and_daily_view_one(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');

        $component = Livewire::test(CalendarScreen::class, ['module' => 'bookings'])->assertOk();

        $this->assertCount(7, $component->viewData('days'));
        $this->assertSame('2026-09-14', $component->viewData('days')[0]['date']);
        $this->assertSame('2026-09-20', $component->viewData('days')[6]['date']);

        $component->call('setView', 'day');
        $this->assertCount(1, $component->viewData('days'));
        $this->assertSame('2026-09-17', $component->viewData('days')[0]['date']);

        // Rentang tak dikenal diabaikan, bukan diterima diam-diam.
        $component->call('setView', 'dekade');
        $this->assertSame('day', $component->get('view'));
    }

    public function test_slots_use_the_recorded_offset_and_are_sorted_per_day(): void
    {
        $this->seedSalonChairs();

        $days = array_column(
            Livewire::test(CalendarScreen::class, ['module' => 'bookings'])->viewData('days'),
            null,
            'date',
        );

        $thursday = $days['2026-09-17']['slots'];
        $this->assertSame(['09:00', '11:00'], array_column($thursday, 'from'));
        $this->assertSame(['10:00', '12:00'], array_column($thursday, 'to'));

        // 09:00+07:00 tidak boleh bergeser menjadi 02:00 (timezone server UTC).
        $this->assertNotContains('02:00', array_column($thursday, 'from'));
    }

    public function test_slot_shows_the_referenced_resource_name_instead_of_its_id(): void
    {
        $this->seedSalonChairs();

        $days = array_column(
            Livewire::test(CalendarScreen::class, ['module' => 'bookings'])->viewData('days'),
            null,
            'date',
        );

        $this->assertSame('Kursi Rambut 1', $days['2026-09-17']['slots'][0]['resource']);
    }

    public function test_navigation_moves_by_week_or_day_and_returns_to_today(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $component = Livewire::test(CalendarScreen::class, ['module' => 'bookings']);

        $component->call('shift', 1);
        $this->assertSame('2026-09-24', $component->get('anchor'));

        $component->call('shift', -2);
        $this->assertSame('2026-09-10', $component->get('anchor'));

        $component->call('setView', 'day')->call('shift', 1);
        $this->assertSame('2026-09-11', $component->get('anchor'));

        $component->call('today');
        $this->assertSame('2026-09-17', $component->get('anchor'));
    }

    public function test_today_follows_the_business_timezone_not_the_server(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');

        // Jam server masih 16 September (UTC); jam usaha sudah 17 September.
        $this->assertSame('2026-09-16', now()->format('Y-m-d'));

        $days = Livewire::test(CalendarScreen::class, ['module' => 'bookings'])
            ->call('setView', 'day')
            ->viewData('days');

        $this->assertSame('2026-09-17', $days[0]['date']);
        $this->assertTrue($days[0]['is_today']);
    }

    public function test_calendar_refuses_to_act_after_the_active_company_changes(): void
    {
        $this->seedSalonChairs();
        $component = Livewire::test(CalendarScreen::class, ['module' => 'bookings']);

        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $component->call('shift', 1)->assertForbidden();
    }

    public function test_calendar_is_closed_when_the_scheduling_capability_is_revoked(): void
    {
        $this->seedSalonChairs();
        $component = Livewire::test(CalendarScreen::class, ['module' => 'bookings'])->assertOk();

        app(CompanySettingsStore::class)->update('salon-ayu', static function (array $settings): array {
            $settings['features']['bookings'] = false;
            $settings['features']['scheduling'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_calendar_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/CalendarScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/calendar.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertDoesNotMatchRegularExpression('/\b(?:Pasien|Pelanggan|Terapis|Mekanik|Kursi)\b/', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    private function seedSalonChairs(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');

        $resources = app(EntityRepository::class)->for('salon-ayu', 'resources');
        $resources->save(['id' => 1, 'type' => 'chair', 'name' => 'Kursi Rambut 1']);
        $resources->save(['id' => 2, 'type' => 'bed', 'name' => 'Bed Facial 1']);

        $bookings = app(EntityRepository::class)->for('salon-ayu', 'bookings');
        // Sengaja disimpan tidak berurutan untuk membuktikan pengurutan layar.
        $bookings->save(['id' => 1, 'resource_id' => 2, 'starts_at' => '2026-09-17T11:00:00+07:00', 'ends_at' => '2026-09-17T12:00:00+07:00']);
        $bookings->save(['id' => 2, 'resource_id' => 1, 'starts_at' => '2026-09-17T09:00:00+07:00', 'ends_at' => '2026-09-17T10:00:00+07:00']);
        $bookings->save(['id' => 3, 'resource_id' => 1, 'starts_at' => '2026-09-19T09:00:00+07:00', 'ends_at' => '2026-09-19T10:00:00+07:00']);
    }
}
