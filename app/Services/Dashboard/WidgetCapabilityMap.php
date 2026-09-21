<?php

namespace App\Services\Dashboard;

/**
 * MQ-01C3: satu kontrak widget->capability, dipakai runtime
 * (WidgetRegistry) dan validator preset (PresetDefinitionValidator).
 * Widget tanpa entri di sini tidak boleh dideklarasi preset apa pun.
 */
final class WidgetCapabilityMap
{
    /**
     * Widget runtime yang dirender DashboardComposer, beserta capability
     * yang wajib aktif sebelum widget tersedia.
     *
     * @var array<string, list<string>>
     */
    private const CAPABILITIES = [
        'upcoming_schedule' => ['scheduling'],
        'low_stock' => ['inventory'],
        'kpi_cashflow' => ['finance.cashbook'],
        'deals_pipeline' => ['deals'],
        'pending_approvals' => ['approval_flow'],
    ];

    /** @return list<string> capability yang diminta widget, kosong bila tidak dikenal */
    public static function required(string $widget): array
    {
        return self::CAPABILITIES[$widget] ?? [];
    }

    /** @return bool true bila widget dikenal kontrak runtime */
    public static function known(string $widget): bool
    {
        return isset(self::CAPABILITIES[$widget]);
    }

    /** @return list<string> seluruh widget yang dikenal kontrak runtime */
    public static function widgets(): array
    {
        return array_keys(self::CAPABILITIES);
    }
}
