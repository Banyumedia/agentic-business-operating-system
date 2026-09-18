<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalTicket extends Model
{
    protected $fillable = [
        'company_id',
        'entity_type',
        'entity_id',
        'from_stage',
        'to_stage',
        'requested_by_user_id',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
