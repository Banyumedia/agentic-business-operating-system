<?php

namespace App\Models;

use App\Contracts\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model implements HasWorkflow
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'business_identity_id',
        'shift_id',
        'contact_id',
        'resource_id',
        'prescription_id',
        'order_no',
        'stage',
        'subtotal',
        'discount_amount',
        'dpp',
        'tax_amount',
        'grand_total',
        'payment_method',
        'paid_at',
        'source',
        'external_ref',
    ];

    public function workflowCompany(): string
    {
        return (string) $this->company_id;
    }

    public function workflowEntity(): string
    {
        return 'orders';
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
        $this->save();
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'dpp' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessIdentity(): BelongsTo
    {
        return $this->belongsTo(BusinessIdentity::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }
}
