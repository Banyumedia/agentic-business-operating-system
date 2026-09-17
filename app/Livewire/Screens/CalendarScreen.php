<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Pola layar kalender generik (harian / mingguan).
 *
 * Rentang waktu, label baris, dan nama sumber daya diturunkan dari schema
 * entitas; tidak ada nama industri maupun jenis layanan di kelas ini.
 *
 * Waktu selalu ditampilkan pada offset yang tercatat di data, bukan timezone
 * server - pelajaran dari defect widget yang menggeser jam operasional 7 jam.
 */
class CalendarScreen extends Component
{
    private const VIEWS = ['day', 'week'];

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    public string $view = 'week';

    public string $anchor = '';

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
        $this->anchor = $this->businessToday();
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->view = $view;
        }
    }

    public function shift(int $steps): void
    {
        $days = ($this->view === 'week' ? 7 : 1) * $steps;
        $this->anchor = $this->anchorDate()->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }

    public function today(): void
    {
        $this->anchor = $this->businessToday();
    }

    /**
     * "Hari ini" menurut jam usaha, bukan jam server.
     *
     * Zona waktu dibaca dari pengaturan company dan jatuh ke `app.timezone` bila
     * belum diatur. Tanpa ini, usaha di UTC+7 yang membuka aplikasi pagi hari
     * akan melihat kalender masih menunjuk tanggal kemarin.
     */
    private function businessToday(): string
    {
        return now()->setTimezone($this->businessTimezone())->format('Y-m-d');
    }

    private function businessTimezone(): DateTimeZone
    {
        $configured = app(CompanySettingsStore::class)->read($this->company)['timezone'] ?? null;

        if (is_string($configured) && $configured !== '') {
            try {
                return new DateTimeZone($configured);
            } catch (Throwable) {
                // Nilai tidak dikenal diabaikan, bukan membuat layar gagal.
            }
        }

        return new DateTimeZone((string) config('app.timezone', 'UTC'));
    }

    public function render(): View
    {
        $definition = $this->definition();
        $schema = EntitySchema::load($definition['entity']);
        $presenter = app(SchemaPresenter::class);

        [$from, $to] = $this->range();
        $titleField = $presenter->titleField($schema);
        $labels = $this->referenceLabels($presenter, $schema);

        $today = $this->businessToday();
        $days = [];
        for ($cursor = $from; $cursor <= $to; $cursor = $cursor->modify('+1 day')) {
            $days[$cursor->format('Y-m-d')] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('D, d M Y'),
                'is_today' => $cursor->format('Y-m-d') === $today,
                'slots' => [],
            ];
        }

        foreach ($this->repository()->all() as $row) {
            $start = $this->parse($row['starts_at'] ?? null);

            if ($start === null) {
                continue;
            }

            $key = $start->format('Y-m-d');
            if (! isset($days[$key])) {
                continue;
            }

            $end = $this->parse($row['ends_at'] ?? null);
            $days[$key]['slots'][] = [
                'id' => $row['id'],
                'title' => $this->slotTitle($row, $titleField),
                'from' => $start->format('H:i'),
                'to' => $end === null ? null : $end->format('H:i'),
                'sort' => $start->format('H:i'),
                'resource' => $this->resourceLabel($row, $labels),
                'stage' => is_string($row['stage'] ?? null) ? $row['stage'] : null,
            ];
        }

        foreach ($days as $key => $day) {
            usort($days[$key]['slots'], static fn (array $left, array $right): int => $left['sort'] <=> $right['sort']);
        }

        return view('livewire.screens.calendar', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entity' => $definition['entity'],
            'days' => array_values($days),
            'rangeLabel' => $from->format('d M Y').($from->format('Y-m-d') === $to->format('Y-m-d') ? '' : ' - '.$to->format('d M Y')),
        ]);
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function range(): array
    {
        $anchor = $this->anchorDate();

        if ($this->view === 'day') {
            return [$anchor, $anchor];
        }

        $start = $anchor->modify('monday this week');

        return [$start, $start->modify('+6 days')];
    }

    private function anchorDate(): DateTimeImmutable
    {
        $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $this->anchor);

        return $anchor === false ? new DateTimeImmutable(now()->format('Y-m-d')) : $anchor;
    }

    private function parse(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $row */
    private function slotTitle(array $row, ?string $field): string
    {
        $value = $field === null ? null : ($row[$field] ?? null);

        return is_string($value) && trim($value) !== '' ? $value : '#'.$row['id'];
    }

    /**
     * Memetakan foreign key ke nama yang terbaca, memakai kolom string pertama
     * pada entitas tujuan. Bila entitas tujuan belum punya data atau schema,
     * kolom tetap kosong alih-alih menampilkan id mentah.
     *
     * @return array<string, array<string, string>>
     */
    private function referenceLabels(SchemaPresenter $presenter, EntitySchema $schema): array
    {
        $labels = [];

        foreach ($schema->references() as $field => $reference) {
            try {
                $target = EntitySchema::load($reference['entity']);
                $titleField = $presenter->titleField($target);
                if ($titleField === null) {
                    continue;
                }

                $map = [];
                foreach (app(EntityRepository::class)->for($this->company(), $reference['entity'])->all() as $row) {
                    $value = $row[$titleField] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        $map[(string) $row['id']] = $value;
                    }
                }

                $labels[$field] = $map;
            } catch (Throwable) {
                continue;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, string>>  $labels
     */
    private function resourceLabel(array $row, array $labels): ?string
    {
        foreach ($labels as $field => $map) {
            $value = $row[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $label = $map[(string) $value] ?? null;
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, $this->submodule), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, $this->submodule);
        abort_if($definition === null, 403);

        return $definition;
    }

    private function repository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->definition()['entity']);
    }

    private function company(): string
    {
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }
}
