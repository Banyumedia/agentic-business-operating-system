<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'period_month',
        'gross_salary',
        'deductions',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'pph21',
        'net_salary',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'deductions' => 'decimal:2',
        'bpjs_kesehatan' => 'decimal:2',
        'bpjs_ketenagakerjaan' => 'decimal:2',
        'pph21' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
