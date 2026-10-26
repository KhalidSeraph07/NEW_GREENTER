<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Boleta extends Model
{
    protected $table = 'h4_boletas';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'artifacts' => 'array', 'number' => 'integer'];
    public function freshTimestamp() { return now('UTC'); }
}
