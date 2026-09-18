<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'task_description',
        'remind_at',
        'is_completed',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
