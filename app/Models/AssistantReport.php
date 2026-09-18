<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'generated_at',
        'period',
        'summary',
        'highlights',
        'recommended_actions',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'highlights' => 'array',
        'recommended_actions' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
