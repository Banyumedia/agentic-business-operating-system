<?php

namespace Tests\Feature;

use App\Services\Dashboard\WidgetCapabilityMap;
use App\Services\Preset\PresetDefinitionValidator;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MQ-01C3: kontrak preset-widget-capability tunggal. Matriks seluruh
 * preset database/presets/*.json harus lolos validator dengan aturan
 * baru: widget dikenal runtime DAN capability-nya aktif di preset itu.
 */
class PresetWidgetCapabilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{string}> */
    public static function presetProvider(): array
    {
        $files = glob(__DIR__.'/../../database/presets/*.json') ?: [];

        return array_map(
            static fn (string $file): array => [basename($file, '.json')],
            $files,
        );
    }

    #[DataProvider('presetProvider')]
    public function test_preset_passes_widget_capability_contract(string $presetKey): void
    {
        $definition = json_decode((string) file_get_contents(__DIR__."/../../database/presets/{$presetKey}.json"), true);
        $this->assertIsArray($definition);

        $validator = app(PresetDefinitionValidator::class);
        $normalized = $validator->validate($definition);

        $capabilities = $normalized['capabilities'];
        foreach ($normalized['dashboard']['industry_zone'] as $selection) {
            $widget = $selection['widget'];

            $this->assertTrue(
                WidgetCapabilityMap::known($widget),
                "{$presetKey}: widget {$widget} tidak dikenal kontrak runtime.",
            );

            foreach (WidgetCapabilityMap::required($widget) as $capability) {
                $this->assertTrue(
                    ($capabilities[$capability] ?? false) === true,
                    "{$presetKey}: widget {$widget} membutuhkan capability aktif {$capability}.",
                );
            }
        }
    }

    public function test_seeder_passes_with_new_contract(): void
    {
        $this->seed(BusinessPresetSeeder::class);
        $this->assertDatabaseCount('business_presets', 40);
    }

    public function test_validator_rejects_widget_without_active_capability(): void
    {
        $definition = json_decode((string) file_get_contents(__DIR__.'/../../database/presets/bengkel.json'), true);
        $this->assertIsArray($definition);

        // Sabotase: deklarasi widget tanpa capability scheduling.
        $definition['capabilities']['scheduling'] = false;
        $definition['dashboard']['industry_zone'][] = ['widget' => 'upcoming_schedule'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('upcoming_schedule membutuhkan capability aktif: scheduling');

        app(PresetDefinitionValidator::class)->validate($definition);
    }

    public function test_validator_rejects_widget_unknown_to_runtime_contract(): void
    {
        $definition = json_decode((string) file_get_contents(__DIR__.'/../../database/presets/bengkel.json'), true);
        $this->assertIsArray($definition);

        $definition['dashboard']['industry_zone'][] = ['widget' => 'kpi_revenue'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget tidak terdaftar: kpi_revenue');

        app(PresetDefinitionValidator::class)->validate($definition);
    }
}
