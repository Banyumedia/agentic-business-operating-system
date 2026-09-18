<?php

namespace App\Models;

use App\Contracts\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class ProductionOrder extends Model implements HasWorkflow
{
    use HasFactory, Searchable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_qty' => 'decimal:3',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'external_ref' => $this->external_ref,
        ];
    }

    public function workflowCompany(): string
    {
        return (string) $this->company_id;
    }

    public function workflowEntity(): string
    {
        return 'production_orders';
    }

    public function workflowIdentifier(): string|int
    {
        return $this->id;
    }

    public function workflowStage(): string
    {
        return $this->stage;
    }

    public function setWorkflowStage(string $stage): void
    {
        $this->stage = $stage;
        if ($stage === 'selesai' && ! $this->completed_at) {
            $this->completed_at = now();
        } elseif ($stage === 'produksi' && ! $this->started_at) {
            $this->started_at = now();
        }
        $this->save();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionOrderLine::class);
    }
}
