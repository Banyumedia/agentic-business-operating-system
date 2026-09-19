<?php

namespace Tests\Unit;

use App\Services\FeatureResolver;
use App\Services\Preset\PresetDefinitionValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PresetDefinitionValidatorTest extends TestCase
{
    private PresetDefinitionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new PresetDefinitionValidator;
    }

    public function test_valid_definition_and_tier_b_capabilities_are_accepted(): void
    {
        $definition = $this->validDefinition();
        $definition['tier'] = 'B';
        $definition['capabilities']['inventory'] = true;
        $definition['capabilities']['inventory.batch_expiry'] = true;
        $definition['capabilities']['pos'] = true;
        $definition['capabilities']['pharmacy.prescription'] = true;

        $this->assertSame($definition, $this->validator->validate($definition));
    }

    public function test_manufacturing_capability_is_catalogued_as_tier_b_with_locked_dependencies(): void
    {
        $definition = $this->validDefinition();
        $definition['tier'] = 'B';
        $definition['capabilities']['inventory'] = true;
        $definition['capabilities']['inventory.bom'] = true;
        $definition['capabilities']['inventory.batch_expiry'] = true;
        $definition['capabilities']['finance.accounting'] = true;
        $definition['capabilities']['manufacturing.production_order'] = true;

        $this->assertContains('manufacturing.production_order', FeatureResolver::CAPABILITIES);
        $this->assertSame($definition, $this->validator->validate($definition));

        foreach (['inventory.bom', 'inventory.batch_expiry', 'finance.accounting'] as $dependency) {
            $withoutDependency = $definition;
            unset($withoutDependency['capabilities'][$dependency]);

            try {
                $this->validator->validate($withoutDependency);
                $this->fail("manufacturing.production_order harus membutuhkan {$dependency}");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString("manufacturing.production_order membutuhkan {$dependency}", $exception->getMessage());
            }
        }
    }

    /** @return array<string, array{callable(array<string, mixed>): array<string, mixed>, string}> */
    public static function invalidCatalogKeys(): array
    {
        return [
            'capability' => [static function (array $definition): array {
                $definition['capabilities']['foreign.capability'] = true;

                return $definition;
            }, 'Capability tidak terdaftar: foreign.capability'],
            'terminology' => [static function (array $definition): array {
                $definition['terminology']['foreign_term'] = 'Asing';

                return $definition;
            }, 'Kunci terminology tidak terdaftar: foreign_term'],
            'widget' => [static function (array $definition): array {
                $definition['dashboard']['industry_zone'][] = ['widget' => 'foreign_widget'];

                return $definition;
            }, 'Widget tidak terdaftar: foreign_widget'],
            'effect' => [static function (array $definition): array {
                $definition['workflows']['orders']['transitions'][0]['effects'] = ['foreign.effect'];

                return $definition;
            }, 'Efek tidak terdaftar: foreign.effect'],
            'effects shape' => [static function (array $definition): array {
                $definition['workflows']['orders']['transitions'][0]['effects'] = 'journal.post';

                return $definition;
            }, 'Effects transisi harus list: orders'],
            'widget props shape' => [static function (array $definition): array {
                $definition['dashboard']['industry_zone'][0]['props'] = null;

                return $definition;
            }, 'Props widget harus object: kpi_cashflow'],
        ];
    }

    #[DataProvider('invalidCatalogKeys')]
    public function test_unknown_catalog_keys_are_rejected(callable $mutate, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->validator->validate($mutate($this->validDefinition()));
    }

    /** @return array<string, array{callable(array<string, mixed>): array<string, mixed>, string}> */
    public static function invalidWorkflows(): array
    {
        return [
            'unreachable stage' => [static function (array $definition): array {
                $definition['workflows']['orders']['stages'][] = ['code' => 'isolated', 'label' => 'Terpisah'];
                $definition['workflows']['orders']['terminal'][] = 'isolated';

                return $definition;
            }, 'Stage tidak terjangkau: orders.isolated'],
            'dead end' => [static function (array $definition): array {
                array_splice($definition['workflows']['orders']['transitions'], 1, 1);

                return $definition;
            }, 'Stage non-terminal tanpa transisi keluar: orders.working'],
            'undeclared terminal' => [static function (array $definition): array {
                $definition['workflows']['orders']['terminal'][] = 'missing';

                return $definition;
            }, 'Stage terminal tidak terdaftar: orders.missing'],
            'missing terminal declaration' => [static function (array $definition): array {
                unset($definition['workflows']['orders']['terminal']);

                return $definition;
            }, 'Terminal workflow wajib dideklarasikan: orders'],
            'backward transition without note' => [static function (array $definition): array {
                unset($definition['workflows']['orders']['transitions'][2]['requires_note']);

                return $definition;
            }, 'Transisi mundur wajib requires_note: orders.review -> working'],
            'stage code outside schema regex' => [static function (array $definition): array {
                $definition['workflows']['orders']['stages'][0]['code'] = 'new1';
                $definition['workflows']['orders']['transitions'][0]['from'] = 'new1';

                return $definition;
            }, 'Kode stage tidak valid'],
            'reachable stage cannot reach terminal' => [static function (array $definition): array {
                $definition['workflows']['orders']['transitions'] = [
                    ['from' => 'new', 'to' => 'working', 'roles' => ['owner']],
                    ['from' => 'working', 'to' => 'review', 'roles' => ['owner']],
                    ['from' => 'review', 'to' => 'working', 'roles' => ['owner'], 'requires_note' => true],
                ];

                return $definition;
            }, 'Stage tidak memiliki jalur ke terminal: orders.new'],
        ];
    }

    #[DataProvider('invalidWorkflows')]
    public function test_invalid_workflow_integrity_is_rejected(callable $mutate, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->validator->validate($mutate($this->validDefinition()));
    }

    public function test_ai_agent_requires_approval_flow(): void
    {
        $definition = $this->validDefinition();
        unset($definition['capabilities']['approval_flow']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dependensi capability tidak aktif: system.ai_agent membutuhkan approval_flow');

        $this->validator->validate($definition);
    }

    public function test_tier_b_dependency_is_fail_closed(): void
    {
        $definition = $this->validDefinition();
        $definition['tier'] = 'B';
        $definition['capabilities']['pharmacy.prescription'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dependensi capability tidak aktif: pharmacy.prescription membutuhkan inventory.batch_expiry');

        $this->validator->validate($definition);
    }

    /** @return array<string, mixed> */
    private function validDefinition(): array
    {
        return [
            'key' => 'service_demo',
            'name' => 'Layanan Demo',
            'tier' => 'A',
            'description' => 'Demo desc',
            'capabilities' => [
                'contacts' => true,
                'approval_flow' => true,
                'finance.cashbook' => true,
                'system.ai_agent' => true,
            ],
            'terminology' => [
                'contact' => 'Pelanggan',
                'contacts' => 'Pelanggan',
            ],
            'workflows' => [
                'orders' => [
                    'stages' => [
                        ['code' => 'new', 'label' => 'Baru'],
                        ['code' => 'working', 'label' => 'Dikerjakan'],
                        ['code' => 'review', 'label' => 'Ditinjau'],
                        ['code' => 'done', 'label' => 'Selesai'],
                    ],
                    'transitions' => [
                        ['from' => 'new', 'to' => 'working', 'roles' => ['owner', 'staff']],
                        ['from' => 'working', 'to' => 'review', 'roles' => ['owner', 'staff']],
                        ['from' => 'review', 'to' => 'working', 'roles' => ['owner'], 'requires_note' => true],
                        ['from' => 'review', 'to' => 'done', 'roles' => ['owner']],
                    ],
                    'terminal' => ['done'],
                ],
            ],
            'dashboard' => [
                'industry_zone' => [
                    ['widget' => 'kpi_cashflow'],
                ],
            ],
            'menus' => [
                'order' => ['contacts', 'accounting', 'settings'],
            ],
        ];
    }
}
