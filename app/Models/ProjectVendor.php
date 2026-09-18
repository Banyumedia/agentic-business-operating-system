<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'vendor_contact_id',
        'vendor_name',
        'service_type',
        'fee',
        'payment_status',
        'attributes',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'attributes' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendorContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_contact_id');
    }
}
