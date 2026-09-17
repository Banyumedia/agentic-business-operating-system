<?php

namespace App\Services\Preset;

use InvalidArgumentException;
use stdClass;

class PresetDefinitionValidator
{
    private const CAPABILITIES = [
        'contacts',
        'deals',
        'projects',
        'projects.progress_billing',
        'scheduling',
        'bookings',
        'bookings.deposit',
        'inventory',
        'inventory.batch_expiry',
        'inventory.bom',
        'pos',
        'pos.tables',
        'quotations',
        'milestone_billing',
        'approval_flow',
        'timesheet',
        'finance.cashbook',
        'finance.accounting',
        'hr.employees',
        'hr.payroll',
        'system.ai_agent',
        'pharmacy.prescription',
        'construction.retention',
    ];

    private const TERMINOLOGY = [
        'contact', 'contacts', 'deal', 'deals', 'project', 'projects',
        'resource', 'resources', 'booking', 'bookings', 'item', 'items',
        'order', 'orders', 'staff', 'staffs', 'invoice', 'invoices',
        'vendor', 'vendors',
    ];

    private const WIDGETS = [
        'kpi_revenue', 'kpi_cashflow', 'kpi_receivables_due', 'ai_report_card',
        'deals_pipeline', 'projects_progress', 'retention_held', 'upcoming_schedule',
        'resources_status', 'bookings_due_today', 'overdue_returns', 'open_bills',
        'expiring_batches', 'low_stock', 'prescription_queue', 'timesheet_summary',
        'vendor_settlement', 'pending_approvals',
    ];

    private const EFFECTS = [
        'invoice.create_dp', 'invoice.create_final', 'deposit.collect',
        'deposit.settle', 'late_fee.compute', 'stock.reserve', 'stock.deduct',
        'journal.post', 'notify.owner_wa', 'approval.request',
    ];

    private const CAPABILITY_DEPENDENCIES = [
        'system.ai_agent' => ['approval_flow'],
    ];

    private const TIER_B_DEPENDENCIES = [
        'pharmacy.prescription' => ['inventory.batch_expiry', 'pos', 'contacts'],
        'construction.retention' => ['projects.progress_billing', 'milestone_billing'],
    ];

    /** @return array<string, mixed> */
    public function validate(array|object $definition): array
    {
        $definition = $this->objectMap($definition, 'Preset');
        $this->assertExactKeys(
            $definition,
            ['key', 'name', 'tier', 'capabilities', 'terminology', 'workflows', 'dashboard', 'menus'],
            'preset',
        );
        $this->assertIdentifier($definition['key'], 'Key preset');
        $this->assertNonEmptyString($definition['name'], 'Nama preset');

        if (! in_array($definition['tier'], ['A', 'B'], true)) {
            throw new InvalidArgumentException('Tier preset harus A atau B.');
        }

        $this->validateCapabilities($definition['capabilities'], $definition['tier']);
        $this->validateTerminology($definition['terminology']);
        $this->validateWorkflows($definition['workflows']);
        $this->validateDashboard($definition['dashboard']);
        $this->validateMenus($definition['menus']);

        return $this->normalize($definition);
    }

    private function validateCapabilities(mixed $value, string $tier): void
    {
        $capabilities = $this->objectMap($value, 'Capabilities');

        foreach ($capabilities as $key => $enabled) {
            if (! in_array($key, self::CAPABILITIES, true)) {
                throw new InvalidArgumentException("Capability tidak terdaftar: {$key}");
            }
            if (! is_bool($enabled)) {
                throw new InvalidArgumentException("Nilai capability harus boolean: {$key}");
            }
        }

        $this->validateCapabilityDependencies($capabilities, self::CAPABILITY_DEPENDENCIES);
        $this->validateCapabilityDependencies($capabilities, self::TIER_B_DEPENDENCIES);

        foreach (array_keys(self::TIER_B_DEPENDENCIES) as $capability) {
            if (($capabilities[$capability] ?? false) === true && $tier !== 'B') {
                throw new InvalidArgumentException("Capability Tier B membutuhkan tier B: {$capability}");
            }
        }
    }

    /** @param array<string, bool> $capabilities @param array<string, list<string>> $dependencies */
    private function validateCapabilityDependencies(array $capabilities, array $dependencies): void
    {
        foreach ($dependencies as $capability => $requiredCapabilities) {
            if (($capabilities[$capability] ?? false) !== true) {
                continue;
            }
            foreach ($requiredCapabilities as $dependency) {
                if (($capabilities[$dependency] ?? false) !== true) {
                    throw new InvalidArgumentException("Dependensi capability tidak aktif: {$capability} membutuhkan {$dependency}");
                }
            }
        }
    }

    private function validateTerminology(mixed $value): void
    {
        $terminology = $this->objectMap($value, 'Terminology');

        foreach ($terminology as $key => $term) {
            if (! in_array($key, self::TERMINOLOGY, true)) {
                throw new InvalidArgumentException("Kunci terminology tidak terdaftar: {$key}");
            }
            if ($term !== null && (! is_string($term) || trim($term) === '')) {
                throw new InvalidArgumentException("Nilai terminology tidak valid: {$key}");
            }
        }
    }

    private function validateWorkflows(mixed $value): void
    {
        $workflows = $this->objectMap($value, 'Workflows');

        foreach ($workflows as $entity => $value) {
            $this->assertIdentifier($entity, 'Entity workflow');
            $workflow = $this->objectMap($value, "Workflow {$entity}");
            $this->assertExactKeys($workflow, ['stages', 'transitions', 'terminal'], "workflow {$entity}");
            $this->validateWorkflow($entity, $workflow);
        }
    }

    /** @param array<string, mixed> $workflow */
    private function validateWorkflow(string $entity, array $workflow): void
    {
        if (! is_array($workflow['stages']) || ! array_is_list($workflow['stages']) || $workflow['stages'] === []) {
            throw new InvalidArgumentException("Stages workflow wajib berupa list non-kosong: {$entity}");
        }

        $stageIndexes = [];
        foreach ($workflow['stages'] as $index => $value) {
            $stage = $this->objectMap($value, "Stage {$entity}");
            $this->assertExactKeys($stage, ['code', 'label'], "stage {$entity}");
            $this->assertStageCode($stage['code']);
            $this->assertNonEmptyString($stage['label'], 'Label stage');
            if (isset($stageIndexes[$stage['code']])) {
                throw new InvalidArgumentException("Kode stage duplikat: {$entity}.{$stage['code']}");
            }
            $stageIndexes[$stage['code']] = $index;
        }

        if (! is_array($workflow['terminal']) || ! array_is_list($workflow['terminal']) || $workflow['terminal'] === []) {
            throw new InvalidArgumentException("Terminal workflow wajib berupa list non-kosong: {$entity}");
        }

        $terminal = [];
        foreach ($workflow['terminal'] as $code) {
            if (! is_string($code) || ! array_key_exists($code, $stageIndexes)) {
                throw new InvalidArgumentException("Stage terminal tidak terdaftar: {$entity}.".(is_scalar($code) ? $code : gettype($code)));
            }
            $terminal[$code] = true;
        }

        if (! is_array($workflow['transitions']) || ! array_is_list($workflow['transitions'])) {
            throw new InvalidArgumentException("Transitions workflow harus list: {$entity}");
        }

        $adjacency = array_fill_keys(array_keys($stageIndexes), []);
        $reverse = array_fill_keys(array_keys($stageIndexes), []);
        foreach ($workflow['transitions'] as $value) {
            $transition = $this->objectMap($value, "Transisi {$entity}");
            $this->assertAllowedKeys(
                $transition,
                ['from', 'to', 'roles', 'effects', 'requires_approval', 'requires_note'],
                "transisi {$entity}",
            );
            foreach (['from', 'to', 'roles'] as $required) {
                if (! array_key_exists($required, $transition)) {
                    throw new InvalidArgumentException("Field wajib tidak ada pada transisi {$entity}: {$required}");
                }
            }

            $from = $transition['from'];
            $to = $transition['to'];
            if (! is_string($from) || ($from !== '*' && ! array_key_exists($from, $stageIndexes))) {
                throw new InvalidArgumentException("Stage asal transisi tidak terdaftar: {$entity}");
            }
            if (! is_string($to) || ! array_key_exists($to, $stageIndexes)) {
                throw new InvalidArgumentException("Stage tujuan transisi tidak terdaftar: {$entity}");
            }
            if (! is_array($transition['roles']) || ! array_is_list($transition['roles']) || $transition['roles'] === []) {
                throw new InvalidArgumentException("Roles transisi wajib berupa list non-kosong: {$entity}");
            }
            foreach ($transition['roles'] as $role) {
                if (! is_string($role) || ! in_array($role, ['owner', 'staff', 'system'], true)) {
                    throw new InvalidArgumentException("Role transisi tidak valid: {$entity}");
                }
            }
            foreach (['requires_approval', 'requires_note'] as $flag) {
                if (array_key_exists($flag, $transition) && ! is_bool($transition[$flag])) {
                    throw new InvalidArgumentException("Flag transisi harus boolean: {$entity}.{$flag}");
                }
            }

            $effects = $transition['effects'] ?? [];
            if (! is_array($effects) || ! array_is_list($effects)) {
                throw new InvalidArgumentException("Effects transisi harus list: {$entity}");
            }
            foreach ($effects as $effect) {
                if (! is_string($effect) || ! in_array($effect, self::EFFECTS, true)) {
                    throw new InvalidArgumentException('Efek tidak terdaftar: '.(is_scalar($effect) ? $effect : gettype($effect)));
                }
            }

            $sources = $from === '*'
                ? array_keys(array_diff_key($stageIndexes, $terminal))
                : [$from];
            foreach ($sources as $source) {
                if ($stageIndexes[$to] < $stageIndexes[$source] && ($transition['requires_note'] ?? false) !== true) {
                    throw new InvalidArgumentException("Transisi mundur wajib requires_note: {$entity}.{$source} -> {$to}");
                }
                $adjacency[$source][] = $to;
                $reverse[$to][] = $source;
            }
        }

        foreach (array_keys($stageIndexes) as $stage) {
            if (! isset($terminal[$stage]) && $adjacency[$stage] === []) {
                throw new InvalidArgumentException("Stage non-terminal tanpa transisi keluar: {$entity}.{$stage}");
            }
        }

        $canReachTerminal = [];
        $queue = array_keys($terminal);
        while ($queue !== []) {
            $stage = array_shift($queue);
            if (isset($canReachTerminal[$stage])) {
                continue;
            }
            $canReachTerminal[$stage] = true;
            array_push($queue, ...$reverse[$stage]);
        }
        foreach (array_keys($stageIndexes) as $stage) {
            if (! isset($canReachTerminal[$stage])) {
                throw new InvalidArgumentException("Stage tidak memiliki jalur ke terminal: {$entity}.{$stage}");
            }
        }

        $reachable = [];
        $queue = [array_key_first($stageIndexes)];
        while ($queue !== []) {
            $stage = array_shift($queue);
            if (isset($reachable[$stage])) {
                continue;
            }
            $reachable[$stage] = true;
            array_push($queue, ...$adjacency[$stage]);
        }
        foreach (array_keys($stageIndexes) as $stage) {
            if (! isset($reachable[$stage])) {
                throw new InvalidArgumentException("Stage tidak terjangkau: {$entity}.{$stage}");
            }
        }
    }

    private function validateDashboard(mixed $value): void
    {
        $dashboard = $this->objectMap($value, 'Dashboard');
        $this->assertExactKeys($dashboard, ['industry_zone'], 'dashboard');
        if (! is_array($dashboard['industry_zone']) || ! array_is_list($dashboard['industry_zone'])) {
            throw new InvalidArgumentException('Dashboard industry_zone harus list.');
        }
        foreach ($dashboard['industry_zone'] as $value) {
            $item = $this->objectMap($value, 'Item dashboard');
            if (! isset($item['widget']) || ! is_string($item['widget'])) {
                throw new InvalidArgumentException('Item dashboard tidak valid.');
            }
            $this->assertAllowedKeys($item, ['widget', 'props'], 'item dashboard');
            if (! in_array($item['widget'], self::WIDGETS, true)) {
                throw new InvalidArgumentException("Widget tidak terdaftar: {$item['widget']}");
            }
            if (array_key_exists('props', $item)) {
                $this->objectMap($item['props'], "Props widget harus object: {$item['widget']}");
            }
        }
    }

    private function validateMenus(mixed $value): void
    {
        $menus = $this->objectMap($value, 'Menus');
        $this->assertExactKeys($menus, ['order'], 'menus');
        if (! is_array($menus['order']) || ! array_is_list($menus['order'])) {
            throw new InvalidArgumentException('Menus order harus list.');
        }
        foreach ($menus['order'] as $module) {
            $this->assertIdentifier($module, 'Module menu');
        }
    }

    /** @param array<string, mixed> $value @param list<string> $allowed */
    private function assertAllowedKeys(array $value, array $allowed, string $context): void
    {
        $unknown = array_diff(array_keys($value), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException("Key tidak diizinkan pada {$context}: ".reset($unknown));
        }
    }

    /** @param array<string, mixed> $value @param list<string> $expected */
    private function assertExactKeys(array $value, array $expected, string $context): void
    {
        $this->assertAllowedKeys($value, $expected, $context);
        $missing = array_diff($expected, array_keys($value));
        if ($missing === []) {
            return;
        }
        if (str_starts_with($context, 'workflow ') && in_array('terminal', $missing, true)) {
            throw new InvalidArgumentException('Terminal workflow wajib dideklarasikan: '.substr($context, 9));
        }
        throw new InvalidArgumentException("Key wajib tidak ada pada {$context}: ".reset($missing));
    }

    /** @return array<string, mixed> */
    private function objectMap(mixed $value, string $label): array
    {
        if ($value instanceof stdClass) {
            return (array) $value;
        }
        if (! is_array($value) || array_is_list($value)) {
            $suffix = str_contains($label, 'harus object') ? '' : ' harus object.';
            throw new InvalidArgumentException($label.$suffix);
        }

        return $value;
    }

    private function assertIdentifier(mixed $value, string $label): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("{$label} tidak valid.");
        }
    }

    private function assertStageCode(mixed $value): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z_]+$/', $value)) {
            throw new InvalidArgumentException('Kode stage tidak valid.');
        }
    }

    private function assertNonEmptyString(mixed $value, string $label): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("{$label} tidak valid.");
        }
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }
        if (! is_array($value)) {
            return $value;
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
