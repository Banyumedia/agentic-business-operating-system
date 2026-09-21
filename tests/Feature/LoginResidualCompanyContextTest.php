<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Eloquent\EloquentEntityRepository;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UR-03 defect: current_company_id residual (mis. sisa impersonasi yang tidak
 * di-stop bersih, atau polusi data) membuat login melempar 500 - login harus
 * self-healing: verifikasi kepemilikan, bukan percaya kolom residual.
 *
 * Test ini eksplisit Eloquent (defect hanya muncul di driver eloquent;
 * JsonCompanyContext fail-closed di luar demo).
 */
class LoginResidualCompanyContextTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('datasource.driver', 'eloquent');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Re-bind kontrak datasource ke Eloquent (provider sudah register
        // dengan driver json sebelum override config test berlaku).
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

    public function test_owner_login_with_residual_current_company_pointing_to_owned_company_succeeds(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@t.test', 'password' => 'secret123']);
        $company = Company::create([
            'name' => 'Usaha', 'slug' => 'usaha-t', 'business_preset' => 'custom', 'owner_user_id' => $owner->id,
        ]);
        // residual TAPI valid (milik owner) - harus tetap login mulus
        $owner->forceFill(['current_company_id' => $company->id])->save();

        Livewire::test('auth.login')
            ->set('email', 'owner@t.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('app.dashboard', absolute: false));
    }

    public function test_login_with_residual_current_company_owned_by_someone_else_self_heals_to_owned_company(): void
    {
        $otherOwner = User::create(['name' => 'Other', 'email' => 'other@t.test', 'password' => 'secret123']);
        $foreignCompany = Company::create([
            'name' => 'Asing', 'slug' => 'asing-t', 'business_preset' => 'custom', 'owner_user_id' => $otherOwner->id,
        ]);

        $owner = User::create(['name' => 'Owner B', 'email' => 'ownerb@t.test', 'password' => 'secret123']);
        Company::create([
            'name' => 'Milik B', 'slug' => 'milik-b-t', 'business_preset' => 'custom', 'owner_user_id' => $owner->id,
        ]);
        // residual menunjuk company milik orang lain (sisa impersonasi kotor)
        $owner->forceFill(['current_company_id' => $foreignCompany->id])->save();

        Livewire::test('auth.login')
            ->set('email', 'ownerb@t.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertNotSame($foreignCompany->id, $owner->fresh()->current_company_id);
        $this->assertSame($owner->companies()->first()->id, $owner->fresh()->current_company_id);
    }

    public function test_admin_login_with_residual_current_company_redirects_to_onboarding_not_500(): void
    {
        $otherOwner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'secret123']);
        $foreignCompany = Company::create([
            'name' => 'Asing', 'slug' => 'asing-adm', 'business_preset' => 'custom', 'owner_user_id' => $otherOwner->id,
        ]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@t.test', 'password' => 'secret123']);
        $admin->forceFill(['is_platform_admin' => true, 'current_company_id' => $foreignCompany->id])->save();

        Livewire::test('auth.login')
            ->set('email', 'admin@t.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('onboarding', absolute: false));

        $this->assertSame(null, $admin->fresh()->current_company_id);
    }
}
