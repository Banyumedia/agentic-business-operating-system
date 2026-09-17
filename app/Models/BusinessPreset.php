<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPreset extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'name',
        'tier',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
        ];
    }
}
