<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransitionLog extends Model
{
    protected $table = 'workflow_transitions_log';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'entity_type',
        'entity_id',
        'from_stage',
        'to_stage',
        'actor_user_id',
        'effects_result',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effects_result' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
