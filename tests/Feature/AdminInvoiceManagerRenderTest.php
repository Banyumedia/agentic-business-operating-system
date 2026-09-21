<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Eloquent\EloquentEntityRepository;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UR-04 defect: render halaman admin invoice manager (blade memakai
 * $this->errors yang tidak valid di Livewire v4 -> PropertyNotFoundException
 * -> 500 di produksi). Test ini menjaga render penuh halaman admin.
 */
class AdminInvoiceManagerRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            EntityRepository::class => EloquentEntityRepository::class,
            PresetSource::class => EloquentPresetSource::class,
            CompanyContext::class => EloquentCompanyContext::class,
            CompanySettingsStore::class => EloquentCompanySettingsStore::class,
        ] as $contract => $implementation) {
            $this->app->forgetInstance($contract);
            $this->app->scoped($contract, $implementation);
        }
    }

    public function test_super_admin_can_render_invoice_manager_page(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-iv@t.test', 'password' => 'secret123']);
        $admin->forceFill(['is_platform_admin' => true])->save();

        $response = $this->actingAs($admin)->get('/admin/invoices');

        $response->assertOk();
    }

    public function test_non_admin_gets_403_on_invoice_manager_page(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner-iv@t.test', 'password' => 'secret123']);

        $this->actingAs($owner)->get('/admin/invoices')->assertForbidden();
    }
}
