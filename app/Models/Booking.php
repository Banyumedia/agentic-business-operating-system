<?php

namespace App\Models;

use App\Contracts\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model implements HasWorkflow
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'resource_id',
        'contact_id',
        'project_id',
        'type',
        'stage',
        'starts_at',
        'ends_at',
        'actual_ends_at',
        'rate_amount',
        'deposit_amount',
        'late_fee_per_unit',
        'late_fee_total',
        'pic_user_id',
        'attributes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'actual_ends_at' => 'datetime',
        'rate_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'late_fee_per_unit' => 'decimal:2',
        'late_fee_total' => 'decimal:2',
        'attributes' => 'array',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<BookingIncident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(BookingIncident::class);
    }

    public function workflowCompany(): string
    {
        return (string) $this->company_id;
    }

    public function workflowEntity(): string
    {
        return 'bookings';
    }

    public function workflowIdentifier(): string|int
    {
        return $this->id ?? 0;
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
}
