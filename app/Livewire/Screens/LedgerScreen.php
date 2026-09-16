<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar buku (ledger) generik dengan saldo berjalan.
 *
 * Kolom nilai, kolom tanggal, dan arah masuk/keluar diturunkan dari schema
 * entitas - bukan dari daftar per entitas - sehingga buku kas maupun buku
 * tagihan memakai satu layar yang sama.
 */
class LedgerScreen extends Component
{
    /** Arah dianggap ada bila schema mendeklarasikan enum tepat seperti ini. */
    private const DIRECTION_ENUM = ['in', 'out'];

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function render(): View
    {
        $definition = $this->definition();
        $schema = EntitySchema::load($definition['entity']);
        $presenter = app(SchemaPresenter::class);

        $amountField = $this->amountField($schema);
        $dateField = $this->dateField($schema);
        $directionField = $this->directionField($schema);
        $descriptionField = $presenter->titleField($schema);

        $rows = $this->repository()->all();
        usort($rows, static function (array $left, array $right) use ($dateField): int {
            $leftKey = [(string) ($left[$dateField] ?? ''), $left['id'] ?? 0];
            $rightKey = [(string) ($right[$dateField] ?? ''), $right['id'] ?? 0];

            return $leftKey <=> $rightKey;
        });

        $balance = 0.0;
        $incoming = 0.0;
        $outgoing = 0.0;
        $entries = [];

        foreach ($rows as $row) {
            $amount = (float) ($row[$amountField] ?? 0);
            $isOutgoing = $directionField !== null && ($row[$directionField] ?? null) === 'out';

            if ($isOutgoing) {
                $outgoing += $amount;
                $balance -= $amount;
            } else {
                $incoming += $amount;
                $balance += $amount;
            }

            $entries[] = [
                'id' => $row['id'],
                'date' => (string) ($row[$dateField] ?? ''),
                'description' => $this->describe($row, $descriptionField, $amountField),
                'amount' => $amount,
                'outgoing' => $isOutgoing,
                'balance' => round($balance, 2),
            ];
        }

        return view('livewire.screens.ledger', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entries' => array_reverse($entries),
            'hasDirection' => $directionField !== null,
            'incoming' => round($incoming, 2),
            'outgoing' => round($outgoing, 2),
            'balance' => round($balance, 2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function describe(array $row, ?string $descriptionField, string $amountField): string
    {
        foreach ([$descriptionField, 'description', 'category'] as $candidate) {
            if ($candidate === null || $candidate === $amountField) {
                continue;
            }

            $value = $row[$candidate] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$row['id'];
    }

    private function amountField(EntitySchema $schema): string
    {
        $properties = $schema->properties();

        if (($properties['amount']['type'] ?? null) === 'number') {
            return 'amount';
        }

        foreach ($properties as $field => $definition) {
            if (($definition['type'] ?? null) === 'number') {
                return $field;
            }
        }

        return 'id';
    }

    private function dateField(EntitySchema $schema): string
    {
        foreach (['date', 'date-time'] as $format) {
            foreach ($schema->properties() as $field => $definition) {
                if (($definition['format'] ?? null) === $format) {
                    return $field;
                }
            }
        }

        return 'id';
    }

    private function directionField(EntitySchema $schema): ?string
    {
        foreach ($schema->properties() as $field => $definition) {
            $enum = $definition['enum'] ?? null;
            if (is_array($enum) && $enum === self::DIRECTION_ENUM) {
                return $field;
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
