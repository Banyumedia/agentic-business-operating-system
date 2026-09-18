<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowDefinition extends Model
{
    protected $fillable = [
        'company_id',
        'entity_type',
        'from_stage',
        'to_stage',
        'requires_approval',
        'required_role',
        'effects',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'effects' => 'array',
        ];
    }
}
