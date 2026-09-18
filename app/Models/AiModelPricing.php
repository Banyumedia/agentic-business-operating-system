<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModelPricing extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'input_multiplier',
        'output_multiplier',
        'is_active',
    ];

    protected $casts = [
        'input_multiplier' => 'float',
        'output_multiplier' => 'float',
        'is_active' => 'boolean',
    ];
}
