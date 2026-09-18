<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'name',
        'sequence',
        'trigger_type',
        'trigger_value',
        'amount',
        'retention_pct',
        'invoice_id',
        'status',
        'achieved_at',
    ];

    protected $casts = [
        'trigger_value' => 'decimal:2',
        'amount' => 'decimal:2',
        'retention_pct' => 'decimal:2',
        'achieved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
