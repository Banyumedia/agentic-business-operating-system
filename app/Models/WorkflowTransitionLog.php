<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransitionLog extends Model
{
    protected $table = 'workflow_transitions_log';

    public $timestamps = true;

    protected $fillable = [
        'company_id',
        'entity',
        'entity_id',
        'from_stage',
        'to_stage',
        'actor_user_id',
        'approval_ticket_id',
        'note',
        'effects_run',
        'changed_by_type',
    ];

    protected function casts(): array
    {
        return [
            'effects_run' => 'array',
        ];
    }
}
