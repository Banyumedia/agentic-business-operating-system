<?php

namespace App\Services\Dashboard;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use RuntimeException;

class DashboardComposer
{
    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly EntityRepository $repository,
        private readonly PresetSource $presets,
        private readonly FeatureResolver $features,
        private readonly TerminologyResolver $terms,
        private readonly WidgetRegistry $widgets,
    ) {}

    /** @return array{company: string, kpis: list<array{label: string, value: string, meta: string, tone: string}>, assistant_report: array{summary: string, generated_at: string, period: string, highlights: list<string>, recommended_actions: list<string>}, widgets: list<array<string, mixed>>} */
    public function compose(): array
    {
        $company = $this->companyContext->current();
        $presetKey = $this->companyContext->preset();
        $preset = $this->presets->find($presetKey);
        if ($preset === null) {
            throw new RuntimeException("Preset dashboard tidak tersedia: {$presetKey}");
        }

        $widgets = [];
        foreach ($preset['dashboard']['industry_zone'] ?? [] as $selection) {
            $key = is_array($selection) ? ($selection['widget'] ?? null) : null;
            if (is_string($key) && $this->widgets->available($key)) {
                $widgets[] = $this->widgets->compose($key);
            }
        }

        return [
            'company' => str($company)->replace('-', ' ')->title()->toString(),
            'kpis' => $this->universalKpis(),
            'assistant_report' => $this->assistantReport(),
            'widgets' => $widgets,
        ];
    }

    /** @return list<array{label: string, value: string, meta: string, tone: string}> */
    private function universalKpis(): array
    {
        $cashEntries = $this->rows('cash_entries');
        $balance = 0.0;
        foreach ($cashEntries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            $balance += ($entry['direction'] ?? null) === 'in' ? $amount : -$amount;
        }

        [$workEntity, $workTerm] = $this->workSource();
        $work = $this->rows($workEntity);
        $contacts = $this->rows('contacts');

        return [
            [
                'label' => 'Arus kas bersih',
                'value' => 'Rp '.number_format($balance, 0, ',', '.'),
                'meta' => count($cashEntries).' transaksi tercatat',
                'tone' => $balance >= 0 ? 'success' : 'danger',
            ],
            [
                'label' => $this->terms->resolve($workTerm).' aktif',
                'value' => number_format(count($work), 0, ',', '.'),
                'meta' => 'Berdasarkan data operasional',
                'tone' => 'accent',
            ],
            [
                'label' => 'Total '.$this->terms->resolve('contacts'),
                'value' => number_format(count($contacts), 0, ',', '.'),
                'meta' => 'Tersedia di company aktif',
                'tone' => 'info',
            ],
        ];
    }

    /** @return array{0: string, 1: string} */
    private function workSource(): array
    {
        if ($this->features->enabled('bookings')) {
            return ['bookings', 'bookings'];
        }

        if ($this->features->enabled('projects')) {
            return ['projects', 'projects'];
        }

        if ($this->features->enabled('deals')) {
            return ['deals', 'deals'];
        }

        return ['contacts', 'contacts'];
    }

    /** @return array{summary: string, generated_at: string, period: string, highlights: list<string>, recommended_actions: list<string>} */
    private function assistantReport(): array
    {
        if (! $this->features->enabled('system.ai_agent')) {
            return $this->emptyReport();
        }

        $report = $this->rows('assistant_report')[0] ?? null;
        if (! is_array($report)) {
            return $this->emptyReport();
        }

        return [
            'summary' => (string) ($report['summary'] ?? 'Laporan belum tersedia.'),
            'generated_at' => (string) ($report['generated_at'] ?? ''),
            'period' => (string) ($report['period'] ?? 'Terbaru'),
            'highlights' => $this->stringList($report['highlights'] ?? []),
            'recommended_actions' => $this->stringList($report['recommended_actions'] ?? []),
        ];
    }

    /** @return array{summary: string, generated_at: string, period: string, highlights: list<string>, recommended_actions: list<string>} */
    private function emptyReport(): array
    {
        return [
            'summary' => 'Laporan belum tersedia.',
            'generated_at' => '',
            'period' => 'Terbaru',
            'highlights' => [],
            'recommended_actions' => [],
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $entity): array
    {
        return $this->repository->for($this->companyContext->current(), $entity)->all();
    }
}
