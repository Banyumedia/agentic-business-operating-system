<?php

namespace App\Models;

use App\Contracts\HasWorkflow;
use App\Services\Workflow\Effects\InvoiceCreatePartial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model implements HasWorkflow
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'contact_id',
        'deal_id',
        'type',
        'name',
        'stage',
        'starts_at',
        'ends_at',
        'venue',
        'budget',
        'progress_pct',
        'owner_user_id',
        'attributes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'budget' => 'decimal:2',
        'progress_pct' => 'decimal:2',
        'attributes' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(ProjectVendor::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(TimesheetEntry::class);
    }

    // HasWorkflow Implementation
    public function workflowCompany(): string
    {
        return (string) $this->company_id;
    }

    public function workflowEntity(): string
    {
        return 'projects';
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

    /**
     * Helper to update progress and trigger milestones
     */
    public function updateProgress(float $newProgress): void
    {
        $this->progress_pct = $newProgress;
        $this->save();
        $this->checkAndTriggerMilestones();
    }

    /**
     * Check milestones of trigger_type 'progress_pct' and execute invoice.create_* effect
     */
    public function checkAndTriggerMilestones(): void
    {
        $pendingMilestones = $this->milestones()
            ->where('status', 'pending')
            ->where('trigger_type', 'progress_pct')
            ->where('trigger_value', '<=', $this->progress_pct)
            ->get();

        foreach ($pendingMilestones as $milestone) {
            // Trigger the invoice creation effect logic manually here for milestones
            // Because they don't inherently transition via WorkflowEngine in the normal sense,
            // but we can use the Effect class directly, or we can just apply logic directly

            // To be 100% compliant with the instruction:
            // "implementasikan efek invoice.create_partial dan/atau invoice.create_full di app/Services/Workflow/Effects/"
            // we will create the effect and call it.
            $effectClass = new InvoiceCreatePartial;
            $effectClass->execute([
                'project_id' => $this->id,
                'milestone_id' => $milestone->id,
                'amount' => $milestone->amount,
            ]);
        }
    }
}
