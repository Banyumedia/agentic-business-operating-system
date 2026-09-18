<?php

namespace Tests\Feature;

use App\Contracts\PresetSource;
use App\Services\Json\JsonPresetSource;
use App\Services\Preset\PresetDefinitionValidator;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Tests\TestCase;

class JsonPresetSourceTest extends TestCase
{
    public function test_contract_resolves_to_json_source_and_loads_three_canonical_presets(): void
    {
        $source = app(PresetSource::class);

        $this->assertInstanceOf(JsonPresetSource::class, $source);

        $keys = array_column($source->all(), 'key');
        sort($keys);

        $this->assertSame([
            'agency', 'bakery_preorder', 'barbershop', 'bengkel', 'contractor', 'cuci_mobil', 'custom', 'eo', 'fnb', 'fotografi',
            'gym', 'katering', 'kedai_kopi', 'klinik', 'kos_coworking', 'kursus', 'laundry', 'pharmacy',
            'praktek_dokter', 'rental', 'salon', 'travel_umroh',
        ], $keys);
        $this->assertSame('klinik', $source->find('klinik')['key']);
        $this->assertNull($source->find('unknown'));
    }

    public function test_demo_workflows_meet_the_minimum_contract(): void
    {
        $source = app(PresetSource::class);
        $workshop = $source->find('bengkel')['workflows']['orders'];

        $this->assertContains(
            ['from' => 'masuk', 'to' => 'pengerjaan', 'roles' => ['owner', 'staff']],
            $workshop['transitions'],
        );
        $this->assertContains(
            ['from' => 'qc', 'to' => 'siap_diambil', 'roles' => ['owner', 'staff']],
            $workshop['transitions'],
        );
        $this->assertContains(
            ['from' => 'qc', 'to' => 'pengerjaan', 'roles' => ['owner', 'staff'], 'requires_note' => true],
            $workshop['transitions'],
        );
        $this->assertSame(['selesai', 'dibatalkan'], $workshop['terminal']);

        foreach (['klinik', 'salon'] as $key) {
            $workflow = $source->find($key)['workflows']['bookings'];

            $this->assertNotEmpty($workflow['terminal']);
            $this->assertContains('selesai', $workflow['terminal']);
            $this->assertContains('dibatalkan', $workflow['terminal']);
        }

        $laundry = $source->find('laundry')['workflows']['orders'];
        $this->assertContains(
            ['from' => 'terima', 'to' => 'proses', 'roles' => ['owner', 'staff']],
            $laundry['transitions'],
        );
        $this->assertSame(['diambil', 'dibatalkan'], $laundry['terminal']);
    }

    public function test_source_rejects_a_filename_that_does_not_match_the_definition_key(): void
    {
        $directory = $this->temporaryDirectory();
        file_put_contents($directory.'/wrong.json', json_encode($this->validDefinition(), JSON_THROW_ON_ERROR));

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Key preset harus sama dengan nama file: wrong');

        (new JsonPresetSource(new PresetDefinitionValidator, $directory))->all();
    }

    public function test_source_rejects_list_shaped_objects_from_json(): void
    {
        $directory = $this->temporaryDirectory();
        file_put_contents($directory.'/service_demo.json', json_encode([
            'key' => 'service_demo',
            'name' => 'Layanan Demo',
            'tier' => 'A',
            'description' => 'Desc',
            'capabilities' => [],
            'terminology' => (object) [],
            'workflows' => (object) [],
            'dashboard' => (object) ['industry_zone' => []],
            'menus' => (object) ['order' => []],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Capabilities harus object');

        (new JsonPresetSource(new PresetDefinitionValidator, $directory))->all();
    }

    public function test_source_rejects_a_missing_directory(): void
    {
        $directory = storage_path('framework/testing/missing-presets-'.bin2hex(random_bytes(5)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Direktori preset tidak tersedia');

        (new JsonPresetSource(new PresetDefinitionValidator, $directory))->all();
    }

    public function test_source_rejects_malformed_json(): void
    {
        $directory = $this->temporaryDirectory();
        file_put_contents($directory.'/broken.json', '{');

        $this->expectException(JsonException::class);

        (new JsonPresetSource(new PresetDefinitionValidator, $directory))->all();
    }

    private function temporaryDirectory(): string
    {
        $directory = storage_path('framework/testing/presets-'.bin2hex(random_bytes(5)));
        mkdir($directory, 0777, true);
        $this->beforeApplicationDestroyed(static function () use ($directory): void {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        });

        return $directory;
    }

    /** @return array<string, mixed> */
    private function validDefinition(): array
    {
        return [
            'key' => 'service_demo',
            'name' => 'Layanan Demo',
            'tier' => 'A',
            'description' => 'Desc',
            'capabilities' => ['contacts' => true],
            'terminology' => ['contact' => 'Pelanggan'],
            'workflows' => (object) [],
            'dashboard' => (object) ['industry_zone' => []],
            'menus' => (object) ['order' => ['contacts', 'settings']],
        ];
    }
}
