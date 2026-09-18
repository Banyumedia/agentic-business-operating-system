<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'doctor_name' => 'encrypted',
            'doctor_sip' => 'encrypted',
            'extracted_lines' => 'encrypted:array',
            'verified_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'patient_contact_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
