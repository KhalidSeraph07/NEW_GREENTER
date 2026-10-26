<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Invoice extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'snapshot' => 'array',
        'artifacts' => 'array',
        'simulated_response' => 'array',
        'subtotal_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
        'number' => 'integer',
    ];
}
