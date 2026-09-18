<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Contact extends Model
{
    use HasAttachments, HasFactory, Searchable;

    protected $fillable = [
        'company_id',
        'business_identity_id',
        'type',
        'name',
        'wa_number',
        'phone',
        'email',
        'source',
        'tags',
        'attributes',
    ];

    protected $casts = [
        'tags' => 'array',
        'name' => 'encrypted',
        'wa_number' => 'encrypted',
        'email' => 'encrypted',
        'attributes' => 'encrypted:array',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
