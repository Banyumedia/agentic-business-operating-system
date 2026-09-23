<?php

namespace App\Http\Controllers\App;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Http\Controllers\Controller;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function show(
        Request $request,
        string $module,
        ?string $submodule = null,
    ): View {
        $companyContext = app(CompanyContext::class);
        $settings = app(CompanySettingsStore::class);
        $registry = app(DynamicMenuRegistry::class);

        // Fail-closed via DynamicMenuRegistry
        abort_unless($registry->hasPath($module, $submodule), 404);
        abort_unless($registry->isModuleVisible($module), 403);

        $definition = $registry->routeDefinition($module, $submodule);
        abort_if($definition === null, 403);

        $company = $companyContext->current();
        $theme = (string) ($settings->read($company)['theme'] ?? 'a');

        $screenComponent = $this->screenComponent($definition['screen']);

        // Controller biasa (bukan Livewire): layout dipasang di dalam shell view
        // via <x-layouts.module>, bukan ->layout() yang hanya berlaku untuk
        // komponen Livewire. @livewire() di dalam shell menjadi top-level
        // Livewire component halaman.
        return view('livewire.module-shell', [
            'definition' => $definition,
            'module' => $module,
            'submodule' => $submodule,
            'screenComponent' => $screenComponent,
            'theme' => $theme,
        ]);
    }

    /**
     * Layar detail generik (MP-01). Segmen `detail` sudah terdaftar di
     * registry (seam MP-00, `navigation: false`) untuk modul `contacts`,
     * `projects`, `pos` - gerbang kapabilitasnya SAMA dengan `show()` di atas
     * (hasPath + isModuleVisible), bukan pemeriksaan baru. `$id` murni
     * parameter routing; isolasi tenant ada di repository ter-scope yang
     * dipanggil `DetailScreen`, bukan di sini.
     */
    public function showDetail(Request $request, string $module, int $id): View
    {
        $companyContext = app(CompanyContext::class);
        $settings = app(CompanySettingsStore::class);
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($module, 'detail'), 404);
        abort_unless($registry->isModuleVisible($module), 403);

        $definition = $registry->routeDefinition($module, 'detail');
        abort_if($definition === null, 403);

        $company = $companyContext->current();
        $theme = (string) ($settings->read($company)['theme'] ?? 'a');

        return view('livewire.module-detail-shell', [
            'definition' => $definition,
            'module' => $module,
            'id' => $id,
            'theme' => $theme,
        ]);
    }

    /**
     * Memetakan pola layar dari registry ke komponen Livewire secara konvensi:
     * `list` -> `App\Livewire\Screens\ListScreen` -> `screens.list-screen`.
     *
     * Menambah pola layar baru cukup dengan menambah satu kelas komponen; tidak
     * ada daftar pola di Blade maupun di sini yang perlu disunting. Pola yang
     * belum punya komponen jatuh ke kartu kontrak, bukan error.
     */
    private function screenComponent(string $screen): ?string
    {
        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $screen)) {
            return null;
        }

        $class = 'App\\Livewire\\Screens\\'.str($screen)->studly()->toString().'Screen';

        return class_exists($class) ? 'screens.'.$screen.'-screen' : null;
    }
}
