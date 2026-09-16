<?php

namespace App\Services;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;

/**
 * Registry navigation berbasis kapabilitas. Nama bisnis tidak pernah menjadi
 * cabang kode; visibilitas dan label selalu diselesaikan untuk company aktif.
 */
class DynamicMenuRegistry
{
    public function __construct(
        private readonly FeatureResolver $features,
        private readonly TerminologyResolver $terms,
        private readonly CompanyContext $companyContext,
        private readonly PresetSource $presets,
    ) {}

    /** @return array<int, string> */
    public function modules(): array
    {
        $aliases = ['employees' => 'hrd'];
        $preset = $this->presets->find($this->companyContext->preset());
        $configured = is_array($preset['menus']['order'] ?? null) ? $preset['menus']['order'] : [];
        $ordered = [];

        foreach ($configured as $module) {
            $resolved = $aliases[$module] ?? $module;
            if (is_string($resolved) && array_key_exists($resolved, $this->catalog()) && ! in_array($resolved, $ordered, true)) {
                $ordered[] = $resolved;
            }
        }

        foreach (array_keys($this->catalog()) as $module) {
            if (! in_array($module, $ordered, true)) {
                $ordered[] = $module;
            }
        }

        return $ordered;
    }

    public function hasModule(?string $module): bool
    {
        return $module !== null && array_key_exists($module, $this->catalog());
    }

    public function isModuleVisible(?string $module): bool
    {
        if (! $this->hasModule($module)) {
            return false;
        }

        $definition = $this->catalog()[$module];

        return $this->requirementsPass($definition);
    }

    public function titleFor(?string $module): string
    {
        if (! $this->hasModule($module)) {
            return '';
        }

        return $this->resolveLabel($this->catalog()[$module]['title']);
    }

    /** @return array<int, array{slug: string, name: string, icon: string, route: string}> */
    public function visibleModules(): array
    {
        $visible = [];

        foreach ($this->modules() as $module) {
            if (! $this->isModuleVisible($module)) {
                continue;
            }

            $menus = $this->menusFor($module);
            if ($menus === []) {
                continue;
            }

            $definition = $this->catalog()[$module];
            $visible[] = [
                'slug' => $module,
                'name' => $this->titleFor($module),
                'icon' => $definition['icon'],
                'route' => $menus[0]['route'],
            ];
        }

        return $visible;
    }

    /**
     * @return array<int, array{label: string, icon: string, route: string, screen: string, entity: string}>
     */
    public function menusFor(?string $module): array
    {
        if (! $this->isModuleVisible($module)) {
            return [];
        }

        $menus = [];

        foreach ($this->catalog()[$module]['items'] as $item) {
            if (($item['navigation'] ?? true) !== true || ! $this->requirementsPass($item)) {
                continue;
            }

            $menus[] = $this->resolveItem($item);
        }

        return $menus;
    }

    public function hasPath(?string $module, ?string $submodule): bool
    {
        if (! $this->hasModule($module)) {
            return false;
        }

        foreach ($this->catalog()[$module]['items'] as $item) {
            if ($item['segment'] === $submodule) {
                return true;
            }
        }

        return false;
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string}|null */
    public function routeDefinition(?string $module, ?string $submodule = null): ?array
    {
        if (! $this->isModuleVisible($module)) {
            return null;
        }

        foreach ($this->catalog()[$module]['items'] as $item) {
            if ($item['segment'] === $submodule && $this->requirementsPass($item)) {
                return $this->resolveItem($item);
            }
        }

        return null;
    }

    public function accentFor(?string $module): string
    {
        return 'bg-[var(--erp-accent)]';
    }

    /** @param array<string, mixed> $definition */
    private function requirementsPass(array $definition): bool
    {
        foreach ($definition['requires_all'] ?? [] as $capability) {
            if (! $this->features->enabled($capability)) {
                return false;
            }
        }

        $any = $definition['requires_any'] ?? [];

        return $any === [] || collect($any)->contains(fn (string $capability): bool => $this->features->enabled($capability));
    }

    /** @param array<string, mixed> $item
     * @return array{label: string, icon: string, route: string, screen: string, entity: string}
     */
    private function resolveItem(array $item): array
    {
        return [
            'label' => $this->resolveLabel($item['label']),
            'icon' => $item['icon'],
            'route' => $item['route'],
            'screen' => $item['screen'],
            'entity' => $item['entity'],
        ];
    }

    /** @param string|array{term: string, prefix?: string, suffix?: string} $label */
    private function resolveLabel(string|array $label): string
    {
        if (is_string($label)) {
            return $label;
        }

        return ($label['prefix'] ?? '').$this->terms->resolve($label['term']).($label['suffix'] ?? '');
    }

    /** @return array<string, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'icon' => 'layout-dashboard',
                'items' => [
                    $this->item(null, 'Ringkasan', '/app/dashboard', 'dashboard', 'assistant_report'),
                ],
            ],
            'contacts' => [
                'title' => ['term' => 'contacts'],
                'icon' => 'book-user',
                'requires_all' => ['contacts'],
                'items' => [
                    $this->item(null, ['term' => 'contacts', 'prefix' => 'Daftar '], '/app/contacts', 'list', 'contacts'),
                    $this->item('deals', ['term' => 'deals', 'prefix' => 'Pipeline '], '/app/contacts/deals', 'pipeline', 'deals', ['deals']),
                ],
            ],
            'projects' => [
                'title' => ['term' => 'projects'],
                'icon' => 'briefcase-business',
                'requires_all' => ['projects'],
                'items' => [
                    $this->item(null, ['term' => 'projects', 'prefix' => 'Daftar '], '/app/projects', 'list', 'projects'),
                    $this->item('quotations', 'Penawaran', '/app/projects/quotations', 'list', 'quotations', ['quotations']),
                    $this->item('timesheet', 'Timesheet', '/app/projects/timesheet', 'list', 'timesheets', ['timesheet']),
                    $this->item('billing', 'Progress Billing', '/app/projects/billing', 'ledger', 'invoices', ['projects.progress_billing']),
                    $this->item('retention', 'Retensi', '/app/projects/retention', 'list', 'project_milestones', ['construction.retention']),
                ],
            ],
            'bookings' => [
                'title' => ['term' => 'bookings'],
                'icon' => 'calendar-check',
                'requires_any' => ['bookings', 'scheduling'],
                'items' => [
                    $this->item(null, 'Kalender', '/app/bookings', 'calendar', 'bookings'),
                    $this->item('resources', ['term' => 'resources', 'prefix' => 'Daftar '], '/app/bookings/resources', 'list', 'resources', ['bookings']),
                    $this->item('rundown', 'Rundown', '/app/bookings/rundown', 'list', 'bookings', ['scheduling']),
                    $this->item('checkin', 'Check-in & Deposit', '/app/bookings/checkin', 'board', 'bookings', ['bookings.deposit']),
                ],
            ],
            'inventory' => [
                'title' => ['term' => 'items'],
                'icon' => 'package',
                'requires_all' => ['inventory'],
                'items' => [
                    $this->item(null, ['term' => 'items', 'prefix' => 'Daftar '], '/app/inventory', 'list', 'items'),
                    $this->item('movements', 'Mutasi Stok', '/app/inventory/movements', 'list', 'item_batches'),
                    $this->item('batches', 'Batch & Kedaluwarsa', '/app/inventory/batches', 'list', 'item_batches', ['inventory.batch_expiry']),
                    $this->item('bom', 'Bill of Materials', '/app/inventory/bom', 'list', 'items', ['inventory.bom']),
                ],
            ],
            'pos' => [
                'title' => 'Kasir',
                'icon' => 'shopping-cart',
                'requires_all' => ['pos'],
                'items' => [
                    $this->item(null, 'Layar Kasir', '/app/pos', 'cashier', 'orders'),
                    $this->item('history', 'Riwayat Transaksi', '/app/pos/history', 'list', 'orders'),
                    $this->item('tables', ['term' => 'orders', 'prefix' => 'Meja & '], '/app/pos/tables', 'board', 'orders', ['pos.tables']),
                    $this->item('prescriptions', 'Resep', '/app/pos/prescriptions', 'list', 'orders', ['pharmacy.prescription']),
                ],
            ],
            'accounting' => [
                'title' => 'Keuangan',
                'icon' => 'landmark',
                'requires_any' => ['finance.cashbook', 'finance.accounting'],
                'items' => [
                    $this->item(null, 'Buku Kas', '/app/accounting', 'ledger', 'cash_entries', ['finance.cashbook']),
                    $this->item('invoices', ['term' => 'invoices'], '/app/accounting/invoices', 'ledger', 'invoices', [], ['milestone_billing', 'pos']),
                    $this->item('reports', 'Laporan Keuangan', '/app/accounting/reports', 'report', 'cash_entries', ['finance.accounting']),
                    $this->item('coa', 'Bagan Akun', '/app/accounting/coa', 'list', 'cash_entries', ['finance.accounting']),
                    $this->item('journals', 'Jurnal', '/app/accounting/journals', 'ledger', 'cash_entries', ['finance.accounting']),
                ],
            ],
            'hrd' => [
                'title' => 'HRD',
                'icon' => 'users-round',
                'requires_any' => ['hr.employees', 'hr.payroll'],
                'items' => [
                    $this->item(null, ['term' => 'staffs', 'prefix' => 'Data '], '/app/hrd', 'list', 'employees', ['hr.employees']),
                    $this->item('employees', ['term' => 'staffs', 'prefix' => 'Data '], '/app/hrd/employees', 'list', 'employees', ['hr.employees'], [], false),
                    $this->item('attendance', 'Presensi & Cuti', '/app/hrd/attendance', 'list', 'timesheets', ['hr.employees']),
                    $this->item('payroll', 'Payroll', '/app/hrd/payroll', 'list', 'employees', ['hr.payroll']),
                ],
            ],
            'settings' => [
                'title' => 'Pengaturan',
                'icon' => 'settings',
                'items' => [
                    $this->item(null, 'Pengaturan Perusahaan', '/app/settings', 'settings', 'company_settings'),
                ],
            ],
        ];
    }

    /**
     * @param  string|array{term: string, prefix?: string, suffix?: string}  $label
     * @param  array<int, string>  $requiresAll
     * @param  array<int, string>  $requiresAny
     * @return array<string, mixed>
     */
    private function item(
        ?string $segment,
        string|array $label,
        string $route,
        string $screen,
        string $entity,
        array $requiresAll = [],
        array $requiresAny = [],
        bool $navigation = true,
    ): array {
        return [
            'segment' => $segment,
            'label' => $label,
            'icon' => 'circle',
            'route' => $route,
            'screen' => $screen,
            'entity' => $entity,
            'requires_all' => $requiresAll,
            'requires_any' => $requiresAny,
            'navigation' => $navigation,
        ];
    }
}
