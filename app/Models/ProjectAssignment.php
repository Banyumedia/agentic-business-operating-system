<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'employee_id',
        'assignee_name',
        'role',
        'scheduled_at',
        'hourly_cost',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'hourly_cost' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
